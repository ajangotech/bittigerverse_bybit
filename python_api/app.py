import requests
from bybit_p2p import P2P
import requests
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
import webbrowser

load_dotenv()

app = Flask(__name__)
application = app

#ALLOWED_ORIGINS = os.getenv("ALLOWED_ORIGINS", "https://127.0.0.1:8080")
#CORS(app, origins=[ALLOWED_ORIGINS])
CORS(app, resources={r"/*": {"origins": "*"}})

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

        # Extract payment IDs
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

            # This is ONLY used to ask Bybit for its
            # allowed price range.
            #
            # The response is NOT used to update anything.
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

            # If the requested price is accepted,
            # simply return the requested price.
            requested_price = str(data.get("price", "0"))

            return jsonify({
                "status": True,
                "price_limit": False,
                "price": requested_price
            })

        except Exception as e:

            error_message = str(e)

            # ---------------------------------
            # EXTRACT BYBIT PRICE RANGE
            # ---------------------------------
            import re

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

            # ---------------------------------
            # OTHER ERROR
            # ---------------------------------
            return jsonify({
                "status": False,
                "error": error_message
            }), 500

    except Exception as e:

        return jsonify({
            "status": False,
            "error": str(e)
        }), 500# =========================

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
        
        # 1. Extract the sortBy parameter from frontend JSON (default to completion_rate)
        sort_by = data.get("sortBy", "completion_rate")
        
        # 2. Define the allowed sorting filters mapped to what Bybit expects
        allowed_sorting = {
            "price": "price",
            "orders": "orders",
            "completion_rate": "completion_rate"
        }
        
        # 3. Block invalid sorting attempts
        if sort_by not in allowed_sorting:
            return jsonify({
                "error": "Invalid sortBy value. Allowed values are: price, orders, completion_rate",
                "allowed": list(allowed_sorting.keys())
            }), 400

        # 4. Pass the sorting parameter to your get_ads_list method
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

        # Bybit GET signature
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
        
        # Extract sortBy parameter (default to "price")
        sort_by = str(data.get("sortBy", "price")).lower()

        ads = api.get_online_ads(
            tokenId=token_id,
            currencyId=currency_id,
            side=side,
            size="30"
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

                # Skip ads that cannot satisfy the requested amount
                if min_amount > 0 and ad_max < min_amount:
                    continue

                # Parse 30-day order count safely to an integer
                raw_orders = ad.get("recentOrderNum", 0)
                try:
                    recent_orders = int(raw_orders) if raw_orders is not None else 0
                except (ValueError, TypeError):
                    recent_orders = 0

                # Parse completion rate safely to a float
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
                    "recentOrderNum": recent_orders,        # 30-Day Total Orders
                    "recentExecuteRate": recent_rate,      # 30-Day Completion Rate (%)
                    "paymentPeriod": ad.get("paymentPeriod")
                })

            except Exception:
                continue

        if not competitors:
            return jsonify({
                "status": False,
                "error": "No valid competitor ads found"
            }), 404

        # Compute Best Competitor Price based on side (for pricing margin calculation)
        if side == "0":  # BUY -> Highest market price
            top_competitor_price = max(c["price"] for c in competitors)
            recommended_price = top_competitor_price * (1 + (margin_pct / 100))
        else:            # SELL -> Lowest market price
            top_competitor_price = min(c["price"] for c in competitors)
            recommended_price = top_competitor_price * (1 - (margin_pct / 100))

        # Apply requested sorting filter
        if sort_by == "orders":
            # Highest 30-day total orders first
            competitors = sorted(
                competitors,
                key=lambda x: x["recentOrderNum"],
                reverse=True
            )
        elif sort_by == "completion_rate":
            # Highest 30-day completion rate first
            competitors = sorted(
                competitors,
                key=lambda x: x["recentExecuteRate"],
                reverse=True
            )
        else:
            # Price sorting (BUY: Highest to Lowest | SELL: Lowest to Highest)
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
            side=data.get("side", "0")  # 0 = Buy, 1 = Sell
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


#AutoPaymentBot

def check_new_orders():
    api = get_api()
    orders = api.get_pending_orders(
        page=1,
        size=20
    )
    if orders["ret_code"] != 0:
        return
    for order in orders["result"]["items"]:
        process_order(order)

def process_order(order):
    if order_exists(order["id"]):
        return
    validate_trading_rules(order)
    verify_seller(order)
    verify_bank(order)

    payment = send_bank_transfer(order)
    if payment["status"] != "SUCCESS":
        notify_failure(order)
        return

    save_transaction(order, payment)
    mark_order_paid(order)
    send_auto_message(order)
    notify_success(order)

def validate_trading_rules(order):
    rules = get_rules()
    amount = float(order["amount"])

    if amount < rules.minimum_amount:
        raise Exception("Below minimum amount")

    if amount > rules.maximum_amount:
        raise Exception("Above maximum amount")

def verify_bank(order):

    blacklist = get_blacklisted_banks()
    if order["bank_name"] in blacklist:
        raise Exception("Bank is blacklisted")

def verify_seller(order):

    profile = bybit.get_user_profile(order["sellerUid"])

    if profile["averageReleaseTime"] > settings.max_release_time:

        raise Exception(
            "Seller release time too high."
        )

def verify_account(bank_code, account_number):
    response = requests.post(
        KUDA_VERIFY_ENDPOINT,
        json={
            "bankCode": bank_code,
            "accountNumber": account_number
        }
    )
    return response.json()

def send_bank_transfer(order):

    payload = {
        "beneficiaryName": order["account_name"],
        "accountNumber": order["account_number"],
        "bankCode": order["bank_code"],
        "amount": order["amount"],
        "narration": f"Order D009458458457"
    }

    response = requests.post(
        KUDA_TRANSFER_ENDPOINT,
        json=payload,
        headers={
            "Authorization": f"Bearer {KUDA_TOKEN}"
        }
    )


    return response.json()

def wait_for_transfer(reference):

    for _ in range(30):
        status = check_transfer(reference)
        if status == "SUCCESS":
            return True
        if status == "FAILED":
            return False
        time.sleep(2)
    return False

def mark_order_paid(order):
    api = get_api()
    api.mark_order_as_paid(
        order["id"]
    )

def notify_success(order):

    print(
        f"[SUCCESS] {order['id']} paid successfully."
    )



# =========================
# 🚀 RUN APP
# =========================
def run_flask():
    app.run(host="127.0.0.1", port=8080, debug=False)


if __name__ == "__main__":
    # start server
    threading.Thread(target=run_flask).start()