import threading
import time
from datetime import datetime

from src import db
from src import bybit as bybit_api


def run_tracking_tick(session, bot, competitors_cache, cached_topper_price):
    user = db.get_user(session["telegram_id"])
    if not user or not user.get("bybit_api_key") or not user.get("bybit_api_secret"):
        return

    api_url = user.get("bybit_api_url") or "https://api2.bybit.com"
    api_key = user["bybit_api_key"]
    api_secret = user["bybit_api_secret"]

    fresh = db.get_session(session["id"])
    if not fresh or not fresh["tracking"]:
        return
    session = fresh

    topper_price = cached_topper_price

    status = False
    competitors = []
    try:
        result = bybit_api.fetch_competitors(
            api_url, api_key, api_secret,
            session["token_id"], session["currency_id"],
        )
        status = result["status"]
        competitors = result["competitors"]
    except Exception:
        competitors_cache.setdefault(session["id"], [])
        return

    if not status:
        competitors_cache[session["id"]] = []
        return

    all_competitors = list(competitors)
    if topper_price is not None:
        all_competitors.insert(0, {
            "id": "TOPPER-BTC",
            "nickName": "TOPPER-BTC",
            "price": topper_price,
        })

    competitors_cache[session["id"]] = all_competitors

    merchant = None
    if session.get("copier_mode"):
        real = [x for x in all_competitors if str(x.get("id")) != "TOPPER-BTC"]
        merchant = real[0] if real else None
        if not merchant:
            return
        if str(merchant["id"]) != str(session.get("selected_merchant_id", "")):
            db.update_session(session["id"], {
                "selected_merchant_id": str(merchant["id"]),
                "selected_merchant_name": merchant["nickName"],
            })
            session["selected_merchant_id"] = str(merchant["id"])
            session["selected_merchant_name"] = merchant["nickName"]
    else:
        merchant = next(
            (x for x in all_competitors if str(x.get("id")) == str(session.get("selected_merchant_id", ""))),
            None,
        )
        if not merchant:
            try:
                bot.send_message(
                    session["telegram_id"],
                    f"⚠️ Merchant *{session.get('selected_merchant_name', '')}* is no longer in the top 10. Tracking paused.",
                    parse_mode="Markdown",
                )
            except Exception:
                pass
            db.update_session(session["id"], {"tracking": False})
            return

    current_price = float(merchant["price"])
    last_merchant_price = float(session.get("last_merchant_price") or 0)
    plus_mode = session.get("plus_mode", False)
    plus_timer_ms = session.get("plus_timer_ms") or 180000

    last_update_time = 0
    if session.get("last_update_time"):
        lt = session["last_update_time"]
        if isinstance(lt, str):
            last_update_time = datetime.fromisoformat(lt.replace("Z", "+00:00")).timestamp() * 1000
        elif isinstance(lt, datetime):
            last_update_time = lt.timestamp() * 1000
    if not last_update_time:
        last_update_time = time.time() * 1000

    time_since = time.time() * 1000 - last_update_time

    should_update = False
    new_price = current_price
    action = ""
    status_text = ""

    if session.get("copier_mode"):
        if current_price > last_merchant_price:
            status_text = f"Copier: #1 {merchant['nickName']} price UP to {current_price}"
            should_update = True
            action = "copier_up"
        elif current_price == last_merchant_price:
            status_text = f"Copier: Synced with #1 ({last_merchant_price})"
        else:
            status_text = f"Copier: Market down ({current_price}) — holding at {last_merchant_price}"
    elif session.get("selected_merchant_id") == "TOPPER-BTC":
        if plus_mode:
            if current_price <= last_merchant_price and time_since < plus_timer_ms:
                status_text = f"At Limit ({last_merchant_price})"
            else:
                status_text = "Syncing Limit (Plus)"
                should_update = True
        else:
            if current_price <= last_merchant_price:
                status_text = f"At API Limit ({last_merchant_price})"
            else:
                status_text = "Syncing to Limit (Upwards)"
                should_update = True
    else:
        if plus_mode:
            if time_since >= plus_timer_ms:
                status_text = f"⏰ Timer expired! Re-editing to {session.get('selected_merchant_name', '')}'s price ({current_price})"
                should_update = True
                action = "timer_resync"
            elif current_price > last_merchant_price:
                status_text = "Tracking Upwards (Plus)"
                should_update = True
                action = "track_up"
            else:
                status_text = f"Maintaining ({last_merchant_price})"
        else:
            if current_price == last_merchant_price:
                status_text = f"Matching Market ({last_merchant_price})"
            elif current_price > last_merchant_price:
                status_text = "Tracking Upwards"
                should_update = True
                action = "track_up"
            else:
                status_text = f"Market down ({current_price}) — holding at {last_merchant_price}"

    if should_update:
        ad = session.get("ad_payload_cache")
        if not ad:
            return

        result = bybit_api.update_ad_price(api_url, api_key, api_secret, ad, new_price)
        if result["success"]:
            db.update_session(session["id"], {
                "current_ad_price": new_price,
                "last_merchant_price": current_price,
                "last_update_time": datetime.utcnow().isoformat() + "Z",
            })
            try:
                bot.send_message(
                    session["telegram_id"],
                    f"✅ Ad updated to *{new_price}*\n📊 {status_text}\n👤 Merchant: {session.get('selected_merchant_name', '')}",
                    parse_mode="Markdown",
                )
            except Exception:
                pass
            db.log_competitor(
                session["telegram_id"], session["id"],
                merchant.get("id") or session.get("selected_merchant_id", ""),
                merchant.get("nickName") or session.get("selected_merchant_name", ""),
                current_price, new_price, action or "syncing_limit",
            )
        else:
            try:
                bot.send_message(
                    session["telegram_id"],
                    f"❌ Failed to update ad: {result.get('error', 'unknown error')}",
                    parse_mode="Markdown",
                )
            except Exception:
                pass


def start_tracking_loop(bot):
    competitors_cache = {}
    cached_topper_price = [None]

    def topper_loop():
        while True:
            try:
                price = bybit_api.fetch_topper_price()
                if price is not None:
                    cached_topper_price[0] = price
            except Exception:
                pass
            time.sleep(1)

    def main_loop():
        while True:
            try:
                sessions = db.get_active_sessions()
                for session in sessions:
                    try:
                        run_tracking_tick(session, bot, competitors_cache, cached_topper_price[0])
                    except Exception as e:
                        print(f"Tracking tick error for session {session['id']}: {e}")
            except Exception as e:
                print(f"Tracking loop error: {e}")
            time.sleep(3)

    t1 = threading.Thread(target=topper_loop, daemon=True)
    t2 = threading.Thread(target=main_loop, daemon=True)
    t1.start()
    t2.start()
