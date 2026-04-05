<?php
// ============================================================
// POST /api/auth/forgot-password
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

require_method('POST');

$body   = get_body();
$errors = validate_required($body, ['email']);
if ($errors) json_error('Validation failed.', 422, $errors);

$email = strtolower(trim($body['email']));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address.', 422);

$db   = Database::get();
$stmt = $db->prepare('SELECT id, full_name FROM customers WHERE email = ? AND status = "active" LIMIT 1');
$stmt->execute([$email]);
$customer = $stmt->fetch();

// Always return success to prevent email enumeration
if (!$customer) {
    json_success(null, 'If that email is registered, you will receive a reset link shortly.');
}

// Generate a reset token
$token  = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

$db->prepare(
    'INSERT INTO password_resets (customer_id, token, expires_at, created_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)'
)->execute([$customer['id'], hash('sha256', $token), $expiry]);

$reset_link = APP_URL . '/reset-password.html?token=' . $token;

// TODO: Send email with $reset_link
// mail($email, 'Reset your DriveSafe Cover password', "Click here: $reset_link");

json_success(null, 'If that email is registered, you will receive a reset link shortly.');
