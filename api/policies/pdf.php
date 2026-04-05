<?php
// ============================================================
// DriveSafe Cover — Policy PDF Download
// GET /api/policies/pdf?id=X — Download policy as PDF
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/pdf.php';
require_once __DIR__ . '/../middleware/auth.php';

require_method('GET');
$user = require_auth();
$db   = Database::get();

$policy_id = intval($_GET['id'] ?? 0);
if (!$policy_id) json_error('Policy ID required.', 400);

// Fetch policy with customer info
$stmt = $db->prepare("
    SELECT p.*, c.full_name AS customer_name, c.email AS customer_email
    FROM policies p
    JOIN customers c ON p.customer_id = c.id
    WHERE p.id = ? AND p.customer_id = ?
");
$stmt->execute([$policy_id, $user['sub']]);
$policy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$policy) json_error('Policy not found.', 404);

// Generate and download
PolicyPDF::download($policy);
