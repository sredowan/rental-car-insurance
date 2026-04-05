<?php
// ============================================================
// POST /api/auth/login
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

require_method('POST');

$body   = get_body();
$errors = validate_required($body, ['email', 'password']);
if ($errors) json_error('Validation failed.', 422, $errors);

$email    = strtolower(trim($body['email']));
$password = $body['password'];

$db   = Database::get();
$stmt = $db->prepare(
    'SELECT id, full_name, email, phone, state, password_hash, status
     FROM customers WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$customer = $stmt->fetch();

if (!$customer || !password_verify($password, $customer['password_hash'])) {
    json_error('Invalid email or password.', 401);
}

if ($customer['status'] !== 'active') {
    json_error('Your account has been suspended. Please contact support.', 403);
}

// Rehash if needed (future-proof)
if (password_needs_rehash($customer['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('UPDATE customers SET password_hash = ? WHERE id = ?')
       ->execute([$new_hash, $customer['id']]);
}

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
