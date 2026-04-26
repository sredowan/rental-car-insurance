<?php
// ============================================================
// GET /api/payments/config
// Returns public Stripe checkout configuration for active mode.
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

require_method('GET');

$stripe = get_stripe_config();
if (empty($stripe['publishable_key'])) {
    json_error('Stripe publishable key is not configured.', 500);
}

json_success([
    'mode' => $stripe['mode'],
    'publishable_key' => $stripe['publishable_key'],
], 'Stripe configuration loaded.');
