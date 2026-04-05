<?php
// ============================================================
// POST /api/auth/register
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

require_method('POST');

$body   = get_body();
$errors = validate_required($body, ['full_name', 'email', 'password', 'phone']);
if ($errors) json_error('Validation failed.', 422, $errors);

$name     = sanitize($body['full_name']);
$email    = strtolower(trim($body['email']));
$phone    = sanitize($body['phone']);
$state    = sanitize($body['state'] ?? '');
$password = $body['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address.', 422);
if (strlen($password) < 8) json_error('Password must be at least 8 characters.', 422);

$db = Database::get();

// Check duplicate email
$stmt = $db->prepare('SELECT id FROM customers WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) json_error('An account with this email already exists.', 409);

// Insert customer
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare(
    'INSERT INTO customers (full_name, email, phone, state, password_hash, status, created_at)
     VALUES (?, ?, ?, ?, ?, "active", NOW())'
);
$stmt->execute([$name, $email, $phone, $state, $hash]);
$customer_id = (int) $db->lastInsertId();

// Issue JWT
$token = JWT::encode([
    'sub'   => $customer_id,
    'email' => $email,
    'name'  => $name,
    'role'  => 'customer',
]);

json_success([
    'token'    => $token,
    'customer' => [
        'id'        => $customer_id,
        'full_name' => $name,
        'email'     => $email,
        'phone'     => $phone,
        'state'     => $state,
    ],
], 'Account created successfully.', 201);
