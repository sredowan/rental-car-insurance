<?php
// ============================================================
// POST /api/policies  — Create policy after payment
// GET  /api/policies  — List customer's own policies
// GET  /api/policies/:id — Single policy detail
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/mailer.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── POST: Create policy (called after payment success) ───────
if ($method === 'POST') {
    $auth = require_auth(); // must be logged in
    $body = get_body();

    $errors = validate_required($body, ['quote_id', 'coverage_amount', 'payment_reference']);
    if ($errors) json_error('Validation failed.', 422, $errors);

    $quote_id          = (int) $body['quote_id'];
    $coverage_amount   = (int) $body['coverage_amount'];
    $plan              = $body['plan'] ?? 'essential';
    $vehicle_type      = $body['vehicle_type'] ?? 'car';
    $payment_reference = sanitize($body['payment_reference']);
    $customer_id       = (int) $auth['sub'];

    $plans = get_coverage_plans();
    if (!isset($plans[$plan])) json_error('Invalid plan selected.', 422);

    $db = Database::get();

    // Verify quote belongs to customer (or is guest quote)
    $stmt = $db->prepare(
        'SELECT * FROM quotes WHERE id = ? AND (customer_id = ? OR customer_id IS NULL) LIMIT 1'
    );
    $stmt->execute([$quote_id, $customer_id]);
    $quote = $stmt->fetch();
    if (!$quote) json_error('Quote not found or does not belong to your account.', 404);
    if ($quote['status'] === 'converted') json_error('This quote has already been converted to a policy.', 409);

    // Calculate final pricing
    $calc = calculate_quote($plan, $vehicle_type, $quote['start_date'], $quote['end_date']);
    if (isset($calc['error'])) json_error($calc['error'], 422);
    $coverage_amount = (int) $calc['coverage_amount'];

    // Generate unique policy number
    do {
        $policy_number = generate_policy_number();
        $exists = $db->prepare('SELECT id FROM policies WHERE policy_number = ?');
        $exists->execute([$policy_number]);
    } while ($exists->fetch());

    // Begin transaction
    $db->beginTransaction();
    try {
        // Insert policy
        $stmt = $db->prepare(
            'INSERT INTO policies
             (customer_id, quote_id, policy_number, state, coverage_amount,
              price_per_day, total_price, days, start_date, start_time,
              end_date, end_time, payment_reference, payment_amount,
              payment_status, status, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $stmt->execute([
            $customer_id,
            $quote_id,
            $policy_number,
            $quote['state'],
            $coverage_amount,
            $calc['price_per_day'],
            $calc['total_price'],
            $calc['days'],
            $quote['start_date'],
            $quote['start_time'],
            $quote['end_date'],
            $quote['end_time'],
            $payment_reference,
            $calc['total_price'],
            'completed',
            'active',
        ]);
        $policy_id = (int) $db->lastInsertId();

        // Update quote status
        $db->prepare('UPDATE quotes SET status = "converted", policy_id = ? WHERE id = ?')
           ->execute([$policy_id, $quote_id]);

        // Update quote customer_id if it was a guest quote
        if (!$quote['customer_id']) {
            $db->prepare('UPDATE quotes SET customer_id = ? WHERE id = ?')
               ->execute([$customer_id, $quote_id]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        json_error('Failed to create policy. Please contact support.', 500);
    }

    // Fetch the created policy
    $stmt = $db->prepare('SELECT * FROM policies WHERE id = ? LIMIT 1');
    $stmt->execute([$policy_id]);
    $policy = $stmt->fetch();

    try {
        $custStmt = $db->prepare('SELECT email, full_name FROM customers WHERE id = ? LIMIT 1');
        $custStmt->execute([$customer_id]);
        $customer = $custStmt->fetch();
        if ($customer && !empty($customer['email'])) {
            Mailer::sendPolicyConfirmation($policy, $customer['email'], $customer['full_name']);
        }
    } catch (Exception $e) {
        error_log('Policy email error: ' . $e->getMessage());
    }

    json_success([
        'policy_id'     => $policy_id,
        'policy_number' => $policy_number,
        'state'         => $policy['state'],
        'coverage'      => $coverage_amount,
        'price_per_day' => (float) $policy['price_per_day'],
        'total_price'   => (float) $policy['total_price'],
        'days'          => (int) $policy['days'],
        'start_date'    => $policy['start_date'],
        'end_date'      => $policy['end_date'],
        'status'        => $policy['status'],
    ], 'Policy created successfully.', 201);
}

// ── GET: List policies ────────────────────────────────────────
if ($method === 'GET') {
    $auth        = require_auth();
    $customer_id = (int) $auth['sub'];

    $page_data   = paginate((int) ($_GET['page'] ?? 1));
    $status      = $_GET['status'] ?? null;

    $db = Database::get();

    $where = 'WHERE p.customer_id = ?';
    $params = [$customer_id];
    if ($status) { $where .= ' AND p.status = ?'; $params[] = $status; }

    $count = $db->prepare("SELECT COUNT(*) FROM policies p $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT p.*, COUNT(cl.id) as claims_count
         FROM policies p
         LEFT JOIN claims cl ON cl.policy_id = p.id
         $where
         GROUP BY p.id
         ORDER BY p.created_at DESC
         LIMIT {$page_data['per_page']} OFFSET {$page_data['offset']}"
    );
    $stmt->execute($params);
    $policies = $stmt->fetchAll();

    $today = date('Y-m-d');
    foreach ($policies as &$p) {
        $p['coverage_amount'] = (int)   $p['coverage_amount'];
        $p['price_per_day']   = (float) $p['price_per_day'];
        $p['total_price']     = (float) $p['total_price'];
        $p['days']            = (int)   $p['days'];
        $p['claims_count']    = (int)   $p['claims_count'];
        
        // Dynamically calculate status based on current date
        if ($p['start_date'] > $today) {
            $p['status'] = 'future';
        } elseif ($p['end_date'] < $today) {
            $p['status'] = 'expired';
        } else {
            $p['status'] = 'active';
        }
    }

    json_paginated($policies, $total, $page_data['page'], $page_data['per_page']);
}

json_error('Method not allowed.', 405);
