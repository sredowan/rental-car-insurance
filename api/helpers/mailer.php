<?php
// ============================================================
// Rental Shield - Email Helper (PHPMailer SMTP)
// Premium branded email templates
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config/config.php';

class Mailer {
    private static function setting(string $key, $default = null) {
        if (function_exists('get_setting')) {
            return get_setting($key, $default);
        }
        if (!class_exists('Database')) {
            return $default;
        }
        try {
            $db = Database::get();
            $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false && $value !== null && $value !== '' ? $value : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    private static function create(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = self::setting('mail_host', MAIL_HOST);
        $mail->Port       = (int) self::setting('mail_port', MAIL_PORT);
        $mail->SMTPAuth   = true;
        $mail->Username   = self::setting('mail_username', MAIL_USERNAME);
        $mail->Password   = self::setting('mail_password', MAIL_PASSWORD);
        $encryption = self::setting('mail_encryption', MAIL_ENCRYPTION);
        if ($encryption === 'none') {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->setFrom(self::setting('mail_from_email', MAIL_FROM_EMAIL), self::setting('mail_from_name', MAIL_FROM_NAME));
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        if (APP_ENV !== 'production') {
            $mail->SMTPDebug = 0;
        }

        return $mail;
    }

    private static function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function money($value): string {
        return '$' . number_format((float) $value, 2);
    }

    private static function dateLabel($value): string {
        if (!$value) return '-';
        $time = strtotime((string) $value);
        return $time ? date('d M Y', $time) : self::e($value);
    }

    private static function policyLink(): string {
        return self::setting('app_url', APP_URL) . '/login.html?next=my-policies';
    }

    private static function iconBadge(string $type = 'shield'): string {
        $configs = [
            'shield'  => ['bg' => '#EEF6FF', 'border' => '#CFE7FF', 'color' => '#1E7FD8', 'mark' => 'RS'],
            'success' => ['bg' => '#ECFDF5', 'border' => '#A7F3D0', 'color' => '#059669', 'mark' => 'OK'],
            'secure'  => ['bg' => '#F5F3FF', 'border' => '#DDD6FE', 'color' => '#6D28D9', 'mark' => 'ID'],
            'support' => ['bg' => '#EFF6FF', 'border' => '#BFDBFE', 'color' => '#2563EB', 'mark' => 'IN'],
            'alert'   => ['bg' => '#FFFBEB', 'border' => '#FDE68A', 'color' => '#D97706', 'mark' => 'i'],
        ];
        $cfg = $configs[$type] ?? $configs['shield'];

        return "<div style='width:58px;height:58px;border-radius:18px;background:{$cfg['bg']};border:1px solid {$cfg['border']};margin:0 auto 16px;text-align:center;line-height:58px;font-size:17px;font-weight:900;color:{$cfg['color']};letter-spacing:0.04em'>{$cfg['mark']}</div>";
    }

    private static function sectionTitle(string $title, string $subtitle = '', string $icon = 'shield'): string {
        $subtitleHtml = $subtitle !== '' ? "<p style='color:#64748B;margin:0;font-size:14px;line-height:1.5'>" . self::e($subtitle) . "</p>" : '';
        return "
            <div style='text-align:center;margin-bottom:26px'>
                " . self::iconBadge($icon) . "
                <h2 style='color:#0B1E3D;margin:0 0 6px;font-size:24px;line-height:1.2;font-weight:850'>" . self::e($title) . "</h2>
                {$subtitleHtml}
            </div>
        ";
    }

    private static function detailRow(string $label, string $value, bool $last = false, string $color = '#0B1E3D'): string {
        $border = $last ? 'none' : '1px solid #E5E7EB';
        return "
            <tr>
                <td style='padding:12px 0;border-bottom:{$border};color:#64748B;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em'>" . self::e($label) . "</td>
                <td style='padding:12px 0;border-bottom:{$border};font-weight:800;color:{$color};text-align:right;font-size:15px'>" . self::e($value) . "</td>
            </tr>
        ";
    }

    private static function cta(string $label, string $href): string {
        return "
            <div style='text-align:center;margin:30px 0 18px'>
                <a href='" . self::e($href) . "' style='display:inline-block;background:#E8003A;color:#ffffff;padding:15px 34px;border-radius:10px;font-weight:850;font-size:15px;text-decoration:none;box-shadow:0 10px 24px rgba(232,0,58,0.22)'>" . self::e($label) . "</a>
            </div>
        ";
    }

    private static function infoPanel(string $title, string $body, string $accent = '#1E7FD8'): string {
        return "
            <div style='background:#F8FAFC;border:1px solid #E5E7EB;border-left:4px solid {$accent};border-radius:12px;padding:17px 18px;margin:22px 0'>
                <p style='margin:0 0 6px;font-weight:850;color:#0B1E3D;font-size:14px'>" . self::e($title) . "</p>
                <p style='margin:0;color:#475569;font-size:13px;line-height:1.6'>" . self::e($body) . "</p>
            </div>
        ";
    }

    /**
     * Send a generic email.
     *
     * Attachments support:
     * - ['path' => '/path/file.pdf', 'name' => 'file.pdf']
     * - ['string' => $bytes, 'name' => 'file.pdf', 'encoding' => 'base64', 'type' => 'application/pdf']
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = []): bool {
        try {
            $mail = self::create();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = self::wrap($subject, $htmlBody);
            $mail->AltBody = $textBody ?? trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));

            foreach ($attachments as $attachment) {
                if (!empty($attachment['string']) && !empty($attachment['name'])) {
                    $mail->addStringAttachment(
                        $attachment['string'],
                        $attachment['name'],
                        $attachment['encoding'] ?? 'base64',
                        $attachment['type'] ?? 'application/octet-stream'
                    );
                } elseif (!empty($attachment['path']) && is_file($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
                }
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log('Email error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }

    public static function sendPolicyConfirmation(array $policy, string $email, string $name): bool {
        $policyNumber = self::e($policy['policy_number'] ?? '');
        $nameLabel = self::e($name ?: 'there');
        $coverage = '$' . number_format((float) ($policy['coverage_amount'] ?? 100000), 0);
        $period = self::dateLabel($policy['start_date'] ?? '') . ' - ' . self::dateLabel($policy['end_date'] ?? '');
        $subject = "Your Rental Shield policy {$policyNumber}";

        $body = self::sectionTitle('Policy Confirmed', 'Your rental car excess cover is ready.', 'success') . "
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0 0 12px'>Hi {$nameLabel},</p>
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0'>Your Rental Shield policy is active. Your policy certificate and GST-inclusive tax invoice are attached to this email.</p>

            <div style='background:#FFFFFF;border:1px solid #E5E7EB;border-radius:14px;padding:20px;margin:24px 0;box-shadow:0 8px 24px rgba(15,23,42,0.04)'>
                <table style='width:100%;border-collapse:collapse'>
                    " . self::detailRow('Policy Number', $policy['policy_number'] ?? '-') . "
                    " . self::detailRow('Coverage Limit', $coverage) . "
                    " . self::detailRow('Plan', ucfirst((string) ($policy['plan'] ?? 'Essential'))) . "
                    " . self::detailRow('Location', $policy['state'] ?? '-') . "
                    " . self::detailRow('Period', $period) . "
                    " . self::detailRow('Excess', '$0', false, '#059669') . "
                    " . self::detailRow('Total Paid', self::money($policy['total_price'] ?? 0), true, '#E8003A') . "
                </table>
            </div>

            " . self::infoPanel('At the rental counter', 'If rental staff offer CDW or LDW waivers, you can politely decline. Keep this email and attached policy certificate handy during pickup.', '#F59E0B') . "
            " . self::cta('View My Policy', self::policyLink()) . "
            <p style='color:#64748B;font-size:12px;line-height:1.6;text-align:center;margin:0'>You may need to sign in first. Your policy certificate and invoice are also attached as PDFs.</p>
        ";

        $attachments = [];
        try {
            require_once __DIR__ . '/pdf.php';
            require_once __DIR__ . '/invoice_pdf.php';
            if (class_exists('PolicyPDF')) {
                $pdfPolicy = array_merge($policy, [
                    'customer_name'  => $name,
                    'customer_email' => $email,
                ]);
                $attachments[] = [
                    'string' => PolicyPDF::generate($pdfPolicy),
                    'name'   => 'RentalShield_Policy_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($policy['policy_number'] ?? 'Certificate')) . '.pdf',
                    'type'   => 'application/pdf',
                ];
            }
            if (class_exists('InvoicePDF')) {
                $invoicePolicy = array_merge($policy, [
                    'customer_name'  => $name,
                    'customer_email' => $email,
                ]);
                $attachments[] = [
                    'string' => InvoicePDF::generate($invoicePolicy),
                    'name'   => 'RentalShield_Invoice_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($policy['policy_number'] ?? 'Invoice')) . '.pdf',
                    'type'   => 'application/pdf',
                ];
            }
        } catch (Throwable $e) {
            error_log('Policy PDF attachment error: ' . $e->getMessage());
        }

        return self::send($email, $subject, $body, null, $attachments);
    }

    public static function sendWelcomeAccount(string $email, string $name, string $temporaryPassword): bool {
        $nameLabel = self::e($name ?: 'there');
        $subject = 'Welcome to Rental Shield - Your Account';
        $body = self::sectionTitle('Your Account Is Ready', 'Use these details to access your dashboard.', 'shield') . "
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0 0 12px'>Hi {$nameLabel},</p>
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0'>We created your account so you can manage policies, download certificates, and lodge claims securely.</p>

            <div style='background:#FFFFFF;border:1px solid #E5E7EB;border-radius:14px;padding:20px;margin:24px 0'>
                <table style='width:100%;border-collapse:collapse'>
                    " . self::detailRow('Login Email', $email) . "
                    " . self::detailRow('Temporary Password', $temporaryPassword, true, '#E8003A') . "
                </table>
            </div>

            " . self::infoPanel('Passwordless sign in available', 'You can also choose Login with Email Code on the sign-in page and use a one-time passcode instead of your password.', '#1E7FD8') . "
            " . self::cta('Sign In to Dashboard', self::setting('app_url', APP_URL) . '/login.html?next=my-policies') . "
        ";

        return self::send($email, $subject, $body);
    }

    public static function sendLoginCode(string $email, string $otp): bool {
        $subject = 'Your Rental Shield login code';
        $expiryMinutes = max(1, (int) self::setting('otp_expiry_min', OTP_EXPIRY_MINUTES));
        $body = self::sectionTitle('Secure Login Code', 'Enter this code on the login page.', 'secure') . "
            <div style='background:#0B1E3D;border-radius:16px;padding:30px;text-align:center;margin:24px 0'>
                <p style='color:#AAB6C8;font-size:11px;text-transform:uppercase;letter-spacing:0.16em;font-weight:800;margin:0 0 10px'>One-time passcode</p>
                <div style='font-size:38px;font-weight:900;color:#FFFFFF;letter-spacing:8px;font-family:Menlo,Consolas,monospace'>" . self::e($otp) . "</div>
            </div>
            " . self::infoPanel('Security note', 'This code expires in ' . $expiryMinutes . ' minutes. If you did not request it, you can safely ignore this email.', '#E8003A') . "
        ";

        return self::send($email, $subject, $body);
    }

    public static function sendPasswordReset(string $email, string $name, string $resetLink): bool {
        $nameLabel = self::e($name ?: 'there');
        $subject = 'Reset your Rental Shield password';
        $body = self::sectionTitle('Password Reset', 'Use this secure link to choose a new password.', 'secure') . "
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0 0 12px'>Hi {$nameLabel},</p>
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0'>We received a request to reset your Rental Shield account password.</p>
            " . self::cta('Reset Password', $resetLink) . "
            " . self::infoPanel('Security note', 'This link expires in 1 hour. If you did not request it, you can safely ignore this email.', '#E8003A') . "
        ";

        return self::send($email, $subject, $body);
    }

    public static function sendSupportReply(string $email, string $name, string $message): bool {
        $nameLabel = self::e($name ?: 'there');
        $safeMessage = nl2br(self::e($message));
        $body = self::sectionTitle('Support Reply', 'A new message from Rental Shield Support.', 'support') . "
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0 0 12px'>Hi {$nameLabel},</p>
            <div style='background:#F8FAFC;border:1px solid #E5E7EB;border-left:4px solid #1E7FD8;border-radius:12px;padding:20px;margin:24px 0'>
                <p style='margin:0;color:#1E293B;font-size:15px;line-height:1.7'>{$safeMessage}</p>
            </div>
            <p style='color:#64748B;font-size:13px;line-height:1.6;margin:0'>You can reply to this email, or sign in to your dashboard to send a secure message.</p>
        ";

        return self::send($email, 'Rental Shield Support', $body);
    }

    public static function sendClaimUpdate(array $claim, string $email, string $name): bool {
        $statusConfig = [
            'submitted'    => ['label' => 'Submitted',    'color' => '#475569', 'bg' => '#F1F5F9'],
            'under_review' => ['label' => 'Under Review', 'color' => '#B45309', 'bg' => '#FFFBEB'],
            'approved'     => ['label' => 'Approved',     'color' => '#047857', 'bg' => '#ECFDF5'],
            'denied'       => ['label' => 'Denied',       'color' => '#B91C1C', 'bg' => '#FEF2F2'],
            'paid'         => ['label' => 'Paid',         'color' => '#1D4ED8', 'bg' => '#EFF6FF'],
        ];
        $cfg = $statusConfig[$claim['status']] ?? ['label' => $claim['status'], 'color' => '#475569', 'bg' => '#F1F5F9'];
        $subject = 'Claim ' . ($claim['claim_number'] ?? '') . ' - ' . $cfg['label'];
        $nameLabel = self::e($name ?: 'there');
        $notes = trim((string) ($claim['admin_notes'] ?? ''));

        $body = self::sectionTitle('Claim Status Update', 'Your claim has a new status.', 'support') . "
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0 0 12px'>Hi {$nameLabel},</p>
            <p style='color:#334155;font-size:15px;line-height:1.7;margin:0'>Claim <strong>" . self::e($claim['claim_number'] ?? '') . "</strong> has been updated.</p>

            <div style='text-align:center;padding:22px;margin:24px 0;background:{$cfg['bg']};border-radius:14px;border:1px solid rgba(15,23,42,0.06)'>
                <span style='font-size:22px;font-weight:900;color:{$cfg['color']}'>" . self::e($cfg['label']) . "</span>
            </div>

            " . ($notes !== '' ? self::infoPanel('Notes from our team', $notes, '#1E7FD8') : '') . "
            " . self::cta('View My Claims', self::setting('app_url', APP_URL) . '/login.html?next=my-claims') . "
        ";

        return self::send($email, $subject, $body);
    }

    private static function wrap(string $title, string $content): string {
        $appUrl = self::setting('app_url', APP_URL);
        $appName = self::setting('app_name', APP_NAME);
        $supportEmail = self::setting('support_email', MAIL_SUPPORT);
        $year = date('Y');
        $logoUrl = $appUrl . '/assets/images/logo.png';

        return "
        <!DOCTYPE html>
        <html lang='en' xmlns='http://www.w3.org/1999/xhtml'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . self::e($title) . "</title>
        </head>
        <body style='margin:0;padding:0;background:#EEF2F7;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased'>
            <div style='display:none;max-height:0;overflow:hidden;color:transparent;opacity:0'>" . self::e($title) . "</div>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#EEF2F7;margin:0;padding:0'>
                <tr>
                    <td align='center' style='padding:34px 14px'>
                        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='max-width:620px;border-collapse:collapse'>
                            <tr>
                                <td style='background:#FFFFFF;border-radius:22px 22px 0 0;padding:26px 34px;text-align:center;border:1px solid #E5E7EB;border-bottom:none'>
                                    <img src='{$logoUrl}' alt='Rental Shield' style='display:block;margin:0 auto;height:54px;width:auto;max-width:220px;background:#FFFFFF'>
                                    <p style='margin:14px 0 0;color:#64748B;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;font-weight:850'>Rental Car Excess Insurance</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background:#FFFFFF;padding:6px 34px 34px;border-left:1px solid #E5E7EB;border-right:1px solid #E5E7EB'>
                                    {$content}
                                </td>
                            </tr>
                            <tr>
                                <td style='background:#0B1E3D;border-radius:0 0 22px 22px;padding:26px 34px;text-align:center'>
                                    <table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto 16px'>
                                        <tr>
                                            <td style='padding:0 8px'><a href='{$appUrl}' style='color:#CBD5E1;font-size:12px;font-weight:700;text-decoration:none'>Website</a></td>
                                            <td style='color:#475569;font-size:12px'>|</td>
                                            <td style='padding:0 8px'><a href='{$appUrl}/login.html?next=my-policies' style='color:#CBD5E1;font-size:12px;font-weight:700;text-decoration:none'>My Policies</a></td>
                                            <td style='color:#475569;font-size:12px'>|</td>
                                            <td style='padding:0 8px'><a href='{$appUrl}/login.html?next=my-claims' style='color:#CBD5E1;font-size:12px;font-weight:700;text-decoration:none'>My Claims</a></td>
                                            <td style='color:#475569;font-size:12px'>|</td>
                                            <td style='padding:0 8px'><a href='mailto:{$supportEmail}' style='color:#CBD5E1;font-size:12px;font-weight:700;text-decoration:none'>Support</a></td>
                                        </tr>
                                    </table>
                                    <p style='color:#94A3B8;font-size:11px;margin:0 0 7px;line-height:1.6'>{$appName} | ABN 19 686 732 043</p>
                                    <p style='color:#94A3B8;font-size:11px;margin:0;line-height:1.6'>
                                        <a href='mailto:{$supportEmail}' style='color:#CBD5E1;text-decoration:underline'>{$supportEmail}</a> &nbsp;|&nbsp; <a href='{$appUrl}' style='color:#CBD5E1;text-decoration:underline'>rentalshield.com.au</a>
                                    </p>
                                    <p style='color:#64748B;font-size:10px;margin:14px 0 0'>&copy; {$year} {$appName}. All rights reserved.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
