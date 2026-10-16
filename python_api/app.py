import requests
from bybit_p2p import P2P
import time
import hmac
import re
import hashlib
from flask import Flask, request, jsonify
from flask_cors import CORS
from functools import wraps
from dotenv import load_dotenv
import os
import threading
import logging

load_dotenv()

app = Flask(__name__)
application = app

# =========================
# LOGGING CONFIGURATION
# =========================
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

CORS(app, resources={r"/*": {"origins": "*"}})

# =========================
# BACKGROUND AUTO-COPIER STATE & WORKER
# =========================
ACTIVE_COPIERS = {}
copier_lock = threading.Lock()

# Copier poll interval in seconds (matches the 200ms frontend engine as closely
# as practical for a server-side loop; 0.2s would be too aggressive for a
# synchronous Python worker, so we use 1s with immediate-first-tick).
COPIER_POLL_INTERVAL = 1.0


def start_background_copier_worker():
    """Background worker that loops continuously to update active copier ads."""

    def run_loop():
        while True:
            with copier_lock:
                jobs = list(ACTIVE_COPIERS.items())

            for ad_id, config in jobs:
                if not config.get("active"):
                    continue

                # Skip if a previous cycle for this ad is still running
                if config.get("busy"):
                    continue

                try:
                    execute_backend_auto_copy(ad_id, config)
                except Exception as e:
                    logger.error(f"Error in background copier for ad {ad_id}: {str(e)}")

            time.sleep(COPIER_POLL_INTERVAL)

    thread = threading.Thread(target=run_loop, daemon=True)
    thread.start()


def execute_backend_auto_copy(ad_id, config):
    """
    Backend auto-copier — mirrors the frontend syncTopperCopier logic exactly:

    1. Call analyze-market to get the top-30 competitors (same endpoint, same
       sort the frontend uses).
    2. Take competitors[0] as the #1 top merchant.
    3. If the top price equals our ad's current price OR the last price we
       already copied, skip the update (no redundant API calls).
    4. Otherwise, update the ad to the exact top price (NO margin adjustment).
    5. On update failure, retry up to 5 times with a short delay before
       giving up for this cycle.
    """
    api = config["api"]
    token_id = config["tokenId"]
    currency_id = config["currencyId"]
    side = str(config.get("side", "0"))
    margin_pct = float(config.get("marginPct", 0))

    config["busy"] = True

    try:
        logger.info(f"[{token_id}/{currency_id}] Scanning market for Ad {ad_id}...")

        # 1. Fetch top competitors via the SAME analyze-market path the
        #    frontend uses, so the #1 merchant matches what the UI shows.
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
            tradeWith=False
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
                competitors.append({
                    "price": price,
                    "id": ad.get("id"),
                    "nickName": ad.get("nickName")
                })
            except Exception:
                continue

        if not competitors:
            logger.warning(f"[{token_id}/{currency_id}] No valid competitor ads found.")
            config["status"] = "No competitors found"
            return

        # 2. Determine the #1 top merchant based on side.
        #    BUY  (side=0): highest price is the top.
        #    SELL (side=1): lowest price is the top.
        if side == "0":
            top_competitor = max(competitors, key=lambda c: c["price"])
        else:
            top_competitor = min(competitors, key=lambda c: c["price"])

        top_price = top_competitor["price"]

        # Use the EXACT top price — no margin adjustment.
        # The frontend copies the raw top merchant price directly.
        final_price = top_price

        logger.info(
            f"Top merchant: {top_competitor.get('nickName')} | "
            f"Price: {top_price} | Target: {final_price}"
        )

        # 3. Skip if price hasn't changed.
        #    Check against BOTH the ad's known current price and the last
        #    price we successfully copied — same guard as the frontend.
        last_price = config.get("last_price")
        current_ad_price = config.get("current_ad_price")

        if final_price == last_price:
            logger.info(f"Price unchanged ({final_price}). Skipping update.")
            config["status"] = f"Synced with #1 ({final_price})"
            return

        if final_price == current_ad_price:
            logger.info(f"Price already matches ad ({final_price}). Skipping update.")
            config["status"] = f"Synced with #1 ({final_price})"
            return

        # 4. Fetch the user's ad details for the update payload.
        #    Cache the ad object so we don't fetch the full list every cycle.
        current_ad = config.get("cached_ad")
        if not current_ad:
            user_ads_resp = api.get_ads_list()
            user_items = []
            if isinstance(user_ads_resp, dict):
                user_items = user_ads_resp.get("result", {}).get("items", [])

            current_ad = next(
                (x for x in user_items if str(x.get("id")) == str(ad_id)),
                None
            )
            if current_ad:
                config["cached_ad"] = current_ad
            else:
                logger.error(f"Ad {ad_id} not found in your account listings!")
                config["status"] = "Ad not found"
                return

        # Build the update payload once from the cached ad.
        payment_ids = [
            p.get("id") for p in current_ad.get("paymentTerms", [])
            if isinstance(p, dict) and p.get("id")
        ]
        tp = current_ad.get("tradingPreferenceSet", {})
        trading_pref = {
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
            "nationalLimit": tp.get("nationalLimit", "")
        }

        # 5. Execute the update with retries (mirrors frontend retry loop).
        max_retries = 5
        retry_delay = 0.2  # 200ms, same as frontend

        for attempt in range(1, max_retries + 1):
            logger.info(
                f"Updating ad {ad_id} -> {final_price} "
                f"(attempt {attempt}/{max_retries})"
            )

            try:
                update_res = api.update_ad(
                    id=current_ad.get("id"),
                    priceType=int(current_ad.get("priceType", 0)),
                    premium=float(current_ad.get("premium", 0)),
                    price=float(final_price),
                    minAmount=float(current_ad.get("minAmount", 0)),
                    maxAmount=float(current_ad.get("maxAmount", 0)),
                    remark=current_ad.get("remark", ""),
                    tradingPreferenceSet=trading_pref,
                    paymentIds=payment_ids,
                    actionType="MODIFY",
                    quantity=str(current_ad.get("quantity", "1")),
                    paymentPeriod=int(current_ad.get("paymentPeriod", 15))
                )

                if update_res and update_res.get("status") is not False:
                    # SUCCESS — record the new price so we don't re-update
                    # to the same value on the next cycle.
                    config["last_price"] = final_price
                    config["current_ad_price"] = final_price
                    config["status"] = f"Successfully copied ({final_price})"
                    logger.info(f"SUCCESS: Ad {ad_id} updated to {final_price}")
                    return
                else:
                    logger.error(
                        f"Update attempt {attempt} failed: {update_res}"
                    )
                    config["status"] = f"Update retry {attempt}/{max_retries}"

            except Exception as e:
                logger.error(f"Update attempt {attempt} error: {str(e)}")
                config["status"] = f"Update error (retry {attempt})"

            if attempt < max_retries:
                time.sleep(retry_delay)

        # All retries exhausted — will try again next cycle.
        config["status"] = "Update failed — will retry next cycle"
        logger.error(f"All {max_retries} retry attempts failed for ad {ad_id}")

    finally:
        config["busy"] = False


# =========================
# API: Start / Stop / Status for copier jobs
# =========================

@app.route("/api/copier/start", methods=["POST"])
def copier_start():
    """Start a backend auto-copier job for a specific ad."""
    data = request.get_json(silent=True) or {}

    if not data.get("api_key") or not data.get("api_secret"):
        return jsonify({"error": "api_key and api_secret are required"}), 400

    ad_id = str(data.get("adId", ""))
    if not ad_id:
        return jsonify({"error": "adId is required"}), 400

    api = P2P(
        testnet=False,
        api_key=data["api_key"],
        api_secret=data["api_secret"]
    )

    with copier_lock:
        ACTIVE_COPIERS[ad_id] = {
            "active": True,
            "api": api,
            "tokenId": data.get("tokenId", "BTC"),
            "currencyId": data.get("currencyId", "NGN"),
            "side": str(data.get("side", "0")),
            "marginPct": float(data.get("marginPct", 0)),
            "last_price": None,
            "current_ad_price": None,
            "cached_ad": None,
            "busy": False,
            "status": "Starting..."
        }

    logger.info(f"Copier started for ad {ad_id} ({data.get('tokenId')}/{data.get('currencyId')})")
    return jsonify({"success": True, "adId": ad_id})


@app.route("/api/copier/stop", methods=["POST"])
def copier_stop():
    """Stop a backend auto-copier job."""
    data = request.get_json(silent=True) or {}
    ad_id = str(data.get("adId", ""))

    with copier_lock:
        if ad_id in ACTIVE_COPIERS:
            ACTIVE_COPIERS[ad_id]["active"] = False
            del ACTIVE_COPIERS[ad_id]

    logger.info(f"Copier stopped for ad {ad_id}")
    return jsonify({"success": True, "adId": ad_id})


@app.route("/api/copier/status", methods=["GET"])
def copier_status():
    """Get status of all active copier jobs."""
    with copier_lock:
        jobs = {}
        for ad_id, config in ACTIVE_COPIERS.items():
            jobs[ad_id] = {
                "active": config.get("active", False),
                "tokenId": config.get("tokenId"),
                "currencyId": config.get("currencyId"),
                "side": config.get("side"),
                "status": config.get("status", "unknown"),
                "last_price": config.get("last_price"),
                "current_ad_price": config.get("current_ad_price")
            }
    return jsonify(jobs)


# =========================
# BASIC SECURITY LAYER
# =========================
def require_api_keys(f):
    @wraps(f)
    def wrapper():
        data = request.get_json(silent=True) or {}

        if not isinstance(data, dict):
            return jsonify({
                "error": "Request body must be a JSON object"
            }), 400

        if not data.get("api_key") or not data.get("api_secret"):
            return jsonify({
                "error": "api_key and api_secret are required"
            }), 400

        return f(data)

    return wrapper


# =========================
# HELPER FUNCTION
# =========================
def get_api(data):
    return P2P(
        testnet=False,
        api_key=data.get("api_key"),
        api_secret=data.get("api_secret")
    )


@app.route("/", methods=["GET"])
def home():
    return jsonify({
        "status": "success",
        "message": "Welcome to ByBit API",
        "version": "1.0"
    })


# =========================
# UPDATE AD
# =========================
@app.route("/api/update-ad", methods=["POST"])
@require_api_keys
def update_ad(data):
    try:
        api = get_api(data)

        payment_ids = [
            p.get("id") for p in data.get("paymentTerms", [])
            if isinstance(p, dict) and p.get("id")
        ]

        tp = data.get("tradingPreferenceSet", {})

        trading_pref = {
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
            "nationalLimit": tp.get("nationalLimit", "")
        }

        result = api.update_ad(
            id=data.get("id"),
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
            paymentPeriod=int(data.get("paymentPeriod", 15))
        )

        return jsonify(result)

    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# GET MAXIMUM AD PRICE
# =========================
@app.route("/api/ad-price-limit", methods=["POST"])
@require_api_keys
def ad_price_limit(data):
    try:
        api = get_api(data)

        payment_ids = [
            p.get("id")
            for p in data.get("paymentTerms", [])
            if isinstance(p, dict) and p.get("id")
        ]

        tp = data.get("tradingPreferenceSet", {})

        trading_pref = {
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
            "nationalLimit": tp.get("nationalLimit", "")
        }

        try:
            api.update_ad(
                id=data.get("id"),
                priceType=int(data.get("priceType", 0)),
                premium=float(data.get("premium", 0)),
                price=float(data.get("price")),
                minAmount=float(data.get("minAmount")),
                maxAmount=float(data.get("maxAmount")),
                remark=data.get("remark", ""),
                tradingPreferenceSet=trading_pref,
                paymentIds=payment_ids,
                actionType="MODIFY",
                quantity=str(
                    data.get("quantity")
                    or data.get("lastQuantity")
                    or "1"
                ),
                paymentPeriod=int(data.get("paymentPeriod", 15))
            )

            requested_price = str(data.get("price", "0"))

            return jsonify({
                "status": True,
                "price_limit": False,
                "price": requested_price
            })

        except Exception as e:
            error_message = str(e)
            pattern = (
                r"lower than\s+([\d.]+)"
                r"\s+or higher than\s+([\d.]+)"
            )

            match = re.search(pattern, error_message)

            if match:
                minimum_price = match.group(1).rstrip(".")
                maximum_price = match.group(2).rstrip(".")
                requested_price = str(data.get("price", "0"))

                return jsonify({
                    "status": True,
                    "price_limit": True,
                    "price": maximum_price,
                    "minimum_price": minimum_price,
                    "maximum_price": maximum_price,
                    "requested_price": requested_price,
                    "price_exceeded": (
                        float(requested_price)
                        > float(maximum_price)
                    ),
                    "price_below_minimum": (
                        float(requested_price)
                        < float(minimum_price)
                    )
                })

            return jsonify({
                "status": False,
                "error": error_message
            }), 500

    except Exception as e:
        return jsonify({
            "status": False,
            "error": str(e)
        }), 500


# =========================
# PAYMENT TYPES
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
# BALANCE
# =========================
@app.route("/api/balance", methods=["POST"])
@require_api_keys
def balance(data):
    try:
        api = get_api(data)
        result = api.get_current_balance(
            accountType=data.get("accountType", "FUND"),
            coin=data.get("coin", "USDC")
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# ACCOUNT INFO
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
# ADS LIST
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
            "completion_rate": "completion_rate"
        }

        if sort_by not in allowed_sorting:
            return jsonify({
                "error": "Invalid sortBy value. Allowed values are: price, orders, completion_rate",
                "allowed": list(allowed_sorting.keys())
            }), 400

        result = api.get_ads_list(sort_by=allowed_sorting[sort_by])
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# AD DETAILS
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
# REMOVE AD
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
# ORDERS
# =========================
@app.route("/api/orders", methods=["POST"])
@require_api_keys
def orders(data):
    try:
        api = get_api(data)
        result = api.get_orders(
            page=data.get("page", 1),
            size=data.get("size", 10)
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# REFERENCE PRICE
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
            hashlib.sha256
        ).hexdigest()

        url = f"https://api.bybit.com/v5/fiat/reference-price?{query_string}"

        headers = {
            "X-BAPI-SIGN": signature,
            "X-BAPI-API-KEY": api_key,
            "X-BAPI-TIMESTAMP": timestamp,
            "X-BAPI-RECV-WINDOW": recv_window,
            "Content-Type": "application/json"
        }

        response = requests.get(url, headers=headers, timeout=20)
        result = response.json()
        return jsonify(result)

    except Exception as e:
        return jsonify({"status": False, "error": str(e)}), 500


# =========================
# PENDING ORDERS
# =========================
@app.route("/api/pending-orders", methods=["POST"])
@require_api_keys
def pending_orders(data):
    try:
        api = get_api(data)
        result = api.get_pending_orders(
            page=data.get("page", 1),
            size=data.get("size", 10)
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# MARKET ANALYSIS
# =========================
@app.route("/api/analyze-market", methods=["POST"])
@require_api_keys
def analyze_market(data):
    try:
        api = get_api(data)

        token_id = data.get("tokenId", "BTC")
        currency_id = data.get("currencyId", "USD")
        side = str(data.get("side", "0"))
        min_amount = float(data.get("minAmount", 0))
        margin_pct = float(data.get("marginPct", 4))
        sort_by = str(data.get("sortBy", "price")).lower()

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
            tradeWith=False
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
                    recent_rate = float(str(raw_rate).replace("%", "").strip()) if raw_rate is not None else 0.0
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
                    "paymentPeriod": ad.get("paymentPeriod")
                })
            except Exception:
                continue

        if not competitors:
            return jsonify({
                "status": False,
                "error": "No valid competitor ads found"
            }), 404

        if side == "0":
            top_competitor_price = max(c["price"] for c in competitors)
            recommended_price = top_competitor_price * (1 + (margin_pct / 100))
        else:
            top_competitor_price = min(c["price"] for c in competitors)
            recommended_price = top_competitor_price * (1 - (margin_pct / 100))

        if sort_by == "orders":
            competitors = sorted(competitors, key=lambda x: x["recentOrderNum"], reverse=True)
        elif sort_by == "completion_rate":
            competitors = sorted(competitors, key=lambda x: x["recentExecuteRate"], reverse=True)
        else:
            competitors = sorted(competitors, key=lambda x: x["price"], reverse=(side == "0"))

        top_10 = competitors[:30]

        return jsonify({
            "status": True,
            "tokenId": token_id,
            "currencyId": currency_id,
            "side": side,
            "sort_by": sort_by,
            "ads_found": len(competitors),
            "top_competitor_price": round(top_competitor_price, 2),
            "margin_percent": margin_pct,
            "recommended_price": round(recommended_price, 2),
            "top_10_competitors": top_10
        })

    except Exception as e:
        return jsonify({"status": False, "error": str(e)}), 500


# =========================
# ONLINE ADS
# =========================
@app.route("/api/online-ads", methods=["POST"])
@require_api_keys
def online_ads(data):
    try:
        api = get_api(data)
        result = api.get_online_ads(
            tokenId=data.get("tokenId", "BTC"),
            currencyId=data.get("currencyId", "USD"),
            side=data.get("side", "0")
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# MARK AS PAID
# =========================
@app.route("/api/mark-as-paid", methods=["POST"])
@require_api_keys
def mark_as_paid(data):
    try:
        api = get_api(data)
        result = api.mark_as_paid(
            orderId=data.get("orderId"),
            paymentType=str(data.get("paymentType")),
            paymentId=str(data.get("paymentId"))
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({"status": False, "error": str(e)}), 500


# =========================
# RUN APP
# =========================
def run_flask():
    # Register and start the static auto-copier job on server startup.
    # Read credentials from environment so they are not hardcoded in source.
    static_api_key = os.getenv("BYBIT_API_KEY", "2hfQxfbVbg9dfBnBNq")
    static_api_secret = os.getenv("BYBIT_API_SECRET", "Q06x6MlTjE0nhl3gq4JMjFgD2PUsTwQcc39u")
    static_ad_id = os.getenv("COPIER_AD_ID", "2065087450549968896")
    static_token = os.getenv("COPIER_TOKEN", "BTC")
    static_currency = os.getenv("COPIER_CURRENCY", "NGN")
    static_side = os.getenv("COPIER_SIDE", "0")
    static_margin = float(os.getenv("COPIER_MARGIN_PCT", "0"))

    with copier_lock:
        static_api = P2P(
            testnet=False,
            api_key=static_api_key,
            api_secret=static_api_secret
        )

        ACTIVE_COPIERS[str(static_ad_id)] = {
            "active": True,
            "api": static_api,
            "tokenId": static_token,
            "currencyId": static_currency,
            "side": static_side,
            "marginPct": static_margin,
            "last_price": None,
            "current_ad_price": None,
            "cached_ad": None,
            "busy": False,
            "status": "Starting..."
        }

    logger.info(
        f"Static copier registered: ad={static_ad_id} "
        f"pair={static_token}/{static_currency} side={static_side}"
    )

    # Start the background worker thread
    start_background_copier_worker()

    # Start server
    app.run(host="127.0.0.1", port=8090, debug=False)


if __name__ == "__main__":
    run_flask()
