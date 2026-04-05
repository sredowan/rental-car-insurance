<?php
// ============================================================
// Rental Shield — App Configuration
// ============================================================
// All secrets are loaded from environment variables.
// Copy .env.example → .env and fill in your values on the server.
// ============================================================

// ─── Composer Autoloader ──────────────────────────────────
require_once __DIR__ . '/../../vendor/autoload.php';

// ─── Load .env if Dotenv is available ─────────────────────
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        // Strip surrounding quotes if present
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// ─── Stripe Payment Gateway ──────────────────────────────
define('STRIPE_SECRET_KEY',      getenv('STRIPE_SECRET_KEY')      ?: '');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: '');
define('STRIPE_WEBHOOK_SECRET',  getenv('STRIPE_WEBHOOK_SECRET')  ?: '');
define('STRIPE_CURRENCY',        'aud');

// ─── Facebook Meta CAPI & Tracking ────────────────────────
define('FB_PIXEL_ID',   getenv('FB_PIXEL_ID')   ?: '');
define('FB_CAPI_TOKEN', getenv('FB_CAPI_TOKEN') ?: '');

define('APP_NAME',    'Rental Shield');
define('APP_ENV',     getenv('APP_ENV') ?: 'development');
define('APP_URL',     getenv('APP_URL') ?: 'https://www.rentalshield.com.au');

// ─── JWT ───────────────────────────────────────────────────
define('JWT_SECRET',  getenv('JWT_SECRET') ?: 'CHANGE_THIS_ON_PRODUCTION');
define('JWT_EXPIRY',  86400 * 7);   // 7 days in seconds
define('JWT_ADMIN_EXPIRY', 3600 * 8); // 8 hours for admin sessions

// ─── Database ──────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_PORT',    (int)(getenv('DB_PORT') ?: 3306));
define('DB_CHARSET', 'utf8mb4');

// ─── Email (SMTP via Hostinger) ────────────────────────
define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.hostinger.com');
define('MAIL_PORT',       (int)(getenv('MAIL_PORT') ?: 465));
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'ssl');
define('MAIL_USERNAME',   getenv('MAIL_USERNAME')   ?: '');
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')   ?: '');
define('MAIL_FROM_NAME',  'Rental Shield');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'info@rentalshield.com.au');
define('MAIL_SUPPORT',    getenv('MAIL_SUPPORT')    ?: 'info@rentalshield.com.au');

// ─── File Uploads ──────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES', ['image/jpeg','image/png','image/gif','application/pdf']);
define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','gif','pdf']);

// ─── OTP Settings ─────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_LENGTH', 6);

// ─── Pricing Tiers ────────────────────────────────────────
define('COVERAGE_TIERS', [
    4000 => 9.96,
    5000 => 11.27,
    6000 => 14.42,
    7000 => 15.48,
    8000 => 16.79,
]);

// ─── Policy Number Prefix ─────────────────────────────────
define('POLICY_PREFIX', 'DSC-' . date('Y') . '-');
define('CLAIM_PREFIX',  'DSC-CLM-');

// ─── Pagination ───────────────────────────────────────────
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// ─── Error Reporting ──────────────────────────────────────
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

