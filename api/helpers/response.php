<?php
// ============================================================
// DriveSafe Cover — JSON Response Helpers
// ============================================================

function json_success(mixed $data = null, string $message = 'Success', int $code = 200): never {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message = 'An error occurred', int $code = 400, mixed $errors = null): never {
    http_response_code($code);
    $body = ['success' => false, 'message' => $message];
    if ($errors !== null) $body['errors'] = $errors;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_paginated(array $items, int $total, int $page, int $per_page, string $message = 'OK'): never {
    http_response_code(200);
    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'data'       => $items,
        'pagination' => [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'last_page'  => (int) ceil($total / $per_page),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        json_error('Method not allowed.', 405);
    }
}

function get_body(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function validate_required(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    return $errors;
}

function generate_policy_number(): string {
    return POLICY_PREFIX . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
}

function generate_claim_number(): string {
    return CLAIM_PREFIX . str_pad(random_int(1, 9999), 3, '0', STR_PAD_LEFT);
}

function generate_otp(): string {
    return str_pad((string) random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

function calculate_quote(int $coverage_amount, string $start_date, string $end_date): array {
    $tiers = COVERAGE_TIERS;
    if (!array_key_exists($coverage_amount, $tiers)) {
        return ['error' => 'Invalid coverage amount.'];
    }
    $start     = new DateTime($start_date);
    $end       = new DateTime($end_date);
    $diff      = $start->diff($end);
    $days      = $diff->days;
    if ($days < 1) return ['error' => 'End date must be after start date.'];

    $price_per_day = $tiers[$coverage_amount];
    $total         = round($price_per_day * $days, 2);

    return [
        'coverage_amount' => $coverage_amount,
        'price_per_day'   => $price_per_day,
        'days'            => $days,
        'total_price'     => $total,
    ];
}

function paginate(int $page, int $per_page = DEFAULT_PAGE_SIZE): array {
    $page     = max(1, (int) $page);
    $per_page = min(MAX_PAGE_SIZE, max(1, (int) $per_page));
    $offset   = ($page - 1) * $per_page;
    return compact('page', 'per_page', 'offset');
}
