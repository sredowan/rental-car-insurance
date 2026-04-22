<?php
// ============================================================
// DriveSafe Cover — Stripe Payment Intent API
// POST /api/payments/create-intent
// Supports both logged-in and guest checkout
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

// Only accept POST
require_method('POST');

// Auth is optional — guest checkout allowed
$user = optional_auth();
$customer_id = $user ? (int) $user['sub'] : null;

// Parse input
$data = get_body();
$quote_id      = intval($data['quote_id'] ?? 0);
$plan          = trim($data['plan'] ?? '');
$vehicle_type  = trim($data['vehicle_type'] ?? 'car');
$guest_email   = trim($data['email'] ?? '');

if (!$quote_id || !$plan) {
    json_error('quote_id and plan are required', 400);
}

// Validate plan
$plans = COVERAGE_PLANS;
if (!isset($plans[$plan])) {
    json_error('Invalid plan selected', 400);
}

// Validate vehicle type
$surcharges = VEHICLE_SURCHARGES;
$surcharge  = $surcharges[$vehicle_type] ?? 0;

try {
    $db = Database::connect();

    // For logged-in users, verify quote belongs to them
    // For guests, just find the quote by ID (guest quotes have customer_id = NULL)
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
        json_error('This quote has already been converted to a policy', 400);
    }

    // Calculate price server-side (NEVER trust client)
    $price_per_day = round($plans[$plan]['price_per_day'] + $surcharge, 2);
    $coverage_amount = $plans[$plan]['coverage_amount'];
    $days          = intval($quote['days']);
    $total_price   = round($price_per_day * $days, 2);
    $total_cents   = intval($total_price * 100); // Stripe uses cents

    // Create Stripe PaymentIntent
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $metadata = [
        'quote_id'         => $quote_id,
        'plan'             => $plan,
        'plan_label'       => $plans[$plan]['label'],
        'coverage_amount'  => $coverage_amount,
        'vehicle_type'     => $vehicle_type,
        'days'             => $days,
        'price_per_day'    => $price_per_day,
    ];
    if ($customer_id) $metadata['customer_id'] = $customer_id;
    if ($guest_email)  $metadata['guest_email'] = $guest_email;

    $intent = \Stripe\PaymentIntent::create([
        'amount'               => $total_cents,
        'currency'             => STRIPE_CURRENCY,
        'payment_method_types' => ['card'],
        'metadata'             => $metadata,
        'description'          => "Rental Shield — {$plans[$plan]['label']} plan, {$vehicle_type}, {$days} days",
    ]);

    json_success([
        'client_secret' => $intent->client_secret,
        'intent_id'     => $intent->id,
        'amount'        => $total_price,
        'currency'      => STRIPE_CURRENCY,
    ], 'Payment intent created');

} catch (\Stripe\Exception\ApiErrorException $e) {
    json_error('Payment error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    json_error('Server error: ' . $e->getMessage(), 500);
}
