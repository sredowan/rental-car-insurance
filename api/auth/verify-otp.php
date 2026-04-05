<?php
// ============================================================
// POST /api/auth/verify-otp
// Verifies 6-digit OTP passcode and returns JWT login
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

require_method('POST');

$body = get_body();
$errors = validate_required($body, ['email', 'otp_code']);
if ($errors) json_error('Validation failed.', 422, $errors);

$email = strtolower(trim($body['email']));
$otp   = trim($body['otp_code']);

$db = Database::get();

$stmt = $db->prepare(
    'SELECT id, full_name, email, phone, state, status, otp_code, otp_expires_at 
     FROM customers WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$customer = $stmt->fetch();

if (!$customer) {
    json_error('Invalid OTP code.', 401);
}

if ($customer['status'] !== 'active') {
    json_error('Your account has been suspended. Please contact support.', 403);
}

if ($customer['otp_code'] !== $otp) {
    json_error('Invalid OTP code.', 401);
}

// Check expiration
if (strtotime($customer['otp_expires_at']) < time()) {
    json_error('OTP code has expired. Please request a new one.', 401);
}

// Clear the OTP to prevent reuse
$db->prepare('UPDATE customers SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?')
   ->execute([$customer['id']]);

$token = JWT::encode([
    'sub'   => (int) $customer['id'],
    'email' => $customer['email'],
    'name'  => $customer['full_name'],
    'role'  => 'customer',
]);

json_success([
    'token'    => $token,
    'customer' => [
        'id'        => (int) $customer['id'],
        'full_name' => $customer['full_name'],
        'email'     => $customer['email'],
        'phone'     => $customer['phone'],
        'state'     => $customer['state'],
    ],
], 'Login successful.');
