<?php
// ============================================================
// DriveSafe Cover — Security Middleware
// Include this in api/index.php for all requests
// ============================================================

// ── Security Headers ─────────────────────────────────────
function set_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── Simple Rate Limiter (file-based, upgrade to Redis in production) ───
function rate_limit(string $key, int $maxAttempts = 60, int $windowSeconds = 60): void {
    $dir = sys_get_temp_dir() . '/dsc_rate_limit';
    if (!is_dir($dir)) mkdir($dir, 0700, true);

    $file = $dir . '/' . md5($key) . '.json';
    $now  = time();

    $data = ['attempts' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
    }

    // Check if blocked
    if ($data['blocked_until'] > $now) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait ' . ($data['blocked_until'] - $now) . ' seconds.',
        ]);
        exit;
    }

    // Filter out old attempts
    $data['attempts'] = array_filter($data['attempts'], function($t) use ($now, $windowSeconds) {
        return $t > ($now - $windowSeconds);
    });

    if (count($data['attempts']) >= $maxAttempts) {
        $data['blocked_until'] = $now + $windowSeconds;
        file_put_contents($file, json_encode($data));
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
        ]);
        exit;
    }

    $data['attempts'][] = $now;
    file_put_contents($file, json_encode($data));
}

// ── Input Sanitizer ──────────────────────────────────────
function deep_sanitize($input) {
    if (is_string($input)) {
        // Strip null bytes, trim, strip tags
        $input = str_replace(chr(0), '', $input);
        $input = trim($input);
        // Don't strip HTML from certain fields (description, etc.)
        return $input;
    }
    if (is_array($input)) {
        return array_map('deep_sanitize', $input);
    }
    return $input;
}

// ── Audit Logger ─────────────────────────────────────────
function audit_log(string $action, string $details = '', ?int $adminId = null, ?string $entityType = null, ?int $entityId = null): void {
    try {
        $db = Database::get();
        $stmt = $db->prepare("
            INSERT INTO audit_log (admin_id, action, details, entity_type, entity_id, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $adminId,
            $action,
            $details,
            $entityType,
            $entityId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Exception $e) {
        // Audit log failure should not break the request
        error_log('Audit log error: ' . $e->getMessage());
    }
}
