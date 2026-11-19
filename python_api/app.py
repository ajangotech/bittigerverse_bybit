import hashlib
import hmac
import logging
import os
import re
import threading
import time
from functools import wraps

from bybit_p2p import P2P
from dotenv import load_dotenv
from flask import Flask, jsonify, request
from flask_cors import CORS
import requests

load_dotenv()

app = Flask(__name__)
application = app

CORS(app, resources={r"/*": {"origins": "*"}})


# =========================
# 🔇 QUIET THE NOISY LIBRARY LOGGER
# =========================
# bybit_p2p logs one ERROR line per failed API call. When the frontend polls
# /api/ad-price-limit every second, each probe produces a log line. Silence
# the library logger so the console isn't flooded.
logging.getLogger("bybit_p2p._p2p_manager").setLevel(logging.CRITICAL)


# =========================
# 🔐 BASIC SECURITY LAYER
# =========================
def require_api_keys(f):
    @wraps(f)
    def wrapper():
        data = request.get_json(silent=True) or {}

        if not isinstance(data, dict):
            return jsonify({"error": "Request body must be a JSON object"}), 400

        if not data.get("api_key") or not data.get("api_secret"):
            return jsonify({"error": "api_key and api_secret are required"}), 400

        return f(data)

    return wrapper


# =========================
# 🔧 HELPER FUNCTION
# =========================
def get_api(data=None):
    if data is None:
        data = {}
    return P2P(
        testnet=False,
        api_key=data.get("api_key", os.getenv("BYBIT_API_KEY")),
        api_secret=data.get("api_secret", os.getenv("BYBIT_API_SECRET")),
    )


def build_trading_pref(tp):
    tp = tp or {}
    return {
        "hasUnPostAd": int(tp.get("hasUnPostAd", 0)),
        "isKyc": int(tp.get("isKyc", 0)),
        "isEmail": int(tp.get("isEmail", 0)),
        "isMobile": int(tp.get("isMobile", 0)),
        "hasRegisterTime": int(tp.get("hasRegisterTime", 0)),
        "registerTimeThreshold": int(tp.get("registerTimeThreshold", 0)),
        "orderFinishNumberDay30": int(tp.get("orderFinishNumberDay30", 0)),
        "hasOrderFinishNumberDay30": int(tp.get("hasOrderFinishNumberDay30", 0)),
        "hasCompleteRateDay30": int(tp.get("hasCompleteRateDay30", 0)),
        "hasNationalLimit": int(tp.get("hasNationalLimit", 0)),
        "completeRateDay30": tp.get("completeRateDay30", ""),
        "nationalLimit": tp.get("nationalLimit", ""),
    }


def extract_payment_ids(data):
    return [
        p.get("id")
        for p in data.get("paymentTerms", [])
        if isinstance(p, dict) and p.get("id")
    ]


# =========================
# 🗄️ SHARED CACHE UTILITIES
# =========================
def _cache_key(*parts):
    return "|".join(str(p) for p in parts)


def _cached(cache, key, ttl):
    entry = cache.get(key)
    if entry and (time.time() - entry["ts"]) < ttl:
        return entry["payload"]
    return None


def _store(cache, key, payload):
    cache[key] = {"ts": time.time(), "payload": payload}


# =========================
# 🗄️ UPDATE-AD COOLDOWN + LOCK
# =========================
# The frontend fires update-ad whenever the tracked merchant price moves and
# also retries every 1 second on failure. Without a cooldown, overlapping and
# rapid-fire requests hit Bybit's mutating endpoint back-to-back and trigger
# ErrCode 10006 ("Too many visits"). The cooldown enforces a minimum gap
# between real Bybit write calls per ad; requests arriving inside the window
# are coalesced — the in-flight call is awaited (lock) and its result is
# served to all waiters instead of issuing another write.
_UPDATE_AD_COOLDOWN = 3  # seconds between real Bybit update calls per ad
_update_ad_last_call = {}  # ad_id -> epoch ts of last Bybit write
_update_ad_locks = {}  # ad_id -> per-ad threading.Lock
_update_ad_locks_guard = threading.Lock()  # guards creation of per-ad locks
_update_ad_last_result = {}  # ad_id -> {"ts": epoch, "payload": {...}}
_update_ad_stats = {"bybit_calls": 0, "coalesced": 0, "rate_limited": 0}


def _update_ad_lock_for(ad_id):
    with _update_ad_locks_guard:
        lock = _update_ad_locks.get(ad_id)
        if lock is None:
            lock = threading.Lock()
            _update_ad_locks[ad_id] = lock
        return lock


def _is_rate_limit_error(error_message):
    return "10006" in error_message or "Too many visits" in error_message


# =========================
# 🗄️ PRICE-LIMIT CACHE + LOCK
# =========================
# The frontend polls /api/ad-price-limit every ~1s. Without caching, each poll
# fires a real update_ad call to Bybit (a mutating write used as a read),
# producing one log line + one API hit per second. The cache deduplicates
# overlapping requests for the same ad so concurrent polls share ONE probe,
# and serves cached bounds for PRICE_LIMIT_TTL seconds.
_PRICE_LIMIT_TTL = 10  # seconds before re-probing Bybit
_price_limit_cache = {}  # ad_id -> {"ts": epoch, "payload": {...}}
_price_limit_lock = threading.Lock()
_price_limit_stats = {"bybit_calls": 0, "cache_hits": 0}


def _cached_price_limit(ad_id):
    return _cached(_price_limit_cache, ad_id, _PRICE_LIMIT_TTL)


def _store_price_limit(ad_id, payload):
    _store(_price_limit_cache, ad_id, payload)


# =========================
# 🗄️ MARKET ANALYSIS CACHE + LOCK
# =========================
# Same pattern as the price-limit cache: the frontend polls
# /api/analyze-market frequently, and each call hits Bybit's
# /v5/p2p/item/online endpoint. Without deduplication this triggers
# ErrCode 10006 ("Too many visits. exceeded the api rate limit"). The cache
# keys on token+currency+side+minAmount+marginPct+sortBy so identical
# concurrent polls share ONE Bybit probe, and serves cached results for
# MARKET_ANALYSIS_TTL seconds.
_MARKET_ANALYSIS_TTL = 15  # seconds before re-probing Bybit
_market_analysis_cache = {}  # key -> {"ts": epoch, "payload": {...}}
_market_analysis_lock = threading.Lock()
_market_analysis_stats = {"bybit_calls": 0, "cache_hits": 0}


def _market_analysis_cache_key(data):
    return _cache_key(
        data.get("tokenId", "BTC"),
        data.get("currencyId", "USD"),
        str(data.get("side", "0")),
        str(data.get("minAmount", 0)),
        str(data.get("marginPct", 4)),
        str(data.get("sortBy", "price")).lower(),
    )


def _cached_market_analysis(key):
    return _cached(_market_analysis_cache, key, _MARKET_ANALYSIS_TTL)


def _store_market_analysis(key, payload):
    _store(_market_analysis_cache, key, payload)


@app.route("/", methods=["GET"])
def home():
    return jsonify(
        {"status": "success", "message": "Welcome to ByBit API", "version": "1.0"}
    )


# =========================
# 📢 UPDATE AD
# =========================
# Strategy (mirrors the price-limit / market-analysis caching pattern):
#  1. If a request for the same ad is already in flight, wait for it and
#     return its result (coalescing) instead of issuing another Bybit write.
#  2. If the last successful write for this ad was less than
#     _UPDATE_AD_COOLDOWN seconds ago, return the cached last result
#     (cooldown) — the price hasn't had time to move meaningfully.
#  3. Otherwise perform ONE Bybit update_ad call under a per-ad lock.
#  4. On Bybit rate-limit (ErrCode 10006), return HTTP 429 so the frontend
#     can back off instead of hammering every 1 second.
@app.route("/api/update-ad", methods=["POST"])
@require_api_keys
def update_ad(data):
    ad_id = data.get("id")
    if not ad_id:
        return jsonify({"status": False, "error": "Ad id is required"}), 400

    ad_lock = _update_ad_lock_for(ad_id)

    with ad_lock:
        # Cooldown: serve last result if we just wrote to Bybit for this ad
        last = _update_ad_last_result.get(ad_id)
        if last and (time.time() - last["ts"]) < _UPDATE_AD_COOLDOWN:
            _update_ad_stats["coalesced"] += 1
            app.logger.info(
                f"[update-ad] COOLDOWN HIT for ad {ad_id} "
                f"(bybit_calls={_update_ad_stats['bybit_calls']}, "
                f"coalesced={_update_ad_stats['coalesced']})"
            )
            return jsonify(last["payload"])

        _update_ad_stats["bybit_calls"] += 1
        app.logger.info(
            f"[update-ad] BYBIT CALL #{_update_ad_stats['bybit_calls']} "
            f"for ad {ad_id}"
        )

        try:
            api = get_api(data)

            payment_ids = extract_payment_ids(data)
            trading_pref = build_trading_pref(data.get("tradingPreferenceSet"))

            result = api.update_ad(
                id=ad_id,
                priceType=int(data.get("priceType", 0)),
                premium=float(data.get("premium", 0)),
                price=float(data.get("price")),
                minAmount=float(data.get("minAmount")),
                maxAmount=float(data.get("maxAmount")),
                remark=data.get("remark", ""),
                tradingPreferenceSet=trading_pref,
                paymentIds=payment_ids,
                actionType="MODIFY",
                quantity=str(data.get("quantity") or data.get("lastQuantity", "1")),
                paymentPeriod=int(data.get("paymentPeriod", 15)),
            )

            payload = result if isinstance(result, dict) else {"status": True, "result": result}
            _update_ad_last_result[ad_id] = {"ts": time.time(), "payload": payload}
            return jsonify(payload)

        except Exception as e:
            error_message = str(e)

            if _is_rate_limit_error(error_message):
                _update_ad_stats["rate_limited"] += 1
                app.logger.warning(
                    f"[update-ad] RATE LIMITED for ad {ad_id} "
                    f"(rate_limited={_update_ad_stats['rate_limited']})"
                )
                # Serve last successful result if we have one (better than
                # an error), otherwise return 429 so the frontend backs off.
                last = _update_ad_last_result.get(ad_id)
                if last:
                    return jsonify(last["payload"])
                return jsonify(
                    {"status": False, "error": "Rate limited by Bybit. Please retry shortly."}
                ), 429

            return jsonify({"error": error_message}), 500


# =========================
# 📈 GET MAXIMUM AD PRICE
# =========================
# Strategy:
#  1. Serve cached bounds if fresh (< _PRICE_LIMIT_TTL seconds old).
#  2. Otherwise acquire a per-ad lock so overlapping concurrent requests
#     share a SINGLE Bybit probe (the rest wait, then read the cache).
#  3. One Bybit API call per TTL window — no flood, no 75-line burst.
@app.route("/api/ad-price-limit", methods=["POST"])
@require_api_keys
def ad_price_limit(data):
    ad_id = data.get("id")
    if not ad_id:
        return jsonify({"status": False, "error": "Ad id is required"}), 400

    requested_price = str(data.get("price", "0"))

    # Fast path: fresh cache, no Bybit call at all
    cached = _cached_price_limit(ad_id)
    if cached is not None:
        _price_limit_stats["cache_hits"] += 1
        app.logger.info(
            f"[ad-price-limit] CACHE HIT for ad {ad_id} "
            f"(bybit_calls={_price_limit_stats['bybit_calls']}, "
            f"cache_hits={_price_limit_stats['cache_hits']})"
        )
        return jsonify(cached)

    # Slow path: one caller probes, the rest wait for the cache
    with _price_limit_lock:
        # Re-check inside the lock (another request may have just filled it)
        cached = _cached_price_limit(ad_id)
        if cached is not None:
            _price_limit_stats["cache_hits"] += 1
            app.logger.info(
                f"[ad-price-limit] CACHE HIT (post-lock) for ad {ad_id} "
                f"(bybit_calls={_price_limit_stats['bybit_calls']}, "
                f"cache_hits={_price_limit_stats['cache_hits']})"
            )
            return jsonify(cached)

        _price_limit_stats["bybit_calls"] += 1
        app.logger.info(
            f"[ad-price-limit] BYBIT CALL #{_price_limit_stats['bybit_calls']} "
            f"for ad {ad_id}"
        )

        api = get_api(data)
        payment_ids = extract_payment_ids(data)
        trading_pref = build_trading_pref(data.get("tradingPreferenceSet"))

        try:
            api.update_ad(
                id=ad_id,
                priceType=int(data.get("priceType", 0)),
                premium=float(data.get("premium", 0)),
                price=float(data.get("price")),
                minAmount=float(data.get("minAmount")),
                maxAmount=float(data.get("maxAmount")),
                remark=data.get("remark", ""),
                tradingPreferenceSet=trading_pref,
                paymentIds=payment_ids,
                actionType="MODIFY",
                quantity=str(data.get("quantity") or data.get("lastQuantity") or "1"),
                paymentPeriod=int(data.get("paymentPeriod", 15)),
            )

            payload = {
                "status": True,
                "price_limit": False,
                "price": requested_price,
            }
            _store_price_limit(ad_id, payload)
            return jsonify(payload)

        except Exception as e:
            error_message = str(e)
            pattern = r"lower than\s+([\d.]+)\s+or higher than\s+([\d.]+)"
            match = re.search(pattern, error_message)

            if match:
                minimum_price = match.group(1).rstrip(".")
                maximum_price = match.group(2).rstrip(".")
                req_val = float(requested_price)

                payload = {
                    "status": True,
                    "price_limit": True,
                    "price": maximum_price,
                    "minimum_price": minimum_price,
                    "maximum_price": maximum_price,
                    "requested_price": requested_price,
                    "price_exceeded": req_val > float(maximum_price),
                    "price_below_minimum": req_val < float(minimum_price),
                }
                _store_price_limit(ad_id, payload)
                return jsonify(payload)

            return jsonify({"status": False, "error": error_message}), 500


# =========================
# 💳 PAYMENT TYPES
# =========================
@app.route("/api/payment-types", methods=["POST"])
@require_api_keys
def payment_types(data):
    try:
        api = get_api(data)
        result = api.get_user_payment_types()
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 💰 BALANCE
# =========================
@app.route("/api/balance", methods=["POST"])
@require_api_keys
def balance(data):
    try:
        api = get_api(data)
        result = api.get_current_balance(
            accountType=data.get("accountType", "FUND"),
            coin=data.get("coin", "USDC"),
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 👤 ACCOUNT INFO
# =========================
@app.route("/api/account", methods=["POST"])
@require_api_keys
def account(data):
    try:
        api = get_api(data)
        result = api.get_account_information()
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 📢 ADS LIST
# =========================
@app.route("/api/ads", methods=["POST"])
@require_api_keys
def ads(data):
    try:
        api = get_api(data)
        sort_by = data.get("sortBy", "completion_rate")
        allowed_sorting = {
            "price": "price",
            "orders": "orders",
            "completion_rate": "completion_rate",
        }

        if sort_by not in allowed_sorting:
            return jsonify({
                "error": (
                    "Invalid sortBy value. Allowed values are: price, orders,"
                    " completion_rate"
                ),
                "allowed": list(allowed_sorting.keys()),
            }), 400

        result = api.get_ads_list(sort_by=allowed_sorting[sort_by])
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 📄 AD DETAILS
# =========================
@app.route("/api/ad-details", methods=["POST"])
@require_api_keys
def ad_details(data):
    try:
        api = get_api(data)
        result = api.get_ad_details(itemId=data.get("itemId"))
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# ❌ REMOVE AD
# =========================
@app.route("/api/remove-ad", methods=["POST"])
@require_api_keys
def remove_ad(data):
    try:
        api = get_api(data)
        result = api.remove_ad(itemId=data.get("itemId"))
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 📦 ORDERS
# =========================
@app.route("/api/orders", methods=["POST"])
@require_api_keys
def orders(data):
    try:
        api = get_api(data)
        result = api.get_orders(
            page=data.get("page", 1), size=data.get("size", 10)
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 💰 REFERENCE PRICE
# =========================
@app.route("/api/reference-price", methods=["POST"])
@require_api_keys
def reference_price(data):
    try:
        api_key = data.get("api_key")
        api_secret = data.get("api_secret")
        symbol = data.get("symbol", "BTC-USD")

        timestamp = str(int(time.time() * 1000))
        recv_window = "5000"
        query_string = f"symbol={symbol}"

        signature_payload = timestamp + api_key + recv_window + query_string
        signature = hmac.new(
            api_secret.encode("utf-8"),
            signature_payload.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()

        url = f"https://api.bybit.com/v5/fiat/reference-price?{query_string}"
        headers = {
            "X-BAPI-SIGN": signature,
            "X-BAPI-API-KEY": api_key,
            "X-BAPI-TIMESTAMP": timestamp,
            "X-BAPI-RECV-WINDOW": recv_window,
            "Content-Type": "application/json",
        }

        response = requests.get(url, headers=headers, timeout=20)
        return jsonify(response.json())
    except Exception as e:
        return jsonify({"status": False, "error": str(e)}), 500


# =========================
# ⏳ PENDING ORDERS
# =========================
@app.route("/api/pending-orders", methods=["POST"])
@require_api_keys
def pending_orders(data):
    try:
        api = get_api(data)
        result = api.get_pending_orders(
            page=data.get("page", 1), size=data.get("size", 10)
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 📊 MARKET ANALYSIS
# =========================
# Strategy (mirrors /api/ad-price-limit):
#  1. Serve cached results if fresh (< _MARKET_ANALYSIS_TTL seconds old).
#  2. Otherwise acquire a lock keyed on the analysis parameters so
#     overlapping concurrent requests share a SINGLE Bybit probe (the rest
#     wait, then read the cache).
#  3. One Bybit API call per TTL window — no flood, no rate-limit (10006).
@app.route("/api/analyze-market", methods=["POST"])
@require_api_keys
def analyze_market(data):
    try:
        token_id = data.get("tokenId", "BTC")
        currency_id = data.get("currencyId", "USD")
        side = str(data.get("side", "0"))
        min_amount = float(data.get("minAmount", 0))
        margin_pct = float(data.get("marginPct", 4))
        sort_by = str(data.get("sortBy", "price")).lower()

        cache_key = _market_analysis_cache_key(data)

        # Fast path: fresh cache, no Bybit call at all
        cached = _cached_market_analysis(cache_key)
        if cached is not None:
            _market_analysis_stats["cache_hits"] += 1
            app.logger.info(
                f"[analyze-market] CACHE HIT for key {cache_key} "
                f"(bybit_calls={_market_analysis_stats['bybit_calls']}, "
                f"cache_hits={_market_analysis_stats['cache_hits']})"
            )
            return jsonify(cached)

        # Slow path: one caller probes, the rest wait for the cache
        with _market_analysis_lock:
            # Re-check inside the lock (another request may have just filled it)
            cached = _cached_market_analysis(cache_key)
            if cached is not None:
                _market_analysis_stats["cache_hits"] += 1
                app.logger.info(
                    f"[analyze-market] CACHE HIT (post-lock) for key {cache_key} "
                    f"(bybit_calls={_market_analysis_stats['bybit_calls']}, "
                    f"cache_hits={_market_analysis_stats['cache_hits']})"
                )
                return jsonify(cached)

            _market_analysis_stats["bybit_calls"] += 1
            app.logger.info(
                f"[analyze-market] BYBIT CALL "
                f"#{_market_analysis_stats['bybit_calls']} for key {cache_key}"
            )

            api = get_api(data)
            ads = api.get_online_ads(
                tokenId=token_id,
                currencyId=currency_id,
                side=side,
                size="30",
                vaMaker=True,
                authMaker=False,
                bulkMaker=False,
                canTrade=False,
                verificationFilter=0,
                sortType="OVERALL_RANKING",
                sortStrategyCode="DEFAULT_BUY",
                itemRegion=1,
                tradeWith=False,
            )

            items = []
            if isinstance(ads, dict):
                items = ads.get("result", {}).get("items", [])

            competitors = []
            for ad in items:
                try:
                    price = float(ad.get("price", 0))
                    if price <= 0:
                        continue

                    ad_min = float(ad.get("minAmount", 0))
                    ad_max = float(ad.get("maxAmount", 0))

                    if min_amount > 0 and ad_max < min_amount:
                        continue

                    raw_orders = ad.get("recentOrderNum", 0)
                    try:
                        recent_orders = int(raw_orders) if raw_orders is not None else 0
                    except (ValueError, TypeError):
                        recent_orders = 0

                    raw_rate = ad.get("recentExecuteRate", 0)
                    try:
                        recent_rate = (
                            float(str(raw_rate).replace("%", "").strip())
                            if raw_rate is not None
                            else 0.0
                        )
                    except (ValueError, TypeError):
                        recent_rate = 0.0

                    competitors.append({
                        "id": ad.get("id"),
                        "nickName": ad.get("nickName"),
                        "price": price,
                        "minAmount": ad_min,
                        "maxAmount": ad_max,
                        "quantity": ad.get("quantity"),
                        "recentOrderNum": recent_orders,
                        "recentExecuteRate": recent_rate,
                        "paymentPeriod": ad.get("paymentPeriod"),
                    })
                except Exception:
                    continue

            if not competitors:
                payload = {
                    "status": False,
                    "error": "No valid competitor ads found",
                }
                # Don't cache the "no ads" miss — let the next request retry
                return jsonify(payload), 404

            if side == "0":
                top_competitor_price = max(c["price"] for c in competitors)
                recommended_price = top_competitor_price * (1 + (margin_pct / 100))
            else:
                top_competitor_price = min(c["price"] for c in competitors)
                recommended_price = top_competitor_price * (1 - (margin_pct / 100))

            if sort_by == "orders":
                competitors = sorted(
                    competitors, key=lambda x: x["recentOrderNum"], reverse=True
                )
            elif sort_by == "completion_rate":
                competitors = sorted(
                    competitors, key=lambda x: x["recentExecuteRate"], reverse=True
                )
            else:
                competitors = sorted(
                    competitors, key=lambda x: x["price"], reverse=(side == "0")
                )

            top_10 = competitors[:30]

            payload = {
                "status": True,
                "tokenId": token_id,
                "currencyId": currency_id,
                "side": side,
                "sort_by": sort_by,
                "ads_found": len(competitors),
                "top_competitor_price": round(top_competitor_price, 2),
                "margin_percent": margin_pct,
                "recommended_price": round(recommended_price, 2),
                "top_10_competitors": top_10,
            }
            _store_market_analysis(cache_key, payload)
            return jsonify(payload)

    except Exception as e:
        return jsonify({"status": False, "error": str(e)}), 500


# =========================
# 🌐 ONLINE ADS
# =========================
@app.route("/api/online-ads", methods=["POST"])
@require_api_keys
def online_ads(data):
    try:
        api = get_api(data)
        result = api.get_online_ads(
            tokenId=data.get("tokenId", "BTC"),
            currencyId=data.get("currencyId", "USD"),
            side=data.get("side", "0"),
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# 💳 MARK AS PAID
# =========================
@app.route("/api/mark-as-paid", methods=["POST"])
@require_api_keys
def mark_as_paid(data):
    try:
        api = get_api(data)
        result = api.mark_as_paid(
            orderId=data.get("orderId"),
            paymentType=str(data.get("paymentType")),
            paymentId=str(data.get("paymentId")),
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"status": False, "error": str(e)}), 500


# =========================
# 🚀 RUN APP
# =========================
if __name__ == "__main__":
    print("=" * 60)
    print("  ByBit P2P API — v2.1 with price-limit + market-analysis cache")
    print("  Price-limit TTL: 10s  |  Market-analysis TTL: 15s")
    print("  Library logger: SILENCED")
    print("  Restart this server to load code changes!")
    print("=" * 60)
    app.run(host="127.0.0.1", port=8090, debug=False)
