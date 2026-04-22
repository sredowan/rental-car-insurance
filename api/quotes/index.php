<?php
// ============================================================
// POST /api/quotes  — Create quote (NO price in response)
// GET  /api/quotes/:id — Get quote detail WITH price
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── POST: Create quote ───────────────────────────────────────
if ($method === 'POST') {
    // Auth optional — guests can get quotes
    $auth = optional_auth();

    $body   = get_body();
    $errors = validate_required($body, ['state', 'start_date', 'end_date']);
    if ($errors) json_error('Validation failed.', 422, $errors);

    $state        = sanitize($body['state']);
    $start_date   = $body['start_date'];
    $end_date     = $body['end_date'];
    $start_time   = $body['start_time'] ?? '09:00';
    $end_time     = $body['end_time']   ?? '09:00';
    $vehicle_type = sanitize($body['vehicle_type'] ?? 'car');

    // Validate vehicle type
    $valid_vehicles = array_keys(VEHICLE_SURCHARGES);
    if (!in_array($vehicle_type, $valid_vehicles)) {
        $vehicle_type = 'car';
    }

    // Validate dates
    if (!strtotime($start_date) || !strtotime($end_date)) {
        json_error('Invalid dates provided.', 422);
    }
    if (strtotime($end_date) <= strtotime($start_date)) {
        json_error('End date must be after start date.', 422);
    }
    if (strtotime($start_date) < strtotime('today')) {
        json_error('Start date cannot be in the past.', 422);
    }

    // Calculate days (do NOT include pricing in POST response)
    $days = (int) ceil((strtotime($end_date) - strtotime($start_date)) / 86400);
    if ($days < 1) json_error('Minimum rental period is 1 day.', 422);
    if ($days > 365) json_error('Maximum rental period is 365 days.', 422);

    $customer_id = $auth ? (int) $auth['sub'] : null;

    $db = Database::get();
    $stmt = $db->prepare(
        'INSERT INTO quotes (customer_id, state, vehicle_type, start_date, start_time, end_date, end_time, days, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW())'
    );
    $stmt->execute([$customer_id, $state, $vehicle_type, $start_date, $start_time, $end_date, $end_time, $days]);
    $quote_id = (int) $db->lastInsertId();

    // Return quote_id ONLY — price is revealed only on GET /quotes/:id
    json_success([
        'quote_id'     => $quote_id,
        'state'        => $state,
        'vehicle_type' => $vehicle_type,
        'days'         => $days,
        'redirect'     => '/quote-result.html?q=' . $quote_id,
    ], 'Quote created.', 201);
}

// ── GET: Retrieve quote WITH price ───────────────────────────
if ($method === 'GET') {
    $quote_id = (int) ($_GET['id'] ?? 0);
    if (!$quote_id) json_error('Quote ID required.', 400);

    $db   = Database::get();
    $stmt = $db->prepare(
        'SELECT q.*, c.full_name as customer_name, c.email as customer_email
         FROM quotes q
         LEFT JOIN customers c ON c.id = q.customer_id
         WHERE q.id = ? LIMIT 1'
    );
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) json_error('Quote not found.', 404);

    $vehicle_type = $quote['vehicle_type'] ?? 'car';
    $surcharge    = VEHICLE_SURCHARGES[$vehicle_type] ?? 0;

    // Build all plan pricing options
    $plans   = COVERAGE_PLANS;
    $options = [];
    foreach ($plans as $key => $plan) {
        $price_per_day = round($plan['price_per_day'] + $surcharge, 2);
        $options[] = [
            'plan'            => $key,
            'plan_label'      => $plan['label'],
            'badge'           => $plan['badge'],
            'coverage_amount' => $plan['coverage_amount'],
            'price_per_day'   => $price_per_day,
            'total_price'     => round($price_per_day * $quote['days'], 2),
            'features'        => $plan['features'],
        ];
    }

    json_success([
        'quote_id'     => (int) $quote['id'],
        'state'        => $quote['state'],
        'vehicle_type' => $vehicle_type,
        'start_date'   => $quote['start_date'],
        'start_time'   => $quote['start_time'],
        'end_date'     => $quote['end_date'],
        'end_time'     => $quote['end_time'],
        'days'         => (int) $quote['days'],
        'status'       => $quote['status'],
        'options'      => $options,  // 3 plans with features
    ], 'Quote retrieved.');
}

json_error('Method not allowed.', 405);
