<?php
// ============================================================
// DriveSafe Cover — Customer Profile API
// GET    /api/profile          — Get my profile
// PUT    /api/profile          — Update my profile
// POST   /api/profile          — Upload documents (multipart)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$user   = require_auth();
$db     = Database::get();
$method = $_SERVER['REQUEST_METHOD'];
$customer_id = (int) $user['sub'];

// ── GET — Current profile ──────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, state, status, profile_photo, driving_licence, created_at, updated_at
        FROM customers WHERE id = ?
    ");
    $stmt->execute([$customer_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) json_error('Profile not found.', 404);

    // Attach summary stats
    $stats = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM policies WHERE customer_id = ?) AS total_policies,
            (SELECT COUNT(*) FROM policies WHERE customer_id = ? AND status = 'active') AS active_policies,
            (SELECT COUNT(*) FROM claims WHERE customer_id = ?) AS total_claims,
            (SELECT COALESCE(SUM(total_price), 0) FROM policies WHERE customer_id = ?) AS total_spent
    ");
    $stats->execute([$customer_id, $customer_id, $customer_id, $customer_id]);
    $profile['stats'] = $stats->fetch(PDO::FETCH_ASSOC);

    json_success($profile);
}

// ── POST — Upload documents (profile_photo / driving_licence) ─
if ($method === 'POST') {
    $upload_type = $_POST['upload_type'] ?? '';
    if (!in_array($upload_type, ['profile_photo', 'driving_licence'])) {
        json_error('Invalid upload_type. Must be profile_photo or driving_licence.', 400);
    }

    if (empty($_FILES['file'])) {
        json_error('No file uploaded.', 400);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('File upload failed.', 400);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        json_error('File too large. Max ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.', 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        json_error('Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS), 400);
    }

    // Create upload directory
    $upload_dir = UPLOAD_DIR . 'profiles/' . $customer_id . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Delete old file if exists
    $stmt = $db->prepare("SELECT {$upload_type} FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $old_file = $stmt->fetchColumn();
    if ($old_file && file_exists(UPLOAD_DIR . $old_file)) {
        unlink(UPLOAD_DIR . $old_file);
    }

    // Save new file
    $filename = $upload_type . '_' . time() . '.' . $ext;
    $relative_path = 'profiles/' . $customer_id . '/' . $filename;
    $full_path = UPLOAD_DIR . $relative_path;

    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        json_error('Failed to save file.', 500);
    }

    // Update DB
    $stmt = $db->prepare("UPDATE customers SET {$upload_type} = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$relative_path, $customer_id]);

    json_success([
        'file_path' => '/uploads/' . $relative_path,
        'type' => $upload_type
    ], ucfirst(str_replace('_', ' ', $upload_type)) . ' uploaded successfully.');
}

// ── PUT — Update profile ───────────────────────────────────
if ($method === 'PUT') {
    $data = get_body();

    $fields = [];
    $params = [];

    if (isset($data['full_name']) && strlen(trim($data['full_name'])) >= 2) {
        $fields[] = "full_name = ?";
        $params[] = sanitize($data['full_name']);
    }
    if (isset($data['phone'])) {
        $fields[] = "phone = ?";
        $params[] = sanitize($data['phone']);
    }
    if (isset($data['state'])) {
        $allowed_states = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT'];
        if (in_array($data['state'], $allowed_states)) {
            $fields[] = "state = ?";
            $params[] = $data['state'];
        }
    }

    // Password change
    if (!empty($data['current_password']) && !empty($data['new_password'])) {
        $stmt = $db->prepare("SELECT password_hash FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $current = $stmt->fetchColumn();

        if (!password_verify($data['current_password'], $current)) {
            json_error('Current password is incorrect.', 400);
        }
        if (strlen($data['new_password']) < 8) {
            json_error('New password must be at least 8 characters.', 400);
        }

        $fields[] = "password_hash = ?";
        $params[] = password_hash($data['new_password'], PASSWORD_BCRYPT);
    }

    if (empty($fields)) json_error('Nothing to update.', 400);

    $fields[] = "updated_at = NOW()";
    $params[] = $customer_id;

    $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    json_success(null, 'Profile updated successfully.');
}

json_error('Method not allowed.', 405);
