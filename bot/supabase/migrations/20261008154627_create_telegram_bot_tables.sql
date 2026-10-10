/*
# Create tables for Bybit P2P Telegram Bot

1. New Tables
- `bot_users` — stores Telegram users' Bybit API credentials and settings.
  - `telegram_id` (bigint, unique) — the Telegram user ID
  - `bybit_api_key` (text) — Bybit P2P API key
  - `bybit_api_secret` (text) — Bybit P2P API secret
  - `bybit_api_url` (text) — custom API proxy URL
  - `plus_mode` (boolean, default false) — whether competitor-plus mode is on
  - `plus_timer_ms` (integer, default 180000) — re-edit timer in ms
  - `created_at` (timestamptz)
  - `updated_at` (timestamptz)

- `tracking_sessions` — represents a user's active tracking session for one ad + one merchant.
  - `id` (uuid, primary key)
  - `telegram_id` (bigint, references bot_users)
  - `ad_id` (text) — the Bybit ad ID being tracked
  - `token_id` (text) — e.g. BTC, USDT
  - `currency_id` (text) — e.g. NGN, USD
  - `current_ad_price` (numeric) — last price the ad was updated to
  - `min_amount` (text)
  - `max_amount` (text)
  - `ad_status` (text)
  - `price_type` (integer, default 0)
  - `premium` (text, default '0')
  - `last_quantity` (text, default '1')
  - `payment_period` (integer, default 30)
  - `payment_terms` (jsonb) — full payment terms array from the ad
  - `trading_preference_set` (jsonb) — full trading preference set from the ad
  - `selected_merchant_id` (text) — which merchant we're tracking
  - `selected_merchant_name` (text)
  - `reference_price` (numeric) — initial price when tracking started
  - `last_merchant_price` (numeric) — last known merchant price
  - `tracking` (boolean, default false)
  - `plus_mode` (boolean, default false)
  - `plus_timer_ms` (integer, default 180000)
  - `last_update_time` (timestamptz) — last successful ad update time
  - `ad_payload_cache` (jsonb) — cached full ad object for update calls
  - `created_at` (timestamptz)
  - `updated_at` (timestamptz)

- `competitor_logs` — audit log of merchant price changes and ad updates.
  - `id` (uuid, primary key)
  - `telegram_id` (bigint)
  - `session_id` (uuid, references tracking_sessions)
  - `merchant_id` (text)
  - `merchant_name` (text)
  - `merchant_price` (numeric)
  - `ad_price_set` (numeric)
  - `action` (text) — 'track_up', 'track_down', 'timer_resync', 'syncing_limit', 'matching'
  - `created_at` (timestamptz)

2. Security
- Enable RLS on all tables.
- Use anon, authenticated policies since this is a no-auth backend (bot writes via service role).
- All policies allow full CRUD for anon + authenticated (the bot uses service_role key server-side).
*/

CREATE TABLE IF NOT EXISTS bot_users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  telegram_id bigint UNIQUE NOT NULL,
  bybit_api_key text,
  bybit_api_secret text,
  bybit_api_url text DEFAULT 'https://api2.bybit.com',
  plus_mode boolean DEFAULT false,
  plus_timer_ms integer DEFAULT 180000,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE bot_users ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "anon_crud_bot_users" ON bot_users;
CREATE POLICY "anon_crud_bot_users" ON bot_users FOR SELECT TO anon, authenticated USING (true);
DROP POLICY IF EXISTS "anon_insert_bot_users" ON bot_users;
CREATE POLICY "anon_insert_bot_users" ON bot_users FOR INSERT TO anon, authenticated WITH CHECK (true);
DROP POLICY IF EXISTS "anon_update_bot_users" ON bot_users;
CREATE POLICY "anon_update_bot_users" ON bot_users FOR UPDATE TO anon, authenticated USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "anon_delete_bot_users" ON bot_users;
CREATE POLICY "anon_delete_bot_users" ON bot_users FOR DELETE TO anon, authenticated USING (true);

CREATE TABLE IF NOT EXISTS tracking_sessions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  telegram_id bigint NOT NULL REFERENCES bot_users(telegram_id) ON DELETE CASCADE,
  ad_id text NOT NULL,
  token_id text,
  currency_id text,
  current_ad_price numeric,
  min_amount text,
  max_amount text,
  ad_status text,
  price_type integer DEFAULT 0,
  premium text DEFAULT '0',
  last_quantity text DEFAULT '1',
  payment_period integer DEFAULT 30,
  payment_terms jsonb,
  trading_preference_set jsonb,
  selected_merchant_id text,
  selected_merchant_name text,
  reference_price numeric,
  last_merchant_price numeric,
  tracking boolean DEFAULT false,
  plus_mode boolean DEFAULT false,
  plus_timer_ms integer DEFAULT 180000,
  last_update_time timestamptz DEFAULT now(),
  ad_payload_cache jsonb,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE tracking_sessions ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "anon_crud_tracking_sessions" ON tracking_sessions;
CREATE POLICY "anon_crud_tracking_sessions" ON tracking_sessions FOR SELECT TO anon, authenticated USING (true);
DROP POLICY IF EXISTS "anon_insert_tracking_sessions" ON tracking_sessions;
CREATE POLICY "anon_insert_tracking_sessions" ON tracking_sessions FOR INSERT TO anon, authenticated WITH CHECK (true);
DROP POLICY IF EXISTS "anon_update_tracking_sessions" ON tracking_sessions;
CREATE POLICY "anon_update_tracking_sessions" ON tracking_sessions FOR UPDATE TO anon, authenticated USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "anon_delete_tracking_sessions" ON tracking_sessions;
CREATE POLICY "anon_delete_tracking_sessions" ON tracking_sessions FOR DELETE TO anon, authenticated USING (true);

CREATE TABLE IF NOT EXISTS competitor_logs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  telegram_id bigint,
  session_id uuid REFERENCES tracking_sessions(id) ON DELETE CASCADE,
  merchant_id text,
  merchant_name text,
  merchant_price numeric,
  ad_price_set numeric,
  action text,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE competitor_logs ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "anon_crud_competitor_logs" ON competitor_logs;
CREATE POLICY "anon_crud_competitor_logs" ON competitor_logs FOR SELECT TO anon, authenticated USING (true);
DROP POLICY IF EXISTS "anon_insert_competitor_logs" ON competitor_logs;
CREATE POLICY "anon_insert_competitor_logs" ON competitor_logs FOR INSERT TO anon, authenticated WITH CHECK (true);
DROP POLICY IF EXISTS "anon_update_competitor_logs" ON competitor_logs;
CREATE POLICY "anon_update_competitor_logs" ON competitor_logs FOR UPDATE TO anon, authenticated USING (true) WITH CHECK (true);
DROP POLICY IF EXISTS "anon_delete_competitor_logs" ON competitor_logs;
CREATE POLICY "anon_delete_competitor_logs" ON competitor_logs FOR DELETE TO anon, authenticated USING (true);

CREATE INDEX IF NOT EXISTS idx_tracking_sessions_telegram_id ON tracking_sessions(telegram_id);
CREATE INDEX IF NOT EXISTS idx_competitor_logs_session_id ON competitor_logs(session_id);
CREATE INDEX IF NOT EXISTS idx_competitor_logs_telegram_id ON competitor_logs(telegram_id);