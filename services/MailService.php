<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    public function isConfigured(): bool
    {
        return SettingsService::isGroupConfigured('smtp');
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('SMTP is not configured. Add Gmail SMTP settings in Admin → Settings.');
        }

        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = 'base64';
            $mail->isSMTP();
            $mail->Host = (string) SettingsService::get('smtp_host');
            $mail->SMTPAuth = true;
            $mail->Username = (string) SettingsService::get('smtp_username');
            $mail->Password = (string) SettingsService::get('smtp_password');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) (SettingsService::get('smtp_port') ?: 587);

            $fromEmail = (string) (SettingsService::get('smtp_from_email') ?: SettingsService::get('smtp_username'));
            $fromName = (string) (SettingsService::get('smtp_from_name') ?: config('app.name'));

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = self::emailSubject($subject);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?? self::emailPlainText(strip_tags($htmlBody));
            $mail->send();
        } catch (MailerException $e) {
            throw new \RuntimeException('Email could not be sent: ' . $mail->ErrorInfo);
        }
    }

    public function sendOtp(string $toEmail, string $toName, string $otp, string $purposeLabel): void
    {
        $appName = config('app.name');
        $subject = "$otp is your Abdu Market verification code";
        $html = $this->wrapTemplate(
            'Verification Code',
            "<p>Hi " . htmlspecialchars($toName) . ",</p>
            <p>Your verification code for <strong>$purposeLabel</strong> is:</p>
            <div class='otp-code'>$otp</div>
            <p>This code expires in 10 minutes. If you didn't request this, you can ignore this email.</p>"
        );
        $this->send($toEmail, $toName, $subject, $html);
    }

    public function sendOrderConfirmation(array $order, array $user, array $items): void
    {
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<tr><td>' . self::formatEmailQuantity((int) $item['quantity']) . ' ' . htmlspecialchars($item['product_name']) .
                '</td><td align="right">$' . number_format((float) $item['line_total'], 2) . '</td></tr>';
        }

        $html = $this->wrapTemplate(
            'Order Confirmed',
            "<p>Hi " . htmlspecialchars($user['first_name']) . ",</p>
            <p>Thank you for your order at Abdu Market! We've received your payment and are preparing your groceries.</p>
            <p><strong>Order #:</strong> " . htmlspecialchars($order['order_number']) . "<br>
            <strong>Total:</strong> $" . number_format((float) $order['total'], 2) . "</p>
            <table width='100%' cellpadding='8' cellspacing='0' style='border-collapse:collapse;'>
                <tr style='background:#f8f8f8;'><th align='left'>Item</th><th align='right'>Amount</th></tr>
                $itemsHtml
            </table>
            <p style='margin-top:20px;'><strong>Pickup:</strong> " . htmlspecialchars(setting('mart.address', config('mart.address'))) . "</p>
            <p>When you arrive, open your order in the app and tap <strong>I'm Here</strong> so we can bring your order to your car.</p>"
        );

        $this->send(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            'Order ' . $order['order_number'] . ' confirmed - Abdu Market',
            $html
        );
    }

    public function sendTestEmail(string $toEmail): void
    {
        $html = $this->wrapTemplate(
            'SMTP Test',
            '<p>Your Abdu Market SMTP settings are working correctly.</p>'
        );
        $this->send($toEmail, 'Admin', 'Abdu Market SMTP test email', $html);
    }

    /**
     * @param array<int, string> $emails
     */
    public function sendToAddresses(array $emails, string $subject, string $html): void
    {
        foreach ($emails as $email) {
            $this->send($email, 'Admin', $subject, $html);
        }
    }

    public function sendAdminNewOrderNotification(array $order, array $user, array $items): void
    {
        $emails = get_admin_notify_emails();
        if ($emails === []) {
            return;
        }

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<tr><td>' . self::formatEmailQuantity((int) $item['quantity']) . ' ' . htmlspecialchars((string) $item['product_name']) .
                '</td><td align="right">$' . number_format((float) $item['line_total'], 2) . '</td></tr>';
        }

        $paymentMethod = (string) ($order['payment_method'] ?? 'stripe');
        $paymentLabel = match ($paymentMethod) {
            'arrival' => 'Pay on arrival',
            'clover' => 'Paid online (Clover)',
            default => 'Paid online (Stripe)',
        };
        $vehicle = trim((string) ($order['vehicle_description'] ?? ''));
        $pickupNotes = trim((string) ($order['pickup_notes'] ?? ''));
        $adminUrl = rtrim(config('app.url'), '/') . '/admin/orders.php?id=' . (int) $order['id'];

        $extra = '';
        if ($vehicle !== '') {
            $extra .= '<p><strong>Vehicle:</strong> ' . htmlspecialchars($vehicle) . '</p>';
        }
        if ($pickupNotes !== '') {
            $extra .= '<p><strong>Pickup notes:</strong> ' . nl2br(htmlspecialchars($pickupNotes)) . '</p>';
        }

        $html = $this->wrapTemplate(
            'New Order',
            '<p>A new curbside order was placed.</p>
            <p><strong>Order #:</strong> ' . htmlspecialchars((string) $order['order_number']) . '<br>
            <strong>Customer:</strong> ' . htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) . '<br>
            <strong>Email:</strong> ' . htmlspecialchars((string) $user['email']) . '<br>
            <strong>Phone:</strong> ' . htmlspecialchars(self::emailMissingValue($user['phone'] ?? null)) . '<br>
            <strong>Payment:</strong> ' . htmlspecialchars($paymentLabel) . '<br>
            <strong>Total:</strong> $' . number_format((float) $order['total'], 2) . '</p>
            <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                <tr style="background:#f8f8f8;"><th align="left">Item</th><th align="right">Amount</th></tr>
                ' . $itemsHtml . '
            </table>
            ' . $extra . '
            <p style="margin-top:20px;"><a href="' . htmlspecialchars($adminUrl) . '">View order in admin</a></p>'
        );

        $this->sendToAddresses(
            $emails,
            'New order ' . $order['order_number'] . ' - Abdu Market',
            $html
        );
    }

    public function sendAdminCustomerHereNotification(array $order, array $user): void
    {
        $emails = get_admin_notify_emails();
        if ($emails === []) {
            return;
        }

        $vehicle = trim((string) ($order['vehicle_description'] ?? ''));
        $checkedIn = !empty($order['customer_here_at'])
            ? date('M j, Y g:i A', strtotime((string) $order['customer_here_at']))
            : 'Just now';
        $adminUrl = rtrim(config('app.url'), '/') . '/admin/orders.php?id=' . (int) $order['id'];

        $vehicleHtml = $vehicle !== ''
            ? '<p><strong>Vehicle:</strong> ' . htmlspecialchars($vehicle) . '</p>'
            : '<p><strong>Vehicle:</strong> Not provided</p>';

        $html = $this->wrapTemplate(
            'Customer Arrived',
            '<p>A customer tapped <strong>I\'m Here</strong> for curbside pickup.</p>
            <p><strong>Order #:</strong> ' . htmlspecialchars((string) $order['order_number']) . '<br>
            <strong>Customer:</strong> ' . htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) . '<br>
            <strong>Phone:</strong> ' . htmlspecialchars(self::emailMissingValue($user['phone'] ?? null)) . '<br>
            <strong>Checked in:</strong> ' . htmlspecialchars($checkedIn) . '</p>
            ' . $vehicleHtml . '
            <p style="margin-top:20px;"><a href="' . htmlspecialchars($adminUrl) . '">Open order in admin</a></p>'
        );

        $this->sendToAddresses(
            $emails,
            'Customer arrived - order ' . $order['order_number'],
            $html
        );
    }

    private function wrapTemplate(string $title, string $content): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="font-family:DM Sans,Arial,sans-serif;background:#fafafa;margin:0;padding:24px;">
        <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:14px;padding:32px;border:1px solid #eee;">
            <div style="color:#c8102e;font-weight:700;font-size:18px;margin-bottom:8px;">Abdu Market</div>
            <h1 style="font-size:22px;margin:0 0 16px;color:#1a1a1a;">' . htmlspecialchars($title) . '</h1>
            <div style="color:#444;line-height:1.6;">' . $content . '</div>
        </div></body></html>
        <style>.otp-code{font-size:32px;font-weight:700;letter-spacing:8px;color:#c8102e;padding:16px 0;}</style>';
    }

    private static function formatEmailQuantity(int $quantity): string
    {
        return (int) $quantity . ' x';
    }

    private static function emailMissingValue(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : 'N/A';
    }

    private static function emailSubject(string $subject): string
    {
        return self::emailPlainText($subject);
    }

    private static function emailPlainText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(
            ["\u{2014}", "\u{2013}", "\u{00D7}", "\u{2212}", "—", "–", "×"],
            ['-', '-', 'x', '-', '-', '-', 'x'],
            $text
        );

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
