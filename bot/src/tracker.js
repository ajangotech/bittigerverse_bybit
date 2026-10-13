/*
 * Tracking engine — runs the polling loops for each active tracking session.
 * Mirrors the Blade view's logic: ratchet mode and plus mode.
 */

const crypto = require('crypto');
const { pool } = require('./db');
const { fetchCompetitors, fetchTopperPrice, updateAdPrice, sleep } = require('./bybit');

function uuid() {
  return crypto.randomUUID();
}

/**
 * Get a user's credentials from the database.
 */
async function getUser(telegramId) {
  const [rows] = await pool.query(
    'SELECT * FROM bot_users WHERE telegram_id = ?',
    [telegramId]
  );
  return rows[0] || null;
}

/**
 * Get all active tracking sessions (tracking = true).
 */
async function getActiveSessions() {
  const [rows] = await pool.query(
    'SELECT * FROM tracking_sessions WHERE tracking = 1'
  );
  return rows;
}

/**
 * Get a single session by id.
 */
async function getSession(sessionId) {
  const [rows] = await pool.query(
    'SELECT * FROM tracking_sessions WHERE id = ?',
    [sessionId]
  );
  return rows[0] || null;
}

/**
 * Update a session row in the database.
 */
async function updateSession(sessionId, updates) {
  const fields = [];
  const values = [];
  for (const [key, value] of Object.entries(updates)) {
    fields.push(`${key} = ?`);
    values.push(value);
  }
  fields.push('updated_at = NOW()');
  values.push(sessionId);
  await pool.query(
    `UPDATE tracking_sessions SET ${fields.join(', ')} WHERE id = ?`,
    values
  );
}

/**
 * Insert a competitor log entry.
 */
async function logCompetitor(session, merchant, price, adPriceSet, action) {
  try {
    await pool.query(
      'INSERT INTO competitor_logs (id, telegram_id, session_id, merchant_id, merchant_name, merchant_price, ad_price_set, action) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [uuid(), session.telegram_id, session.id, merchant?.id || session.selected_merchant_id, merchant?.nickName || session.selected_merchant_name, price, adPriceSet, action]
    );
  } catch (e) {
    // logging is best-effort
  }
}

/**
 * Parse JSON fields in a session row that MySQL returns as strings.
 */
function normalizeSession(session) {
  if (!session) return session;
  const s = { ...session };
  if (typeof s.payment_terms === 'string') {
    try { s.payment_terms = JSON.parse(s.payment_terms); } catch (_) {}
  }
  if (typeof s.trading_preference_set === 'string') {
    try { s.trading_preference_set = JSON.parse(s.trading_preference_set); } catch (_) {}
  }
  if (typeof s.ad_payload_cache === 'string') {
    try { s.ad_payload_cache = JSON.parse(s.ad_payload_cache); } catch (_) {}
  }
  if (typeof s.tracking === 'number') s.tracking = !!s.tracking;
  if (typeof s.plus_mode === 'number') s.plus_mode = !!s.plus_mode;
  if (typeof s.copier_mode === 'number') s.copier_mode = !!s.copier_mode;
  return s;
}

/**
 * Run one tick of the tracking loop for a single session.
 */
async function runTrackingTick(session, bot, competitorsCache, cachedTopperPrice) {
  const user = await getUser(session.telegram_id);
  if (!user || !user.bybit_api_key || !user.bybit_api_secret) return;

  const apiUrl = user.bybit_api_url || 'https://02-bittiger.ajango.com.ng/api';
  const apiKey = user.bybit_api_key;
  const apiSecret = user.bybit_api_secret;

  const fresh = await getSession(session.id);
  if (!fresh || !fresh.tracking) return;
  session = normalizeSession(fresh);

  const topperPrice = cachedTopperPrice;

  let status = false;
  let competitors = [];
  try {
    const result = await fetchCompetitors(
      apiUrl,
      apiKey,
      apiSecret,
      session.token_id,
      session.currency_id
    );
    status = result.status;
    competitors = result.competitors;
  } catch (e) {
    competitorsCache[session.id] = competitorsCache[session.id] || [];
    return;
  }

  if (!status) {
    competitorsCache[session.id] = [];
    return;
  }

  let allCompetitors = [...competitors];
  if (topperPrice !== null) {
    allCompetitors.unshift({
      id: 'TOPPER-BTC',
      nickName: 'TOPPER-BTC',
      price: topperPrice,
    });
  }

  competitorsCache[session.id] = allCompetitors;

  let merchant;
  if (session.copier_mode) {
    const realCompetitors = allCompetitors.filter(
      (x) => String(x.id) !== 'TOPPER-BTC'
    );
    merchant = realCompetitors[0] || null;

    if (!merchant) {
      return;
    }

    if (String(merchant.id) !== String(session.selected_merchant_id)) {
      await updateSession(session.id, {
        selected_merchant_id: String(merchant.id),
        selected_merchant_name: merchant.nickName,
      });
      session.selected_merchant_id = String(merchant.id);
      session.selected_merchant_name = merchant.nickName;
    }
  } else {
    merchant = allCompetitors.find(
      (x) => String(x.id) === String(session.selected_merchant_id)
    );

    if (!merchant) {
      await bot.sendMessage(
        session.telegram_id,
        `⚠️ Merchant *${session.selected_merchant_name}* is no longer in the top 10. Tracking paused.`,
        { parse_mode: 'Markdown' }
      );
      await updateSession(session.id, { tracking: false });
      return;
    }
  }

  const currentPrice = parseFloat(merchant.price);
  if (isNaN(currentPrice)) return;

  const lastMerchantPrice = parseFloat(session.last_merchant_price) || 0;
  const plusMode = session.plus_mode;
  const plusTimerMs = session.plus_timer_ms || 180000;
  const lastUpdateTime = session.last_update_time
    ? new Date(session.last_update_time).getTime()
    : Date.now();
  const timeSinceLastUpdate = Date.now() - lastUpdateTime;

  let shouldUpdate = false;
  let newPrice = currentPrice;
  let action = '';
  let statusText = '';

  if (session.copier_mode) {
    if (currentPrice > lastMerchantPrice) {
      statusText = `Copier: #1 ${merchant.nickName} price UP to ${currentPrice}`;
      shouldUpdate = true;
      action = 'copier_up';
    } else if (currentPrice === lastMerchantPrice) {
      statusText = `Copier: Synced with #1 (${lastMerchantPrice})`;
    } else {
      statusText = `Copier: Market down (${currentPrice}) — holding at ${lastMerchantPrice}`;
    }
  } else if (session.selected_merchant_id === 'TOPPER-BTC') {
    if (plusMode) {
      if (currentPrice <= lastMerchantPrice && timeSinceLastUpdate < plusTimerMs) {
        statusText = `At Limit (${lastMerchantPrice})`;
      } else {
        statusText = 'Syncing Limit (Plus)';
        shouldUpdate = true;
      }
    } else {
      if (currentPrice <= lastMerchantPrice) {
        statusText = `At API Limit (${lastMerchantPrice})`;
      } else {
        statusText = 'Syncing to Limit (Upwards)';
        shouldUpdate = true;
      }
    }
  } else {
    if (plusMode) {
      if (timeSinceLastUpdate >= plusTimerMs) {
        statusText = `⏰ Timer expired! Re-editing to ${session.selected_merchant_name}'s price (${currentPrice})`;
        shouldUpdate = true;
        action = 'timer_resync';
      } else if (currentPrice > lastMerchantPrice) {
        statusText = 'Tracking Upwards (Plus)';
        shouldUpdate = true;
        action = 'track_up';
      } else {
        statusText = `Maintaining (${lastMerchantPrice})`;
      }
    } else {
      if (currentPrice === lastMerchantPrice) {
        statusText = `Matching Market (${lastMerchantPrice})`;
      } else if (currentPrice > lastMerchantPrice) {
        statusText = 'Tracking Upwards';
        shouldUpdate = true;
        action = 'track_up';
      } else {
        statusText = `Market down (${currentPrice}) — holding at ${lastMerchantPrice}`;
      }
    }
  }

  if (shouldUpdate) {
    const ad = session.ad_payload_cache;
    if (!ad) return;

    const result = await updateAdPrice(apiUrl, apiKey, apiSecret, ad, newPrice);
    if (result.success) {
      await updateSession(session.id, {
        current_ad_price: newPrice,
        last_merchant_price: currentPrice,
        last_update_time: new Date().toISOString(),
      });

      await bot.sendMessage(
        session.telegram_id,
        `✅ Ad updated to *${newPrice}*\n📊 ${statusText}\n👤 Merchant: ${session.selected_merchant_name}`,
        { parse_mode: 'Markdown' }
      );

      await logCompetitor(session, merchant, currentPrice, newPrice, action || 'syncing_limit');
    } else {
      await bot.sendMessage(
        session.telegram_id,
        `❌ Failed to update ad: ${result.error || 'unknown error'}`,
        { parse_mode: 'Markdown' }
      );
    }
  }
}

/**
 * Start the global tracking loop. Runs every 3 seconds, processing all active sessions.
 * Also runs a topper-price-only poll every 1 second.
 */
function startTrackingLoop(bot) {
  const competitorsCache = {};
  let cachedTopperPrice = null;

  setInterval(async () => {
    try {
      const price = await fetchTopperPrice();
      if (price !== null) cachedTopperPrice = price;
    } catch (e) {
      // best-effort
    }
  }, 1000);

  setInterval(async () => {
    try {
      const sessions = await getActiveSessions();
      for (const session of sessions) {
        try {
          await runTrackingTick(session, bot, competitorsCache, cachedTopperPrice);
        } catch (e) {
          console.error(`Tracking tick error for session ${session.id}:`, e.message);
        }
      }
    } catch (e) {
      console.error('Tracking loop error:', e.message);
    }
  }, 3000);
}

module.exports = {
  getUser,
  getActiveSessions,
  getSession,
  updateSession,
  logCompetitor,
  runTrackingTick,
  startTrackingLoop,
  uuid,
};
