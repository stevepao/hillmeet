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

    /**
     * Send "poll locked" notification. If $icsContent is non-empty, attach as .ics file.
     */
    public function sendPollLocked(
        string $to,
        string $pollTitle,
        string $finalTimeLocalized,
        string $organizerName,
        string $organizerEmail,
        string $pollUrl = '',
        string $icsContent = ''
    ): bool {
        $subject = 'Meeting time finalized: ' . $pollTitle;
        $html = $this->renderTemplate('poll_locked', [
            'pollTitle' => $pollTitle,
            'finalTimeLocalized' => $finalTimeLocalized,
            'organizerName' => $organizerName,
            'organizerEmail' => $organizerEmail,
            'pollUrl' => $pollUrl,
            'hasIcs' => $icsContent !== '',
        ]);
        $text = "Meeting time finalized: {$pollTitle}\n\nFinal time: {$finalTimeLocalized}\nOrganizer: {$organizerName} ({$organizerEmail})\n";
        if ($pollUrl !== '') {
            $text .= "\nView poll: {$pollUrl}";
        }
        if ($icsContent !== '') {
            $text .= "\n\nA calendar file is attached. Add it to your calendar to save the event.";
        }
        return $this->sendWithAttachment($to, $subject, $html, $text, $icsContent, 'invite.ics');
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

    /**
     * Send email with optional attachment. Pass empty string for attachmentContent to send without attachment.
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $attachmentContent,
        string $attachmentFilename = 'attachment.ics'
    ): bool {
        $this->lastError = '';
        $host = config('smtp.host', '');
        if ($host === '') {
            $this->lastError = 'SMTP not configured: smtp.host is empty. Set SMTP_* in .env.';
            return false;
        }

        $mail = new PHPMailer(false);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) config('smtp.port', 587);
            $mail->Timeout = 15;
            $mail->SMTPAuth = true;
            $mail->Username = config('smtp.user', '');
            $mail->Password = config('smtp.pass', '');
            $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $appUrl = config('app.url', '');
            if ($appUrl !== '') {
                $ehloHost = parse_url($appUrl, PHP_URL_HOST);
                if (is_string($ehloHost) && $ehloHost !== '') {
                    $mail->Hostname = $ehloHost;
                }
            }

            $mail->setFrom(config('smtp.from', 'noreply@localhost'), config('smtp.from_name', 'Hillmeet'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            if ($attachmentContent !== '') {
                $mail->addStringAttachment($attachmentContent, $attachmentFilename, PHPMailer::ENCODING_BASE64, 'text/calendar');
            }

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

    private function send(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        return $this->sendWithAttachment($to, $subject, $htmlBody, $textBody, '', '');
    }
}
