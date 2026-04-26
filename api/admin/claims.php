<?php
// ============================================================
// GET   /api/admin/claims         — List all claims
// GET   /api/admin/claims?id=X    — Single claim detail
// PATCH /api/admin/claims?id=X    — Update claim status
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/mailer.php';

$method   = $_SERVER['REQUEST_METHOD'];
$admin    = require_admin();
$claim_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$db       = Database::get();

// ── GET single claim ──────────────────────────────────────────
if ($method === 'GET' && $claim_id) {
    $stmt = $db->prepare(
        "SELECT cl.*, cu.full_name as customer_name, cu.email as customer_email,
                cu.phone as customer_phone,
                p.policy_number, p.coverage_amount, p.state
         FROM claims cl
         JOIN customers cu ON cu.id = cl.customer_id
         JOIN policies  p  ON p.id  = cl.policy_id
         WHERE cl.id = ? LIMIT 1"
    );
    $stmt->execute([$claim_id]);
    $claim = $stmt->fetch();
    if (!$claim) json_error('Claim not found.', 404);

    // Get documents
    $docs = $db->prepare(
        'SELECT id, document_type, file_name, file_path, file_size, mime_type, uploaded_at
         FROM claim_documents WHERE claim_id = ? ORDER BY uploaded_at'
    );
    $docs->execute([$claim_id]);
    $claim['documents'] = $docs->fetchAll();
    $claim['amount_claimed'] = (float) $claim['amount_claimed'];
    $claim['amount_paid']    = (float) ($claim['amount_paid'] ?? 0);

    json_success($claim);
}

// ── GET list claims ───────────────────────────────────────────
if ($method === 'GET') {
    $page_data = paginate((int) ($_GET['page'] ?? 1));
    $status    = $_GET['status']   ?? null;
    $search    = $_GET['search']   ?? null;

    $where  = 'WHERE 1=1';
    $params = [];
    if ($status) { $where .= ' AND cl.status = ?'; $params[] = $status; }
    if ($search) {
        $where .= ' AND (cu.full_name LIKE ? OR cl.claim_number LIKE ? OR p.policy_number LIKE ?)';
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like]);
    }

    $count = $db->prepare("SELECT COUNT(*) FROM claims cl JOIN customers cu ON cu.id = cl.customer_id JOIN policies p ON p.id = cl.policy_id $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT cl.id, cl.claim_number, cl.status, cl.amount_claimed, cl.amount_paid,
                cl.incident_date, cl.damage_types, cl.created_at,
                cu.full_name as customer_name, cu.email as customer_email,
                p.policy_number, p.coverage_amount, p.state
         FROM claims cl
         JOIN customers cu ON cu.id = cl.customer_id
         JOIN policies  p  ON p.id  = cl.policy_id
         $where
         ORDER BY cl.created_at DESC
         LIMIT {$page_data['per_page']} OFFSET {$page_data['offset']}"
    );
    $stmt->execute($params);
    $claims = $stmt->fetchAll();

    foreach ($claims as &$c) {
        $c['amount_claimed'] = (float) $c['amount_claimed'];
        $c['amount_paid']    = (float) ($c['amount_paid'] ?? 0);
    }

    json_paginated($claims, $total, $page_data['page'], $page_data['per_page']);
}

// ── PATCH: Update claim status ────────────────────────────────
if ($method === 'PATCH') {
    if (!$claim_id) json_error('Claim ID required.', 400);

    $body          = get_body();
    $new_status    = $body['status']       ?? null;
    $admin_notes   = sanitize($body['admin_notes'] ?? '');
    $amount_paid   = isset($body['amount_paid']) ? (float) $body['amount_paid'] : null;

    $valid_statuses = ['under_review', 'approved', 'denied', 'paid'];
    if (!$new_status || !in_array($new_status, $valid_statuses, true)) {
        json_error('Invalid status. Must be: under_review, approved, denied, or paid.', 422);
    }

    $stmt = $db->prepare('
        SELECT cl.id, cl.claim_number, cl.status, cl.amount_claimed, cl.customer_id, cu.email, cu.full_name
        FROM claims cl
        JOIN customers cu ON cu.id = cl.customer_id
        WHERE cl.id = ? LIMIT 1
    ');
    $stmt->execute([$claim_id]);
    $claim = $stmt->fetch();
    if (!$claim) json_error('Claim not found.', 404);

    $fields = ['status = ?', 'admin_notes = ?', 'updated_at = NOW()'];
    $params = [$new_status, $admin_notes];

    if ($new_status === 'approved') {
        $fields[] = 'approved_at = NOW()';
        $fields[] = 'approved_by = ?';
        $params[]  = (int) $admin['sub'];
    }
    if ($new_status === 'paid' && $amount_paid !== null) {
        $fields[] = 'amount_paid = ?';
        $fields[] = 'paid_at = NOW()';
        $params[]  = $amount_paid;
    }
    if ($new_status === 'denied') {
        $fields[] = 'denied_at = NOW()';
    }

    $params[] = $claim_id;
    $db->prepare('UPDATE claims SET ' . implode(', ', $fields) . ' WHERE id = ?')
       ->execute($params);

    // Audit log
    $db->prepare(
        'INSERT INTO audit_log (admin_id, action, details, entity_type, entity_id, ip_address, user_agent, created_at)
         VALUES (?, "claim_status_update", ?, "claim", ?, ?, ?, NOW())'
    )->execute([
        (int) $admin['sub'],
        json_encode(['old_status' => $claim['status'], 'new_status' => $new_status]),
        $claim_id,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);

    // Send email notification to customer
    if ($new_status !== $claim['status'] || !empty($admin_notes)) {
        try {
            $updatedClaim = [
                'claim_number' => $claim['claim_number'],
                'status'       => $new_status,
                'admin_notes'  => $admin_notes
            ];
            Mailer::sendClaimUpdate($updatedClaim, $claim['email'], $claim['full_name']);
        } catch (Exception $e) {
            error_log("Failed to send admin claim update email: " . $e->getMessage());
        }
    }

    json_success(['claim_id' => $claim_id, 'status' => $new_status], 'Claim updated successfully.');
}

json_error('Method not allowed.', 405);
