<?php
// ============================================================
// Rental Shield — PDF Policy Certificate Generator
// Professional branded A4 policy document
// ============================================================

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/config.php';

class PolicyPDF {

    /**
     * Generate a PDF policy certificate and return as string
     */
    public static function generate(array $policy): string {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $html = self::template($policy);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Generate and save PDF to uploads directory
     */
    public static function generateAndSave(array $policy): string {
        $pdf = self::generate($policy);
        $dir = UPLOAD_DIR . 'policies/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = "policy_{$policy['policy_number']}.pdf";
        $filepath = $dir . $filename;
        file_put_contents($filepath, $pdf);

        return $filepath;
    }

    /**
     * Send PDF directly to browser for download
     */
    public static function download(array $policy): void {
        $pdf = self::generate($policy);
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=\"RentalShield_Policy_{$policy['policy_number']}.pdf\"");
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /**
     * Get plan features based on plan key
     */
    private static function getPlanFeatures(string $plan): array {
        $plans = self::getCoveragePlans();
        return $plans[$plan]['features'] ?? $plans['essential']['features'];
    }

    /**
     * Get plan label
     */
    private static function getPlanLabel(string $plan): string {
        $plans = self::getCoveragePlans();
        return $plans[$plan]['label'] ?? 'Essential';
    }

    /**
     * Get coverage plans without requiring callers to load response helpers first.
     */
    private static function getCoveragePlans(): array {
        if (function_exists('get_coverage_plans')) {
            return get_coverage_plans();
        }
        return defined('COVERAGE_PLANS') ? COVERAGE_PLANS : [];
    }

    /**
     * Get vehicle type label
     */
    private static function getVehicleLabel(string $type): string {
        $labels = [
            'car'       => 'Car (Sedan / Hatchback / Wagon)',
            'campervan' => 'Campervan',
            'motorhome' => 'Motorhome / RV',
            'bus'       => 'Bus / Small Coach',
            '4x4'      => '4x4 / SUV',
        ];
        return $labels[$type] ?? 'Car';
    }

    /**
     * HTML template — professional branded certificate
     */
    private static function template(array $policy): string {
        $policyNumber   = htmlspecialchars((string) ($policy['policy_number'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $customerName   = htmlspecialchars((string) ($policy['customer_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $customerEmail  = htmlspecialchars((string) ($policy['customer_email'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $coverageAmount = number_format($policy['coverage_amount'] ?? 100000);
        $totalPrice     = number_format($policy['total_price'] ?? 0, 2);
        $pricePerDay    = number_format($policy['price_per_day'] ?? 0, 2);
        $state          = htmlspecialchars((string) ($policy['state'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $plan           = $policy['plan'] ?? 'essential';
        $vehicleType    = $policy['vehicle_type'] ?? 'car';
        $startTs        = strtotime($policy['start_date'] ?? 'now');
        $endTs          = strtotime($policy['end_date'] ?? 'now');
        $startDate      = date('d M Y', $startTs);
        $endDate        = date('d M Y', $endTs);
        $computedDays   = max(1, (int) ceil(($endTs - $startTs) / 86400));
        $days           = max(1, (int) ($policy['days'] ?? $computedDays));
        $createdAt      = date('d M Y, g:i A', strtotime($policy['created_at'] ?? 'now'));
        $appName        = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
        $appUrl         = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8');
        $year           = date('Y');
        
        $logoPath = __DIR__ . '/../../assets/images/logo-pdf.jpg';
        if (!is_file($logoPath)) {
            $logoPath = __DIR__ . '/../../assets/images/logo.png';
        }
        $logoMime = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)) === 'jpg' ? 'image/jpeg' : 'image/png';
        $logoHtml = is_file($logoPath)
            ? '<img src="data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath)) . '" class="logo-img" alt="' . $appName . '">'
            : '<div class="logo-fallback">Rental Shield</div>';

        $planLabel    = htmlspecialchars(self::getPlanLabel($plan), ENT_QUOTES, 'UTF-8');
        $vehicleLabel = htmlspecialchars(self::getVehicleLabel($vehicleType), ENT_QUOTES, 'UTF-8');
        
        $features     = self::getPlanFeatures($plan);
        $featuresHtml = '';
        foreach ($features as $f) {
            $safeF = htmlspecialchars($f, ENT_QUOTES, 'UTF-8');
            $featuresHtml .= "<tr><td class='list-item'><span class='pill-ok'>OK</span>{$safeF}</td></tr>";
        }

        $exclusions = [
            'Damage from driving under the influence of alcohol or drugs',
            'Breach of the rental agreement or local traffic laws',
            'Vehicles from peer-to-peer or private rental platforms',
            'Personal belongings inside the vehicle',
            'Mechanical failure not caused by an accident',
            'Racing, competitive driving, or racetrack use',
            'Damage outside the policy coverage period',
            'Claims lodged more than 30 days after the incident',
        ];
        $exclusionsHtml = '';
        foreach ($exclusions as $e) {
            $safeE = htmlspecialchars($e, ENT_QUOTES, 'UTF-8');
            $exclusionsHtml .= "<tr><td class='list-item'><span class='pill-no'>NO</span>{$safeE}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0 0 82px 0; }
    body {
        font-family: Helvetica, Arial, sans-serif;
        color: #1F2937;
        margin: 0;
        padding: 0;
        font-size: 11px;
        line-height: 1.5;
        background-color: #FFFFFF;
    }
    .top-accent { height: 8px; background: #0D2B6E; border-bottom: 3px solid #1E7FD8; }

    /* Layout Utilities */
    .table-full { width: 100%; border-collapse: collapse; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .v-middle { vertical-align: middle; }
    .v-top { vertical-align: top; }

    .header {
        padding: 24px 40px 22px;
        border-bottom: 1px solid #DCEAF8;
        background: #FFFFFF;
    }
    .logo-img {
        width: 210px;
        height: auto;
    }
    .cert-title {
        font-size: 25px;
        font-weight: 900;
        color: #0D2B6E;
        margin: 0;
        letter-spacing: -0.8px;
    }
    .cert-subtitle {
        display: inline-block;
        margin-top: 8px;
        background: #1E7FD8;
        color: #FFFFFF;
        border-radius: 999px;
        padding: 6px 13px;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: 900;
    }

    /* Policy Highlight Strip */
    .policy-summary {
        background: #F8FBFF;
        border: 1px solid #DCEAF8;
        border-left: 6px solid #1E7FD8;
        border-radius: 14px;
        padding: 16px 20px;
        margin: 28px 40px 22px;
    }
    .summary-label {
        font-size: 9px;
        color: #64748B;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 2px;
        letter-spacing: 0.7px;
    }
    .summary-value {
        font-size: 13px;
        color: #0D2B6E;
        font-weight: 800;
    }
    .status-badge {
        display: inline-block;
        background: #ECFDF5;
        color: #059669;
        padding: 5px 13px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .content { padding: 0 40px 22px; }

    /* Detail Grid */
    .section-head {
        font-size: 11px;
        font-weight: 900;
        color: #1E7FD8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #E6F2FB;
    }
    .detail-item {
        padding: 8px 0;
        border-bottom: 1px solid #EEF2F7;
    }
    .detail-label {
        font-size: 9px;
        color: #94A3B8;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .detail-value {
        font-size: 11px;
        color: #0F172A;
        font-weight: 600;
    }

    .detail-panel {
        border: 1px solid #DCEAF8;
        border-radius: 14px;
        padding: 16px 18px;
        min-height: 166px;
        background: #FFFFFF;
    }

    /* Coverage Hero Box */
    .coverage-hero {
        background: #0D2B6E;
        border-radius: 16px;
        padding: 24px 28px;
        color: #fff;
        margin: 22px 0;
        border-bottom: 5px solid #1E7FD8;
    }
    .coverage-limit-label {
        font-size: 10px;
        color: #AAB6C8;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1px;
    }
    .coverage-amount {
        font-size: 31px;
        font-weight: 900;
        margin: 5px 0;
    }
    .excess-seal {
        background: #059669;
        color: #fff;
        padding: 8px 16px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        text-align: center;
    }

    /* Lists */
    .list-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #EEF2F7;
        font-size: 9.5px;
        color: #334155;
    }
    .list-card { border: 1px solid #DCEAF8; border-radius: 12px; padding: 13px 14px; background: #FFFFFF; min-height: 196px; }
    .pill-ok, .pill-no { display: inline-block; width: 20px; margin-right: 8px; font-size: 7px; font-weight: 900; letter-spacing: .4px; }
    .pill-ok { color: #059669; }
    .pill-no { color: #B91C1C; }

    /* Notice Boxes */
    .notice-box {
        margin-top: 30px;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 10px;
    }
    .notice-yellow { background: #FFFBEB; border: 1px solid #FDE68A; border-left: 5px solid #F59E0B; }
    .notice-blue { background: #F0F7FF; border: 1px solid #BFDBFE; border-left: 5px solid #1E7FD8; }
    .notice-title { font-weight: 900; color: #0D2B6E; margin-bottom: 5px; text-transform: uppercase; font-size: 10px; }
    .notice-content { color: #4B5563; }

    /* Footer */
    .footer {
        position: fixed;
        bottom: -82px;
        left: 0;
        right: 0;
        height: 58px;
        border-top: 2px solid #0D2B6E;
        padding: 12px 40px;
        background: #F8FAFC;
        text-align: center;
        color: #9CA3AF;
        font-size: 8px;
    }
    .footer strong { color: #6B7280; }

    /* Stamp/Signature */
    .stamp-wrap { margin-top: 28px; text-align: right; }
    .stamp-text {
        font-size: 9px;
        font-weight: 700;
        color: #1E7FD8;
        text-transform: uppercase;
        border: 2px solid #1E7FD8;
        padding: 5px 10px;
        display: inline-block;
        transform: rotate(-3deg);
        border-radius: 4px;
        opacity: 0.8;
    }
</style>
</head>
<body>
    <div class="top-accent"></div>

    <div class="header">
        <table class="table-full">
            <tr>
                <td class="v-middle">
                    {$logoHtml}
                </td>
                <td class="text-right v-middle">
                    <h1 class="cert-title">Certificate of Insurance</h1>
                    <div class="cert-subtitle">Rental Vehicle Excess Protection</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="policy-summary">
        <table class="table-full">
            <tr>
                <td style="width: 30%">
                    <div class="summary-label">Policy Number</div>
                    <div class="summary-value" style="font-family: monospace">{$policyNumber}</div>
                </td>
                <td style="width: 25%">
                    <div class="summary-label">Status</div>
                    <div class="status-badge">ACTIVE</div>
                </td>
                <td style="width: 25%">
                    <div class="summary-label">Issue Date</div>
                    <div class="summary-value">{$createdAt}</div>
                </td>
                <td style="width: 20%" class="text-right">
                    <div class="summary-label">Total Premium</div>
                    <div class="summary-value">AUD \${$totalPrice}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">

    <table class="table-full" style="margin-bottom: 20px;">
        <tr>
            <td class="v-top" style="width: 50%; padding-right: 20px;">
                <div class="detail-panel">
                <div class="section-head">Policy Holder Details</div>
                <div class="detail-item">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value">{$customerName}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value">{$customerEmail}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Plan</div>
                    <div class="detail-value">{$planLabel}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Coverage Region</div>
                    <div class="detail-value">{$state}, Australia</div>
                </div>
                </div>
            </td>
            <td class="v-top" style="width: 50%; padding-left: 20px;">
                <div class="detail-panel">
                    <div class="section-head">Coverage Period</div>
                    <div class="detail-item">
                        <div class="detail-label">Start Date</div>
                        <div class="detail-value">{$startDate} (12:01 AM)</div>
                    </div>
                <div class="detail-item">
                    <div class="detail-label">End Date</div>
                    <div class="detail-value">{$endDate} (11:59 PM)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Vehicle Type</div>
                    <div class="detail-value">{$vehicleLabel}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Duration</div>
                    <div class="detail-value">{$days} Days</div>
                </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="coverage-hero">
        <table class="table-full">
            <tr>
                <td class="v-middle">
                    <div class="coverage-limit-label">Maximum Liability Coverage</div>
                    <div class="coverage-amount">AUD \${$coverageAmount}</div>
                    <div style="font-size: 10px; color: rgba(255,255,255,0.5)">Applicable per incident during rental period</div>
                </td>
                <td class="text-right v-middle" style="width: 150px;">
                    <div class="excess-seal">\$0 EXCESS POLICY</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="table-full">
        <tr>
            <td class="v-top" style="width: 50%; padding-right: 20px;">
                <div class="list-card">
                <div class="section-head">What's Covered</div>
                    <table class="table-full list-table">
                        {$featuresHtml}
                    </table>
                </div>
            </td>
            <td class="v-top" style="width: 50%; padding-left: 20px;">
                <div class="list-card">
                    <div class="section-head">General Exclusions</div>
                    <table class="table-full list-table">
                        {$exclusionsHtml}
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="notice-box notice-yellow">
        <div class="notice-title">Important: At the Rental Counter</div>
        <div class="notice-content">
            You are <strong>not required</strong> to purchase additional excess reduction from the rental company. 
            If they insist, simply inform them that you are protected by Rental Shield. This policy provides full 
            reimbursement for the excess amount charged by the rental provider.
        </div>
    </div>

    <div class="notice-box notice-blue">
        <div class="notice-title">How to Make a Claim</div>
        <div class="notice-content">
            In the event of damage or theft, please visit <strong>{$appUrl}/my-claims.html</strong> or contact 
            <strong>info@rentalshield.com.au</strong>. Ensure you collect a damage report, repair invoice, 
            and proof of payment for any excess charges from the rental provider.
        </div>
    </div>

    <div class="stamp-wrap">
        <div class="stamp-text">Certified Policy</div>
        <div style="margin-top: 10px; font-weight: bold; color: #0B1E3D;">Rental Shield Australia</div>
        <div style="font-size: 9px; color: #9CA3AF;">Official Digital Issuance</div>
    </div>

    </div>

    <div class="footer">
        <p><strong>{$appName}</strong> · ABN: 19 686 732 043 · Level 25/6 Parramatta Sq, Parramatta NSW 2150</p>
        <p>This is a computer-generated document. Valid for authorized rentals only. © {$year} {$appName}</p>
    </div>

</body>
</html>
HTML;
    }
}
