/*
 * Tracking engine — runs the polling loops for each active tracking session.
 * Mirrors the Blade view's logic: ratchet mode and plus mode.
 */

const { supabase } = require('./supabase');
const { fetchCompetitors, fetchTopperPrice, updateAdPrice, sleep } = require('./bybit');

/**
 * Get a user's credentials from the database.
 */
async function getUser(telegramId) {
  const { data, error } = await supabase
    .from('bot_users')
    .select('*')
    .eq('telegram_id', telegramId)
    .maybeSingle();
  if (error) throw error;
  return data;
}

/**
 * Get all active tracking sessions (tracking = true).
 */
async function getActiveSessions() {
  const { data, error } = await supabase
    .from('tracking_sessions')
    .select('*')
    .eq('tracking', true);
  if (error) throw error;
  return data || [];
}

/**
 * Get a single session by id.
 */
async function getSession(sessionId) {
  const { data, error } = await supabase
    .from('tracking_sessions')
    .select('*')
    .eq('id', sessionId)
    .maybeSingle();
  if (error) throw error;
  return data;
}

/**
 * Update a session row in the database.
 */
async function updateSession(sessionId, updates) {
  const { error } = await supabase
    .from('tracking_sessions')
    .update({ ...updates, updated_at: new Date().toISOString() })
    .eq('id', sessionId);
  if (error) throw error;
}

/**
 * Insert a competitor log entry.
 */
async function logCompetitor(session, merchant, price, adPriceSet, action) {
  try {
    await supabase.from('competitor_logs').insert({
      telegram_id: session.telegram_id,
      session_id: session.id,
      merchant_id: merchant?.id || session.selected_merchant_id,
      merchant_name: merchant?.nickName || session.selected_merchant_name,
      merchant_price: price,
      ad_price_set: adPriceSet,
      action,
    });
  } catch (e) {
    // logging is best-effort
  }
}

/**
 * Run one tick of the tracking loop for a single session.
 * Fetches competitors + topper price, then decides whether to update the ad.
 *
 * @param {object} session - full session row
 * @param {object} bot - telegram bot instance for notifications
 * @param {object} competitorsCache - mutable cache { [sessionId]: array }
 */
async function runTrackingTick(session, bot, competitorsCache, cachedTopperPrice) {
  const user = await getUser(session.telegram_id);
  if (!user || !user.bybit_api_key || !user.bybit_api_secret) return;

  const apiUrl = user.bybit_api_url || 'https://api2.bybit.com';
  const apiKey = user.bybit_api_key;
  const apiSecret = user.bybit_api_secret;

  // Re-fetch session to get latest state (could have been stopped by user)
  const fresh = await getSession(session.id);
  if (!fresh || !fresh.tracking) return;
  session = fresh;

  // 1. Use cached topper price (updated every 1 second by the topper poll loop)
  const topperPrice = cachedTopperPrice;

  // 2. Fetch competitors (resilient to temporary API failures)
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
    // API temporarily unavailable — skip this tick, retry next cycle
    competitorsCache[session.id] = competitorsCache[session.id] || [];
    return;
  }

  if (!status) {
    competitorsCache[session.id] = [];
    return;
  }

  // Inject topper at top
  let allCompetitors = [...competitors];
  if (topperPrice !== null) {
    allCompetitors.unshift({
      id: 'TOPPER-BTC',
      nickName: 'TOPPER-BTC',
      price: topperPrice,
    });
  }

  competitorsCache[session.id] = allCompetitors;

  // 3. Find the selected merchant
  let merchant;
  if (session.copier_mode) {
    // Copier mode: always track whoever is #1 in the market right now
    // (skip TOPPER-BTC injected entry — only real market competitors)
    const realCompetitors = allCompetitors.filter(
      (x) => String(x.id) !== 'TOPPER-BTC'
    );
    merchant = realCompetitors[0] || null;

    if (!merchant) {
      // No competitors available this tick — skip, retry next cycle
      return;
    }

    // Update session to reflect the current #1 merchant (it may change)
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

  // 4. Decide action
  let shouldUpdate = false;
  let newPrice = currentPrice;
  let action = '';
  let statusText = '';

  if (session.copier_mode) {
    // Copier: only update when the #1 market price goes UP (never down)
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
      // Ratchet: only update when price goes UP (never down)
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

  // 5. Update ad price if needed
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
  } else {
    // Just update last_merchant_price in DB for tracking accuracy
    if (currentPrice !== lastMerchantPrice) {
      await updateSession(session.id, {
        last_merchant_price: currentPrice,
      });
    }
  }
}

/**
 * Start the global tracking loop. Runs every 3 seconds, processing all active sessions.
 * Also runs a topper-price-only poll every 1 second (for faster limit detection).
 *
 * @param {object} bot - telegram bot instance
 * @returns {void}
 */
function startTrackingLoop(bot) {
  const competitorsCache = {};
  let cachedTopperPrice = null;

  // Topper price poll every 1 second (matches the web app's 1-second topper check)
  setInterval(async () => {
    try {
      const price = await fetchTopperPrice();
      if (price !== null) cachedTopperPrice = price;
    } catch (e) {
      // best-effort
    }
  }, 1000);

  // Main loop every 3 seconds (matches the Blade view's market poll)
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
};
