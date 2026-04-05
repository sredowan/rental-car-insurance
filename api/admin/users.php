<?php
// ============================================================
// DriveSafe Cover — Admin Users Management API
// GET    /api/admin/users         — List admin accounts
// POST   /api/admin/users         — Create admin account
// PUT    /api/admin/users?id=X    — Update admin account
// DELETE /api/admin/users?id=X    — Deactivate admin
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$admin  = require_super_admin(); // Only super admins manage users
$db     = Database::get();
$method = $_SERVER['REQUEST_METHOD'];
$id     = intval($_GET['id'] ?? 0);

// ── GET — List admins ──────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->query("
        SELECT id, full_name, email, role, status, last_login, created_at
        FROM admins
        ORDER BY created_at DESC
    ");
    json_success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── POST — Create admin ────────────────────────────────────
if ($method === 'POST') {
    $data = get_body();
    $errors = validate_required($data, ['full_name', 'email', 'password', 'role']);
    if ($errors) json_error('Validation failed.', 422, $errors);

    if (!in_array($data['role'], ['admin', 'super_admin'])) {
        json_error('Invalid role.', 400);
    }

    // Check email uniqueness
    $check = $db->prepare("SELECT id FROM admins WHERE email = ?");
    $check->execute([strtolower($data['email'])]);
    if ($check->fetch()) json_error('Email already exists.', 409);

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);

    $stmt = $db->prepare("
        INSERT INTO admins (full_name, email, password_hash, role, status, created_at)
        VALUES (?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([
        sanitize($data['full_name']),
        strtolower(sanitize($data['email'])),
        $hash,
        $data['role'],
    ]);

    json_success(['id' => $db->lastInsertId()], 'Admin created successfully.', 201);
}

// ── PUT — Update admin ─────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_error('Admin ID required.', 400);
    $data = get_body();

    $fields = [];
    $params = [];

    if (!empty($data['full_name'])) { $fields[] = "full_name = ?"; $params[] = sanitize($data['full_name']); }
    if (!empty($data['role']) && in_array($data['role'], ['admin', 'super_admin'])) { $fields[] = "role = ?"; $params[] = $data['role']; }
    if (!empty($data['status']) && in_array($data['status'], ['active', 'suspended'])) { $fields[] = "status = ?"; $params[] = $data['status']; }
    if (!empty($data['password'])) { $fields[] = "password_hash = ?"; $params[] = password_hash($data['password'], PASSWORD_BCRYPT); }

    if (empty($fields)) json_error('Nothing to update.', 400);

    $fields[] = "updated_at = NOW()";
    $params[] = $id;

    $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    json_success(null, 'Admin updated.');
}

// ── DELETE — Deactivate admin ──────────────────────────────
if ($method === 'DELETE') {
    if (!$id) json_error('Admin ID required.', 400);

    // Don't allow self-delete
    if ($id === intval($admin['sub'])) {
        json_error('Cannot deactivate your own account.', 400);
    }

    $db->prepare("UPDATE admins SET status = 'suspended', updated_at = NOW() WHERE id = ?")->execute([$id]);
    json_success(null, 'Admin deactivated.');
}

json_error('Method not allowed.', 405);
