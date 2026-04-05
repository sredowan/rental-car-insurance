<?php
// ============================================================
// DriveSafe Cover — Admin Audit Log API
// GET /api/admin/audit — List audit log entries
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

require_admin();
require_method('GET');

$db = Database::get();

$page   = intval($_GET['page'] ?? 1);
$limit  = intval($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;
$action = $_GET['action'] ?? '';

$where  = '';
$params = [];
if ($action) {
    $where = "WHERE action = ?";
    $params[] = $action;
}

$stmt = $db->prepare("
    SELECT al.*, 
           COALESCE(a.full_name, 'System') AS admin_name
    FROM audit_log al
    LEFT JOIN admins a ON al.admin_id = a.id
    $where
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

json_paginated($logs, $total, $page, $limit);
