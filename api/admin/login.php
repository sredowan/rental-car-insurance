<?php
// ============================================================
// POST /api/admin/login   — Step 1: credentials check
// POST /api/admin/otp     — Step 2: OTP verify, returns JWT
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/mailer.php';

$method  = $_SERVER['REQUEST_METHOD'];
$segment = $_GET['action'] ?? 'login'; // 'login' or 'otp'

// ── Step 1: Credentials ──────────────────────────────────────
if ($method === 'POST' && $segment === 'login') {
    $body   = get_body();
    $errors = validate_required($body, ['email', 'password']);
    if ($errors) json_error('Validation failed.', 422, $errors);

    $email    = strtolower(trim($body['email']));
    $password = $body['password'];

    $db   = Database::get();
    $stmt = $db->prepare(
        'SELECT id, full_name, email, role, password_hash, status
         FROM admins WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        // Log failed attempt (don't let audit log failure block auth)
        try {
            $db->prepare(
                'INSERT INTO audit_log (admin_id, action, details, ip_address, created_at)
                 VALUES (NULL, "admin_login_failed", ?, ?, NOW())'
            )->execute([json_encode(['email' => $email]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) { /* table may not exist yet */ }

        json_error('Invalid email or password.', 401);
    }

    if ($admin['status'] !== 'active') {
        json_error('Your admin account has been suspended.', 403);
    }

    // Generate OTP
    $otp        = generate_otp();
    $expiryMinutes = max(1, (int) get_setting('otp_expiry_min', OTP_EXPIRY_MINUTES));
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+' . $expiryMinutes . ' minutes'));
    $otp_hash   = hash('sha256', $otp);

    $db->prepare(
        'UPDATE admins SET otp_code = ?, otp_expires_at = ?, otp_attempts = 0 WHERE id = ?'
    )->execute([$otp_hash, $otp_expiry, $admin['id']]);

    $sent = Mailer::sendLoginCode($admin['email'], $otp);
    if (!$sent && APP_ENV === 'production') {
        json_error('Could not send verification code. Please contact support.', 500);
    }

    // Return OTP in development only for local testing.
    $dev_otp = APP_ENV === 'development' ? $otp : null;

    json_success([
        'admin_id' => (int) $admin['id'],
        'otp_sent' => true,
        'dev_otp'  => $dev_otp, // null in production
    ], 'Verification code sent to your email.');
}

// ── Step 2: Verify OTP ───────────────────────────────────────
if ($method === 'POST' && $segment === 'otp') {
    $body   = get_body();
    $errors = validate_required($body, ['admin_id', 'otp']);
    if ($errors) json_error('Validation failed.', 422, $errors);

    $admin_id = (int) $body['admin_id'];
    $otp      = trim($body['otp']);
    $otp_hash = hash('sha256', $otp);

    $db   = Database::get();
    $stmt = $db->prepare(
        'SELECT id, full_name, email, role, otp_code, otp_expires_at, otp_attempts
         FROM admins WHERE id = ? AND status = "active" LIMIT 1'
    );
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if (!$admin) json_error('Invalid request.', 400);

    // Rate limit: max 5 OTP attempts
    if ((int) $admin['otp_attempts'] >= 5) {
        json_error('Too many failed attempts. Please sign in again.', 429);
    }

    if (!$admin['otp_code'] || strtotime($admin['otp_expires_at']) < time()) {
        json_error('OTP has expired. Please sign in again.', 401);
    }

    if (!hash_equals($admin['otp_code'], $otp_hash)) {
        $db->prepare('UPDATE admins SET otp_attempts = otp_attempts + 1 WHERE id = ?')
           ->execute([$admin_id]);
        json_error('Invalid verification code.', 401);
    }

    // Clear OTP
    $db->prepare('UPDATE admins SET otp_code = NULL, otp_expires_at = NULL, otp_attempts = 0, last_login = NOW() WHERE id = ?')
       ->execute([$admin_id]);

    // Log successful login
    try {
        $db->prepare(
            'INSERT INTO audit_log (admin_id, action, details, ip_address, created_at)
             VALUES (?, "admin_login", ?, ?, NOW())'
        )->execute([$admin_id, json_encode(['email' => $admin['email']]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) { /* table may not exist yet */ }

    // Issue admin JWT
    $token = JWT::encode([
        'sub'   => (int) $admin['id'],
        'email' => $admin['email'],
        'name'  => $admin['full_name'],
        'role'  => $admin['role'],
    ], JWT_ADMIN_EXPIRY);

    json_success([
        'token' => $token,
        'admin' => [
            'id'        => (int) $admin['id'],
            'full_name' => $admin['full_name'],
            'email'     => $admin['email'],
            'role'      => $admin['role'],
        ],
    ], 'Login successful. Welcome, ' . $admin['full_name'] . '.');
}

json_error('Method not allowed.', 405);
