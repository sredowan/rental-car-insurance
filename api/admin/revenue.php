<?php
// ============================================================
// DriveSafe Cover — Admin Revenue API
// GET /api/admin/revenue — Revenue analytics + breakdown
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

require_admin();
require_method('GET');

$db = Database::get();

// Period filter
$period = $_GET['period'] ?? 'month'; // 'week', 'month', 'year', 'all'

$dateFilter = '';
switch ($period) {
    case 'week':  $dateFilter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case 'month': $dateFilter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
    case 'year':  $dateFilter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
    default:      $dateFilter = ''; break;
}

try {
    // ── Overall KPIs ──────────────────────────────────────────
    $kpi = $db->query("
        SELECT 
            COALESCE(SUM(total_price), 0)         AS total_revenue,
            COUNT(*)                               AS total_policies,
            COALESCE(AVG(total_price), 0)         AS avg_order_value,
            COALESCE(SUM(total_price) / NULLIF(SUM(days), 0), 0) AS avg_daily_rate
        FROM policies
        WHERE status != 'cancelled' $dateFilter
    ")->fetch(PDO::FETCH_ASSOC);

    // ── Revenue by coverage tier ──────────────────────────────
    $byTier = $db->query("
        SELECT 
            coverage_amount AS tier,
            COUNT(*) AS count,
            COALESCE(SUM(total_price), 0) AS revenue
        FROM policies
        WHERE status != 'cancelled' $dateFilter
        GROUP BY coverage_amount
        ORDER BY coverage_amount ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Revenue by state ──────────────────────────────────────
    $byState = $db->query("
        SELECT 
            state,
            COUNT(*) AS count,
            COALESCE(SUM(total_price), 0) AS revenue
        FROM policies
        WHERE status != 'cancelled' $dateFilter
        GROUP BY state
        ORDER BY revenue DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Monthly trend (last 12 months) ────────────────────────
    $monthly = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count,
            COALESCE(SUM(total_price), 0) AS revenue
        FROM policies
        WHERE status != 'cancelled'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Claims cost (to calculate net revenue) ────────────────
    $claims = $db->query("
        SELECT 
            COALESCE(SUM(amount_paid), 0) AS total_paid,
            COUNT(CASE WHEN status = 'approved' OR status = 'paid' THEN 1 END) AS approved_count
        FROM claims
        WHERE 1=1 $dateFilter
    ")->fetch(PDO::FETCH_ASSOC);

    $kpi['claims_paid']   = floatval($claims['total_paid']);
    $kpi['net_revenue']   = floatval($kpi['total_revenue']) - floatval($claims['total_paid']);
    $kpi['loss_ratio']    = floatval($kpi['total_revenue']) > 0 
        ? round(floatval($claims['total_paid']) / floatval($kpi['total_revenue']) * 100, 1) 
        : 0;

    json_success([
        'kpi'      => $kpi,
        'by_tier'  => $byTier,
        'by_state' => $byState,
        'monthly'  => $monthly,
        'period'   => $period,
    ]);
} catch (Exception $e) {
    json_error('Revenue query error: ' . $e->getMessage(), 500);
}
