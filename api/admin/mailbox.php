<?php
// ============================================================
// GET  /api/admin/mailbox         — List unique conversations or messages
// GET  /api/admin/mailbox?thread=X — Get messages for a specific customer
// POST /api/admin/mailbox         — Reply to a customer
// PATCH /api/admin/mailbox        — Mark message as read
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/mailer.php';

$method = $_SERVER['REQUEST_METHOD'];
$admin  = require_admin();
$db     = Database::get();

// ── GET: Threads or Single Thread ────────────────────────────────
if ($method === 'GET') {
    $thread = isset($_GET['thread']) ? (int) $_GET['thread'] : null;

    if ($thread) {
        // Fetch specific continuous chat history with a customer
        $stmt = $db->prepare("
            SELECT m.*, c.full_name as customer_name, c.email as customer_email
            FROM support_messages m
            JOIN customers c ON c.id = m.customer_id
            WHERE m.customer_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$thread]);
        
        // Auto-mark as read
        $db->prepare("UPDATE support_messages SET read_status = 'read' WHERE customer_id = ? AND direction = 'inbound'")->execute([$thread]);
        
        json_success($stmt->fetchAll());
    } else {
        // Group by customer to show 'Inbox'
        // Find latest message per customer
        $stmt = $db->query("
            SELECT m1.*, c.full_name as customer_name, c.email as customer_email,
                   (SELECT COUNT(*) FROM support_messages WHERE customer_id = m1.customer_id AND direction = 'inbound' AND read_status = 'unread') as unread_count
            FROM support_messages m1
            JOIN customers c ON c.id = m1.customer_id
            WHERE m1.id = (
                SELECT MAX(id) FROM support_messages m2 WHERE m2.customer_id = m1.customer_id
            )
            ORDER BY m1.created_at DESC
        ");
        json_success($stmt->fetchAll());
    }
}

// ── POST: Send Reply ──────────────────────────────────────────
if ($method === 'POST') {
    $body = get_body();
    
    $customer_id = isset($body['customer_id']) ? (int) $body['customer_id'] : 0;
    $message     = sanitize($body['message'] ?? '');
    
    if (!$customer_id || !$message) {
        json_error('Customer ID and message are required', 422);
    }
    
    // Get customer info
    $stmt = $db->prepare("SELECT email, full_name FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        json_error("Customer not found", 404);
    }

    // Insert to DB
    $ins = $db->prepare("INSERT INTO support_messages (customer_id, subject, message, direction, read_status, created_at) VALUES (?, ?, ?, 'outbound', 'read', NOW())");
    $ins->execute([$customer_id, 'RE: Support Request', $message]);
    
    $msgId = $db->lastInsertId();

    // Send real email
    try {
        Mailer::sendSupportReply($customer['email'], $customer['full_name'], $message);
    } catch(Exception $e) {
        error_log("Failed sending support email: " . $e->getMessage());
    }
    
    json_success([
        'id' => $msgId,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s')
    ], 'Reply sent successfully.');
}

json_error('Method not allowed.', 405);
