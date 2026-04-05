<?php
// ============================================================
// DriveSafe Cover — API Router
// All requests to /api/* land here via .htaccess rewrite
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/middleware/security.php';

// Security headers on every API response
set_security_headers();

// Parse the URI — works with both .htaccess and PHP built-in server
$request_uri = strtok($_SERVER['REQUEST_URI'], '?');
$request_uri = trim($request_uri, '/');

// Strip 'api/' prefix if present (Apache .htaccess already strips it, dev server doesn't)
if (str_starts_with($request_uri, 'api/')) {
    $uri = substr($request_uri, 4);
} else {
    $uri = $request_uri;
}
$uri    = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Route map: 'path' => 'handler_file'
$routes = [
    // ── Auth ─────────────────────────────────────
    'auth/register'        => __DIR__ . '/auth/register.php',
    'auth/login'           => __DIR__ . '/auth/login.php',
    'auth/forgot-password' => __DIR__ . '/auth/forgot-password.php',
    'auth/send-otp'        => __DIR__ . '/auth/send-otp.php',
    'auth/verify-otp'      => __DIR__ . '/auth/verify-otp.php',

    // ── Quotes ───────────────────────────────────
    'quotes'               => __DIR__ . '/quotes/index.php',

    // ── Policies ─────────────────────────────────
    'policies'             => __DIR__ . '/policies/index.php',
    'policies/pdf'         => __DIR__ . '/policies/pdf.php',

    // ── Claims ───────────────────────────────────
    'claims'               => __DIR__ . '/claims/index.php',

    // ── Profile ──────────────────────────────────
    'profile'              => __DIR__ . '/profile/index.php',

    // ── Payments (Stripe) ────────────────────────
    'payments/create-intent' => __DIR__ . '/payments/create-intent.php',
    'payments/confirm'       => __DIR__ . '/payments/confirm.php',

    // ── Admin ─────────────────────────────────────
    'admin/login'          => __DIR__ . '/admin/login.php',
    'admin/otp'            => __DIR__ . '/admin/login.php',    // same file, action=otp
    'admin/dashboard'      => __DIR__ . '/admin/dashboard.php',
    'admin/claims'         => __DIR__ . '/admin/claims.php',
    'admin/customers'      => __DIR__ . '/admin/customers.php',
    'admin/policies'       => __DIR__ . '/admin/policies.php',
    'admin/quotes'         => __DIR__ . '/admin/quotes.php',
    'admin/revenue'        => __DIR__ . '/admin/revenue.php',
    'admin/settings'       => __DIR__ . '/admin/settings.php',
    'admin/audit'          => __DIR__ . '/admin/audit.php',
    'admin/users'          => __DIR__ . '/admin/users.php',
    'admin/mailbox'        => __DIR__ . '/admin/mailbox.php',
    
    // ── Support ───────────────────────────────────
    'support'              => __DIR__ . '/support/index.php',

    // ── Health Check ─────────────────────────────
    'health'               => null,   // handled inline below
    'ping'                 => null,
];

// ── Health check ──────────────────────────────────────────────
if ($uri === 'health' || $uri === 'ping') {
    $db_ok = false;
    try {
        require_once __DIR__ . '/config/database.php';
        Database::get()->query('SELECT 1');
        $db_ok = true;
    } catch (Exception $e) {}

    json_success([
        'api'      => 'DriveSafe Cover API',
        'version'  => '1.0.0',
        'database' => $db_ok ? 'connected' : 'error',
        'time'     => date('c'),
    ], $db_ok ? 'API is healthy.' : 'API running but DB unreachable.');
}

// ── OTP action routing ────────────────────────────────────────
if ($uri === 'admin/otp') {
    $_GET['action'] = 'otp';
}

// ── Rate Limiting for auth endpoints ──────────────────────────
$rate_limited_routes = ['auth/login', 'auth/register', 'auth/forgot-password', 'auth/send-otp', 'auth/verify-otp', 'admin/login', 'admin/otp'];
if (in_array($uri, $rate_limited_routes)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    rate_limit("auth:{$uri}:{$ip}", 10, 60); // 10 attempts per minute per IP
}

// ── Dispatch ──────────────────────────────────────────────────
if (isset($routes[$uri]) && $routes[$uri] !== null) {
    require $routes[$uri];
    exit;
}

// ── 404 ───────────────────────────────────────────────────────
json_error("Endpoint not found: /$uri", 404);
