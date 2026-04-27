<?php
// ============================================================
// POST /api/auth/send-otp
// Generates and emails a 6-digit OTP passcode
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/mailer.php';

require_method('POST');

$body = get_body();
$errors = validate_required($body, ['email']);
if ($errors) json_error('Validation failed.', 422, $errors);

$email = strtolower(trim($body['email']));
$db = Database::get();

// Find user
$stmt = $db->prepare('SELECT id, full_name FROM customers WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$customer = $stmt->fetch();

// Security: ALWAYS return success even if email not found to prevent user enumeration
if (!$customer) {
    json_success(null, 'If the email exists, an OTP has been sent.');
}

try {
    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiryMinutes = max(1, (int) get_setting('otp_expiry_min', OTP_EXPIRY_MINUTES));
    $expires = date('Y-m-d H:i:s', strtotime('+' . $expiryMinutes . ' minutes'));

    // Save to DB
    $stmt = $db->prepare('UPDATE customers SET otp_code = ?, otp_expires_at = ? WHERE id = ?');
    $stmt->execute([$otp, $expires, $customer['id']]);

    if (!Mailer::sendLoginCode($email, $otp)) {
        error_log('Customer OTP email send failed for customer_id=' . $customer['id']);
    }

    json_success(null, 'If the email exists, an OTP has been sent.');
} catch (Exception $e) {
    error_log("OTP Send Error: " . $e->getMessage());
    json_error('Failed to generate OTP. Please try again later.', 500);
}
