<?php
// ============================================================
// DriveSafe Cover — Confirm Payment & Create Policy
// POST /api/payments/confirm
// Supports both logged-in and guest checkout
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/tracking.php';

require_method('POST');

// Auth optional — guest checkout allowed
$user = optional_auth();
$customer_id = $user ? (int) $user['sub'] : null;

$data = get_body();
$payment_intent_id = trim($data['payment_intent_id'] ?? '');
$quote_id          = intval($data['quote_id'] ?? 0);
$plan              = trim($data['plan'] ?? '');
$vehicle_type      = trim($data['vehicle_type'] ?? 'car');
$guest_email       = trim($data['email'] ?? '');
$guest_name        = trim($data['name'] ?? '');
$guest_phone       = trim($data['phone'] ?? '');

if (!$payment_intent_id || !$quote_id || !$plan) {
    json_error('payment_intent_id, quote_id, and plan are required', 400);
}

$plans = COVERAGE_PLANS;
if (!isset($plans[$plan])) {
    json_error('Invalid plan selected', 400);
}
$surcharges = VEHICLE_SURCHARGES;
$surcharge  = $surcharges[$vehicle_type] ?? 0;

try {
    // Verify the PaymentIntent with Stripe
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

    if ($intent->status !== 'succeeded') {
        json_error('Payment has not been confirmed yet. Status: ' . $intent->status, 400);
    }

    $db = Database::connect();

    // Find quote
    if ($customer_id) {
        $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ? AND customer_id = ?");
        $stmt->execute([$quote_id, $customer_id]);
    } else {
        $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ? AND (customer_id IS NULL OR customer_id = 0)");
        $stmt->execute([$quote_id]);
    }
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        json_error('Quote not found', 404);
    }

    if ($quote['status'] === 'converted') {
        // Idempotent — if already converted, return the existing policy
        $stmt = $db->prepare("SELECT * FROM policies WHERE quote_id = ?");
        $stmt->execute([$quote_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        json_success($existing, 'Policy already created');
    }

    // For guest checkout, create or find a customer account
    if (!$customer_id) {
        if (!$guest_email) {
            json_error('Email is required for guest checkout', 400);
        }

        // Check if customer already exists with this email
        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$guest_email]);
        $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $is_new_customer = false;
        $new_account_password = null;

        if ($existing_customer) {
            $customer_id = (int) $existing_customer['id'];
        } else {
            $is_new_customer = true;
            // Create new customer with a random password (they can reset later)
            // using random_int logic or just hex. 8 chars is friendly enough
            $new_account_password = bin2hex(random_bytes(4));
            $stmt = $db->prepare(
                "INSERT INTO customers (full_name, email, phone, password_hash, status, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())"
            );
            $stmt->execute([
                $guest_name ?: 'Guest Customer',
                $guest_email,
                $guest_phone,
                password_hash($new_account_password, PASSWORD_BCRYPT),
            ]);
            $customer_id = (int) $db->lastInsertId();
        }

        // Link the quote to this customer
        $stmt = $db->prepare("UPDATE quotes SET customer_id = ? WHERE id = ?");
        $stmt->execute([$customer_id, $quote_id]);
    }

    // Calculate price server-side
    $coverage_amount = $plans[$plan]['coverage_amount'];
    $price_per_day = round($plans[$plan]['price_per_day'] + $surcharge, 2);
    $days          = intval($quote['days']);
    $total_price   = round($price_per_day * $days, 2);

    // Generate policy number
    $policy_number = generate_policy_number();

    // Begin transaction
    $db->beginTransaction();

    // Create policy
    $stmt = $db->prepare("
        INSERT INTO policies (customer_id, quote_id, policy_number, state, plan, vehicle_type, coverage_amount, 
                              start_date, end_date, days, price_per_day, total_price,
                              payment_reference, payment_amount, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([
        $customer_id,
        $quote_id,
        $policy_number,
        $quote['state'],
        $plan,
        $vehicle_type,
        $coverage_amount,
        $quote['start_date'],
        $quote['end_date'],
        $days,
        $price_per_day,
        $total_price,
        $payment_intent_id,
        $total_price,
    ]);

    $policy_id = $db->lastInsertId();

    // Update quote status
    $stmt = $db->prepare("UPDATE quotes SET status = 'converted' WHERE id = ?");
    $stmt->execute([$quote_id]);

    $db->commit();

    // Fetch the created policy
    $stmt = $db->prepare("SELECT * FROM policies WHERE id = ?");
    $stmt->execute([$policy_id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send policy confirmation email
    try {
        require_once __DIR__ . '/../helpers/mailer.php';
        $email_to = $guest_email ?: '';
        $name_to  = $guest_name ?: '';
        if ($customer_id && !$email_to) {
            $stmt = $db->prepare("SELECT email, full_name FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $cust = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cust) {
                $email_to = $cust['email'];
                $name_to  = $cust['full_name'];
            }
        }
        if ($email_to) {
            Mailer::sendPolicyConfirmation($policy, $email_to, $name_to);
        }
    } catch (Exception $e) {
        error_log("Policy email error: " . $e->getMessage());
        // Don't fail the purchase because of email
    }

    // Send Facebook Conversion API Purchase Event
    try {
        Tracking::sendFacebookEvent('Purchase', [
            'value'    => $total_price,
            'currency' => 'AUD',
            'order_id' => $policy_number,
            'email'    => $guest_email ?: ($email_to ?? ''),
            'phone'    => $guest_phone,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $tEx) {
        error_log("Tracking execution error: " . $tEx->getMessage());
    }

    // Send New Account password email
    if (isset($is_new_customer) && $is_new_customer && isset($new_account_password)) {
        try {
            $welcomeSub = "Welcome to Rental Shield — Your Account";
            $welcomeHtml = "
                <div style='text-align:center;margin-bottom:24px'>
                    <div style='width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#1D4ED8,#3B82F6);margin:0 auto 16px;display:flex;align-items:center;justify-content:center'>
                        <span style='font-size:28px;color:#fff;line-height:64px'>&#9733;</span>
                    </div>
                    <h2 style='color:#0B1E3D;margin:0 0 4px;font-size:22px'>Welcome to Rental Shield</h2>
                    <p style='color:#6B7280;margin:0;font-size:14px'>Your account has been created</p>
                </div>

                <p style='color:#374151;font-size:15px;line-height:1.6'>Hi {$guest_name},</p>
                <p style='color:#374151;font-size:15px;line-height:1.6'>An account was automatically created during your purchase so you can manage your policies, download certificates, and file claims anytime.</p>

                <div style='background:#F8FAFC;border:1px solid #E5E7EB;border-radius:12px;padding:20px;margin:24px 0'>
                    <table style='width:100%;border-collapse:collapse'>
                        <tr>
                            <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Login Email</td>
                            <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;font-weight:700;color:#0B1E3D;text-align:right;font-size:15px'>{$guest_email}</td>
                        </tr>
                        <tr>
                            <td style='padding:10px 0;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Temporary Password</td>
                            <td style='padding:10px 0;font-weight:700;color:#E8003A;text-align:right;font-family:monospace;font-size:16px;letter-spacing:2px'>{$new_account_password}</td>
                        </tr>
                    </table>
                </div>

                <div style='background:#EFF6FF;border:1px solid #BFDBFE;border-left:4px solid #3B82F6;border-radius:8px;padding:16px;margin:20px 0'>
                    <p style='margin:0;color:#1E40AF;font-size:13px;line-height:1.5'>You can also sign in without a password using a one-time email code. Just click \"Login with Email Code\" on the sign-in page.</p>
                </div>

                <div style='text-align:center;margin:28px 0 16px'>
                    <a href='" . APP_URL . "/login.html' style='display:inline-block;background:linear-gradient(135deg,#E8003A,#C7002F);color:#fff;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none'>Sign In to Dashboard</a>
                </div>
            ";
            Mailer::send($guest_email, $welcomeSub, $welcomeHtml);
        } catch(Exception $ex) {
            error_log("Welcome email error: " . $ex->getMessage());
        }
    }

    // Fetch full customer to generate token
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $cust = $stmt->fetch();

    require_once __DIR__ . '/../helpers/jwt.php';
    $token = JWT::encode([
        'sub'   => (int) $cust['id'],
        'email' => $cust['email'],
        'name'  => $cust['full_name'],
        'role'  => 'customer',
    ]);

    json_success([
        'policy' => $policy,
        'customer' => [
            'id' => $cust['id'],
            'full_name' => $cust['full_name'],
            'email' => $cust['email']
        ],
        'token' => $token
    ], 'Policy created successfully');

} catch (\Stripe\Exception\ApiErrorException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    json_error('Stripe error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    json_error('Server error: ' . $e->getMessage(), 500);
}
