<?php
// Set CORS headers for Flutter Web and all browser-based API consumers.
require_once __DIR__ . '/../shared/cors.php';

/**
 * Central database configuration.
 *
 * Reads credentials from the .env file located at the web-root
 * (www/wwwroot/digitallami.com/.env). Falls back to the values
 * supplied as fallback strings so the application still works if
 * .env is absent (e.g. during initial deployment).
 *
 * All PHP files should require_once this file instead of
 * declaring inline credentials.
 */

// ── .env loader ──────────────────────────────────────────────────────────────
$_dbEnvFile = __DIR__ . '/../.env';
if (file_exists($_dbEnvFile)) {
    $lines = file($_dbEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (strpos($_line, '=') === false) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_k = trim($_k);
        $_v = trim($_v, " \t\"'");  // strip surrounding quotes
        if ($_k !== '' && getenv($_k) === false) {
            putenv("$_k=$_v");
            $_ENV[$_k] = $_v;
        }
    }
    unset($lines, $_line, $_k, $_v);
}
unset($_dbEnvFile);

// ── Main "ms" database constants ─────────────────────────────────────────────
// Fallback credentials work for local dev (XAMPP/WAMP: root / no password).
// On PRODUCTION / SHARED HOSTING always supply real credentials via .env —
// the root fallback must never be used in production.
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'ms');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
// Use !== false so that an empty password (DB_PASS= in .env) is honoured instead
// of being overridden by the fallback (the ?: operator treats '' as falsy).
$_dbPass = getenv('DB_PASS');
if (!defined('DB_PASS')) define('DB_PASS', $_dbPass !== false ? $_dbPass : '');
unset($_dbPass);

// ── Public URL constants (for absolute media/profile links) ──────────────────
if (!defined('APP_PUBLIC_BASE_URL')) define('APP_PUBLIC_BASE_URL', rtrim(getenv('APP_PUBLIC_BASE_URL') ?: 'https://digitallami.com', '/'));
if (!defined('APP_API2_BASE_URL')) define('APP_API2_BASE_URL', APP_PUBLIC_BASE_URL . '/Api2/');

// ── "adminchat" database constants ───────────────────────────────────────────
// Falls back to the same credentials as the main DB if not set separately.
if (!defined('ADMINCHAT_DB_HOST')) define('ADMINCHAT_DB_HOST', getenv('ADMINCHAT_DB_HOST') ?: DB_HOST);
if (!defined('ADMINCHAT_DB_NAME')) define('ADMINCHAT_DB_NAME', getenv('ADMINCHAT_DB_NAME') ?: 'adminchat');
if (!defined('ADMINCHAT_DB_USER')) define('ADMINCHAT_DB_USER', getenv('ADMINCHAT_DB_USER') ?: DB_USER);
// Use !== false so that an empty password is honoured (see DB_PASS note above).
$_adminchatDbPass = getenv('ADMINCHAT_DB_PASS');
if (!defined('ADMINCHAT_DB_PASS')) define('ADMINCHAT_DB_PASS', $_adminchatDbPass !== false ? $_adminchatDbPass : DB_PASS);
unset($_adminchatDbPass);

// ── Agora constants (server-side token generation only) ──────────────────────
if (!defined('AGORA_APP_ID'))   define('AGORA_APP_ID',   getenv('AGORA_APP_ID')   ?: '');
if (!defined('AGORA_APP_CERT')) define('AGORA_APP_CERT', getenv('AGORA_APP_CERT') ?: '');
