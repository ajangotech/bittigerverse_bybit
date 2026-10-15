import json
import threading
from datetime import datetime

from telegram import Update, ReplyKeyboardMarkup, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import (
    Application,
    CommandHandler,
    MessageHandler,
    CallbackQueryHandler,
    ContextTypes,
    filters,
)

from src import db
from src import bybit as bybit_api
from src.tracker import start_tracking_loop
from src.app_state import get_bot, set_bot

import os

TOKEN = os.getenv("TELEGRAM_BOT_TOKEN")

TIMER_OPTIONS = [
    {"label": "1.0 min", "ms": 60000},
    {"label": "1.5 min", "ms": 90000},
    {"label": "2.0 min", "ms": 120000},
    {"label": "2.5 min", "ms": 150000},
    {"label": "3.0 min", "ms": 180000},
    {"label": "3.5 min", "ms": 210000},
    {"label": "4.0 min", "ms": 240000},
    {"label": "4.5 min", "ms": 270000},
    {"label": "5.0 min", "ms": 300000},
    {"label": "5.5 min", "ms": 330000},
    {"label": "6.0 min", "ms": 360000},
    {"label": "6.5 min", "ms": 390000},
    {"label": "7.0 min", "ms": 420000},
]

MAIN_MENU = ReplyKeyboardMarkup(
    [
        ["📋 My Ads", "🎯 Track"],
        ["🤖 Copier", "📊 Status"],
        ["🛑 Stop", "🚀 Plus Mode"],
        ["⏱️ Timer", "🔑 Set Keys"],
        ["🌐 Set URL", "❓ Help"],
    ],
    resize_keyboard=True,
    one_time_keyboard=False,
)

# In-memory state for multi-step flows, keyed by telegram user id
_user_state: dict = {}

HELP_TEXT = (
    "*Commands:*\n\n"
    "/start — Register & welcome\n"
    "/menu — Show the command menu\n"
    "/setkeys — Set Bybit API key and secret\n"
    "/seturl — Set custom API proxy URL\n"
    "/myads — List your ads + TOPPER-BTC\n"
    "/track — Start tracking a competitor\n"
    "/copier — Auto-copy the #1 market merchant (upward only)\n"
    "/stop — Stop active tracking\n"
    "/status — Show current tracking status\n"
    "/plus — Toggle Plus mode for a session\n"
    "/timer — Set re-edit timer for a session\n"
    "/cancel — Cancel current operation\n"
    "/help — Show this help"
)


# ---------------------------------------------------------------------------
# /start
# ---------------------------------------------------------------------------

async def cmd_start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    db.ensure_user(update.effective_user.id)
    await update.message.reply_text(
        "👋 Welcome to the *Bybit P2P Competitor Tracker Bot*!\n\n"
        "I can help you track competitor prices and auto-update your Bybit P2P ads.\n\n"
        "Use the menu below to navigate, or type commands directly.\n\n"
        "*Quick Start:*\n"
        '1️⃣ Tap "Set Keys" to set your Bybit API key and secret\n'
        '2️⃣ Tap "My Ads" to see your advertisements\n'
        '3️⃣ Tap "Track" to start tracking a competitor',
        parse_mode="Markdown",
        reply_markup=MAIN_MENU,
    )


async def cmd_menu(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        "📋 *Main Menu* — tap a button below:",
        parse_mode="Markdown",
        reply_markup=MAIN_MENU,
    )


async def cmd_help(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        HELP_TEXT, parse_mode="Markdown", reply_markup=MAIN_MENU
    )


async def cmd_setkeys(update: Update, context: ContextTypes.DEFAULT_TYPE):
    _user_state[update.effective_user.id] = {"step": "awaiting_api_key"}
    await update.message.reply_text(
        "Please send your *Bybit API Key*.\n\n⚠️ Your next message will be saved as your API key. Send /cancel to abort.",
        parse_mode="Markdown",
    )


async def cmd_seturl(update: Update, context: ContextTypes.DEFAULT_TYPE):
    _user_state[update.effective_user.id] = {"step": "awaiting_api_url"}
    await update.message.reply_text(
        "Please send your *API proxy URL* (e.g. https://api2.bybit.com).\n\nSend /cancel to abort.",
        parse_mode="Markdown",
    )


async def cmd_cancel(update: Update, context: ContextTypes.DEFAULT_TYPE):
    _user_state.pop(update.effective_user.id, None)
    await update.message.reply_text("❌ Operation cancelled.", reply_markup=MAIN_MENU)


async def cmd_stop(update: Update, context: ContextTypes.DEFAULT_TYPE):
    sessions = db.get_user_sessions(update.effective_user.id, limit=100, active_only=True)
    if not sessions:
        await update.message.reply_text("ℹ️ No active tracking sessions.", reply_markup=MAIN_MENU)
        return
    for s in sessions:
        db.update_session(s["id"], {"tracking": False})
    await update.message.reply_text(
        f"🛑 Stopped {len(sessions)} tracking session(s).", reply_markup=MAIN_MENU
    )


async def cmd_status(update: Update, context: ContextTypes.DEFAULT_TYPE):
    sessions = db.get_user_sessions(update.effective_user.id, limit=5)
    if not sessions:
        await update.message.reply_text(
            'ℹ️ No tracking sessions found. Tap "Track" to start.',
            reply_markup=MAIN_MENU,
        )
        return

    text = "*📊 Tracking Status:*\n\n"
    for s in sessions:
        tracking = s.get("tracking")
        plus_mode = s.get("plus_mode")
        copier_mode = s.get("copier_mode")
        status = "🟢 Active" if tracking else "🔴 Stopped"
        mode = " 🤖 Copier" if copier_mode else (" (Plus)" if plus_mode else "")
        text += f"{status}{mode}\n"
        text += f"Ad: {s.get('token_id', '')}/{s.get('currency_id', '')}\n"
        text += f"Ad Price: {s.get('current_ad_price') or '---'}\n"
        text += f"Merchant: {s.get('selected_merchant_name') or '---'}\n"
        text += f"Merchant Price: {s.get('last_merchant_price') or '---'}\n"
        if plus_mode and tracking:
            last_update = 0
            lt = s.get("last_update_time")
            if lt:
                if isinstance(lt, str):
                    last_update = datetime.fromisoformat(lt.replace("Z", "+00:00")).timestamp() * 1000
                elif isinstance(lt, datetime):
                    last_update = lt.timestamp() * 1000
            elapsed = datetime.utcnow().timestamp() * 1000 - last_update
            remaining = max(0, s.get("plus_timer_ms", 180000) - elapsed)
            mins = int(remaining // 60000)
            secs = int((remaining % 60000) // 1000)
            text += f"Timer: {mins}m {secs}s remaining\n"
        text += "\n"

    await update.message.reply_text(text, parse_mode="Markdown", reply_markup=MAIN_MENU)


# ---------------------------------------------------------------------------
# /myads
# ---------------------------------------------------------------------------

async def handle_myads(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    user_id = update.effective_user.id

    user = db.ensure_user(user_id)
    if not user.get("bybit_api_key") or not user.get("bybit_api_secret"):
        await context.bot.send_message(chat_id, "❌ Please set your API keys first with /setkeys")
        return

    bot_msg = await context.bot.send_message(chat_id, "⏳ Loading your ads...")
    try:
        ads = bybit_api.fetch_ads(user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"])
    except Exception as e:
        await context.bot.send_message(chat_id, f"❌ Failed to load ads: {e}")
        return

    if not ads:
        await context.bot.edit_message_text("No advertisements found.", chat_id=chat_id, message_id=bot_msg.message_id)
        return

    unique_pairs = list({f"{a.get('tokenId')}/{a.get('currencyId')}" for a in ads})
    topper_price = bybit_api.fetch_topper_price()
    topper_prices = {}
    if topper_price is not None:
        for pair in unique_pairs:
            topper_prices[pair] = topper_price

    keyboard = []
    for pair in unique_pairs:
        if pair in topper_prices:
            keyboard.append([InlineKeyboardButton(
                f"⭐ TOPPER-BTC | {pair} | {topper_prices[pair]}",
                callback_data=f"topperinfo:{pair}",
            )])

    for ad in ads[:20]:
        keyboard.append([InlineKeyboardButton(
            f"{ad.get('tokenId')}/{ad.get('currencyId')} | {ad.get('price')}",
            callback_data=f"adinfo:{ad.get('id')}",
        )])

    await context.bot.edit_message_text(
        "📋 *Your Advertisements + TOPPER-BTC* (tap for details):",
        chat_id=chat_id,
        message_id=bot_msg.message_id,
        parse_mode="Markdown",
        reply_markup=InlineKeyboardMarkup(keyboard),
    )

    state = _user_state.setdefault(user_id, {})
    state["ads_cache"] = ads
    state["topper_prices"] = topper_prices


# ---------------------------------------------------------------------------
# /track
# ---------------------------------------------------------------------------

async def handle_track(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    user_id = update.effective_user.id

    user = db.ensure_user(user_id)
    if not user.get("bybit_api_key") or not user.get("bybit_api_secret"):
        await context.bot.send_message(chat_id, "❌ Please set your API keys first with /setkeys")
        return

    bot_msg = await context.bot.send_message(chat_id, "⏳ Loading your ads for tracking...")
    try:
        ads = bybit_api.fetch_ads(user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"])
    except Exception as e:
        await context.bot.send_message(chat_id, f"❌ Failed to load ads: {e}")
        return

    if not ads:
        await context.bot.edit_message_text("No advertisements found.", chat_id=chat_id, message_id=bot_msg.message_id)
        return

    keyboard = [
        [InlineKeyboardButton(
            f"{ad.get('tokenId')}/{ad.get('currencyId')} | {ad.get('price')}",
            callback_data=f"trackad:{ad.get('id')}",
        )]
        for ad in ads[:20]
    ]

    await context.bot.edit_message_text(
        "📋 *Select an ad to track:*",
        chat_id=chat_id,
        message_id=bot_msg.message_id,
        parse_mode="Markdown",
        reply_markup=InlineKeyboardMarkup(keyboard),
    )

    state = _user_state.setdefault(user_id, {})
    state["ads_cache"] = ads


# ---------------------------------------------------------------------------
# /copier
# ---------------------------------------------------------------------------

async def handle_copier(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    user_id = update.effective_user.id

    user = db.ensure_user(user_id)
    if not user.get("bybit_api_key") or not user.get("bybit_api_secret"):
        await context.bot.send_message(chat_id, "❌ Please set your API keys first with /setkeys")
        return

    bot_msg = await context.bot.send_message(chat_id, "⏳ Loading your ads for Copier mode...")
    try:
        ads = bybit_api.fetch_ads(user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"])
    except Exception as e:
        await context.bot.send_message(chat_id, f"❌ Failed to load ads: {e}")
        return

    if not ads:
        await context.bot.edit_message_text("No advertisements found.", chat_id=chat_id, message_id=bot_msg.message_id)
        return

    keyboard = [
        [InlineKeyboardButton(
            f"{ad.get('tokenId')}/{ad.get('currencyId')} | {ad.get('price')}",
            callback_data=f"copierad:{ad.get('id')}",
        )]
        for ad in ads[:20]
    ]

    await context.bot.edit_message_text(
        "🤖 *Copier Mode*\n\nSelect an ad. The bot will auto-track the #1 market merchant and only update your price when the market goes UP (never down).",
        chat_id=chat_id,
        message_id=bot_msg.message_id,
        parse_mode="Markdown",
        reply_markup=InlineKeyboardMarkup(keyboard),
    )

    state = _user_state.setdefault(user_id, {})
    state["ads_cache"] = ads


# ---------------------------------------------------------------------------
# /plus
# ---------------------------------------------------------------------------

async def handle_plus(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    user_id = update.effective_user.id

    sessions = db.get_user_sessions(user_id, limit=10)
    active = [s for s in sessions if s.get("tracking")]

    if not active:
        await update.message.reply_text(
            "ℹ️ No active tracking sessions. Start tracking first with /track.",
            reply_markup=MAIN_MENU,
        )
        return

    if len(active) == 1:
        session = active[0]
        new_plus = not session.get("plus_mode", False)
        db.update_session(session["id"], {
            "plus_mode": new_plus,
            "last_update_time": datetime.utcnow().isoformat() + "Z",
        })
        if new_plus:
            msg = (
                f"🚀 *Plus mode enabled* for {session['token_id']}/{session['currency_id']}\n"
                f"Ad will re-edit on timer ({session.get('plus_timer_ms', 180000) / 60000} min)."
            )
        else:
            msg = (
                f"ℹ️ *Plus mode disabled* for {session['token_id']}/{session['currency_id']}. "
                "Using standard ratchet tracking."
            )
        await update.message.reply_text(msg, parse_mode="Markdown", reply_markup=MAIN_MENU)
        return

    keyboard = [
        [InlineKeyboardButton(
            f"{'🚀' if s.get('plus_mode') else '⚪'} {s['token_id']}/{s['currency_id']} | {s.get('selected_merchant_name', '')}",
            callback_data=f"plussession:{s['id']}",
        )]
        for s in active
    ]
    await update.message.reply_text(
        "🚀 *Select a tracking session to toggle Plus mode:*",
        parse_mode="Markdown",
        reply_markup=InlineKeyboardMarkup(keyboard),
    )


# ---------------------------------------------------------------------------
# /timer
# ---------------------------------------------------------------------------

async def handle_timer(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.effective_chat.id
    user_id = update.effective_user.id

    sessions = db.get_user_sessions(user_id, limit=10, active_only=True)
    if not sessions:
        await update.message.reply_text(
            "ℹ️ No active tracking sessions. Start tracking first with /track.",
            reply_markup=MAIN_MENU,
        )
        return

    if len(sessions) == 1:
        session = sessions[0]
        keyboard = [
            [InlineKeyboardButton(t["label"], callback_data=f"settimer:{session['id']}:{t['ms']}")]
            for t in TIMER_OPTIONS
        ]
        await update.message.reply_text(
            f"⏱️ *Select re-edit timer* for {session['token_id']}/{session['currency_id']}:",
            parse_mode="Markdown",
            reply_markup=InlineKeyboardMarkup(keyboard),
        )
        return

    keyboard = [
        [InlineKeyboardButton(
            f"⏱️ {s['token_id']}/{s['currency_id']} | {s.get('selected_merchant_name', '')} (current: {s.get('plus_timer_ms', 180000) / 60000} min)",
            callback_data=f"picksession_timer:{s['id']}",
        )]
        for s in sessions
    ]
    await update.message.reply_text(
        "⏱️ *Select a tracking session to set its timer:*",
        parse_mode="Markdown",
        reply_markup=InlineKeyboardMarkup(keyboard),
    )


# ---------------------------------------------------------------------------
# Plain-text message handler (menu buttons + multi-step flows)
# ---------------------------------------------------------------------------

MENU_ROUTES = {
    "📋 My Ads": "myads",
    "🎯 Track": "track",
    "🤖 Copier": "copier",
    "📊 Status": "status",
    "🛑 Stop": "stop",
    "🚀 Plus Mode": "plus",
    "⏱️ Timer": "timer",
    "🔑 Set Keys": "setkeys",
    "🌐 Set URL": "seturl",
    "❓ Help": "help",
}

MENU_HANDLERS = {
    "myads": handle_myads,
    "track": handle_track,
    "copier": handle_copier,
    "plus": handle_plus,
    "timer": handle_timer,
}


async def on_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not update.message or not update.message.text:
        return

    chat_id = update.effective_chat.id
    user_id = update.effective_user.id
    text = update.message.text.strip()

    route = MENU_ROUTES.get(text)
    if route:
        if route in MENU_HANDLERS:
            await MENU_HANDLERS[route](update, context)
            return
        if route == "stop":
            await cmd_stop(update, context)
            return
        if route == "status":
            await cmd_status(update, context)
            return
        if route == "setkeys":
            _user_state[user_id] = {"step": "awaiting_api_key"}
            await context.bot.send_message(
                chat_id,
                "Please send your *Bybit API Key*.\n\nSend /cancel to abort.",
                parse_mode="Markdown",
            )
            return
        if route == "seturl":
            _user_state[user_id] = {"step": "awaiting_api_url"}
            await context.bot.send_message(
                chat_id,
                "Please send your *API proxy URL*.\n\nSend /cancel to abort.",
                parse_mode="Markdown",
            )
            return
        if route == "help":
            await context.bot.send_message(
                chat_id, HELP_TEXT, parse_mode="Markdown", reply_markup=MAIN_MENU
            )
            return
        return

    if text.startswith("/"):
        return

    state = _user_state.get(user_id)
    if not state:
        return

    try:
        if state.get("step") == "awaiting_api_key":
            state["api_key"] = text
            state["step"] = "awaiting_api_secret"
            await context.bot.send_message(
                chat_id, "✅ API key saved. Now send your *Bybit API Secret*.",
                parse_mode="Markdown",
            )
            return

        if state.get("step") == "awaiting_api_secret":
            api_secret = text
            db.update_user_keys(user_id, state["api_key"], api_secret)
            _user_state.pop(user_id, None)
            await context.bot.send_message(
                chat_id,
                '✅ Bybit API credentials saved successfully! Tap "My Ads" to see your ads.',
                reply_markup=MAIN_MENU,
            )
            return

        if state.get("step") == "awaiting_api_url":
            url = text.rstrip("/")
            db.update_user_url(user_id, url)
            _user_state.pop(user_id, None)
            await context.bot.send_message(
                chat_id, f"✅ API URL set to: {url}", reply_markup=MAIN_MENU
            )
            return
    except Exception as e:
        await context.bot.send_message(
            chat_id, f"❌ Error: {e}", reply_markup=MAIN_MENU
        )
        _user_state.pop(user_id, None)


# ---------------------------------------------------------------------------
# Callback query handler
# ---------------------------------------------------------------------------

async def on_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    chat_id = query.message.chat_id
    user_id = query.from_user.id
    data = query.data

    async def answer(text=None):
        try:
            await query.answer(text)
        except Exception:
            pass

    try:
        if data == "backtomenu":
            await answer()
            return

        # --- TOPPER-BTC info ---
        if data.startswith("topperinfo:"):
            pair = data.split(":", 1)[1]
            state = _user_state.get(user_id, {})
            topper_price = state.get("topper_prices", {}).get(pair)
            await answer()
            await context.bot.send_message(
                chat_id,
                f"⭐ *TOPPER-BTC*\n\nPair: {pair}\nCurrent Limit Price: {topper_price or '---'}\n\n"
                "Use /track and select TOPPER-BTC to track this limit.",
                parse_mode="Markdown",
            )
            return

        # --- Ad info ---
        if data.startswith("adinfo:"):
            ad_id = data.split(":", 1)[1]
            state = _user_state.get(user_id, {})
            ad = next((a for a in state.get("ads_cache", []) if str(a.get("id")) == str(ad_id)), None)
            if not ad:
                await answer("Ad data expired. Use /myads again.")
                return
            pair = f"{ad.get('tokenId')}/{ad.get('currencyId')}"
            topper_price = state.get("topper_prices", {}).get(pair)
            text = (
                f"📋 *Ad Details*\n\n"
                f"Pair: {pair}\n"
                f"Price: {ad.get('price')}\n"
                f"Min: {ad.get('minAmount')}\n"
                f"Max: {ad.get('maxAmount')}\n"
                f"Status: {ad.get('status') or '---'}\n"
            )
            if topper_price is not None:
                text += f"⭐ TOPPER-BTC Limit: {topper_price}\n"
            text += "\nUse /track to start tracking this ad."
            await answer()
            await context.bot.send_message(chat_id, text, parse_mode="Markdown")
            return

        # --- Track ad: show merchants ---
        if data.startswith("trackad:"):
            ad_id = data.split(":", 1)[1]
            state = _user_state.get(user_id, {})
            ad = next((a for a in state.get("ads_cache", []) if str(a.get("id")) == str(ad_id)), None)
            if not ad:
                await answer("Ad data expired. Try again.")
                return

            await answer("Loading competitors...")
            user = db.ensure_user(user_id)
            bot_msg = await context.bot.send_message(chat_id, "⏳ Fetching competitors...")

            all_competitors = []
            try:
                result = bybit_api.fetch_competitors(
                    user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"],
                    ad.get("tokenId"), ad.get("currencyId"),
                )
                all_competitors = list(result["competitors"])
            except Exception as e:
                print(f"fetchCompetitors error: {e}")

            topper_price = bybit_api.fetch_topper_price()
            if topper_price is not None:
                all_competitors.insert(0, {
                    "id": "TOPPER-BTC", "nickName": "TOPPER-BTC", "price": topper_price,
                })

            if not all_competitors:
                await context.bot.edit_message_text(
                    "No competitors found for this pair. The API may be temporarily unavailable — try again in a moment.",
                    chat_id=chat_id, message_id=bot_msg.message_id,
                )
                return

            # BTC/NGN: auto-select top market competitor
            if ad.get("tokenId") == "BTC" and ad.get("currencyId") == "NGN":
                top_merchant = next((m for m in all_competitors if m.get("id") != "TOPPER-BTC"), all_competitors[0])
                reference_price = float(top_merchant["price"])

                db.delete_active_sessions(user_id, ad.get("id"))

                sid = db.insert_session({
                    "telegram_id": user_id,
                    "ad_id": str(ad.get("id")),
                    "token_id": ad.get("tokenId"),
                    "currency_id": ad.get("currencyId"),
                    "current_ad_price": ad.get("price"),
                    "min_amount": ad.get("minAmount"),
                    "max_amount": ad.get("maxAmount"),
                    "ad_status": ad.get("status"),
                    "price_type": ad.get("priceType", 0),
                    "premium": str(ad.get("premium", "0")),
                    "last_quantity": str(ad.get("lastQuantity", "1")),
                    "payment_period": ad.get("paymentPeriod", 30),
                    "payment_terms": ad.get("paymentTerms"),
                    "trading_preference_set": ad.get("tradingPreferenceSet"),
                    "selected_merchant_id": str(top_merchant["id"]),
                    "selected_merchant_name": top_merchant["nickName"],
                    "reference_price": reference_price,
                    "last_merchant_price": reference_price,
                    "tracking": True,
                    "copier_mode": True,
                    "plus_mode": False,
                    "plus_timer_ms": 180000,
                    "last_update_time": datetime.utcnow().isoformat() + "Z",
                    "ad_payload_cache": ad,
                })

                session = db.get_session(sid)
                result = bybit_api.update_ad_price(
                    user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"],
                    ad, reference_price,
                )
                if result["success"]:
                    db.update_session(session["id"], {
                        "current_ad_price": reference_price,
                        "last_update_time": datetime.utcnow().isoformat() + "Z",
                    })

                await context.bot.edit_message_text(
                    f"🟢 *Tracking started!*\n\n"
                    f"Ad: {ad.get('tokenId')}/{ad.get('currencyId')}\n"
                    f"Auto-selected #1 merchant: {top_merchant['nickName']}\n"
                    f"Price: {reference_price}\n"
                    f"Mode: Copier (follows #1 market merchant, updates only when UP)\n\n"
                    f"Use /stop to stop. Use /status to check.",
                    chat_id=chat_id,
                    message_id=bot_msg.message_id,
                    parse_mode="Markdown",
                    reply_markup=InlineKeyboardMarkup([[InlineKeyboardButton("📋 Back to Menu", callback_data="backtomenu")]]),
                )
                await context.bot.send_message(chat_id, "Main menu:", reply_markup=MAIN_MENU)
                _user_state.pop(user_id, None)
                return

            # Other pairs: show merchant selection
            state["selected_ad"] = ad
            state["competitors_cache"] = all_competitors

            keyboard = []
            for i, m in enumerate(all_competitors):
                prefix = "⭐" if m.get("id") == "TOPPER-BTC" else f"#{i}"
                keyboard.append([InlineKeyboardButton(
                    f"{prefix} | {m.get('nickName')} | {m.get('price')}",
                    callback_data=f"trackmerchant:{i}",
                )])

            await context.bot.edit_message_text(
                "👤 *Select a merchant to track:*",
                chat_id=chat_id, message_id=bot_msg.message_id,
                parse_mode="Markdown",
                reply_markup=InlineKeyboardMarkup(keyboard),
            )
            return

        # --- Track merchant: start tracking ---
        if data.startswith("trackmerchant:"):
            idx = int(data.split(":", 1)[1])
            state = _user_state.get(user_id, {})
            competitors = state.get("competitors_cache", [])
            if idx >= len(competitors):
                await answer("Merchant data expired.")
                return
            merchant = competitors[idx]
            await answer()

            ad = state.get("selected_ad")
            user = db.ensure_user(user_id)
            reference_price = float(merchant["price"])

            db.delete_active_sessions(user_id, ad.get("id"))

            sid = db.insert_session({
                "telegram_id": user_id,
                "ad_id": str(ad.get("id")),
                "token_id": ad.get("tokenId"),
                "currency_id": ad.get("currencyId"),
                "current_ad_price": ad.get("price"),
                "min_amount": ad.get("minAmount"),
                "max_amount": ad.get("maxAmount"),
                "ad_status": ad.get("status"),
                "price_type": ad.get("priceType", 0),
                "premium": str(ad.get("premium", "0")),
                "last_quantity": str(ad.get("lastQuantity", "1")),
                "payment_period": ad.get("paymentPeriod", 30),
                "payment_terms": ad.get("paymentTerms"),
                "trading_preference_set": ad.get("tradingPreferenceSet"),
                "selected_merchant_id": str(merchant["id"]),
                "selected_merchant_name": merchant["nickName"],
                "reference_price": reference_price,
                "last_merchant_price": reference_price,
                "tracking": True,
                "copier_mode": False,
                "plus_mode": False,
                "plus_timer_ms": 180000,
                "last_update_time": datetime.utcnow().isoformat() + "Z",
                "ad_payload_cache": ad,
            })

            session = db.get_session(sid)
            result = bybit_api.update_ad_price(
                user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"],
                ad, reference_price,
            )
            if result["success"]:
                db.update_session(session["id"], {
                    "current_ad_price": reference_price,
                    "last_update_time": datetime.utcnow().isoformat() + "Z",
                })

            await context.bot.send_message(
                chat_id,
                f"🟢 *Tracking started!*\n\n"
                f"Ad: {ad.get('tokenId')}/{ad.get('currencyId')}\n"
                f"Merchant: {merchant['nickName']}\n"
                f"Price: {reference_price}\n"
                f"Mode: Ratchet (default)\n"
                f"Timer: 3.0 min\n\n"
                f"Use /plus to enable Plus mode for this ad.\n"
                f"Use /timer to change the re-edit timer for this ad.\n"
                f"Use /stop to stop tracking. Use /status to check.",
                parse_mode="Markdown",
                reply_markup=MAIN_MENU,
            )
            _user_state.pop(user_id, None)
            return

        # --- Copier mode ---
        if data.startswith("copierad:"):
            ad_id = data.split(":", 1)[1]
            state = _user_state.get(user_id, {})
            ad = next((a for a in state.get("ads_cache", []) if str(a.get("id")) == str(ad_id)), None)
            if not ad:
                await answer("Ad data expired. Try again.")
                return

            await answer("Starting Copier...")
            user = db.ensure_user(user_id)

            top_merchant = None
            try:
                result = bybit_api.fetch_competitors(
                    user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"],
                    ad.get("tokenId"), ad.get("currencyId"),
                )
                if result["status"] and result["competitors"]:
                    top_merchant = result["competitors"][0]
            except Exception as e:
                print(f"Copier fetchCompetitors error: {e}")

            reference_price = float(top_merchant["price"]) if top_merchant else float(ad.get("price"))

            db.delete_active_sessions(user_id, ad.get("id"))

            sid = db.insert_session({
                "telegram_id": user_id,
                "ad_id": str(ad.get("id")),
                "token_id": ad.get("tokenId"),
                "currency_id": ad.get("currencyId"),
                "current_ad_price": ad.get("price"),
                "min_amount": ad.get("minAmount"),
                "max_amount": ad.get("maxAmount"),
                "ad_status": ad.get("status"),
                "price_type": ad.get("priceType", 0),
                "premium": str(ad.get("premium", "0")),
                "last_quantity": str(ad.get("lastQuantity", "1")),
                "payment_period": ad.get("paymentPeriod", 30),
                "payment_terms": ad.get("paymentTerms"),
                "trading_preference_set": ad.get("tradingPreferenceSet"),
                "selected_merchant_id": str(top_merchant["id"]) if top_merchant else "AUTO",
                "selected_merchant_name": top_merchant["nickName"] if top_merchant else "Auto-Detecting #1",
                "reference_price": reference_price,
                "last_merchant_price": reference_price,
                "tracking": True,
                "copier_mode": True,
                "plus_mode": False,
                "plus_timer_ms": 180000,
                "last_update_time": datetime.utcnow().isoformat() + "Z",
                "ad_payload_cache": ad,
            })

            session = db.get_session(sid)
            if top_merchant:
                result = bybit_api.update_ad_price(
                    user["bybit_api_url"], user["bybit_api_key"], user["bybit_api_secret"],
                    ad, reference_price,
                )
                if result["success"]:
                    db.update_session(session["id"], {
                        "current_ad_price": reference_price,
                        "last_update_time": datetime.utcnow().isoformat() + "Z",
                    })

            await context.bot.send_message(
                chat_id,
                f"🤖 *Copier Mode Active!*\n\n"
                f"Ad: {ad.get('tokenId')}/{ad.get('currencyId')}\n"
                f"Target: #1 Market Merchant (auto-detected)\n"
                f"Current #1: {top_merchant['nickName'] if top_merchant else 'Detecting...'}\n"
                f"Price: {reference_price}\n"
                f"Strategy: Only updates when market goes UP\n\n"
                f"Use /stop to stop. Use /status to check.",
                parse_mode="Markdown",
                reply_markup=MAIN_MENU,
            )
            _user_state.pop(user_id, None)
            return

        # --- Plus session toggle ---
        if data.startswith("plussession:"):
            session_id = data.split(":", 1)[1]
            await answer()
            session = db.get_session(session_id)
            if not session:
                await context.bot.send_message(chat_id, "❌ Session not found.", reply_markup=MAIN_MENU)
                return
            new_plus = not session.get("plus_mode", False)
            db.update_session(session["id"], {
                "plus_mode": new_plus,
                "last_update_time": datetime.utcnow().isoformat() + "Z",
            })
            if new_plus:
                msg = (
                    f"🚀 *Plus mode enabled* for {session['token_id']}/{session['currency_id']}\n"
                    f"Timer: {session.get('plus_timer_ms', 180000) / 60000} min\n"
                    "Use /timer to change it."
                )
            else:
                msg = (
                    f"ℹ️ *Plus mode disabled* for {session['token_id']}/{session['currency_id']}. "
                    "Standard ratchet tracking."
                )
            await context.bot.send_message(chat_id, msg, parse_mode="Markdown", reply_markup=MAIN_MENU)
            return

        # --- Timer: pick session ---
        if data.startswith("picksession_timer:"):
            session_id = data.split(":", 1)[1]
            await answer()
            session = db.get_session(session_id)
            if not session:
                await context.bot.send_message(chat_id, "❌ Session not found.", reply_markup=MAIN_MENU)
                return
            keyboard = [
                [InlineKeyboardButton(t["label"], callback_data=f"settimer:{session_id}:{t['ms']}")]
                for t in TIMER_OPTIONS
            ]
            await context.bot.send_message(
                chat_id,
                f"⏱️ *Select timer* for {session['token_id']}/{session['currency_id']}:",
                parse_mode="Markdown",
                reply_markup=InlineKeyboardMarkup(keyboard),
            )
            return

        # --- Set timer ---
        if data.startswith("settimer:"):
            parts = data.split(":")
            session_id = parts[1]
            ms = int(parts[2])
            await answer()
            session = db.get_session(session_id)
            if not session:
                await context.bot.send_message(chat_id, "❌ Session not found.", reply_markup=MAIN_MENU)
                return
            db.update_session(session["id"], {"plus_timer_ms": ms})
            await context.bot.send_message(
                chat_id,
                f"✅ Re-edit timer set to *{ms / 60000} minutes* for {session['token_id']}/{session['currency_id']}.",
                parse_mode="Markdown",
                reply_markup=MAIN_MENU,
            )
            return

    except Exception as e:
        await answer()
        print(f"Callback error: {e}")
        try:
            await context.bot.send_message(
                chat_id, f"❌ Error: {e}", reply_markup=MAIN_MENU
            )
        except Exception:
            pass


# ---------------------------------------------------------------------------
# Application setup
# ---------------------------------------------------------------------------

_application = None
_tracking_started = False


def setup_bot():
    global _application, _tracking_started
    if not TOKEN:
        print("WARNING: TELEGRAM_BOT_TOKEN is not set. Bot will not start.")
        return None

    app = Application.builder().token(TOKEN).build()

    app.add_handler(CommandHandler("start", cmd_start))
    app.add_handler(CommandHandler("menu", cmd_menu))
    app.add_handler(CommandHandler("help", cmd_help))
    app.add_handler(CommandHandler("setkeys", cmd_setkeys))
    app.add_handler(CommandHandler("seturl", cmd_seturl))
    app.add_handler(CommandHandler("cancel", cmd_cancel))
    app.add_handler(CommandHandler("stop", cmd_stop))
    app.add_handler(CommandHandler("status", cmd_status))
    app.add_handler(CommandHandler("myads", handle_myads))
    app.add_handler(CommandHandler("track", handle_track))
    app.add_handler(CommandHandler("copier", handle_copier))
    app.add_handler(CommandHandler("plus", handle_plus))
    app.add_handler(CommandHandler("timer", handle_timer))

    app.add_handler(CallbackQueryHandler(on_callback))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, on_message))

    _application = app
    set_bot(app.bot)
    return app


def start_bot():
    app = setup_bot()
    if app is None:
        return

    if not _tracking_started:
        start_tracking_loop(app.bot)
        _tracking_started = True

    app.run_polling(allowed_updates=Update.ALL_TYPES)
