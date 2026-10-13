require('dotenv').config();

const http = require('http');
const { supabase } = require('./src/supabase');
const { getActiveSessions } = require('./src/tracker');

const PORT = process.env.PORT || 3000;
const token = process.env.TELEGRAM_BOT_TOKEN;

const server = http.createServer(async (req, res) => {
  if (req.url === '/' || req.url === '/health') {
    try {
      const sessions = await getActiveSessions();
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        status: 'ok',
        bot: 'Bybit P2P Competitor Tracker',
        botConnected: !!token,
        activeSessions: sessions.length,
        uptime: process.uptime(),
        timestamp: new Date().toISOString(),
      }));
    } catch (e) {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        status: 'ok',
        bot: 'Bybit P2P Competitor Tracker',
        botConnected: !!token,
        error: e.message,
        timestamp: new Date().toISOString(),
      }));
    }
    return;
  }

  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not Found');
});

server.listen(PORT, () => {
  console.log(`Server listening on port ${PORT}`);
});

if (token) {
  require('./src/bot');
  console.log('Bybit P2P Competitor Tracker Bot is running...');
} else {
  console.error('WARNING: TELEGRAM_BOT_TOKEN is not set. Server is running but bot is not started.');
  console.error('Add your token to .env and restart to enable the bot.');
}

process.on('SIGTERM', () => {
  console.log('SIGTERM received, shutting down...');
  server.close(() => process.exit(0));
});

process.on('SIGINT', () => {
  console.log('SIGINT received, shutting down...');
  server.close(() => process.exit(0));
});
