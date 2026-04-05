<?php
// ============================================================
// DriveSafe Cover — CORS Middleware
// ============================================================

function cors_headers(): void {
    $allowed_origins = [
        'http://localhost:8765',
        'http://localhost:3000',
        'http://127.0.0.1:8765',
        'https://yourdomain.com.au',
        'https://www.yourdomain.com.au',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        // In development, allow all; lock this down in production
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Call immediately on include
cors_headers();
