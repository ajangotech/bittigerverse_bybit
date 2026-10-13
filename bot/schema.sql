
CREATE TABLE IF NOT EXISTS bot_users (
    id VARCHAR(36) PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    bybit_api_key TEXT,
    bybit_api_secret TEXT,
    bybit_api_url VARCHAR(255) DEFAULT 'https://03-bittiger.ajango.com.ng/api',
    plus_mode BOOLEAN DEFAULT FALSE,
    plus_timer_ms INT DEFAULT 180000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tracking_sessions (
  id VARCHAR(36) PRIMARY KEY,
  telegram_id BIGINT NOT NULL,
  ad_id TEXT NOT NULL,
  token_id TEXT,
  currency_id TEXT,
  current_ad_price DECIMAL(20,8),
  min_amount TEXT,
  max_amount TEXT,
  ad_status TEXT,
  price_type INT DEFAULT 0,
  premium VARCHAR(255) DEFAULT '0',
  last_quantity VARCHAR(255) DEFAULT '1',
  payment_period INT DEFAULT 30,
  payment_terms JSON,
  trading_preference_set JSON,
  selected_merchant_id TEXT,
  selected_merchant_name TEXT,
  reference_price DECIMAL(20,8),
  last_merchant_price DECIMAL(20,8),
  tracking BOOLEAN DEFAULT FALSE,
  copier_mode BOOLEAN DEFAULT FALSE,
  plus_mode BOOLEAN DEFAULT FALSE,
  plus_timer_ms INT DEFAULT 180000,
  last_update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ad_payload_cache JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (telegram_id) REFERENCES bot_users(telegram_id) ON DELETE CASCADE,
  INDEX idx_tracking_sessions_telegram_id (telegram_id)
);

CREATE TABLE IF NOT EXISTS competitor_logs (
  id VARCHAR(36) PRIMARY KEY,
  telegram_id BIGINT,
  session_id VARCHAR(36),
  merchant_id TEXT,
  merchant_name TEXT,
  merchant_price DECIMAL(20,8),
  ad_price_set DECIMAL(20,8),
  action TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES tracking_sessions(id) ON DELETE CASCADE,
  INDEX idx_competitor_logs_session_id (session_id),
  INDEX idx_competitor_logs_telegram_id (telegram_id)
);
