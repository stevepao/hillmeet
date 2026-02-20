<?php

declare(strict_types=1);

namespace Hillmeet\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use function Hillmeet\Support\config;

final class EmailService
{
    private string $lastError = '';

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function sendPinEmail(string $to, string $pin): bool
    {
        $subject = 'Your Hillmeet sign-in PIN';
        $html = $this->renderTemplate('pin', ['pin' => $pin, 'expiry_minutes' => 10]);
        $text = "Your sign-in PIN is: {$pin}\n\nPIN expires in 10 minutes.";
        return $this->send($to, $subject, $html, $text);
    }

    public function sendPollInvite(string $to, string $pollTitle, string $pollUrl): bool
    {
        $subject = 'You\'re invited to vote: ' . $pollTitle;
        $html = $this->renderTemplate('poll_invite', ['pollTitle' => $pollTitle, 'pollUrl' => $pollUrl]);
        $text = "You're invited to vote on: {$pollTitle}\n\nOpen this link to vote: {$pollUrl}";
        return $this->send($to, $subject, $html, $text);
    }

    private function renderTemplate(string $name, array $vars): string
    {
        $path = dirname(__DIR__, 2) . "/views/emails/{$name}.php";
        if (!is_file($path)) {
            return '';
        }
        ob_start();
        extract($vars, EXTR_SKIP);
        include $path;
        return ob_get_clean();
    }

    private function send(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        $this->lastError = '';
        $host = config('smtp.host', '');
        if ($host === '') {
            $this->lastError = 'SMTP not configured: smtp.host is empty. Set SMTP_* in .env.';
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) config('smtp.port', 587);
            $mail->SMTPAuth = true;
            $mail->Username = config('smtp.user', '');
            $mail->Password = config('smtp.pass', '');
            $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom(config('smtp.from', 'noreply@localhost'), config('smtp.from_name', 'Hillmeet'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->exceptions = false;
            $ok = $mail->send();
            if (!$ok) {
                $this->lastError = $mail->ErrorInfo ?: 'SMTP send failed.';
            }
            return $ok;
        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}
