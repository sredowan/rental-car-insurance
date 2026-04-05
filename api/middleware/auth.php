<?php
// ============================================================
// DriveSafe Cover — Auth Middleware (JWT Guard)
// ============================================================
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

function require_auth(): array {
    $token = JWT::from_header();
    if (!$token) json_error('Authentication required.', 401);

    $payload = JWT::decode($token);
    if (!$payload) json_error('Invalid or expired token. Please sign in again.', 401);

    if (!isset($payload['sub']) || !isset($payload['role'])) {
        json_error('Malformed token.', 401);
    }

    return $payload;
}

function require_admin(): array {
    $payload = require_auth();
    if (!in_array($payload['role'], ['admin', 'super_admin'], true)) {
        json_error('Access denied. Admin only.', 403);
    }
    return $payload;
}

function require_super_admin(): array {
    $payload = require_auth();
    if ($payload['role'] !== 'super_admin') {
        json_error('Access denied. Super admin only.', 403);
    }
    return $payload;
}

function optional_auth(): ?array {
    $token = JWT::from_header();
    if (!$token) return null;
    return JWT::decode($token);
}
