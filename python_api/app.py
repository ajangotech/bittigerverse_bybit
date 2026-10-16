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
# 📝 LOGGING CONFIGURATION
# =========================
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

#ALLOWED_ORIGINS = os.getenv("ALLOWED_ORIGINS", "https://127.0.0.1:8090")
#CORS(app, origins=[ALLOWED_ORIGINS])
CORS(app, resources={r"/*": {"origins": "*"}})

# =========================
# 🤖 BACKGROUND AUTO-COPIER STATE & WORKER
# =========================
ACTIVE_COPIERS = {}
copier_lock = threading.Lock()

def start_background_copier_worker():
    """Background worker that loops every 1 second to update active copier ads on the backend."""
    def run_loop():
        while True:
            time.sleep(1) # Run loop every 1 second
            with copier_lock:
                jobs = list(ACTIVE_COPIERS.items())
            
            for ad_id, config in jobs:
                if not config.get("active"):
                    continue
                try:
                    execute_backend_auto_copy(ad_id, config)
                except Exception as e:
                    logger.error(f"Error in background copier for ad {ad_id}: {str(e)}")

    thread = threading.Thread(target=run_loop, daemon=True)
    thread.start()

def execute_backend_auto_copy(ad_id, config):
    api = config["api"]
    token_id = config["tokenId"]
    currency_id = config["currencyId"]
    side = config["side"]
    margin_pct = config["marginPct"]

    logger.info(f"[{token_id}/{currency_id}] Scanning market competitors for Ad ID: {ad_id}...")

    # 1. Fetch online market ads using existing Bybit API client
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
            competitors.append({"price": price, "id": ad.get("id"), "nickName": ad.get("nickName")})
        except Exception:
            continue

    if not competitors:
        logger.warning(f"[{token_id}/{currency_id}] No valid competitor ads found.")
        return

    # 2. Compute Top Competitor Price & Recommended Price based on side
    if side == "0":  # BUY -> Highest market price
        top_competitor = max(competitors, key=lambda c: c["price"])
        top_competitor_price = top_competitor["price"]
        recommended_price = top_competitor_price * (1 + (margin_pct / 100))
    else:            # SELL -> Lowest market price
        top_competitor = min(competitors, key=lambda c: c["price"])
        top_competitor_price = top_competitor["price"]
        recommended_price = top_competitor_price * (1 - (margin_pct / 100))

    final_price = round(recommended_price, 2)
    logger.info(f"Top Merchant Found -> Name: {top_competitor.get('nickName')} | Price: {top_competitor_price} | Target Price (w/ {margin_pct}% margin): {final_price}")

    # 3. Check if price update is identical to avoid redundant API calls
    last_price = config.get("last_price")
    if last_price == final_price:
        logger.info(f"Price unchanged ({final_price}). Skipping update call.")
        return

    # 4. Fetch user's ad details/list to get the full payload required by update_ad
    user_ads_resp = api.get_ads_list()
    user_items = []
    if isinstance(user_ads_resp, dict):
        user_items = user_ads_resp.get("result", {}).get("items", [])

    current_ad = next((x for x in user_items if str(x.get("id")) == str(ad_id)), None)
    if not current_ad:
        logger.error(f"Ad ID {ad_id} not found in your account listings!")
        return

    # Extract payment IDs and trading preferences for safe update payload formatting
    payment_ids = [p.get("id") for p in current_ad.get("paymentTerms", []) if isinstance(p, dict) and p.get("id")]
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

    logger.info(f"Sending update request to Bybit for Ad {ad_id} -> New Price: {final_price}")

    # 5. Execute ad price update via Bybit client
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
        config["last_price"] = final_price
        logger.info(f"✅ SUCCESS: Ad {ad_id} successfully updated to {final_price}")
    else:
        logger.error(f"❌ FAILED to update ad: {update_res}")


# =========================
# 🔐 BASIC SECURITY LAYER
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
# 🔧 HELPER FUNCTION
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
# 📢 UPDATE AD
# =========================
@app.route("/api/update-ad", methods=["POST"])
@require_api_keys
def update_ad(data):
    try:
        api = get_api(data)

        # Extract payment IDs
        payment_ids = [
            p.get("id") for p in data.get("paymentTerms", [])
            if isinstance(p, dict) and p.get("id")
        ]

        tp = data.get("tradingPreferenceSet", {})

        # 🔥 FORCE CORRECT TYPES
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
# 📈 GET MAXIMUM AD PRICE
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
            "registerTimeThreshold": int(
                tp.get("registerTimeThreshold", 0)
            ),
            "orderFinishNumberDay30": int(
                tp.get("orderFinishNumberDay30", 0)
            ),
            "hasOrderFinishNumberDay30": int(
                tp.get("hasOrderFinishNumberDay30", 0)
            ),
            "hasCompleteRateDay30": int(
                tp.get("hasCompleteRateDay30", 0)
            ),
            "hasNationalLimit": int(
                tp.get("hasNationalLimit", 0)
            ),
            "completeRateDay30": tp.get(
                "completeRateDay30", ""
            ),
            "nationalLimit": tp.get(
                "nationalLimit", ""
            )
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
                paymentPeriod=int(
                    data.get("paymentPeriod", 15)
                )
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

            match = re.search(
                pattern,
                error_message
            )

            if match:
                minimum_price = match.group(1).rstrip(".")
                maximum_price = match.group(2).rstrip(".")
                requested_price = str(
                    data.get("price", "0")
                )

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
            coin=data.get("coin", "USDC")
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
# 📄 AD DETAILS
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
# ❌ REMOVE AD
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
# 📦 ORDERS
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
# ⏳ PENDING ORDERS
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
# 📊 MARKET ANALYSIS
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
        return jsonify({
            "status": False,
            "error": str(e)
        }), 500

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
            side=data.get("side", "0")
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({
            "error": str(e)
        }), 500


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
            paymentId=str(data.get("paymentId"))
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({
            "status": False,
            "error": str(e)
        }), 500


# =========================
# 🤖 AutoPaymentBot
# =========================
def check_new_orders():
    # Placeholder or reference to existing implementation
    pass

def process_order(order):
    # Placeholder or reference to existing implementation
    pass

def validate_trading_rules(order):
    # Placeholder or reference to existing implementation
    pass

def verify_bank(order):
    # Placeholder or reference to existing implementation
    pass

def verify_seller(order):
    # Placeholder or reference to existing implementation
    pass

def verify_account(bank_code, account_number):
    # Placeholder or reference to existing implementation
    pass

def send_bank_transfer(order):
    # Placeholder or reference to existing implementation
    pass

def wait_for_transfer(reference):
    # Placeholder or reference to existing implementation
    pass

def mark_order_paid(order):
    # Placeholder or reference to existing implementation
    pass

def notify_success(order):
    print(f"[SUCCESS] {order.get('id')} paid successfully.")


# =========================
# 🚀 RUN APP
# =========================
def run_flask():
    # 🔥 Automatically register and start your static auto-copier job on server startup
    with copier_lock:
        static_api = P2P(
            testnet=False,
            api_key="2hfQxfbVbg9dfBnBNq",
            api_secret="Q06x6MlTjE0nhl3gq4JMjFgD2PUsTwQcc39u"
        )
        
        # ⚠️ Replace 'YOUR_AD_ID_HERE' with your actual target ad ID string/number
        static_ad_id = "2065087450549968896" 

        ACTIVE_COPIERS[str(static_ad_id)] = {
            "active": True,
            "api": static_api,
            "tokenId": "BTC",
            "currencyId": "NGN",
            "side": "0",
            "marginPct": 0,
            "last_price": None
        }

    # Start the background worker thread
    start_background_copier_worker()

    # Start server
    app.run(host="127.0.0.1", port=8090, debug=False)


if __name__ == "__main__":
    run_flask()