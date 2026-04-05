<?php
// ============================================================
// GET /api/admin/customers        — List all customers
// GET /api/admin/customers?id=X   — Customer profile + history
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

require_method('GET');
require_admin();

$db          = Database::get();
$customer_id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── Single customer ───────────────────────────────────────────
if ($customer_id) {
    $stmt = $db->prepare(
        'SELECT id, full_name, email, phone, state, status, created_at, updated_at
         FROM customers WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    if (!$customer) json_error('Customer not found.', 404);

    // Policy summary
    $policies = $db->prepare(
        'SELECT id, policy_number, coverage_amount, total_price, status, start_date, end_date
         FROM policies WHERE customer_id = ? ORDER BY created_at DESC'
    );
    $policies->execute([$customer_id]);
    $customer['policies'] = $policies->fetchAll();

    // Claims summary
    $claims = $db->prepare(
        'SELECT cl.id, cl.claim_number, cl.status, cl.amount_claimed, cl.amount_paid, cl.created_at,
                p.policy_number
         FROM claims cl JOIN policies p ON p.id = cl.policy_id
         WHERE cl.customer_id = ? ORDER BY cl.created_at DESC'
    );
    $claims->execute([$customer_id]);
    $customer['claims'] = $claims->fetchAll();

    // Totals
    $customer['id']             = (int) $customer['id'];
    $customer['total_policies'] = count($customer['policies']);
    $customer['total_claims']   = count($customer['claims']);
    $customer['total_spent']    = array_sum(array_column($customer['policies'], 'total_price'));

    json_success($customer);
}

// ── List customers ────────────────────────────────────────────
$page_data = paginate((int) ($_GET['page'] ?? 1));
$search    = $_GET['search'] ?? null;
$status    = $_GET['status'] ?? null;

$where  = 'WHERE 1=1';
$params = [];
if ($search) {
    $where .= ' AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like   = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($status) { $where .= ' AND status = ?'; $params[] = $status; }

$count = $db->prepare("SELECT COUNT(*) FROM customers $where");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $db->prepare(
    "SELECT c.id, c.full_name, c.email, c.phone, c.state, c.status, c.created_at,
            COUNT(DISTINCT p.id) as policy_count,
            COUNT(DISTINCT cl.id) as claim_count,
            COALESCE(SUM(p.total_price),0) as total_spent
     FROM customers c
     LEFT JOIN policies p  ON p.customer_id  = c.id
     LEFT JOIN claims   cl ON cl.customer_id = c.id
     $where
     GROUP BY c.id
     ORDER BY c.created_at DESC
     LIMIT {$page_data['per_page']} OFFSET {$page_data['offset']}"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

foreach ($customers as &$c) {
    $c['id']           = (int)   $c['id'];
    $c['policy_count'] = (int)   $c['policy_count'];
    $c['claim_count']  = (int)   $c['claim_count'];
    $c['total_spent']  = (float) $c['total_spent'];
}

json_paginated($customers, $total, $page_data['page'], $page_data['per_page']);
