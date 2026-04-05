<?php
// ============================================================
// DriveSafe Cover — Admin Quotes API
// GET  /api/admin/quotes          — list all quotes
// GET  /api/admin/quotes?id=X     — single quote detail
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$admin = require_admin();
require_method('GET');

$id = intval($_GET['id'] ?? 0);
$db = Database::get();

if ($id) {
    $stmt = $db->prepare("
        SELECT q.*, c.full_name AS customer_name, c.email AS customer_email
        FROM quotes q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) json_error('Quote not found.', 404);
    json_success($quote);
}

// List all
$status = $_GET['status'] ?? '';
$page   = intval($_GET['page'] ?? 1);
$limit  = intval($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

$where  = '';
$params = [];

if ($status) {
    $where = "WHERE q.status = ?";
    $params[] = $status;
}

$stmt = $db->prepare("
    SELECT q.*, c.full_name AS customer_name, c.email AS customer_email
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    $where
    ORDER BY q.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPI
$counts = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='converted' THEN 1 ELSE 0 END) AS converted,
        SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) AS expired
    FROM quotes
")->fetch(PDO::FETCH_ASSOC);

json_success([
    'quotes' => $quotes,
    'kpi'    => $counts,
]);
