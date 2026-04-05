<?php
// ============================================================
// DriveSafe Cover — Admin Policies API
// GET  /api/admin/policies          — list all policies
// GET  /api/admin/policies?id=X     — single policy detail
// PUT  /api/admin/policies?id=X     — update policy status
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$admin  = require_admin();
$method = $_SERVER['REQUEST_METHOD'];
$id     = intval($_GET['id'] ?? 0);
$db     = Database::get();

// ── GET — List / Detail ────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        // Single policy
        $stmt = $db->prepare("
            SELECT p.*, c.full_name AS customer_name, c.email AS customer_email
            FROM policies p
            JOIN customers c ON p.customer_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$policy) json_error('Policy not found.', 404);
        json_success($policy);
    }

    // List all
    $status = $_GET['status'] ?? '';
    $page   = intval($_GET['page'] ?? 1);
    $limit  = intval($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $where  = '';
    $params = [];

    if ($status) {
        $where = "WHERE p.status = ?";
        $params[] = $status;
    }

    $stmt = $db->prepare("
        SELECT p.*, c.full_name AS customer_name, c.email AS customer_email
        FROM policies p
        JOIN customers c ON p.customer_id = c.id
        $where
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // KPI counts
    $counts = $db->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) AS expired,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
            COALESCE(SUM(total_price), 0) AS revenue
        FROM policies
    ")->fetch(PDO::FETCH_ASSOC);

    json_success([
        'policies' => $policies,
        'kpi'      => $counts,
    ]);
}

// ── PUT — Update policy status ─────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_error('Policy ID required.', 400);

    $data   = get_body();
    $status = $data['status'] ?? '';

    if (!in_array($status, ['active', 'expired', 'cancelled'])) {
        json_error('Invalid status.', 400);
    }

    $stmt = $db->prepare("UPDATE policies SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);

    json_success(null, 'Policy updated.');
}

json_error('Method not allowed.', 405);
