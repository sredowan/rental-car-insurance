<?php
// ============================================================
// DriveSafe Cover — PHP Dev Server Router
// Start with: php -S localhost:8765 router.php
// Serves static files + routes /api/* to API
// ============================================================

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// ── API requests → route to api/index.php ────────────────────
if (str_starts_with($uri, '/api/')) {
    // Pass through — api/index.php handles the /api/ prefix
    require __DIR__ . '/api/index.php';
    return true;
}

// ── Serve static files directly ──────────────────────────────
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    // Let PHP serve the file with correct MIME type
    return false;
}

// ── Default: serve index.html ────────────────────────────────
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.html';
    return true;
}

// Try .html extension
if (is_file($file . '.html')) {
    require $file . '.html';
    return true;
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html><body><h1>404 — Page Not Found</h1><a href="/">Go Home</a></body></html>';
return true;
