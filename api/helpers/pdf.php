<?php
// ============================================================
// DriveSafe Cover — PDF Policy Certificate Generator
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
        header("Content-Disposition: attachment; filename=\"DriveSafe_Policy_{$policy['policy_number']}.pdf\"");
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /**
     * HTML template
     */
    private static function template(array $policy): string {
        $policyNumber   = $policy['policy_number'] ?? '—';
        $customerName   = $policy['customer_name'] ?? '—';
        $coverageAmount = number_format($policy['coverage_amount'] ?? 0);
        $totalPrice     = number_format($policy['total_price'] ?? 0, 2);
        $state          = $policy['state'] ?? '—';
        $startDate      = date('d M Y', strtotime($policy['start_date'] ?? 'now'));
        $endDate        = date('d M Y', strtotime($policy['end_date'] ?? 'now'));
        $createdAt      = date('d M Y H:i', strtotime($policy['created_at'] ?? 'now'));
        $appName        = APP_NAME;
        $year           = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; margin: 0; padding: 40px; font-size: 13px; line-height: 1.6; }
    .header { text-align: center; border-bottom: 3px solid #E8003A; padding-bottom: 20px; margin-bottom: 30px; }
    .logo { font-size: 28px; font-weight: 800; color: #0B1E3D; }
    .logo span { color: #E8003A; }
    .subtitle { font-size: 12px; color: #6B7280; letter-spacing: 2px; text-transform: uppercase; margin-top: 6px; }
    .cert-title { font-size: 22px; font-weight: 800; color: #0B1E3D; text-align: center; margin: 24px 0 8px; }
    .cert-number { text-align: center; font-size: 14px; color: #E8003A; font-weight: 700; margin-bottom: 24px; }
    table.details { width: 100%; border-collapse: collapse; margin: 20px 0; }
    table.details td { padding: 10px 14px; border-bottom: 1px solid #E5E7EB; }
    table.details td:first-child { font-weight: 600; color: #6B7280; width: 40%; }
    table.details td:last-child { font-weight: 700; color: #1f2937; }
    .coverage-box { background: #FEF3F2; border: 2px solid #E8003A; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
    .coverage-amount { font-size: 32px; font-weight: 800; color: #E8003A; }
    .coverage-label { font-size: 12px; color: #6B7280; text-transform: uppercase; letter-spacing: 1px; }
    .terms { font-size: 11px; color: #9CA3AF; margin-top: 24px; padding-top: 16px; border-top: 1px solid #E5E7EB; }
    .terms h4 { color: #6B7280; font-size: 12px; margin-bottom: 8px; }
    .footer { text-align: center; margin-top: 32px; padding-top: 16px; border-top: 2px solid #0B1E3D; font-size: 11px; color: #9CA3AF; }
    .badge { display: inline-block; background: #DCFCE7; color: #166534; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; }
</style>
</head>
<body>
    <div class="header">
        <div class="logo"><span>🛡️</span> {$appName}</div>
        <div class="subtitle">Certificate of Insurance</div>
    </div>

    <div class="cert-title">Policy Certificate</div>
    <div class="cert-number">{$policyNumber}</div>

    <table class="details">
        <tr><td>Policy Holder</td><td>{$customerName}</td></tr>
        <tr><td>Policy Number</td><td>{$policyNumber}</td></tr>
        <tr><td>Status</td><td><span class="badge">ACTIVE</span></td></tr>
        <tr><td>State / Territory</td><td>{$state}</td></tr>
        <tr><td>Coverage Period</td><td>{$startDate} — {$endDate}</td></tr>
        <tr><td>Issue Date</td><td>{$createdAt}</td></tr>
        <tr><td>Premium Paid</td><td>AUD \${$totalPrice}</td></tr>
    </table>

    <div class="coverage-box">
        <div class="coverage-label">Maximum Coverage</div>
        <div class="coverage-amount">AUD \${$coverageAmount}</div>
        <div style="font-size:12px;color:#6B7280;margin-top:4px">\$0 Excess on Every Claim</div>
    </div>

    <div class="terms">
        <h4>What's Covered</h4>
        <p>This policy covers damage to or theft of any rental vehicle during the coverage period above, including collision damage, windscreen, tyres, undercarriage, key loss and theft. Cover applies to vehicles rented from any recognised rental company within Australia.</p>
        <h4>Conditions</h4>
        <p>This certificate is subject to the Product Disclosure Statement (PDS) and the terms and conditions available at drivesafecover.com.au/pds. The policyholder must report any incident within 48 hours and provide supporting documentation.</p>
    </div>

    <div class="footer">
        <p><strong>{$appName}</strong> | AFSL Authorised | ABN XX XXX XXX XXX</p>
        <p>This is a computer-generated document. No signature required.</p>
        <p>© {$year} {$appName}. All rights reserved.</p>
    </div>
</body>
</html>
HTML;
    }
}
