from bybit_p2p import P2P
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
    def wrapper(*args, **kwargs):
        data = request.get_json()

        if not data:
            return jsonify({"error": "Request body is required"}), 400

        if not data.get("api_key") or not data.get("api_secret"):
            return jsonify({"error": "api_key and api_secret are required"}), 401

        return f(data, *args, **kwargs)

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

        print("PAYLOAD RECEIVED:", data)

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

        result = api.get_ads_list()

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
# 🚀 RUN APP
# =========================
def run_flask():
    app.run(host="127.0.0.1", port=8080, debug=False)


if __name__ == "__main__":
    # start server
    threading.Thread(target=run_flask).start()