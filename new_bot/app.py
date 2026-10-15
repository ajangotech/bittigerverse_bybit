import os
import threading
from datetime import datetime

from flask import Flask, request, jsonify
from dotenv import load_dotenv

load_dotenv()

from src import db
from src import bybit as bybit_api

app = Flask(__name__)

PORT = int(os.getenv("PORT", "3000"))
TOKEN = os.getenv("TELEGRAM_BOT_TOKEN")


# ---------------------------------------------------------------------------
# Health check
# ---------------------------------------------------------------------------

@app.route("/", methods=["GET"])
@app.route("/health", methods=["GET"])
def health():
    try:
        sessions = db.get_active_sessions()
        return jsonify({
            "status": "ok",
            "bot": "Bybit P2P Competitor Tracker",
            "botConnected": bool(TOKEN),
            "activeSessions": len(sessions),
            "uptime": (datetime.utcnow() - START_TIME).total_seconds(),
            "timestamp": datetime.utcnow().isoformat() + "Z",
        })
    except Exception as e:
        return jsonify({
            "status": "ok",
            "bot": "Bybit P2P Competitor Tracker",
            "botConnected": bool(TOKEN),
            "error": str(e),
            "timestamp": datetime.utcnow().isoformat() + "Z",
        })


# ---------------------------------------------------------------------------
# API proxy endpoints (merged from the Python API app)
# ---------------------------------------------------------------------------

@app.route("/ads", methods=["POST"])
def proxy_ads():
    body = request.get_json(silent=True) or {}
    api_key = body.get("api_key")
    api_secret = body.get("api_secret")
    api_url = body.get("api_url") or os.getenv("BYBIT_API_URL", "https://api2.bybit.com")

    if not api_key or not api_secret:
        return jsonify({"error": "api_key and api_secret are required"}), 400

    try:
        ads = bybit_api.fetch_ads(api_url, api_key, api_secret)
        return jsonify({"result": {"items": ads}})
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route("/analyze-market", methods=["POST"])
def proxy_analyze():
    body = request.get_json(silent=True) or {}
    api_key = body.get("api_key")
    api_secret = body.get("api_secret")
    token_id = body.get("tokenId")
    currency_id = body.get("currencyId")
    api_url = body.get("api_url") or os.getenv("BYBIT_API_URL", "https://api2.bybit.com")

    if not api_key or not api_secret:
        return jsonify({"error": "api_key and api_secret are required"}), 400

    try:
        result = bybit_api.fetch_competitors(api_url, api_key, api_secret, token_id, currency_id)
        return jsonify({
            "status": result["status"],
            "top_10_competitors": result["competitors"],
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route("/update-ad", methods=["POST"])
def proxy_update():
    body = request.get_json(silent=True) or {}
    api_key = body.get("api_key")
    api_secret = body.get("api_secret")
    api_url = body.get("api_url") or os.getenv("BYBIT_API_URL", "https://api2.bybit.com")

    if not api_key or not api_secret:
        return jsonify({"error": "api_key and api_secret are required"}), 400

    ad = {k: v for k, v in body.items() if k not in ("api_key", "api_secret", "api_url")}
    new_price = ad.get("price")

    try:
        result = bybit_api.update_ad_price(api_url, api_key, api_secret, ad, new_price)
        if result["success"]:
            return jsonify({"success": True, "price": result["price"]})
        return jsonify({"error": result["error"]}), 500
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route("/api/ad-price-limit", methods=["POST"])
def proxy_topper():
    try:
        price = bybit_api.fetch_topper_price()
        if price is not None:
            return jsonify({"price": str(price)})
        return jsonify({"error": "Failed to fetch topper price"}), 500
    except Exception as e:
        return jsonify({"error": str(e)}), 500


START_TIME = datetime.utcnow()


# ---------------------------------------------------------------------------
# Start the Telegram bot in a background thread
# ---------------------------------------------------------------------------

def start_bot_thread():
    from src.bot import setup_bot, start_tracking_loop
    from src.app_state import set_bot

    if not TOKEN:
        print("WARNING: TELEGRAM_BOT_TOKEN is not set. Server is running but bot is not started.")
        print("Add your token to .env and restart to enable the bot.")
        return

    import asyncio
    from telegram.ext import Application

    app_bot = setup_bot()
    if app_bot is None:
        return

    set_bot(app_bot.bot)

    # Start tracking loop
    start_tracking_loop(app_bot.bot)

    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)

    # run_polling blocks, so we run it in this thread
    app_bot.run_polling(allowed_updates=None)


def main():
    bot_thread = threading.Thread(target=start_bot_thread, daemon=True)
    bot_thread.start()

    print(f"Server starting on port {PORT}")
    app.run(host="0.0.0.0", port=PORT, debug=False, use_reloader=False)


if __name__ == "__main__":
    main()
