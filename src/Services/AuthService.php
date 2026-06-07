<?php

declare(strict_types=1);

/**
 * AuthService.php
 * Purpose: Google and email PIN auth, find/create user, send/verify PIN.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Services;

use Hillmeet\Repositories\EmailLoginPinRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Support\AuditLog;
use Hillmeet\Support\RateLimit;
use function Hillmeet\Support\config;

final class AuthService
{
    public function __construct(
        private UserRepository $userRepo,
        private EmailLoginPinRepository $pinRepo,
        private EmailService $emailService
    ) {}

    /** Find or create user from Google profile (sub, email, name, picture). Used by League OAuth2 callback. */
    public function findOrCreateUserFromGoogleProfile(string $googleId, string $email, string $name, ?string $avatarUrl = null): ?object
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $user = $this->userRepo->findByGoogleId($googleId);
        if ($user === null) {
            $user = $this->userRepo->findByEmail($email);
            if ($user !== null) {
                $this->userRepo->attachGoogleId((int) $user->id, $email, $googleId, $name, $avatarUrl);
                $user = $this->userRepo->findById((int) $user->id);
            } else {
                $user = $this->userRepo->createFromGoogle($email, $name, $googleId, $avatarUrl);
            }
        }
        AuditLog::log('auth.google_login', 'user', (string) $user->id);
        return $user;
    }

    /** Send PIN to email. Returns error message or null on success. */
    public function sendPin(string $email, string $ip, string $turnstileToken = ''): ?string
    {
        $key = 'pin_request:' . $ip;
        if (!RateLimit::check($key, (int) config('rate.pin_request'))) {
            return 'Too many attempts—please wait a minute and try again.';
        }
        $email = strtolower(trim($email));
        if ($email === '') {
            return 'Please enter your email.';
        }
        if (!$this->verifyTurnstile($turnstileToken, $ip)) {
            return 'Please complete the security check and try again.';
        }
        $emailKey = 'pin_request_email:' . hash('sha256', $email);
        $emailLimit = (int) config('rate.pin_request_email', config('rate.pin_request', 3));
        if (!RateLimit::check($emailKey, $emailLimit)) {
            return 'Too many attempts—please wait a minute and try again.';
        }
        $pin = (string) random_int(100000, 999999);
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);
        $this->pinRepo->create($email, $pinHash);
        if (!$this->emailService->sendPinEmail($email, $pin)) {
            return 'We couldn\'t send the email. Check SMTP settings in .env (see README) or try again.';
        }
        return null;
    }

    private function verifyTurnstile(string $token, string $ip): bool
    {
        $siteKey = (string) config('turnstile.site_key', '');
        $secretKey = (string) config('turnstile.secret_key', '');
        if ($siteKey === '' || $secretKey === '') {
            return true;
        }
        if ($token === '') {
            return false;
        }

        $remoteIp = trim(explode(',', $ip)[0] ?? '');
        $payload = [
            'secret' => $secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($payload),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]));
        if ($response === false) {
            return false;
        }

        $decoded = json_decode($response, true);
        return \is_array($decoded) && ($decoded['success'] ?? false) === true;
    }

    /** Verify PIN and sign in. Returns error message or null on success (user set in session). */
    public function verifyPin(string $email, string $pin, string $ip): ?string
    {
        $key = 'pin_attempt:' . $ip;
        if (!RateLimit::check($key, (int) config('rate.pin_attempt'))) {
            return 'Too many attempts—please wait a minute and try again.';
        }
        $email = strtolower(trim($email));
        $record = $this->pinRepo->findValid($email);
        if ($record === null) {
            return 'PIN expired. Request a new one.';
        }
        if (!password_verify($pin, $record->pin_hash)) {
            $this->pinRepo->incrementAttempts((int) $record->id);
            return "That PIN doesn't look right. Try again.";
        }
        $this->pinRepo->invalidate((int) $record->id);
        $user = $this->userRepo->getOrCreateByEmail($email);
        $_SESSION['user'] = $user;
        AuditLog::log('auth.pin_login', 'user', (string) $user->id);
        return null;
    }

    public function signOut(): void
    {
        if (isset($_SESSION['user']->id)) {
            AuditLog::log('auth.sign_out', 'user', (string) $_SESSION['user']->id);
        }
        unset($_SESSION['user']);
    }
}
