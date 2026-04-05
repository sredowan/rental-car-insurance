<?php
// ============================================================
// GET /api/admin/dashboard — KPI stats for admin dashboard
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

require_method('GET');
require_admin();

$db   = Database::get();
$now  = date('Y-m-d');
$mon  = date('Y-m-01');
$prev = date('Y-m-01', strtotime('-1 month'));
$prev_end = date('Y-m-t', strtotime('-1 month'));

// ── KPIs ─────────────────────────────────────────────────────
$quotes_today = (int) $db->query(
    "SELECT COUNT(*) FROM quotes WHERE DATE(created_at) = '$now'"
)->fetchColumn();

$quotes_yesterday = (int) $db->query(
    "SELECT COUNT(*) FROM quotes WHERE DATE(created_at) = DATE_SUB('$now', INTERVAL 1 DAY)"
)->fetchColumn();

$active_policies = (int) $db->query(
    "SELECT COUNT(*) FROM policies WHERE status = 'active'"
)->fetchColumn();

$policies_last_month = (int) $db->query(
    "SELECT COUNT(*) FROM policies WHERE status = 'active' AND created_at BETWEEN '$prev' AND '$prev_end 23:59:59'"
)->fetchColumn();

$pending_claims = (int) $db->query(
    "SELECT COUNT(*) FROM claims WHERE status IN ('submitted','under_review')"
)->fetchColumn();

$new_claims_today = (int) $db->query(
    "SELECT COUNT(*) FROM claims WHERE DATE(created_at) = '$now'"
)->fetchColumn();

// ── Revenue ───────────────────────────────────────────────────
$revenue_this_month = (float) $db->query(
    "SELECT COALESCE(SUM(total_price),0) FROM policies WHERE created_at >= '$mon' AND payment_status = 'completed'"
)->fetchColumn();

$revenue_last_month = (float) $db->query(
    "SELECT COALESCE(SUM(total_price),0) FROM policies WHERE created_at BETWEEN '$prev' AND '$prev_end 23:59:59' AND payment_status = 'completed'"
)->fetchColumn();

$revenue_change_pct = $revenue_last_month > 0
    ? round(($revenue_this_month - $revenue_last_month) / $revenue_last_month * 100, 1)
    : 0;

// ── Coverage Tier Breakdown ───────────────────────────────────
$tier_stmt = $db->query(
    "SELECT coverage_amount, COUNT(*) as count, SUM(total_price) as revenue
     FROM policies WHERE created_at >= '$mon'
     GROUP BY coverage_amount ORDER BY coverage_amount ASC"
);
$tiers = $tier_stmt->fetchAll();

foreach ($tiers as &$t) {
    $t['coverage_amount'] = (int)   $t['coverage_amount'];
    $t['count']           = (int)   $t['count'];
    $t['revenue']         = (float) $t['revenue'];
    $t['price_per_day']   = COVERAGE_TIERS[$t['coverage_amount']] ?? 0;
}

// ── Claims by Status ─────────────────────────────────────────
$claims_stmt = $db->query(
    "SELECT status, COUNT(*) as count FROM claims GROUP BY status"
);
$claims_by_status = [];
foreach ($claims_stmt->fetchAll() as $row) {
    $claims_by_status[$row['status']] = (int) $row['count'];
}

// ── Conversion Rate ───────────────────────────────────────────
$total_quotes     = (int) $db->query("SELECT COUNT(*) FROM quotes WHERE created_at >= '$mon'")->fetchColumn();
$converted_quotes = (int) $db->query("SELECT COUNT(*) FROM quotes WHERE status = 'converted' AND created_at >= '$mon'")->fetchColumn();
$conversion_rate  = $total_quotes > 0 ? round($converted_quotes / $total_quotes * 100, 1) : 0;

// ── Recent Quotes ─────────────────────────────────────────────
$recent_quotes_stmt = $db->query(
    "SELECT q.id, q.state, q.days, q.status, q.created_at,
            c.full_name as customer_name, c.email as customer_email,
            p.policy_number, p.coverage_amount, p.total_price
     FROM quotes q
     LEFT JOIN customers c  ON c.id = q.customer_id
     LEFT JOIN policies  p  ON p.id = q.policy_id
     ORDER BY q.created_at DESC LIMIT 10"
);
$recent_quotes = $recent_quotes_stmt->fetchAll();

// ── Pending Claims ───────────────────────────────────────────
$pending_stmt = $db->query(
    "SELECT cl.id, cl.claim_number, cl.amount_claimed, cl.status,
            cl.incident_date, cl.created_at,
            cu.full_name as customer_name,
            p.policy_number, p.coverage_amount
     FROM claims cl
     JOIN customers cu ON cu.id = cl.customer_id
     JOIN policies  p  ON p.id  = cl.policy_id
     WHERE cl.status IN ('submitted','under_review')
     ORDER BY cl.created_at ASC LIMIT 10"
);
$pending_claims_list = $pending_stmt->fetchAll();

json_success([
    'kpis' => [
        'quotes_today'        => $quotes_today,
        'quotes_yesterday'    => $quotes_yesterday,
        'active_policies'     => $active_policies,
        'policies_last_month' => $policies_last_month,
        'pending_claims'      => $pending_claims,
        'new_claims_today'    => $new_claims_today,
        'revenue_this_month'  => round($revenue_this_month, 2),
        'revenue_last_month'  => round($revenue_last_month, 2),
        'revenue_change_pct'  => $revenue_change_pct,
        'conversion_rate'     => $conversion_rate,
    ],
    'coverage_tiers'     => $tiers,
    'claims_by_status'   => $claims_by_status,
    'recent_quotes'      => $recent_quotes,
    'pending_claims_list'=> $pending_claims_list,
], 'Dashboard data retrieved.');
