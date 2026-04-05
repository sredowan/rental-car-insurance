<?php
// ============================================================
// POST /api/auth/send-otp
// Generates and emails a 6-digit OTP passcode
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/mailer.php';

require_method('POST');

$body = get_body();
$errors = validate_required($body, ['email']);
if ($errors) json_error('Validation failed.', 422, $errors);

$email = strtolower(trim($body['email']));
$db = Database::get();

// Find user
$stmt = $db->prepare('SELECT id, full_name FROM customers WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$customer = $stmt->fetch();

// Security: ALWAYS return success even if email not found to prevent user enumeration
if (!$customer) {
    json_success(null, 'If the email exists, an OTP has been sent.');
}

try {
    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Save to DB
    $stmt = $db->prepare('UPDATE customers SET otp_code = ?, otp_expires_at = ? WHERE id = ?');
    $stmt->execute([$otp, $expires, $customer['id']]);

    // Email
    $subject = "Your Rental Shield Login Code";
    $html = "
        <div style='text-align:center;margin-bottom:24px'>
            <div style='width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#7C3AED,#A855F7);margin:0 auto 16px;display:flex;align-items:center;justify-content:center'>
                <span style='font-size:28px;color:#fff;line-height:64px'>&#128274;</span>
            </div>
            <h2 style='color:#0B1E3D;margin:0 0 4px;font-size:22px'>Secure Login Code</h2>
            <p style='color:#6B7280;margin:0;font-size:14px'>Enter this code on the login page</p>
        </div>

        <div style='background:linear-gradient(135deg,#0B1E3D,#1A3A5C);border-radius:12px;padding:28px;text-align:center;margin:24px 0'>
            <p style='color:rgba(255,255,255,0.6);font-size:11px;text-transform:uppercase;letter-spacing:0.15em;font-weight:600;margin:0 0 8px'>Your one-time passcode</p>
            <div style='font-size:36px;font-weight:800;color:#fff;letter-spacing:8px;font-family:monospace'>{$otp}</div>
        </div>

        <div style='background:#FEF2F2;border:1px solid #FECACA;border-left:4px solid #EF4444;border-radius:8px;padding:16px;margin:20px 0'>
            <p style='margin:0;color:#991B1B;font-size:13px;line-height:1.5'>This code expires in <strong>15 minutes</strong>. If you did not request this code, please ignore this email — your account is safe.</p>
        </div>
    ";
    
    Mailer::send($email, $subject, $html);

    json_success(null, 'If the email exists, an OTP has been sent.');
} catch (Exception $e) {
    error_log("OTP Send Error: " . $e->getMessage());
    json_error('Failed to generate OTP. Please try again later.', 500);
}
