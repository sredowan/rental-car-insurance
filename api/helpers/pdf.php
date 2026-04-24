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
        $plans = COVERAGE_PLANS;
        return $plans[$plan]['features'] ?? $plans['essential']['features'];
    }

    /**
     * Get plan label
     */
    private static function getPlanLabel(string $plan): string {
        $plans = COVERAGE_PLANS;
        return $plans[$plan]['label'] ?? 'Essential';
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
        $policyNumber   = $policy['policy_number'] ?? '—';
        $customerName   = $policy['customer_name'] ?? '—';
        $customerEmail  = $policy['customer_email'] ?? '—';
        $coverageAmount = number_format($policy['coverage_amount'] ?? 100000);
        $totalPrice     = number_format($policy['total_price'] ?? 0, 2);
        $pricePerDay    = number_format($policy['price_per_day'] ?? 0, 2);
        $days           = $policy['days'] ?? '—';
        $state          = $policy['state'] ?? '—';
        $plan           = $policy['plan'] ?? 'essential';
        $vehicleType    = $policy['vehicle_type'] ?? 'car';
        $startDate      = date('d M Y', strtotime($policy['start_date'] ?? 'now'));
        $endDate        = date('d M Y', strtotime($policy['end_date'] ?? 'now'));
        $createdAt      = date('d M Y, g:i A', strtotime($policy['created_at'] ?? 'now'));
        $appName        = APP_NAME;
        $appUrl         = APP_URL;
        $year           = date('Y');

        $planLabel    = self::getPlanLabel($plan);
        $vehicleLabel = self::getVehicleLabel($vehicleType);
        $features     = self::getPlanFeatures($plan);

        // Build features HTML
        $featuresHtml = '';
        foreach ($features as $feature) {
            $featuresHtml .= "<tr><td style='padding:5px 0 5px 0;font-size:11px;color:#374151;border-bottom:1px solid #F3F4F6'><span style='color:#059669;font-weight:700;margin-right:6px'>✓</span> {$feature}</td></tr>";
        }

        // Build exclusions list
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
        foreach ($exclusions as $ex) {
            $exclusionsHtml .= "<tr><td style='padding:4px 0;font-size:10px;color:#6B7280;border-bottom:1px solid #F9FAFB'><span style='color:#DC2626;margin-right:4px'>✕</span> {$ex}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0; }
    body {
        font-family: Helvetica, Arial, sans-serif;
        color: #1F2937;
        margin: 0;
        padding: 0;
        font-size: 12px;
        line-height: 1.5;
    }

    /* Header banner */
    .header {
        background: linear-gradient(135deg, #0B1E3D 0%, #1A3A5C 100%);
        padding: 28px 40px 24px;
        color: #fff;
    }
    .header-inner {
        display: table;
        width: 100%;
    }
    .header-left {
        display: table-cell;
        vertical-align: middle;
    }
    .header-right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
    }
    .logo {
        font-size: 22px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.5px;
    }
    .logo-sub {
        font-size: 10px;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-top: 2px;
    }
    .cert-badge {
        display: inline-block;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* Body content */
    .content {
        padding: 28px 40px 20px;
    }

    /* Policy number strip */
    .policy-strip {
        background: #F0F7FF;
        border: 1.5px solid #BFDBFE;
        border-radius: 8px;
        padding: 14px 20px;
        margin-bottom: 22px;
    }
    .policy-strip-inner {
        display: table;
        width: 100%;
    }
    .policy-strip-left, .policy-strip-right {
        display: table-cell;
        vertical-align: middle;
    }
    .policy-strip-right { text-align: right; }
    .policy-number-label {
        font-size: 9px;
        color: #6B7280;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
    }
    .policy-number {
        font-size: 16px;
        font-weight: 800;
        color: #1E7FD8;
        letter-spacing: 0.5px;
        font-family: 'Courier New', monospace;
    }
    .status-badge {
        display: inline-block;
        background: #DCFCE7;
        color: #166534;
        padding: 4px 14px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Details grid */
    .details-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .details-grid td {
        padding: 9px 14px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: top;
    }
    .detail-label {
        font-size: 10px;
        color: #9CA3AF;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        width: 35%;
    }
    .detail-value {
        font-size: 12px;
        color: #1F2937;
        font-weight: 600;
    }

    /* Coverage box */
    .coverage-box {
        background: linear-gradient(135deg, #0B1E3D 0%, #1A3A5C 100%);
        border-radius: 10px;
        padding: 20px 24px;
        margin: 20px 0;
        color: #fff;
    }
    .coverage-box-inner {
        display: table;
        width: 100%;
    }
    .coverage-main, .coverage-side {
        display: table-cell;
        vertical-align: middle;
    }
    .coverage-side { text-align: right; }
    .coverage-label {
        font-size: 10px;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-weight: 700;
    }
    .coverage-amount {
        font-size: 28px;
        font-weight: 800;
        color: #fff;
        margin-top: 2px;
    }
    .excess-badge {
        display: inline-block;
        background: rgba(5,150,105,0.2);
        border: 1px solid rgba(5,150,105,0.3);
        color: #34D399;
        padding: 6px 16px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 800;
    }

    /* Section headers */
    .section-head {
        font-size: 11px;
        font-weight: 800;
        color: #1E7FD8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 22px 0 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #E5E7EB;
    }

    /* Features table */
    .features-table {
        width: 100%;
        border-collapse: collapse;
    }

    /* Two-column layout */
    .two-col {
        width: 100%;
        border-collapse: collapse;
    }
    .two-col td {
        vertical-align: top;
        width: 50%;
    }
    .two-col td:first-child { padding-right: 12px; }
    .two-col td:last-child { padding-left: 12px; }

    /* Claims section */
    .claims-box {
        background: #FFFBEB;
        border: 1px solid #FDE68A;
        border-left: 4px solid #F59E0B;
        border-radius: 6px;
        padding: 14px 16px;
        margin: 16px 0;
    }
    .claims-title {
        font-size: 11px;
        font-weight: 800;
        color: #92400E;
        margin-bottom: 4px;
    }
    .claims-text {
        font-size: 10px;
        color: #78350F;
        line-height: 1.6;
    }

    /* Important notice */
    .notice-box {
        background: #F0F7FF;
        border: 1px solid #BFDBFE;
        border-left: 4px solid #1E7FD8;
        border-radius: 6px;
        padding: 14px 16px;
        margin: 12px 0;
    }
    .notice-title {
        font-size: 11px;
        font-weight: 800;
        color: #1E40AF;
        margin-bottom: 4px;
    }
    .notice-text {
        font-size: 10px;
        color: #1E40AF;
        line-height: 1.6;
    }

    /* Terms */
    .terms {
        font-size: 9px;
        color: #9CA3AF;
        line-height: 1.7;
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #E5E7EB;
    }
    .terms p { margin: 0 0 6px; }

    /* Footer */
    .footer {
        background: #F8FAFC;
        border-top: 2px solid #0B1E3D;
        padding: 16px 40px;
        text-align: center;
    }
    .footer p {
        font-size: 9px;
        color: #9CA3AF;
        margin: 0 0 3px;
        line-height: 1.5;
    }
    .footer strong { color: #6B7280; }
</style>
</head>
<body>

    <!-- HEADER BANNER -->
    <div class="header">
        <div class="header-inner">
            <div class="header-left">
                <div class="logo">🛡️ {$appName}</div>
                <div class="logo-sub">Rental Car Excess Insurance</div>
            </div>
            <div class="header-right">
                <div class="cert-badge">Certificate of Insurance</div>
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- Policy Number Strip -->
        <div class="policy-strip">
            <div class="policy-strip-inner">
                <div class="policy-strip-left">
                    <div class="policy-number-label">Policy Number</div>
                    <div class="policy-number">{$policyNumber}</div>
                </div>
                <div class="policy-strip-right">
                    <span class="status-badge">● ACTIVE</span>
                </div>
            </div>
        </div>

        <!-- Policy Details -->
        <table class="details-grid">
            <tr>
                <td class="detail-label">Policy Holder</td>
                <td class="detail-value">{$customerName}</td>
            </tr>
            <tr>
                <td class="detail-label">Email</td>
                <td class="detail-value">{$customerEmail}</td>
            </tr>
            <tr>
                <td class="detail-label">Plan</td>
                <td class="detail-value">{$planLabel}</td>
            </tr>
            <tr>
                <td class="detail-label">Vehicle Type</td>
                <td class="detail-value">{$vehicleLabel}</td>
            </tr>
            <tr>
                <td class="detail-label">State / Territory</td>
                <td class="detail-value">{$state}</td>
            </tr>
            <tr>
                <td class="detail-label">Coverage Period</td>
                <td class="detail-value">{$startDate} — {$endDate} ({$days} days)</td>
            </tr>
            <tr>
                <td class="detail-label">Issue Date</td>
                <td class="detail-value">{$createdAt}</td>
            </tr>
            <tr>
                <td class="detail-label">Daily Rate</td>
                <td class="detail-value">AUD \${$pricePerDay} / day</td>
            </tr>
            <tr>
                <td class="detail-label">Total Premium Paid</td>
                <td class="detail-value" style="font-size:14px;font-weight:800;color:#1E7FD8">AUD \${$totalPrice}</td>
            </tr>
        </table>

        <!-- Coverage Box -->
        <div class="coverage-box">
            <div class="coverage-box-inner">
                <div class="coverage-main">
                    <div class="coverage-label">Maximum Coverage Limit</div>
                    <div class="coverage-amount">AUD \${$coverageAmount}</div>
                </div>
                <div class="coverage-side">
                    <div class="excess-badge">\$0 EXCESS</div>
                </div>
            </div>
        </div>

        <!-- Two Column: Features + Exclusions -->
        <table class="two-col">
            <tr>
                <td>
                    <div class="section-head">What's Covered — {$planLabel} Plan</div>
                    <table class="features-table">
                        {$featuresHtml}
                    </table>
                </td>
                <td>
                    <div class="section-head">General Exclusions</div>
                    <table class="features-table">
                        {$exclusionsHtml}
                    </table>
                </td>
            </tr>
        </table>

        <!-- At the Rental Counter -->
        <div class="claims-box">
            <div class="claims-title">📋 At the Rental Counter</div>
            <div class="claims-text">
                When the rental company staff offer Collision Damage Waiver (CDW) or Loss Damage Waiver (LDW), you may politely decline. 
                You are already covered by your Rental Shield policy. Simply show this certificate if requested.
            </div>
        </div>

        <!-- How to Claim -->
        <div class="notice-box">
            <div class="notice-title">How to Make a Claim</div>
            <div class="notice-text">
                1. Report the incident to the rental company immediately and obtain documentation.<br>
                2. Lodge your claim within 30 days via your dashboard at {$appUrl}/my-claims.html or email info@rentalshield.com.au.<br>
                3. Provide: rental agreement, damage report, excess invoice/receipt, photos of damage, police report (if applicable).<br>
                4. Claims are acknowledged within 24 hours and assessed within 5 business days. Approved claims are paid within 3 business days.
            </div>
        </div>

        <!-- Terms & Conditions Summary -->
        <div class="terms">
            <p><strong style="color:#6B7280">Terms & Conditions:</strong> This policy is subject to the full Terms and Conditions and Product Disclosure Statement (PDS) available at {$appUrl}/terms.html and {$appUrl}/pds.html. By purchasing this policy, you confirm that you have read, understood, and agreed to these terms.</p>
            <p><strong style="color:#6B7280">Cancellation:</strong> You may cancel this policy at any time before your rental pickup date for a full refund. After the rental period has commenced, partial refunds may be offered for unused coverage days. Policies with a lodged or paid claim are not eligible for refund.</p>
            <p><strong style="color:#6B7280">Governing Law:</strong> This policy is governed by the laws of New South Wales, Australia.</p>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p><strong>{$appName}</strong> · ABN: 19 686 732 043 · Level 25/6 Parramatta Sq, Parramatta NSW 2150</p>
        <p>info@rentalshield.com.au · {$appUrl}</p>
        <p>This is a computer-generated document. No signature is required.</p>
        <p>© {$year} {$appName}. All rights reserved.</p>
    </div>

</body>
</html>
HTML;
    }
}
