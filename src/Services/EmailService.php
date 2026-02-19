<?php

declare(strict_types=1);

namespace Hillmeet\Services;

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
        $from = config('smtp.from', 'noreply@localhost');
        $fromName = config('smtp.from_name', 'Hillmeet');
        $boundary = '----=_Part_' . bin2hex(random_bytes(8));
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . ($fromName ? "\"{$fromName}\" <{$from}>" : $from),
            'Reply-To: ' . $from,
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n"
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n"
            . "--{$boundary}--";
        $socket = @fsockopen(
            ($port = (int) config('smtp.port', 587)) === 465 ? 'ssl://' . $host : $host,
            $port ?: 587,
            $errno,
            $errstr,
            10
        );
        if (!$socket) {
            $this->lastError = "Connection failed: {$errstr} ({$errno})";
            return false;
        }
        $user = config('smtp.user', '');
        $pass = config('smtp.pass', '');
        $ok = true;
        $readBuf = null;
        $read = function () use ($socket, &$readBuf) {
            if ($readBuf !== null) {
                $line = $readBuf;
                $readBuf = null;
                return $line;
            }
            $line = fgets($socket);
            return $line !== false ? trim($line) : '';
        };
        // Read until we get the final SMTP line (code + space, not code + hyphen for continuation)
        $readFullResponse = function () use ($read) {
            $last = '';
            do {
                $last = $read();
            } while ($last !== '' && preg_match('/^\d{3}-/', $last));
            return $last;
        };
        $cmd = function (string $s) use ($socket, $read, $readFullResponse, &$ok) {
            fwrite($socket, $s . "\r\n");
            $r = $readFullResponse();
            if (!preg_match('/^[23]\d{2}(\s|$)/', $r)) {
                $ok = false;
                $safe = preg_replace('/[^\x20-\x7E]/', '?', $r);
                $this->lastError = $r === '' ? 'SMTP: (empty response)' : ('SMTP: ' . ($safe !== '' ? $safe : '(non-printable response)'));
            }
            return $r;
        };
        $ehloName = 'localhost';
        $appUrl = config('app.url', '');
        if ($appUrl !== '') {
            $hostFromUrl = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($hostFromUrl) && $hostFromUrl !== '') {
                $ehloName = $hostFromUrl;
            }
        }
        if ($ehloName === 'localhost' && isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '') {
            $ehloName = $_SERVER['SERVER_NAME'];
        }
        $readFullResponse(); // banner
        $cmd("EHLO " . $ehloName);
        if ($user !== '' && $pass !== '' && $port === 587) {
            $cmd('STARTTLS');
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            // IONOS and others send a new 220 after TLS; must consume before second EHLO or we get 503
            $afterTls = $read();
            while ($afterTls !== '' && preg_match('/^220\s/', $afterTls)) {
                stream_set_timeout($socket, 1);
                $afterTls = $read();
            }
            if ($afterTls !== '') {
                $readBuf = $afterTls;
            }
            stream_set_timeout($socket, 10);
            $cmd("EHLO " . $ehloName);
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $cmd(base64_encode($pass));
        }
        $cmd('MAIL FROM:<' . $from . '>');
        $cmd('RCPT TO:<' . $to . '>');
        $cmd('DATA');
        fwrite($socket, "Subject: {$subject}\r\n" . implode("\r\n", $headers) . "\r\n\r\n{$body}\r\n.\r\n");
        $readFullResponse();
        fclose($socket);
        return $ok;
    }
}
