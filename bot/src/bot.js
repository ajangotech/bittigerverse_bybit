/*
 * Telegram bot — command handlers and inline keyboard callbacks.
 *
 * Commands:
 *  /start       — register + welcome + show menu
 *  /menu        — show the main command keyboard
 *  /setkeys     — set Bybit API key/secret (prompt-based)
 *  /seturl      — set custom API proxy URL
 *  /myads       — list your Bybit ads + TOPPER-BTC (inline buttons)
 *  /track       — start tracking flow (pick ad -> pick merchant)
 *  /stop        — stop active tracking
 *  /status      — show current tracking status
 *  /plus        — toggle plus mode for a specific tracking session
 *  /timer       — set re-edit timer for a specific tracking session
 *  /help        — show all commands
 *  /cancel      — cancel current multi-step flow
 */

const TelegramBot = require('node-telegram-bot-api');
const { supabase } = require('./supabase');
const { fetchAds, fetchCompetitors, updateAdPrice, fetchTopperPrice } = require('./bybit');
const {
  getUser,
  getSession,
  updateSession,
  startTrackingLoop,
} = require('./tracker');

const token = process.env.TELEGRAM_BOT_TOKEN;
if (!token) {
  console.error('TELEGRAM_BOT_TOKEN is not set. Add it to .env');
  process.exit(1);
}

const bot = new TelegramBot(token, { polling: true });

// In-memory state for multi-step flows (keyed by telegram_id)
const userState = {};

// Timer options (same as the Blade view)
const TIMER_OPTIONS = [
  { label: '1.0 min', ms: 60000 },
  { label: '1.5 min', ms: 90000 },
  { label: '2.0 min', ms: 120000 },
  { label: '2.5 min', ms: 150000 },
  { label: '3.0 min', ms: 180000 },
  { label: '3.5 min', ms: 210000 },
  { label: '4.0 min', ms: 240000 },
  { label: '4.5 min', ms: 270000 },
  { label: '5.0 min', ms: 300000 },
  { label: '5.5 min', ms: 330000 },
  { label: '6.0 min', ms: 360000 },
  { label: '6.5 min', ms: 390000 },
  { label: '7.0 min', ms: 420000 },
];

/*
|--------------------------------------------------------------------------
| Main menu keyboard — persistent reply keyboard
|--------------------------------------------------------------------------
*/
const mainMenuKeyboard = {
  keyboard: [
    [{ text: '📋 My Ads' }, { text: '🎯 Track' }],
    [{ text: '🤖 Copier' }, { text: '📊 Status' }],
    [{ text: '🛑 Stop' }, { text: '🚀 Plus Mode' }],
    [{ text: '⏱️ Timer' }, { text: '🔑 Set Keys' }],
    [{ text: '🌐 Set URL' }, { text: '❓ Help' }],
  ],
  resize_keyboard: true,
  one_time_keyboard: false,
};

/*
|--------------------------------------------------------------------------
| Ensure user exists in DB
|--------------------------------------------------------------------------
*/
async function ensureUser(telegramId) {
  let user = await getUser(telegramId);
  if (!user) {
    const { data, error } = await supabase
      .from('bot_users')
      .insert({ telegram_id: telegramId })
      .select()
      .maybeSingle();
    if (error) throw error;
    user = data;
  }
  return user;
}

/*
|--------------------------------------------------------------------------
| /start
|--------------------------------------------------------------------------
*/
bot.onText(/\/start/, async (msg) => {
  const chatId = msg.chat.id;
  try {
    await ensureUser(msg.from.id);
    bot.sendMessage(
      chatId,
      '👋 Welcome to the *Bybit P2P Competitor Tracker Bot*!\n\n' +
        'I can help you track competitor prices and auto-update your Bybit P2P ads.\n\n' +
        'Use the menu below to navigate, or type commands directly.\n\n' +
        '*Quick Start:*\n' +
        '1️⃣ Tap "Set Keys" to set your Bybit API key and secret\n' +
        '2️⃣ Tap "My Ads" to see your advertisements\n' +
        '3️⃣ Tap "Track" to start tracking a competitor',
      { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
    );
  } catch (e) {
    bot.sendMessage(chatId, '❌ Error: ' + e.message);
  }
});

/*
|--------------------------------------------------------------------------
| /menu — show the main keyboard
|--------------------------------------------------------------------------
*/
bot.onText(/\/menu/, (msg) => {
  bot.sendMessage(msg.chat.id, '📋 *Main Menu* — tap a button below:', {
    parse_mode: 'Markdown',
    reply_markup: mainMenuKeyboard,
  });
});

/*
|--------------------------------------------------------------------------
| /help
|--------------------------------------------------------------------------
*/
bot.onText(/\/help/, (msg) => {
  bot.sendMessage(
    msg.chat.id,
    '*Commands:*\n\n' +
      '/start — Register & welcome\n' +
      '/menu — Show the command menu\n' +
      '/setkeys — Set Bybit API key and secret\n' +
      '/seturl — Set custom API proxy URL\n' +
      '/myads — List your ads + TOPPER-BTC\n' +
      '/track — Start tracking a competitor\n' +
      '/copier — Auto-copy the #1 market merchant (upward only)\n' +
      '/stop — Stop active tracking\n' +
      '/status — Show current tracking status\n' +
      '/plus — Toggle Plus mode for a session\n' +
      '/timer — Set re-edit timer for a session\n' +
      '/cancel — Cancel current operation\n' +
      '/help — Show this help',
    { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
  );
});

/*
|--------------------------------------------------------------------------
| /setkeys — prompt-based flow
|--------------------------------------------------------------------------
*/
bot.onText(/\/setkeys/, (msg) => {
  const chatId = msg.chat.id;
  userState[msg.from.id] = { step: 'awaiting_api_key' };
  bot.sendMessage(
    chatId,
    'Please send your *Bybit API Key*.\n\n⚠️ Your next message will be saved as your API key. Send /cancel to abort.',
    { parse_mode: 'Markdown' }
  );
});

/*
|--------------------------------------------------------------------------
| /seturl
|--------------------------------------------------------------------------
*/
bot.onText(/\/seturl/, (msg) => {
  const chatId = msg.chat.id;
  userState[msg.from.id] = { step: 'awaiting_api_url' };
  bot.sendMessage(
    chatId,
    'Please send your *API proxy URL* (e.g. https://api2.bybit.com).\n\nSend /cancel to abort.',
    { parse_mode: 'Markdown' }
  );
});

/*
|--------------------------------------------------------------------------
| /cancel
|--------------------------------------------------------------------------
*/
bot.onText(/\/cancel/, (msg) => {
  delete userState[msg.from.id];
  bot.sendMessage(msg.chat.id, '❌ Operation cancelled.', { reply_markup: mainMenuKeyboard });
});

/*
|--------------------------------------------------------------------------
| Handle plain-text messages (menu button presses + multi-step flows)
|--------------------------------------------------------------------------
*/
bot.on('message', async (msg) => {
  const chatId = msg.chat.id;
  const userId = msg.from.id;
  const text = msg.text ? msg.text.trim() : '';

  // Route menu button presses to their command equivalents
  const menuRoutes = {
    '📋 My Ads': '/myads',
    '🎯 Track': '/track',
    '🤖 Copier': '/copier',
    '📊 Status': '/status',
    '🛑 Stop': '/stop',
    '🚀 Plus Mode': '/plus',
    '⏱️ Timer': '/timer',
    '🔑 Set Keys': '/setkeys',
    '🌐 Set URL': '/seturl',
    '❓ Help': '/help',
  };

  if (menuRoutes[text]) {
    msg.text = menuRoutes[text];
    // Re-emit as a fake command by calling the handler directly
    const cmd = menuRoutes[text];
    if (cmd === '/myads') return handleMyAds(msg);
    if (cmd === '/track') return handleTrack(msg);
    if (cmd === '/copier') return handleCopier(msg);
    if (cmd === '/status') return handleStatus(msg);
    if (cmd === '/stop') return handleStop(msg);
    if (cmd === '/plus') return handlePlus(msg);
    if (cmd === '/timer') return handleTimer(msg);
    if (cmd === '/setkeys') {
      userState[userId] = { step: 'awaiting_api_key' };
      return bot.sendMessage(chatId, 'Please send your *Bybit API Key*.\n\nSend /cancel to abort.', { parse_mode: 'Markdown' });
    }
    if (cmd === '/seturl') {
      userState[userId] = { step: 'awaiting_api_url' };
      return bot.sendMessage(chatId, 'Please send your *API proxy URL*.\n\nSend /cancel to abort.', { parse_mode: 'Markdown' });
    }
    if (cmd === '/help') {
      return bot.sendMessage(
        chatId,
        '*Commands:*\n\n' +
          '/start — Register & welcome\n' +
          '/menu — Show the command menu\n' +
          '/setkeys — Set Bybit API key and secret\n' +
          '/seturl — Set custom API proxy URL\n' +
          '/myads — List your ads + TOPPER-BTC\n' +
          '/track — Start tracking a competitor\n' +
          '/copier — Auto-copy the #1 market merchant (upward only)\n' +
          '/stop — Stop active tracking\n' +
          '/status — Show current tracking status\n' +
          '/plus — Toggle Plus mode for a session\n' +
          '/timer — Set re-edit timer for a session\n' +
          '/cancel — Cancel current operation\n' +
          '/help — Show this help',
        { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
      );
    }
    return;
  }

  // Ignore command messages (handled by onText handlers)
  if (text.startsWith('/')) return;

  const state = userState[userId];
  if (!state) return;

  try {
    // --- API Key input ---
    if (state.step === 'awaiting_api_key') {
      state.apiKey = text;
      state.step = 'awaiting_api_secret';
      bot.sendMessage(chatId, '✅ API key saved. Now send your *Bybit API Secret*.', {
        parse_mode: 'Markdown',
      });
      return;
    }

    // --- API Secret input ---
    if (state.step === 'awaiting_api_secret') {
      const apiSecret = text;
      const { error } = await supabase
        .from('bot_users')
        .update({
          bybit_api_key: state.apiKey,
          bybit_api_secret: apiSecret,
          updated_at: new Date().toISOString(),
        })
        .eq('telegram_id', userId);
      if (error) throw error;

      delete userState[userId];
      bot.sendMessage(chatId, '✅ Bybit API credentials saved successfully! Tap "My Ads" to see your ads.', {
        reply_markup: mainMenuKeyboard,
      });
      return;
    }

    // --- API URL input ---
    if (state.step === 'awaiting_api_url') {
      const url = text.replace(/\/$/, '');
      const { error } = await supabase
        .from('bot_users')
        .update({
          bybit_api_url: url,
          updated_at: new Date().toISOString(),
        })
        .eq('telegram_id', userId);
      if (error) throw error;

      delete userState[userId];
      bot.sendMessage(chatId, `✅ API URL set to: ${url}`, { reply_markup: mainMenuKeyboard });
      return;
    }
  } catch (e) {
    bot.sendMessage(chatId, '❌ Error: ' + e.message, { reply_markup: mainMenuKeyboard });
    delete userState[userId];
  }
});

/*
|--------------------------------------------------------------------------
| /myads — list ads + TOPPER-BTC as inline buttons
|--------------------------------------------------------------------------
*/
async function handleMyAds(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const user = await ensureUser(userId);
    if (!user.bybit_api_key || !user.bybit_api_secret) {
      bot.sendMessage(chatId, '❌ Please set your API keys first with /setkeys');
      return;
    }

    const botMsg = await bot.sendMessage(chatId, '⏳ Loading your ads...');
    const ads = await fetchAds(user.bybit_api_url, user.bybit_api_key, user.bybit_api_secret);

    if (ads.length === 0) {
      bot.editMessageText('No advertisements found.', {
        chat_id: chatId,
        message_id: botMsg.message_id,
      });
      return;
    }

    // Fetch TOPPER-BTC price (static payload — same price for all pairs)
    const uniquePairs = [...new Set(ads.map((a) => `${a.tokenId}/${a.currencyId}`))];
    const topperPrice = await fetchTopperPrice();
    const topperPrices = {};
    if (topperPrice !== null) {
      for (const pair of uniquePairs) {
        topperPrices[pair] = topperPrice;
      }
    }

    // Build keyboard: TOPPER-BTC entries first (per pair), then user's ads
    const keyboard = [];

    for (const pair of uniquePairs) {
      if (topperPrices[pair] !== undefined) {
        keyboard.push([
          {
            text: `⭐ TOPPER-BTC | ${pair} | ${topperPrices[pair]}`,
            callback_data: `topperinfo:${pair}`,
          },
        ]);
      }
    }

    ads.slice(0, 20).forEach((ad) => {
      keyboard.push([
        {
          text: `${ad.tokenId}/${ad.currencyId} | ${ad.price}`,
          callback_data: `adinfo:${ad.id}`,
        },
      ]);
    });

    bot.editMessageText('📋 *Your Advertisements + TOPPER-BTC* (tap for details):', {
      chat_id: chatId,
      message_id: botMsg.message_id,
      parse_mode: 'Markdown',
      reply_markup: { inline_keyboard: keyboard },
    });

    userState[userId] = userState[userId] || {};
    userState[userId].adsCache = ads;
    userState[userId].topperPrices = topperPrices;
  } catch (e) {
    bot.sendMessage(chatId, '❌ Failed to load ads: ' + e.message);
  }
}

bot.onText(/\/myads/, handleMyAds);

/*
|--------------------------------------------------------------------------
| /track — start tracking flow
|--------------------------------------------------------------------------
*/
async function handleTrack(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const user = await ensureUser(userId);
    if (!user.bybit_api_key || !user.bybit_api_secret) {
      bot.sendMessage(chatId, '❌ Please set your API keys first with /setkeys');
      return;
    }

    const botMsg = await bot.sendMessage(chatId, '⏳ Loading your ads for tracking...');
    const ads = await fetchAds(user.bybit_api_url, user.bybit_api_key, user.bybit_api_secret);

    if (ads.length === 0) {
      bot.editMessageText('No advertisements found.', {
        chat_id: chatId,
        message_id: botMsg.message_id,
      });
      return;
    }

    const keyboard = ads.slice(0, 20).map((ad) => [
      {
        text: `${ad.tokenId}/${ad.currencyId} | ${ad.price}`,
        callback_data: `trackad:${ad.id}`,
      },
    ]);

    bot.editMessageText('📋 *Select an ad to track:*', {
      chat_id: chatId,
      message_id: botMsg.message_id,
      parse_mode: 'Markdown',
      reply_markup: { inline_keyboard: keyboard },
    });

    userState[userId] = userState[userId] || {};
    userState[userId].adsCache = ads;
  } catch (e) {
    bot.sendMessage(chatId, '❌ Failed to load ads: ' + e.message);
  }
}

bot.onText(/\/track/, handleTrack);

/*
|--------------------------------------------------------------------------
| /copier — auto-copy the #1 market merchant (upward only)
|--------------------------------------------------------------------------
*/
async function handleCopier(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const user = await ensureUser(userId);
    if (!user.bybit_api_key || !user.bybit_api_secret) {
      bot.sendMessage(chatId, '❌ Please set your API keys first with /setkeys');
      return;
    }

    const botMsg = await bot.sendMessage(chatId, '⏳ Loading your ads for Copier mode...');
    const ads = await fetchAds(user.bybit_api_url, user.bybit_api_key, user.bybit_api_secret);

    if (ads.length === 0) {
      bot.editMessageText('No advertisements found.', {
        chat_id: chatId,
        message_id: botMsg.message_id,
      });
      return;
    }

    const keyboard = ads.slice(0, 20).map((ad) => [
      {
        text: `${ad.tokenId}/${ad.currencyId} | ${ad.price}`,
        callback_data: `copierad:${ad.id}`,
      },
    ]);

    bot.editMessageText(
      '🤖 *Copier Mode*\n\nSelect an ad. The bot will auto-track the #1 market merchant and only update your price when the market goes UP (never down).',
      {
        chat_id: chatId,
        message_id: botMsg.message_id,
        parse_mode: 'Markdown',
        reply_markup: { inline_keyboard: keyboard },
      }
    );

    userState[userId] = userState[userId] || {};
    userState[userId].adsCache = ads;
  } catch (e) {
    bot.sendMessage(chatId, '❌ Failed to load ads: ' + e.message);
  }
}

bot.onText(/\/copier/, handleCopier);

/*
|--------------------------------------------------------------------------
| /stop — stop active tracking
|--------------------------------------------------------------------------
*/
async function handleStop(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const { data: sessions } = await supabase
      .from('tracking_sessions')
      .select('*')
      .eq('telegram_id', userId)
      .eq('tracking', true);

    if (!sessions || sessions.length === 0) {
      bot.sendMessage(chatId, 'ℹ️ No active tracking sessions.', { reply_markup: mainMenuKeyboard });
      return;
    }

    for (const s of sessions) {
      await updateSession(s.id, { tracking: false });
    }

    bot.sendMessage(chatId, `🛑 Stopped ${sessions.length} tracking session(s).`, {
      reply_markup: mainMenuKeyboard,
    });
  } catch (e) {
    bot.sendMessage(chatId, '❌ Error: ' + e.message, { reply_markup: mainMenuKeyboard });
  }
}

bot.onText(/\/stop/, handleStop);

/*
|--------------------------------------------------------------------------
| /status — show current tracking status
|--------------------------------------------------------------------------
*/
async function handleStatus(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const { data: sessions } = await supabase
      .from('tracking_sessions')
      .select('*')
      .eq('telegram_id', userId)
      .order('updated_at', { ascending: false })
      .limit(5);

    if (!sessions || sessions.length === 0) {
      bot.sendMessage(chatId, 'ℹ️ No tracking sessions found. Tap "Track" to start.', {
        reply_markup: mainMenuKeyboard,
      });
      return;
    }

    let text = '*📊 Tracking Status:*\n\n';
    for (const s of sessions) {
      const status = s.tracking ? '🟢 Active' : '🔴 Stopped';
      const mode = s.copier_mode ? ' 🤖 Copier' : (s.plus_mode ? ' (Plus)' : '');
      text += `${status}${mode}\n`;
      text += `Ad: ${s.token_id}/${s.currency_id}\n`;
      text += `Ad Price: ${s.current_ad_price || '---'}\n`;
      text += `Merchant: ${s.selected_merchant_name || '---'}\n`;
      text += `Merchant Price: ${s.last_merchant_price || '---'}\n`;
      if (s.plus_mode && s.tracking) {
        const lastUpdate = s.last_update_time ? new Date(s.last_update_time).getTime() : 0;
        const elapsed = Date.now() - lastUpdate;
        const remaining = Math.max(0, s.plus_timer_ms - elapsed);
        const mins = Math.floor(remaining / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        text += `Timer: ${mins}m ${secs}s remaining\n`;
      }
      text += '\n';
    }

    bot.sendMessage(chatId, text, { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard });
  } catch (e) {
    bot.sendMessage(chatId, '❌ Error: ' + e.message, { reply_markup: mainMenuKeyboard });
  }
}

bot.onText(/\/status/, handleStatus);

/*
|--------------------------------------------------------------------------
| /plus — toggle plus mode for a specific active session
|--------------------------------------------------------------------------
*/
async function handlePlus(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const { data: sessions } = await supabase
      .from('tracking_sessions')
      .select('*')
      .eq('telegram_id', userId)
      .order('updated_at', { ascending: false })
      .limit(10);

    const activeSessions = (sessions || []).filter((s) => s.tracking);

    if (activeSessions.length === 0) {
      bot.sendMessage(chatId, 'ℹ️ No active tracking sessions. Start tracking first with /track.', {
        reply_markup: mainMenuKeyboard,
      });
      return;
    }

    // If only one active session, toggle it directly
    if (activeSessions.length === 1) {
      const session = activeSessions[0];
      const newPlus = !session.plus_mode;
      await updateSession(session.id, {
        plus_mode: newPlus,
        last_update_time: new Date().toISOString(),
      });

      bot.sendMessage(
        chatId,
        newPlus
          ? `🚀 *Plus mode enabled* for ${session.token_id}/${session.currency_id}\nAd will re-edit on timer (${session.plus_timer_ms / 60000} min).`
          : `ℹ️ *Plus mode disabled* for ${session.token_id}/${session.currency_id}. Using standard ratchet tracking.`,
        { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
      );
      return;
    }

    // Multiple sessions — let user pick which one
    const keyboard = activeSessions.map((s) => [
      {
        text: `${s.plus_mode ? '🚀' : '⚪'} ${s.token_id}/${s.currency_id} | ${s.selected_merchant_name}`,
        callback_data: `plussession:${s.id}`,
      },
    ]);

    bot.sendMessage(chatId, '🚀 *Select a tracking session to toggle Plus mode:*', {
      parse_mode: 'Markdown',
      reply_markup: { inline_keyboard: keyboard },
    });
  } catch (e) {
    bot.sendMessage(chatId, '❌ Error: ' + e.message, { reply_markup: mainMenuKeyboard });
  }
}

bot.onText(/\/plus/, handlePlus);

/*
|--------------------------------------------------------------------------
| /timer — set re-edit timer for a specific active session
|--------------------------------------------------------------------------
*/
async function handleTimer(msg) {
  const chatId = msg.chat.id;
  const userId = msg.from.id;

  try {
    const { data: sessions } = await supabase
      .from('tracking_sessions')
      .select('*')
      .eq('telegram_id', userId)
      .eq('tracking', true)
      .order('updated_at', { ascending: false })
      .limit(10);

    if (!sessions || sessions.length === 0) {
      bot.sendMessage(chatId, 'ℹ️ No active tracking sessions. Start tracking first with /track.', {
        reply_markup: mainMenuKeyboard,
      });
      return;
    }

    // If only one active session, show timer options directly
    if (sessions.length === 1) {
      const session = sessions[0];
      const keyboard = TIMER_OPTIONS.map((t) => [
        { text: t.label, callback_data: `settimer:${session.id}:${t.ms}` },
      ]);

      bot.sendMessage(
        chatId,
        `⏱️ *Select re-edit timer* for ${session.token_id}/${session.currency_id}:`,
        {
          parse_mode: 'Markdown',
          reply_markup: { inline_keyboard: keyboard },
        }
      );
      return;
    }

    // Multiple sessions — let user pick which one to set timer for
    const keyboard = sessions.map((s) => [
      {
        text: `⏱️ ${s.token_id}/${s.currency_id} | ${s.selected_merchant_name} (current: ${s.plus_timer_ms / 60000} min)`,
        callback_data: `picksession_timer:${s.id}`,
      },
    ]);

    bot.sendMessage(chatId, '⏱️ *Select a tracking session to set its timer:*', {
      parse_mode: 'Markdown',
      reply_markup: { inline_keyboard: keyboard },
    });
  } catch (e) {
    bot.sendMessage(chatId, '❌ Error: ' + e.message, { reply_markup: mainMenuKeyboard });
  }
}

bot.onText(/\/timer/, handleTimer);

/*
|--------------------------------------------------------------------------
| Callback query handler (inline button presses)
|--------------------------------------------------------------------------
*/
bot.on('callback_query', async (callbackQuery) => {
  const msg = callbackQuery.message;
  const chatId = msg.chat.id;
  const userId = callbackQuery.from.id;
  const data = callbackQuery.data;

  // Answer the callback immediately so Telegram's 5-second timeout doesn't fire.
  // Use a helper that swallows "query is too old" errors.
  const answer = (text) => {
    bot.answerCallbackQuery(callbackQuery.id, text ? { text } : undefined).catch(() => {});
  };

  try {
    // --- Back to menu ---
    if (data === 'backtomenu') {
      answer();
      return;
    }

    // --- TOPPER-BTC info ---
    if (data.startsWith('topperinfo:')) {
      const pair = data.split(':')[1];
      const state = userState[userId];
      const topperPrice = state?.topperPrices?.[pair];

      answer();
      bot.sendMessage(
        chatId,
        `⭐ *TOPPER-BTC*\n\nPair: ${pair}\nCurrent Limit Price: ${topperPrice || '---'}\n\nUse /track and select TOPPER-BTC to track this limit.`,
        { parse_mode: 'Markdown' }
      );
      return;
    }

    // --- Ad info view ---
    if (data.startsWith('adinfo:')) {
      const adId = data.split(':')[1];
      const state = userState[userId];
      const ad = state?.adsCache?.find((a) => String(a.id) === String(adId));

      if (!ad) {
        answer('Ad data expired. Use /myads again.');
        return;
      }

      const pair = `${ad.tokenId}/${ad.currencyId}`;
      const topperPrice = state?.topperPrices?.[pair];
      const text =
        `📋 *Ad Details*\n\n` +
        `Pair: ${pair}\n` +
        `Price: ${ad.price}\n` +
        `Min: ${ad.minAmount}\n` +
        `Max: ${ad.maxAmount}\n` +
        `Status: ${ad.status || '---'}\n` +
        (topperPrice !== undefined ? `⭐ TOPPER-BTC Limit: ${topperPrice}\n` : '') +
        `\nUse /track to start tracking this ad.`;

      answer();
      bot.sendMessage(chatId, text, { parse_mode: 'Markdown' });
      return;
    }

    // --- Track ad: selected an ad, now show merchants (including TOPPER-BTC) ---
    if (data.startsWith('trackad:')) {
      const adId = data.split(':')[1];
      const state = userState[userId];
      const ad = state?.adsCache?.find((a) => String(a.id) === String(adId));

      if (!ad) {
        answer('Ad data expired. Try again.');
        return;
      }

      answer('Loading competitors...');

      const user = await ensureUser(userId);
      const botMsg = await bot.sendMessage(chatId, '⏳ Fetching competitors...');

      let allCompetitors = [];
      try {
        const { status, competitors } = await fetchCompetitors(
          user.bybit_api_url,
          user.bybit_api_key,
          user.bybit_api_secret,
          ad.tokenId,
          ad.currencyId
        );
        allCompetitors = [...competitors];
      } catch (e) {
        console.error('fetchCompetitors error:', e.message);
      }

      // Fetch topper price
      const topperPrice = await fetchTopperPrice();

      if (topperPrice !== null) {
        allCompetitors.unshift({
          id: 'TOPPER-BTC',
          nickName: 'TOPPER-BTC',
          price: topperPrice,
        });
      }

      if (allCompetitors.length === 0) {
        bot.editMessageText('No competitors found for this pair. The API may be temporarily unavailable — try again in a moment.', {
          chat_id: chatId,
          message_id: botMsg.message_id,
        });
        return;
      }

      // BTC/NGN: auto-select the top market competitor (skip merchant selection)
      if (ad.tokenId === 'BTC' && ad.currencyId === 'NGN') {
        // Use the first real competitor (not TOPPER-BTC)
        const topMerchant = allCompetitors.find((m) => m.id !== 'TOPPER-BTC') || allCompetitors[0];
        const referencePrice = parseFloat(topMerchant.price);

        // Delete any existing active sessions for this user+ad
        await supabase
          .from('tracking_sessions')
          .delete()
          .eq('telegram_id', userId)
          .eq('tracking', true)
          .eq('ad_id', String(ad.id));

        const { data: session, error } = await supabase
          .from('tracking_sessions')
          .insert({
            telegram_id: userId,
            ad_id: String(ad.id),
            token_id: ad.tokenId,
            currency_id: ad.currencyId,
            current_ad_price: ad.price,
            min_amount: ad.minAmount,
            max_amount: ad.maxAmount,
            ad_status: ad.status,
            price_type: ad.priceType !== undefined ? ad.priceType : 0,
            premium: ad.premium !== undefined ? String(ad.premium) : '0',
            last_quantity: ad.lastQuantity ? String(ad.lastQuantity) : '1',
            payment_period: ad.paymentPeriod || 30,
            payment_terms: ad.paymentTerms || null,
            trading_preference_set: ad.tradingPreferenceSet || null,
            selected_merchant_id: String(topMerchant.id),
            selected_merchant_name: topMerchant.nickName,
            reference_price: referencePrice,
            last_merchant_price: referencePrice,
            tracking: true,
            copier_mode: true,
            plus_mode: false,
            plus_timer_ms: 180000,
            last_update_time: new Date().toISOString(),
            ad_payload_cache: ad,
          })
          .select()
          .maybeSingle();

        if (error) throw error;

        // Immediately update ad to the top competitor's price
        const result = await updateAdPrice(
          user.bybit_api_url,
          user.bybit_api_key,
          user.bybit_api_secret,
          ad,
          referencePrice
        );

        if (result.success) {
          await updateSession(session.id, {
            current_ad_price: referencePrice,
            last_update_time: new Date().toISOString(),
          });
        }

        bot.editMessageText(
          `🟢 *Tracking started!*\n\n` +
            `Ad: ${ad.tokenId}/${ad.currencyId}\n` +
            `Auto-selected #1 merchant: ${topMerchant.nickName}\n` +
            `Price: ${referencePrice}\n` +
            `Mode: Copier (follows #1 market merchant, updates only when UP)\n\n` +
            `Use /stop to stop. Use /status to check.`,
          {
            chat_id: chatId,
            message_id: botMsg.message_id,
            parse_mode: 'Markdown',
            reply_markup: { inline_keyboard: [[
              { text: '📋 Back to Menu', callback_data: 'backtomenu' },
            ]] },
          }
        );

        // Also send the main menu keyboard
        bot.sendMessage(chatId, 'Main menu:', { reply_markup: mainMenuKeyboard });

        delete userState[userId];
        return;
      }

      // Other pairs: show merchant selection as before
      state.selectedAd = ad;
      state.competitorsCache = allCompetitors;

      const keyboard = allCompetitors.map((m, i) => {
        const prefix = m.id === 'TOPPER-BTC' ? '⭐' : `#${i}`;
        return [
          {
            text: `${prefix} | ${m.nickName} | ${m.price}`,
            callback_data: `trackmerchant:${i}`,
          },
        ];
      });

      bot.editMessageText('👤 *Select a merchant to track:*', {
        chat_id: chatId,
        message_id: botMsg.message_id,
        parse_mode: 'Markdown',
        reply_markup: { inline_keyboard: keyboard },
      });
      return;
    }

    // --- Track merchant: start tracking ---
    if (data.startsWith('trackmerchant:')) {
      const idx = parseInt(data.split(':')[1]);
      const state = userState[userId];
      const merchant = state?.competitorsCache?.[idx];

      if (!merchant) {
        answer('Merchant data expired.');
        return;
      }

      answer();

      const ad = state.selectedAd;
      const user = await ensureUser(userId);
      const referencePrice = parseFloat(merchant.price);

      // Delete any existing active sessions for this user+ad
      await supabase
        .from('tracking_sessions')
        .delete()
        .eq('telegram_id', userId)
        .eq('tracking', true)
        .eq('ad_id', String(ad.id));

      // Create new tracking session (per-ad plus_mode and timer)
      const { data: session, error } = await supabase
        .from('tracking_sessions')
        .insert({
          telegram_id: userId,
          ad_id: String(ad.id),
          token_id: ad.tokenId,
          currency_id: ad.currencyId,
          current_ad_price: ad.price,
          min_amount: ad.minAmount,
          max_amount: ad.maxAmount,
          ad_status: ad.status,
          price_type: ad.priceType !== undefined ? ad.priceType : 0,
          premium: ad.premium !== undefined ? String(ad.premium) : '0',
          last_quantity: ad.lastQuantity ? String(ad.lastQuantity) : '1',
          payment_period: ad.paymentPeriod || 30,
          payment_terms: ad.paymentTerms || null,
          trading_preference_set: ad.tradingPreferenceSet || null,
          selected_merchant_id: String(merchant.id),
          selected_merchant_name: merchant.nickName,
          reference_price: referencePrice,
          last_merchant_price: referencePrice,
          tracking: true,
          plus_mode: false,
          plus_timer_ms: 180000,
          last_update_time: new Date().toISOString(),
          ad_payload_cache: ad,
        })
        .select()
        .maybeSingle();

      if (error) throw error;

      // Immediately update ad to merchant's price
      const result = await updateAdPrice(
        user.bybit_api_url,
        user.bybit_api_key,
        user.bybit_api_secret,
        ad,
        referencePrice
      );

      if (result.success) {
        await updateSession(session.id, {
          current_ad_price: referencePrice,
          last_update_time: new Date().toISOString(),
        });
      }

      bot.sendMessage(
        chatId,
        `🟢 *Tracking started!*\n\n` +
          `Ad: ${ad.tokenId}/${ad.currencyId}\n` +
          `Merchant: ${merchant.nickName}\n` +
          `Price: ${referencePrice}\n` +
          `Mode: Ratchet (default)\n` +
          `Timer: 3.0 min\n\n` +
          `Use /plus to enable Plus mode for this ad.\n` +
          `Use /timer to change the re-edit timer for this ad.\n` +
          `Use /stop to stop tracking. Use /status to check.`,
        { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
      );

      delete userState[userId];
      return;
    }

    // --- Copier mode: select an ad, auto-track #1 market merchant ---
    if (data.startsWith('copierad:')) {
      const adId = data.split(':')[1];
      const state = userState[userId];
      const ad = state?.adsCache?.find((a) => String(a.id) === String(adId));

      if (!ad) {
        answer('Ad data expired. Try again.');
        return;
      }

      answer('Starting Copier...');

      const user = await ensureUser(userId);

      // Fetch current competitors to get the #1 merchant right now
      let topMerchant = null;
      try {
        const { status, competitors } = await fetchCompetitors(
          user.bybit_api_url,
          user.bybit_api_key,
          user.bybit_api_secret,
          ad.tokenId,
          ad.currencyId
        );
        if (status && competitors.length > 0) {
          topMerchant = competitors[0];
        }
      } catch (e) {
        console.error('Copier fetchCompetitors error:', e.message);
      }

      const referencePrice = topMerchant ? parseFloat(topMerchant.price) : parseFloat(ad.price);

      // Delete any existing active sessions for this user+ad
      await supabase
        .from('tracking_sessions')
        .delete()
        .eq('telegram_id', userId)
        .eq('tracking', true)
        .eq('ad_id', String(ad.id));

      // Create new copier tracking session
      const { data: session, error } = await supabase
        .from('tracking_sessions')
        .insert({
          telegram_id: userId,
          ad_id: String(ad.id),
          token_id: ad.tokenId,
          currency_id: ad.currencyId,
          current_ad_price: ad.price,
          min_amount: ad.minAmount,
          max_amount: ad.maxAmount,
          ad_status: ad.status,
          price_type: ad.priceType !== undefined ? ad.priceType : 0,
          premium: ad.premium !== undefined ? String(ad.premium) : '0',
          last_quantity: ad.lastQuantity ? String(ad.lastQuantity) : '1',
          payment_period: ad.paymentPeriod || 30,
          payment_terms: ad.paymentTerms || null,
          trading_preference_set: ad.tradingPreferenceSet || null,
          selected_merchant_id: topMerchant ? String(topMerchant.id) : 'AUTO',
          selected_merchant_name: topMerchant ? topMerchant.nickName : 'Auto-Detecting #1',
          reference_price: referencePrice,
          last_merchant_price: referencePrice,
          tracking: true,
          copier_mode: true,
          plus_mode: false,
          plus_timer_ms: 180000,
          last_update_time: new Date().toISOString(),
          ad_payload_cache: ad,
        })
        .select()
        .maybeSingle();

      if (error) throw error;

      // Immediately update ad to the #1 merchant's price
      if (topMerchant) {
        const result = await updateAdPrice(
          user.bybit_api_url,
          user.bybit_api_key,
          user.bybit_api_secret,
          ad,
          referencePrice
        );

        if (result.success) {
          await updateSession(session.id, {
            current_ad_price: referencePrice,
            last_update_time: new Date().toISOString(),
          });
        }
      }

      bot.sendMessage(
        chatId,
        `🤖 *Copier Mode Active!*\n\n` +
          `Ad: ${ad.tokenId}/${ad.currencyId}\n` +
          `Target: #1 Market Merchant (auto-detected)\n` +
          `Current #1: ${topMerchant ? topMerchant.nickName : 'Detecting...'}\n` +
          `Price: ${referencePrice}\n` +
          `Strategy: Only updates when market goes UP\n\n` +
          `Use /stop to stop. Use /status to check.`,
        { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
      );

      delete userState[userId];
      return;
    }

    // --- Plus mode: toggle for a specific session ---
    if (data.startsWith('plussession:')) {
      const sessionId = data.split(':')[1];
      answer();
      const session = await getSession(sessionId);
      if (!session) {
        bot.sendMessage(chatId, '❌ Session not found.', { reply_markup: mainMenuKeyboard });
        return;
      }

      const newPlus = !session.plus_mode;
      await updateSession(session.id, {
        plus_mode: newPlus,
        last_update_time: new Date().toISOString(),
      });

      bot.sendMessage(
        chatId,
        newPlus
          ? `🚀 *Plus mode enabled* for ${session.token_id}/${session.currency_id}\nTimer: ${session.plus_timer_ms / 60000} min\nUse /timer to change it.`
          : `ℹ️ *Plus mode disabled* for ${session.token_id}/${session.currency_id}. Standard ratchet tracking.`,
        { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
      );
      return;
    }

    // --- Timer: pick a session first (when multiple active) ---
    if (data.startsWith('picksession_timer:')) {
      const sessionId = data.split(':')[1];
      answer();
      const session = await getSession(sessionId);
      if (!session) {
        bot.sendMessage(chatId, '❌ Session not found.', { reply_markup: mainMenuKeyboard });
        return;
      }

      const keyboard = TIMER_OPTIONS.map((t) => [
        { text: t.label, callback_data: `settimer:${sessionId}:${t.ms}` },
      ]);

      bot.sendMessage(
        chatId,
        `⏱️ *Select timer* for ${session.token_id}/${session.currency_id}:`,
        {
          parse_mode: 'Markdown',
          reply_markup: { inline_keyboard: keyboard },
        }
      );
      return;
    }

    // --- Set timer for a specific session ---
    if (data.startsWith('settimer:')) {
      const parts = data.split(':');
      const sessionId = parts[1];
      const ms = parseInt(parts[2]);
      answer();
      const session = await getSession(sessionId);
      if (!session) {
        bot.sendMessage(chatId, '❌ Session not found.', { reply_markup: mainMenuKeyboard });
        return;
      }

      await updateSession(session.id, { plus_timer_ms: ms });

      bot.sendMessage(
        chatId,
        `✅ Re-edit timer set to *${ms / 60000} minutes* for ${session.token_id}/${session.currency_id}.`,
        { parse_mode: 'Markdown', reply_markup: mainMenuKeyboard }
      );
      return;
    }
  } catch (e) {
    answer();
    console.error('Callback error:', e.message);
    try {
      bot.sendMessage(chatId, '❌ Error: ' + e.message, { reply_markup: mainMenuKeyboard });
    } catch (_) {}
  }
});

// Start the tracking loop
startTrackingLoop(bot);

module.exports = { bot };
