<?php
// ============================================================
// DriveSafe Cover — JWT Helper (Pure PHP, HS256, no library)
// ============================================================
require_once __DIR__ . '/../config/config.php';

class JWT {
    // ── Encode ───────────────────────────────────────────────
    public static function encode(array $payload, int $expiry = null): string {
        $header = self::base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payload['iat'] = time();
        $payload['exp'] = time() + ($expiry ?? JWT_EXPIRY);

        $payload_encoded = self::base64url_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$header.$payload_encoded",
            JWT_SECRET,
            true
        );

        return "$header.$payload_encoded." . self::base64url_encode($signature);
    }

    // ── Decode & Verify ──────────────────────────────────────
    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;

        // Verify signature
        $expected_sig = self::base64url_encode(
            hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
        );
        if (!hash_equals($expected_sig, $sig)) return null;

        // Decode payload
        $data = json_decode(self::base64url_decode($payload), true);
        if (!$data) return null;

        // Check expiry
        if (isset($data['exp']) && $data['exp'] < time()) return null;

        return $data;
    }

    // ── Extract from Authorization Header ───────────────────
    public static function from_header(): ?string {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // ── Helpers ──────────────────────────────────────────────
    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
