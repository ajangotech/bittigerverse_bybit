import os
import json
import uuid as uuid_mod
import mysql.connector
from mysql.connector import pooling
from dotenv import load_dotenv

load_dotenv()

_pool = pooling.MySQLConnectionPool(
    host=os.getenv("MYSQL_HOST", "localhost"),
    port=int(os.getenv("MYSQL_PORT", "3306")),
    user=os.getenv("MYSQL_USER", "root"),
    password=os.getenv("MYSQL_PASSWORD", ""),
    database=os.getenv("MYSQL_DATABASE", "bybit_bot"),
    pool_size=10,
    charset="utf8mb4",
    autocommit=False,
)


def _get_conn():
    return _pool.get_connection()


def uuid():
    return str(uuid_mod.uuid4())


# ---------------------------------------------------------------------------
# bot_users
# ---------------------------------------------------------------------------

def get_user(telegram_id):
    conn = _get_conn()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM bot_users WHERE telegram_id = %s", (telegram_id,))
        row = cur.fetchone()
        return row
    finally:
        cur.close()
        conn.close()


def ensure_user(telegram_id):
    user = get_user(telegram_id)
    if not user:
        conn = _get_conn()
        try:
            cur = conn.cursor()
            cur.execute(
                "INSERT INTO bot_users (id, telegram_id) VALUES (%s, %s)",
                (uuid(), telegram_id),
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()
        user = get_user(telegram_id)
    return user


def update_user_keys(telegram_id, api_key, api_secret):
    conn = _get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "UPDATE bot_users SET bybit_api_key=%s, bybit_api_secret=%s, updated_at=NOW() WHERE telegram_id=%s",
            (api_key, api_secret, telegram_id),
        )
        conn.commit()
    finally:
        cur.close()
        conn.close()


def update_user_url(telegram_id, api_url):
    conn = _get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "UPDATE bot_users SET bybit_api_url=%s, updated_at=NOW() WHERE telegram_id=%s",
            (api_url, telegram_id),
        )
        conn.commit()
    finally:
        cur.close()
        conn.close()


# ---------------------------------------------------------------------------
# tracking_sessions
# ---------------------------------------------------------------------------

def _normalize_session(row):
    if not row:
        return row
    s = dict(row)
    for key in ("payment_terms", "trading_preference_set", "ad_payload_cache"):
        val = s.get(key)
        if isinstance(val, str):
            try:
                s[key] = json.loads(val)
            except (json.JSONDecodeError, TypeError):
                pass
    if isinstance(s.get("tracking"), int):
        s["tracking"] = bool(s["tracking"])
    if isinstance(s.get("plus_mode"), int):
        s["plus_mode"] = bool(s["plus_mode"])
    if isinstance(s.get("copier_mode"), int):
        s["copier_mode"] = bool(s["copier_mode"])
    return s


def get_session(session_id):
    conn = _get_conn()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM tracking_sessions WHERE id = %s", (session_id,))
        row = cur.fetchone()
        return _normalize_session(row)
    finally:
        cur.close()
        conn.close()


def get_active_sessions():
    conn = _get_conn()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM tracking_sessions WHERE tracking = 1")
        rows = cur.fetchall()
        return [_normalize_session(r) for r in rows]
    finally:
        cur.close()
        conn.close()


def get_user_sessions(telegram_id, limit=5, active_only=False):
    conn = _get_conn()
    try:
        cur = conn.cursor(dictionary=True)
        if active_only:
            sql = (
                "SELECT * FROM tracking_sessions WHERE telegram_id=%s AND tracking=1 "
                "ORDER BY updated_at DESC LIMIT %s"
            )
        else:
            sql = (
                "SELECT * FROM tracking_sessions WHERE telegram_id=%s "
                "ORDER BY updated_at DESC LIMIT %s"
            )
        cur.execute(sql, (telegram_id, limit))
        rows = cur.fetchall()
        return [_normalize_session(r) for r in rows]
    finally:
        cur.close()
        conn.close()


def update_session(session_id, updates):
    if not updates:
        return
    fields = []
    values = []
    for key, value in updates.items():
        if key in ("payment_terms", "trading_preference_set", "ad_payload_cache"):
            value = json.dumps(value)
        fields.append(f"{key} = %s")
        values.append(value)
    fields.append("updated_at = NOW()")
    values.append(session_id)
    conn = _get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            f"UPDATE tracking_sessions SET {', '.join(fields)} WHERE id = %s",
            tuple(values),
        )
        conn.commit()
    finally:
        cur.close()
        conn.close()


def delete_active_sessions(telegram_id, ad_id):
    conn = _get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "DELETE FROM tracking_sessions WHERE telegram_id=%s AND tracking=1 AND ad_id=%s",
            (telegram_id, str(ad_id)),
        )
        conn.commit()
    finally:
        cur.close()
        conn.close()


def insert_session(data):
    sid = uuid()
    conn = _get_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            """INSERT INTO tracking_sessions
               (id, telegram_id, ad_id, token_id, currency_id, current_ad_price,
                min_amount, max_amount, ad_status, price_type, premium, last_quantity,
                payment_period, payment_terms, trading_preference_set,
                selected_merchant_id, selected_merchant_name, reference_price,
                last_merchant_price, tracking, copier_mode, plus_mode, plus_timer_ms,
                last_update_time, ad_payload_cache)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
            (
                sid,
                data["telegram_id"],
                str(data["ad_id"]),
                data.get("token_id"),
                data.get("currency_id"),
                data.get("current_ad_price"),
                data.get("min_amount"),
                data.get("max_amount"),
                data.get("ad_status"),
                data.get("price_type", 0),
                data.get("premium", "0"),
                data.get("last_quantity", "1"),
                data.get("payment_period", 30),
                json.dumps(data.get("payment_terms")) if data.get("payment_terms") else None,
                json.dumps(data.get("trading_preference_set")) if data.get("trading_preference_set") else None,
                str(data.get("selected_merchant_id", "")),
                data.get("selected_merchant_name"),
                data.get("reference_price"),
                data.get("last_merchant_price"),
                1 if data.get("tracking") else 0,
                1 if data.get("copier_mode") else 0,
                1 if data.get("plus_mode", False) else 0,
                data.get("plus_timer_ms", 180000),
                data.get("last_update_time"),
                json.dumps(data.get("ad_payload_cache")) if data.get("ad_payload_cache") else None,
            ),
        )
        conn.commit()
        return sid
    finally:
        cur.close()
        conn.close()


# ---------------------------------------------------------------------------
# competitor_logs
# ---------------------------------------------------------------------------

def log_competitor(telegram_id, session_id, merchant_id, merchant_name, price, ad_price_set, action):
    try:
        conn = _get_conn()
        try:
            cur = conn.cursor()
            cur.execute(
                """INSERT INTO competitor_logs
                   (id, telegram_id, session_id, merchant_id, merchant_name, merchant_price, ad_price_set, action)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s)""",
                (uuid(), telegram_id, session_id, merchant_id, merchant_name, price, ad_price_set, action),
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()
    except Exception:
        pass
