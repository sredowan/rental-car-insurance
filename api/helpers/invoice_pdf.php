<?php
// ============================================================
// Rental Shield — GST-inclusive Invoice PDF Generator
// ============================================================

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/config.php';

class InvoicePDF {
    public static function generate(array $policy): string {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::template($policy));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function e($value): string {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private static function money($value): string {
        return 'AUD $' . number_format((float) $value, 2);
    }

    private static function dateLabel($value): string {
        $ts = strtotime((string) $value);
        return $ts ? date('d M Y', $ts) : '-';
    }

    private static function planLabel(string $plan): string {
        if (function_exists('get_coverage_plans')) {
            $plans = get_coverage_plans();
        } else {
            $plans = defined('COVERAGE_PLANS') ? COVERAGE_PLANS : [];
        }
        return $plans[$plan]['label'] ?? ucfirst($plan ?: 'Essential');
    }

    private static function vehicleLabel(string $type): string {
        $labels = [
            'car'       => 'Car (Sedan / Hatchback / Wagon)',
            'campervan' => 'Campervan',
            'motorhome' => 'Motorhome / RV',
            'bus'       => 'Bus / Small Coach',
            '4x4'       => '4x4 / SUV',
        ];
        return $labels[$type] ?? ucfirst($type ?: 'Car');
    }

    private static function logoHtml(string $appName): string {
        $logoPath = __DIR__ . '/../../assets/images/logo-pdf.jpg';
        if (!is_file($logoPath)) {
            $logoPath = __DIR__ . '/../../assets/images/logo.png';
        }
        if (!is_file($logoPath)) {
            return '<div class="logo-fallback">Rental Shield</div>';
        }
        $logoMime = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)) === 'jpg' ? 'image/jpeg' : 'image/png';
        $src = 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
        return '<img src="' . $src . '" class="logo-img" alt="' . self::e($appName) . '">';
    }

    private static function template(array $policy): string {
        $appName = self::e(APP_NAME);
        $appUrl = self::e(APP_URL);
        $logoHtml = self::logoHtml(APP_NAME);

        $policyNumber = self::e($policy['policy_number'] ?? '-');
        $invoiceNumber = 'INV-' . preg_replace('/[^A-Za-z0-9]/', '', (string) ($policy['policy_number'] ?? date('YmdHis')));
        $customerName = self::e($policy['customer_name'] ?? '-');
        $customerEmail = self::e($policy['customer_email'] ?? '-');
        $plan = self::e(self::planLabel((string) ($policy['plan'] ?? 'essential')));
        $vehicle = self::e(self::vehicleLabel((string) ($policy['vehicle_type'] ?? 'car')));
        $state = self::e($policy['state'] ?? '-');
        $days = max(1, (int) ($policy['days'] ?? 1));
        $pricePerDay = (float) ($policy['price_per_day'] ?? 0);
        $total = (float) ($policy['total_price'] ?? $policy['payment_amount'] ?? 0);
        $gst = round($total / 11, 2);
        $subtotal = round($total - $gst, 2);
        $pricePerDayLabel = self::money($pricePerDay);
        $subtotalLabel = self::money($subtotal);
        $gstLabel = self::money($gst);
        $totalLabel = self::money($total);
        $paymentRef = self::e($policy['payment_reference'] ?? '-');
        $issueDate = self::dateLabel($policy['created_at'] ?? 'now');
        $period = self::dateLabel($policy['start_date'] ?? '') . ' - ' . self::dateLabel($policy['end_date'] ?? '');
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0 0 82px 0; }
    body { font-family: Helvetica, Arial, sans-serif; margin: 0; color: #1F2937; font-size: 12px; line-height: 1.5; background: #FFFFFF; }
    .top-accent { height: 8px; background: #0B2A6F; border-bottom: 3px solid #1E88E5; }
    .header { padding: 26px 40px 24px; border-bottom: 1px solid #DCEAF8; background: #FFFFFF; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: middle; }
    .brand-card { display: inline-block; min-width: 248px; min-height: 72px; padding: 0; background: #FFFFFF; }
    .logo-img { max-width: 240px; max-height: 72px; }
    .invoice-title { text-align: right; }
    .invoice-title h1 { margin: 0; font-size: 34px; color: #102A6B; letter-spacing: -1px; }
    .invoice-title p { display: inline-block; margin: 8px 0 0; color: #FFFFFF; background: #1E88E5; border-radius: 999px; padding: 6px 14px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.3px; font-weight: 900; }
    .content { padding: 30px 40px 20px; }
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    .summary td { width: 50%; vertical-align: top; }
    .panel { border: 1px solid #DCEAF8; border-radius: 14px; padding: 17px 19px; min-height: 96px; background: #FFFFFF; }
    .panel h3 { margin: 0 0 10px; color: #1E88E5; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
    .panel p { margin: 0 0 5px; color: #334155; }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 5px 0; border-bottom: 1px solid #F1F5F9; }
    .label { color: #94A3B8; font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; font-weight: 800; }
    .value { text-align: right; color: #102A6B; font-weight: 800; }
    .items { width: 100%; border-collapse: collapse; margin-top: 8px; border: 1px solid #DCEAF8; border-radius: 12px; }
    .items th { background: #102A6B; color: #FFFFFF; padding: 12px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; }
    .items td { padding: 13px 12px; border-bottom: 1px solid #F1F5F9; vertical-align: top; }
    .items .right { text-align: right; }
    .desc strong { display: block; color: #102A6B; margin-bottom: 3px; }
    .desc span { color: #64748B; font-size: 10px; }
    .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 18px; }
    .totals-left { width: 55%; vertical-align: top; padding-right: 18px; }
    .totals-right { width: 45%; vertical-align: top; }
    .gst-note { background: #F0F7FF; border: 1px solid #BFDBFE; border-left: 5px solid #1E88E5; border-radius: 10px; padding: 14px 15px; color: #1E3A8A; font-size: 10px; }
    .total-table { width: 100%; border-collapse: collapse; border: 1px solid #DCEAF8; }
    .total-table td { padding: 9px 12px; border-bottom: 1px solid #F1F5F9; }
    .total-table .amount { text-align: right; font-weight: 800; }
    .grand td { background: #102A6B; color: #FFFFFF; font-size: 15px; font-weight: 900; }
    .paid { display: inline-block; margin-top: 12px; background: #DCFCE7; color: #166534; padding: 7px 14px; border-radius: 999px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 900; }
    .payment-strip { margin-top: 22px; background: #F8FBFF; border: 1px solid #DCEAF8; border-radius: 12px; padding: 13px 16px; color: #475569; font-size: 10px; }
    .footer { position: fixed; bottom: -82px; left: 0; right: 0; height: 58px; padding: 12px 40px; border-top: 2px solid #102A6B; background: #F8FAFC; text-align: center; }
    .footer p { margin: 0 0 3px; color: #94A3B8; font-size: 8.5px; }
</style>
</head>
<body>
    <div class="top-accent"></div>
    <div class="footer">
        <p><strong>{$appName}</strong> · ABN: 19 686 732 043 · Level 25/6 Parramatta Sq, Parramatta NSW 2150</p>
        <p>info@rentalshield.com.au · {$appUrl} · © {$year} {$appName}. All rights reserved.</p>
    </div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td><div class="brand-card">{$logoHtml}</div></td>
                <td class="invoice-title"><h1>Tax Invoice</h1><p>GST Included</p></td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="summary">
            <tr>
                <td style="padding-right:12px">
                    <div class="panel">
                        <h3>Billed To</h3>
                        <p><strong>{$customerName}</strong></p>
                        <p>{$customerEmail}</p>
                        <p>Policy: {$policyNumber}</p>
                    </div>
                </td>
                <td style="padding-left:12px">
                    <div class="panel">
                        <h3>Invoice Details</h3>
                        <table class="meta-table">
                            <tr><td class="label">Invoice No.</td><td class="value">{$invoiceNumber}</td></tr>
                            <tr><td class="label">Issue Date</td><td class="value">{$issueDate}</td></tr>
                            <tr><td class="label">Payment Ref.</td><td class="value">{$paymentRef}</td></tr>
                            <tr><td class="label">Status</td><td class="value" style="color:#059669">Paid</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="width:58px" class="right">Days</th>
                    <th style="width:82px" class="right">Rate</th>
                    <th style="width:92px" class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="desc">
                        <strong>{$plan} Rental Vehicle Excess Insurance</strong>
                        <span>{$vehicle} · {$state} · {$period}</span>
                    </td>
                    <td class="right">{$days}</td>
                    <td class="right">{$pricePerDayLabel}</td>
                    <td class="right"><strong>{$totalLabel}</strong></td>
                </tr>
            </tbody>
        </table>

        <table class="totals-wrap">
            <tr>
                <td class="totals-left">
                    <div class="gst-note">
                        GST is included in the total premium paid. This invoice records payment received for the Rental Shield policy certificate listed above.
                    </div>
                    <div class="paid">Paid by card</div>
                </td>
                <td class="totals-right">
                    <table class="total-table">
                        <tr><td>Subtotal excl. GST</td><td class="amount">{$subtotalLabel}</td></tr>
                        <tr><td>GST included</td><td class="amount">{$gstLabel}</td></tr>
                        <tr class="grand"><td>Total Paid</td><td class="amount">{$totalLabel}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="payment-strip">
            This tax invoice confirms payment received for policy <strong>{$policyNumber}</strong>. GST is included in the total premium paid.
        </div>
    </div>
</body>
</html>
HTML;
    }
}
