<?php
// ============================================================
// DriveSafe Cover — Admin Settings API
// GET  /api/admin/settings     — get current settings
// POST /api/admin/settings     — update settings
// PUT  /api/admin/settings     — update settings (legacy/API clients)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$admin = require_admin();
$db    = Database::get();
$method = $_SERVER['REQUEST_METHOD'];

function ensure_settings_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT UNSIGNED NULL,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

ensure_settings_table($db);

// ── GET — Current settings ─────────────────────────────────
if ($method === 'GET') {
    // Fetch from settings table, or return defaults
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Merge with defaults if table doesn't exist yet
    $defaults = [
        'app_name'         => APP_NAME,
        'app_url'          => APP_URL,
        'support_email'    => MAIL_SUPPORT,
        'mail_host'        => MAIL_HOST,
        'mail_port'        => (string) MAIL_PORT,
        'mail_encryption'  => MAIL_ENCRYPTION,
        'mail_username'    => MAIL_USERNAME,
        'mail_from_name'   => MAIL_FROM_NAME,
        'mail_from_email'  => MAIL_FROM_EMAIL,
        'stripe_mode'          => get_stripe_mode(),
        'stripe_pub_key'       => mask_secret(get_stripe_config()['publishable_key']),
        'stripe_key_source'    => 'Environment variables',
        'max_file_size_mb'     => (string) (MAX_FILE_SIZE / 1024 / 1024),
        'otp_expiry_min'       => (string) OTP_EXPIRY_MINUTES,
        'plan_price_essential' => (string) COVERAGE_PLANS['essential']['price_per_day'],
        'plan_price_premium'   => (string) COVERAGE_PLANS['premium']['price_per_day'],
        'plan_price_ultimate'  => (string) COVERAGE_PLANS['ultimate']['price_per_day'],
    ];

    $merged = array_merge($defaults, $settings);
    
    // Ensure we return the current mode-specific keys and also the raw settings if they exist
    $merged['stripe_mode'] = get_stripe_mode();
    $config = get_stripe_config();
    $merged['stripe_pub_key'] = mask_secret($config['publishable_key']);
    
    // Add specific keys to merged data so they can be edited in UI
    $merged['stripe_test_pub_key'] = get_setting('stripe_test_pub_key', STRIPE_TEST_PUBLISHABLE_KEY);
    $merged['stripe_test_sec_key'] = mask_secret(get_setting('stripe_test_sec_key', STRIPE_TEST_SECRET_KEY));
    $merged['stripe_live_pub_key'] = get_setting('stripe_live_pub_key', STRIPE_LIVE_PUBLISHABLE_KEY);
    $merged['stripe_live_sec_key'] = mask_secret(get_setting('stripe_live_sec_key', STRIPE_LIVE_SECRET_KEY));

    json_success($merged);
}

// ── POST/PUT — Update settings ──────────────────────────────
// Some shared hosts reject PUT before PHP receives the request, so the admin
// UI saves via POST while PUT remains supported for direct API clients.
if ($method === 'POST' || $method === 'PUT') {
    $payload = require_admin(); // Allow any admin to change settings for now
    $data = get_body();

    if (empty($data)) json_error('No settings provided.', 400);

    $allowed = [
        'app_name', 'app_url', 'support_email',
        'mail_host', 'mail_port', 'mail_encryption',
        'mail_username', 'mail_password', 'mail_from_name', 'mail_from_email',
        'stripe_mode', 'stripe_test_pub_key', 'stripe_test_sec_key', 'stripe_live_pub_key', 'stripe_live_sec_key',
        'max_file_size_mb', 'otp_expiry_min',
        'plan_price_essential', 'plan_price_premium', 'plan_price_ultimate',
    ];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at, updated_by)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW(), updated_by = VALUES(updated_by)
        ");

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                if (strpos($key, 'plan_price_') === 0 && (!is_numeric($value) || (float) $value < 0)) {
                    json_error('Plan prices must be valid positive numbers.', 422);
                }
                if ($key === 'stripe_mode' && !in_array($value, ['test', 'live'], true)) {
                    json_error('Stripe mode must be test or live.', 422);
                }
                $stmt->execute([$key, trim((string) $value), $admin['sub']]);
            }
        }

        $db->commit();
        json_success(null, 'Settings updated successfully.');
    } catch (Exception $e) {
        $db->rollBack();
        json_error('Failed to save settings: ' . $e->getMessage(), 500);
    }
}

json_error('Method not allowed.', 405);
