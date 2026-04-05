<?php
// ============================================================
// DriveSafe Cover — Admin Settings API
// GET  /api/admin/settings     — get current settings
// PUT  /api/admin/settings     — update settings
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$admin = require_admin();
$db    = Database::get();
$method = $_SERVER['REQUEST_METHOD'];

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
        'stripe_mode'      => 'test',
        'stripe_pub_key'   => substr(STRIPE_PUBLISHABLE_KEY, 0, 20) . '...',
        'max_file_size_mb' => (string) (MAX_FILE_SIZE / 1024 / 1024),
        'otp_expiry_min'   => (string) OTP_EXPIRY_MINUTES,
    ];

    json_success(array_merge($defaults, $settings));
}

// ── PUT — Update settings ──────────────────────────────────
if ($method === 'PUT') {
    $payload = require_super_admin(); // Only super admin can change settings
    $data = get_body();

    if (empty($data)) json_error('No settings provided.', 400);

    $allowed = [
        'app_name', 'app_url', 'support_email',
        'mail_host', 'mail_port', 'mail_encryption',
        'mail_username', 'mail_password', 'mail_from_name', 'mail_from_email',
        'stripe_mode', 'max_file_size_mb', 'otp_expiry_min',
    ];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at, updated_by)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW(), updated_by = VALUES(updated_by)
        ");

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $stmt->execute([$key, $value, $admin['sub']]);
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
