const { execFileSync } = require('child_process');
const path = require('path');

/**
 * Runs once before the Playwright suite.
 *
 * The app rate-limits logins / registrations / OTP requests per IP in the
 * database (audit H4). Every e2e test comes from 127.0.0.1, so reset the
 * counters first; otherwise back-to-back runs would lock themselves out.
 */
module.exports = async () => {
  const script = path.join(__dirname, '..', 'support', 'reset_rate_limits.php');
  try {
    const out = execFileSync('php', [script], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    process.stdout.write(`[global-setup] ${out.trim()}\n`);
  } catch (err) {
    process.stdout.write(`[global-setup] rate-limit reset skipped: ${err.message.split('\n')[0]}\n`);
  }
};
