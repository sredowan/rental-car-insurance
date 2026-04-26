<?php
// ============================================================
// DriveSafe Cover — JSON Response Helpers
// ============================================================

function json_success($data = null, string $message = 'Success', int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message = 'An error occurred', int $code = 400, $errors = null) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $body = ['success' => false, 'message' => $message];
    if ($errors !== null) $body['errors'] = $errors;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_paginated(array $items, int $total, int $page, int $per_page, string $message = 'OK') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
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

function get_setting(string $key, $default = null) {
    try {
        $db = Database::get();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null && $value !== '' ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function get_stripe_mode(): string {
    $mode = strtolower((string) get_setting('stripe_mode', 'test'));
    return $mode === 'live' ? 'live' : 'test';
}

function get_stripe_config(?string $mode = null): array {
    $mode = $mode ? strtolower($mode) : get_stripe_mode();
    $mode = $mode === 'live' ? 'live' : 'test';
    
    $publishable = $mode === 'live' ? STRIPE_LIVE_PUBLISHABLE_KEY : STRIPE_TEST_PUBLISHABLE_KEY;
    $secret      = $mode === 'live' ? STRIPE_LIVE_SECRET_KEY : STRIPE_TEST_SECRET_KEY;

    return [
        'mode' => $mode,
        'publishable_key' => $publishable,
        'secret_key' => $secret,
    ];
}

function mask_secret(?string $value, int $visible = 6): string {
    $value = (string) $value;
    if ($value === '') return '';
    if (strlen($value) <= ($visible * 2)) return str_repeat('*', strlen($value));
    return substr($value, 0, $visible) . str_repeat('*', max(8, strlen($value) - ($visible * 2))) . substr($value, -$visible);
}

function get_coverage_plans(): array {
    $plans = COVERAGE_PLANS;
    try {
        $db = Database::get();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('plan_price_essential', 'plan_price_premium', 'plan_price_ultimate')");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (isset($settings['plan_price_essential']) && is_numeric($settings['plan_price_essential'])) {
            $plans['essential']['price_per_day'] = (float) $settings['plan_price_essential'];
        }
        if (isset($settings['plan_price_premium']) && is_numeric($settings['plan_price_premium'])) {
            $plans['premium']['price_per_day'] = (float) $settings['plan_price_premium'];
        }
        if (isset($settings['plan_price_ultimate']) && is_numeric($settings['plan_price_ultimate'])) {
            $plans['ultimate']['price_per_day'] = (float) $settings['plan_price_ultimate'];
        }
    } catch (Exception $e) {
        // Fallback to defaults if DB fails or isn't connected yet
    }
    return $plans;
}

function calculate_quote(string $plan, string $vehicle_type, string $start_date, string $end_date): array {
    $plans = get_coverage_plans();
    if (!array_key_exists($plan, $plans)) {
        return ['error' => 'Invalid plan selected.'];
    }
    $surcharges = VEHICLE_SURCHARGES;
    $surcharge  = $surcharges[$vehicle_type] ?? 0;

    $start     = new DateTime($start_date);
    $end       = new DateTime($end_date);
    $diff      = $start->diff($end);
    $days      = $diff->days;
    if ($days < 1) return ['error' => 'End date must be after start date.'];

    $price_per_day = round($plans[$plan]['price_per_day'] + $surcharge, 2);
    $total         = round($price_per_day * $days, 2);

    return [
        'plan'            => $plan,
        'plan_label'      => $plans[$plan]['label'],
        'coverage_amount' => $plans[$plan]['coverage_amount'],
        'vehicle_type'    => $vehicle_type,
        'price_per_day'   => $price_per_day,
        'days'            => $days,
        'total_price'     => $total,
        'features'        => $plans[$plan]['features'],
    ];
}

function paginate(int $page, int $per_page = DEFAULT_PAGE_SIZE): array {
    $page     = max(1, (int) $page);
    $per_page = min(MAX_PAGE_SIZE, max(1, (int) $per_page));
    $offset   = ($page - 1) * $per_page;
    return compact('page', 'per_page', 'offset');
}
