<?php
// ============================================================
// Rental Shield — Email Helper (PHPMailer SMTP)
// Premium branded email templates
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config/config.php';

class Mailer {
    private static function create(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        if (APP_ENV !== 'production') {
            $mail->SMTPDebug = 0;
        }

        return $mail;
    }

    /**
     * Send a generic email
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
        try {
            $mail = self::create();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = self::wrap($subject, $htmlBody);
            $mail->AltBody = $textBody ?? strip_tags($htmlBody);
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send policy confirmation email
     */
    public static function sendPolicyConfirmation(array $policy, string $email, string $name): bool {
        $subject = "Your Rental Shield Policy #{$policy['policy_number']}";
        $body = "
            <div style='text-align:center;margin-bottom:24px'>
                <div style='width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#10B981,#059669);margin:0 auto 16px;display:flex;align-items:center;justify-content:center'>
                    <span style='font-size:28px;color:#fff;line-height:64px'>&#10003;</span>
                </div>
                <h2 style='color:#0B1E3D;margin:0 0 4px;font-size:22px'>Policy Confirmed</h2>
                <p style='color:#6B7280;margin:0;font-size:14px'>Your coverage is now active</p>
            </div>

            <p style='color:#374151;font-size:15px;line-height:1.6'>Hi {$name},</p>
            <p style='color:#374151;font-size:15px;line-height:1.6'>Your rental car insurance policy has been activated. Here are the details:</p>

            <div style='background:#F8FAFC;border:1px solid #E5E7EB;border-radius:12px;padding:20px;margin:20px 0'>
                <table style='width:100%;border-collapse:collapse'>
                    <tr>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Policy Number</td>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;font-weight:700;color:#0B1E3D;text-align:right;font-family:monospace;font-size:15px'>{$policy['policy_number']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Coverage Limit</td>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;font-weight:700;color:#0B1E3D;text-align:right;font-size:15px'>\${$policy['coverage_amount']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>State</td>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#374151;text-align:right;font-size:15px'>{$policy['state']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Period</td>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#374151;text-align:right;font-size:15px'>{$policy['start_date']} &mdash; {$policy['end_date']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Excess</td>
                        <td style='padding:10px 0;border-bottom:1px solid #E5E7EB;font-weight:700;color:#10B981;text-align:right;font-size:15px'>\$0</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 0;color:#6B7280;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em'>Total Paid</td>
                        <td style='padding:10px 0;font-weight:800;color:#E8003A;text-align:right;font-size:18px'>\${$policy['total_price']}</td>
                    </tr>
                </table>
            </div>

            <div style='background:#FFFBEB;border:1px solid #FDE68A;border-left:4px solid #F59E0B;border-radius:8px;padding:16px;margin:20px 0'>
                <p style='margin:0 0 4px;font-weight:700;color:#92400E;font-size:14px'>At the Rental Counter</p>
                <p style='margin:0;color:#78350F;font-size:13px;line-height:1.5'>When staff offer CDW or LDW waivers, politely decline. You are already fully covered by Rental Shield.</p>
            </div>

            <div style='text-align:center;margin:28px 0 16px'>
                <a href='" . APP_URL . "/my-policies.html' style='display:inline-block;background:linear-gradient(135deg,#E8003A,#C7002F);color:#fff;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none'>View My Policy</a>
            </div>

            <p style='color:#6B7280;font-size:13px;text-align:center;margin-top:20px'>You can download your Certificate of Insurance anytime from your dashboard.</p>
        ";
        return self::send($email, $subject, $body);
    }

    /**
     * Send claim status update email
     */
    public static function sendClaimUpdate(array $claim, string $email, string $name): bool {
        $statusConfig = [
            'submitted'    => ['label' => 'Submitted',    'color' => '#6B7280', 'bg' => '#F3F4F6'],
            'under_review' => ['label' => 'Under Review', 'color' => '#D97706', 'bg' => '#FFFBEB'],
            'approved'     => ['label' => 'Approved',     'color' => '#059669', 'bg' => '#ECFDF5'],
            'denied'       => ['label' => 'Denied',       'color' => '#DC2626', 'bg' => '#FEF2F2'],
            'paid'         => ['label' => 'Paid',         'color' => '#1D4ED8', 'bg' => '#EFF6FF'],
        ];
        $cfg = $statusConfig[$claim['status']] ?? ['label' => $claim['status'], 'color' => '#6B7280', 'bg' => '#F3F4F6'];
        $subject = "Claim #{$claim['claim_number']} — {$cfg['label']}";
        $body = "
            <h2 style='color:#0B1E3D;margin:0 0 16px;font-size:20px'>Claim Status Update</h2>
            <p style='color:#374151;font-size:15px;line-height:1.6'>Hi {$name},</p>
            <p style='color:#374151;font-size:15px;line-height:1.6'>Your claim <strong>#{$claim['claim_number']}</strong> has been updated to:</p>

            <div style='text-align:center;padding:20px;margin:20px 0;background:{$cfg['bg']};border-radius:12px'>
                <span style='font-size:22px;font-weight:800;color:{$cfg['color']}'>{$cfg['label']}</span>
            </div>

            " . (!empty($claim['admin_notes']) ? "<div style='background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:16px;margin:16px 0'>
                <p style='margin:0 0 4px;font-weight:700;color:#374151;font-size:13px;text-transform:uppercase;letter-spacing:0.05em'>Notes from our team</p>
                <p style='margin:0;color:#374151;font-size:14px;line-height:1.5'>{$claim['admin_notes']}</p>
            </div>" : "") . "

            <div style='text-align:center;margin:28px 0 16px'>
                <a href='" . APP_URL . "/my-claims.html' style='display:inline-block;background:linear-gradient(135deg,#E8003A,#C7002F);color:#fff;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;text-decoration:none'>View My Claims</a>
            </div>

            <p style='color:#374151;font-size:14px;margin-top:20px'>Best regards,<br><strong>Rental Shield Claims Team</strong></p>
        ";
        return self::send($email, $subject, $body);
    }

    /**
     * Wrap content in premium branded email template
     */
    private static function wrap(string $title, string $content): string {
        $appUrl = APP_URL;
        $appName = APP_NAME;
        $year = date('Y');

        return "
        <!DOCTYPE html>
        <html lang='en' xmlns='http://www.w3.org/1999/xhtml' xmlns:v='urn:schemas-microsoft-com:vml' xmlns:o='urn:schemas-microsoft-com:office:office'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta name='color-scheme' content='light dark'>
            <meta name='supported-color-schemes' content='light dark'>
            <style>
                :root {
                    color-scheme: light dark;
                }
                body {
                    margin: 0; padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
                    background-color: #F0F2F5;
                    -webkit-font-smoothing: antialiased;
                }
                a { color: #1E7FD8; text-decoration: none; }
                
                @media (prefers-color-scheme: dark) {
                    body, .email-body { background-color: #111827 !important; }
                    .content-wrapper { background-color: #1F2937 !important; border-color: #374151 !important; }
                    .footer-wrapper { background-color: #111827 !important; border-color: #374151 !important; }
                    
                    /* Force text colors for dark mode */
                    h1, h2, h3, h4, p, td, span, strong, div { color: #E5E7EB !important; border-color: #374151 !important; }
                    
                    /* Except for highly specific colored badges/boxes */
                    .badge-text { color: inherit !important; }
                    .keep-white { color: #ffffff !important; }
                    
                    /* Dynamic boxes */
                    .info-box { background-color: #374151 !important; border-color: #4B5563 !important; }
                    .warning-box { background-color: #422006 !important; border-color: #78350F !important; }
                    .table-row td { border-bottom-color: #374151 !important; }
                }
            </style>
        </head>
        <body class='email-body'>
            <div style='max-width:600px;margin:0 auto;padding:32px 16px'>

                <!-- Header -->
                <div style='background:linear-gradient(135deg,#0B1E3D 0%,#1A3A5C 100%);border-radius:16px 16px 0 0;padding:32px 40px;text-align:center'>
                    <table cellpadding='0' cellspacing='0' border='0' style='margin:0 auto'>
                        <tr>
                            <td style='vertical-align:middle;text-align:center' class='keep-white'>
                                <img src='{$appUrl}/assets/images/logo.png' alt='Rental Shield' style='height:48px;width:auto;display:block'>
                            </td>
                        </tr>
                    </table>
                    <p class='keep-white' style='color:rgba(255,255,255,0.7) !important;font-size:12px;margin:12px 0 0;letter-spacing:0.1em;text-transform:uppercase;font-weight:600'>Rental Car Excess Insurance</p>
                </div>

                <!-- Content -->
                <div class='content-wrapper' style='background:#ffffff;padding:36px 40px;border-left:1px solid #E5E7EB;border-right:1px solid #E5E7EB'>
                    {$content}
                </div>

                <!-- Footer -->
                <div class='footer-wrapper' style='background:#F8FAFC;border-radius:0 0 16px 16px;padding:28px 40px;border:1px solid #E5E7EB;border-top:none;text-align:center'>
                    <table cellpadding='0' cellspacing='0' border='0' style='margin:0 auto 16px'>
                        <tr>
                            <td style='padding:0 8px'><a href='{$appUrl}' style='color:#6B7280;font-size:12px;font-weight:500'>Website</a></td>
                            <td style='color:#D1D5DB;font-size:12px'>|</td>
                            <td style='padding:0 8px'><a href='{$appUrl}/my-policies.html' style='color:#6B7280;font-size:12px;font-weight:500'>My Policies</a></td>
                            <td style='color:#D1D5DB;font-size:12px'>|</td>
                            <td style='padding:0 8px'><a href='{$appUrl}/my-claims.html' style='color:#6B7280;font-size:12px;font-weight:500'>My Claims</a></td>
                            <td style='color:#D1D5DB;font-size:12px'>|</td>
                            <td style='padding:0 8px'><a href='mailto:" . MAIL_SUPPORT . "' style='color:#6B7280;font-size:12px;font-weight:500'>Support</a></td>
                        </tr>
                    </table>
                    <p style='color:#9CA3AF;font-size:11px;margin:0 0 6px;line-height:1.5'>
                        {$appName} | ABN 19 686 732 043
                    </p>
                    <p style='color:#9CA3AF;font-size:11px;margin:0 0 6px;line-height:1.5'>
                        <a href='mailto:" . MAIL_SUPPORT . "' style='color:#9CA3AF;text-decoration:underline'>" . MAIL_SUPPORT . "</a> &bull; <a href='{$appUrl}' style='color:#9CA3AF;text-decoration:underline'>rentalshield.com.au</a>
                    </p>
                    <p style='color:#D1D5DB;font-size:10px;margin:12px 0 0'>
                        &copy; {$year} {$appName}. All rights reserved. This is an automated message.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
