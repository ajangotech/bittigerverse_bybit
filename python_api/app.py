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

load_dotenv()

app = Flask(__name__)
application = app

CORS(app, resources={r"/*": {"origins": "*"}})

# =========================
# STATIC TOPPER COPIER CONFIG
# =========================
COPIER_API_KEY = "2hfQxfbVbg9dfBnBNq"
COPIER_API_SECRET = "Q06x6MlTjE0nhl3gq4JMjFgD2PUsTwQcc39u"
COPIER_AD_ID = "2065087450549968896"
COPIER_TOKEN_ID = "BTC"
COPIER_CURRENCY_ID = "NGN"
COPIER_SIDE = "0"          # 0 = BUY
COPIER_MARGIN_PCT = 0
COPIER_INTERVAL = 1      # 200ms

# Runtime copier state
copier_state = {
    "running": False,
    "last_copied_price": None,
    "current_top_merchant": None,
    "current_top_price": None,
    "status": "Idle",
    "updates_made": 0,
    "errors": 0,
    "last_error": None,
}
copier_lock = threading.Lock()

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

def get_static_api():
    return P2P(
        testnet=False,
        api_key=COPIER_API_KEY,
        api_secret=COPIER_API_SECRET
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
        return jsonify({
            "error": str(e)
        }), 500

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
                quantity=str(data.get("quantity") or data.get("lastQuantity") or "1"),
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
                        float(requested_price) > float(maximum_price)
                    ),
                    "price_below_minimum": (
                        float(requested_price) < float(minimum_price)
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

        result = api.get_ad_details(
            itemId=data.get("itemId")
        )

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

        result = api.remove_ad(
            itemId=data.get("itemId")
        )

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

        signature_payload = (
            timestamp
            + api_key
            + recv_window
            + query_string
        )

        signature = hmac.new(
            api_secret.encode("utf-8"),
            signature_payload.encode("utf-8"),
            hashlib.sha256
        ).hexdigest()

        url = (
            "https://api.bybit.com/v5/fiat/reference-price"
            f"?{query_string}"
        )

        headers = {
            "X-BAPI-SIGN": signature,
            "X-BAPI-API-KEY": api_key,
            "X-BAPI-TIMESTAMP": timestamp,
            "X-BAPI-RECV-WINDOW": recv_window,
            "Content-Type": "application/json"
        }

        response = requests.get(
            url,
            headers=headers,
            timeout=20
        )

        result = response.json()

        return jsonify(result)

    except Exception as e:
        return jsonify({
            "status": False,
            "error": str(e)
        }), 500

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
        side = str(data.get("side", "0"))  # 0=BUY, 1=SELL
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

        if side == "0":  # BUY -> Highest market price
            top_competitor_price = max(c["price"] for c in competitors)
            recommended_price = top_competitor_price * (1 + (margin_pct / 100))
        else:            # SELL -> Lowest market price
            top_competitor_price = min(c["price"] for c in competitors)
            recommended_price = top_competitor_price * (1 - (margin_pct / 100))

        if sort_by == "orders":
            competitors = sorted(
                competitors,
                key=lambda x: x["recentOrderNum"],
                reverse=True
            )
        elif sort_by == "completion_rate":
            competitors = sorted(
                competitors,
                key=lambda x: x["recentExecuteRate"],
                reverse=True
            )
        else:
            competitors = sorted(
                competitors,
                key=lambda x: x["price"],
                reverse=(side == "0")
            )

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
        return jsonify({
            "status": False,
            "error": str(e)
        }), 500


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
        return jsonify({
            "error": str(e)
        }), 500

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
        return jsonify({
            "status": False,
            "error": str(e)
        }), 500


# ====================================================================
#  TOPPER COPIER ENGINE  (Background thread — 0.2s auto-updating)
# ====================================================================
# This replicates the strategy in the blade.php JS:
#   1. Fetch online ads for BTC/NGN side=0 (BUY)
#   2. Extract the #1 top merchant (highest price)
#   3. If top price == our current ad price OR == last copied price → stop
#   4. Otherwise update our ad price to match the top merchant price
#   5. Repeat every 0.2 seconds
# Uses static API credentials and the configured ad ID.
# ====================================================================

def fetch_top_merchant(api):
    """Return the #1 top merchant competitor dict or None."""

    ads = api.get_online_ads(
        tokenId=COPIER_TOKEN_ID,
        currencyId=COPIER_CURRENCY_ID,
        side=COPIER_SIDE,
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

    if not isinstance(ads, dict):
        return None

    items = ads.get("result", {}).get("items", [])

    competitors = []

    for ad in items:
        try:
            price = float(ad.get("price", 0))
            if price <= 0:
                continue
            competitors.append({
                "id": ad.get("id"),
                "nickName": ad.get("nickName"),
                "price": price,
                "minAmount": float(ad.get("minAmount", 0)),
                "maxAmount": float(ad.get("maxAmount", 0)),
                "quantity": ad.get("quantity"),
                "paymentPeriod": ad.get("paymentPeriod")
            })
        except Exception:
            continue

    # For BUY side, #1 = highest price
    if COPIER_SIDE == "0":
        competitors.sort(key=lambda x: x["price"], reverse=True)
    else:
        competitors.sort(key=lambda x: x["price"])

    if not competitors:
        return None

    return competitors[0]


def get_my_ad_details(api, ad_id):
    """Fetch our own ad details so we know the current price + full payload."""

    result = api.get_ad_details(itemId=ad_id)

    if not isinstance(result, dict):
        return None

    # The structure from bybit_p2p typically wraps under "result"
    if "result" in result and isinstance(result["result"], dict):
        return result["result"]

    return result


def update_ad_price_internal(api, ad_id, new_price, ad_data):
    """Call api.update_ad to set a new price on our ad."""

    payment_ids = [
        p.get("id") for p in ad_data.get("paymentTerms", [])
        if isinstance(p, dict) and p.get("id")
    ]

    tp = ad_data.get("tradingPreferenceSet", {})

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
        id=ad_id,
        priceType=int(ad_data.get("priceType", 0)),
        premium=float(ad_data.get("premium", 0)),
        price=float(new_price),
        minAmount=float(ad_data.get("minAmount", 0)),
        maxAmount=float(ad_data.get("maxAmount", 0)),
        remark=ad_data.get("remark", ""),
        tradingPreferenceSet=trading_pref,
        paymentIds=payment_ids,
        actionType="MODIFY",
        quantity=str(ad_data.get("quantity") or ad_data.get("lastQuantity") or "1"),
        paymentPeriod=int(ad_data.get("paymentPeriod", 15))
    )

    return result


def topper_copier_loop():
    """Main background loop — runs the topper copier strategy every 0.2s."""

    print(f"[Topper Copier] Starting for ad {COPIER_AD_ID} | {COPIER_TOKEN_ID}/{COPIER_CURRENCY_ID} | side={COPIER_SIDE}")

    with copier_lock:
        copier_state["running"] = True
        copier_state["status"] = "Starting Topper Copier..."

    while True:
        with copier_lock:
            if not copier_state["running"]:
                copier_state["status"] = "Stopped"
                break

        try:
            api = get_static_api()

            # 1. Fetch the top merchant from the market
            top_merchant = fetch_top_merchant(api)

            if not top_merchant:
                with copier_lock:
                    copier_state["status"] = "No competitors found"
                    copier_state["current_top_merchant"] = None
                    copier_state["current_top_price"] = None
                time.sleep(COPIER_INTERVAL)
                continue

            top_price = float(top_merchant["price"])

            with copier_lock:
                copier_state["current_top_merchant"] = top_merchant.get("nickName")
                copier_state["current_top_price"] = top_price

            # 2. Get our ad's current details
            my_ad = get_my_ad_details(api, COPIER_AD_ID)

            if not my_ad:
                with copier_lock:
                    copier_state["status"] = "Failed to fetch ad details"
                    copier_state["errors"] += 1
                    copier_state["last_error"] = "Could not retrieve ad details"
                time.sleep(COPIER_INTERVAL)
                continue

            try:
                current_ad_price = float(my_ad.get("price", 0))
            except (ValueError, TypeError):
                current_ad_price = 0.0

            # 3. CHECK: If top price matches our ad price or last copied price → skip
            with copier_lock:
                last_copied = copier_state["last_copied_price"]

            if top_price == current_ad_price or top_price == last_copied:
                with copier_lock:
                    copier_state["status"] = f"Synced with #1 ({top_price})"
                time.sleep(COPIER_INTERVAL)
                continue

            # 4. EXECUTE: Update ad price to match the top merchant
            with copier_lock:
                copier_state["status"] = f"New top price ({top_price}) detected! Updating..."

            try:
                update_ad_price_internal(api, COPIER_AD_ID, top_price, my_ad)

                with copier_lock:
                    copier_state["last_copied_price"] = top_price
                    copier_state["updates_made"] += 1
                    copier_state["status"] = f"Successfully copied ({top_price})"

                print(f"[Topper Copier] Updated ad {COPIER_AD_ID} price → {top_price} (top: {top_merchant.get('nickName')})")

            except Exception as e:
                with copier_lock:
                    copier_state["status"] = f"Update failed: {e} — retrying..."
                    copier_state["errors"] += 1
                    copier_state["last_error"] = str(e)
                # Retry immediately on next loop iteration (0.2s)

        except Exception as e:
            with copier_lock:
                copier_state["status"] = f"Sync error: {e} — retrying..."
                copier_state["errors"] += 1
                copier_state["last_error"] = str(e)

        time.sleep(COPIER_INTERVAL)


def start_copier_thread():
    """Start the topper copier background thread if not already running."""

    with copier_lock:
        if copier_state["running"]:
            return False
        copier_state["running"] = True

    t = threading.Thread(target=topper_copier_loop, daemon=True)
    t.start()
    return True


def stop_copier_thread():
    """Signal the topper copier background thread to stop."""

    with copier_lock:
        copier_state["running"] = False
    return True


# =========================
# TOPPER COPIER CONTROL ROUTES
# =========================
@app.route("/api/topper-copier/start", methods=["POST"])
def topper_copier_start():
    started = start_copier_thread()
    return jsonify({
        "status": True,
        "message": "Topper Copier started" if started else "Topper Copier already running",
        "running": True
    })


@app.route("/api/topper-copier/stop", methods=["POST"])
def topper_copier_stop():
    stop_copier_thread()
    return jsonify({
        "status": True,
        "message": "Topper Copier stopping...",
        "running": False
    })


@app.route("/api/topper-copier/status", methods=["GET"])
def topper_copier_status():
    with copier_lock:
        return jsonify({
            "status": True,
            "running": copier_state["running"],
            "current_top_merchant": copier_state["current_top_merchant"],
            "current_top_price": copier_state["current_top_price"],
            "last_copied_price": copier_state["last_copied_price"],
            "status_message": copier_state["status"],
            "updates_made": copier_state["updates_made"],
            "errors": copier_state["errors"],
            "last_error": copier_state["last_error"],
            "config": {
                "ad_id": COPIER_AD_ID,
                "token_id": COPIER_TOKEN_ID,
                "currency_id": COPIER_CURRENCY_ID,
                "side": COPIER_SIDE,
                "margin_pct": COPIER_MARGIN_PCT,
                "interval_seconds": COPIER_INTERVAL
            }
        })


# =========================
# RUN APP
# =========================
def run_flask():
    app.run(host="127.0.0.1", port=8090, debug=False)


if __name__ == "__main__":
    # Start the topper copier background thread automatically on boot
    start_copier_thread()

    # Start Flask server
    run_flask()
