<?php
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user   = require_auth();
$db     = Database::get();

// GET all messages in the thread
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT * FROM support_messages
        WHERE customer_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$user['sub']]);
    
    // Mark outbound (admin replies) as read
    $db->prepare("UPDATE support_messages SET read_status = 'read' WHERE customer_id = ? AND direction = 'outbound'")->execute([$user['sub']]);
    
    json_success($stmt->fetchAll());
}

// POST new message to support
if ($method === 'POST') {
    $body = get_body();
    $message = sanitize($body['message'] ?? '');
    
    if (!$message) json_error('Message cannot be empty', 422);
    
    $ins = $db->prepare("INSERT INTO support_messages (customer_id, subject, message, direction, read_status, created_at) VALUES (?, 'Support Request', ?, 'inbound', 'unread', NOW())");
    $ins->execute([$user['sub'], $message]);
    
    json_success(['id' => $db->lastInsertId()], 'Message sent');
}

json_error('Method not allowed.', 405);
