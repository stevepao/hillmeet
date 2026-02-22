<?php

declare(strict_types=1);

/**
 * AuthController.php
 * Purpose: Login, Google OAuth, email PIN, signout, timezone.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */

namespace Hillmeet\Controllers;

use Hillmeet\Repositories\EmailLoginPinRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\AuthService;
use Hillmeet\Services\EmailService;
use function Hillmeet\Support\config;
use function Hillmeet\Support\redirect_to_return_or;
use function Hillmeet\Support\url;

final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService(
            new UserRepository(),
            new EmailLoginPinRepository(),
            new EmailService()
        );
    }

    public function loginPage(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: ' . url('/'));
            exit;
        }
        $googleClientId = \Hillmeet\Support\config('google.client_id', '');
        require dirname(__DIR__, 2) . '/views/auth/login.php';
    }

    /** Redirect to Google OAuth for sign-in (League OAuth2). */
    public function googleRedirect(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: ' . url('/'));
            exit;
        }
        $clientId = config('google.client_id', '');
        $clientSecret = config('google.client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $redirectUri = config('google.redirect_uri') ?: (rtrim((string) config('app.url', ''), '/') . '/auth/google/callback');
        $provider = new \League\OAuth2\Client\Provider\Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUri,
        ]);
        $authUrl = $provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    public function emailPage(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: ' . url('/'));
            exit;
        }
        require dirname(__DIR__, 2) . '/views/auth/email.php';
    }

    public function verifyPage(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: ' . url('/'));
            exit;
        }
        require dirname(__DIR__, 2) . '/views/auth/verify.php';
    }

    public function sendPin(): void
    {
        $email = trim($_POST['email'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $err = $this->auth->sendPin($email, $ip);
        if ($err !== null) {
            $_SESSION['auth_error'] = $err;
            $_SESSION['auth_email'] = $email;
            header('Location: ' . url('/auth/email'));
            exit;
        }
        $_SESSION['pin_sent_to'] = $email;
        header('Location: ' . url('/auth/verify?email=' . urlencode($email)));
        exit;
    }

    public function verifyPin(): void
    {
        $email = trim($_POST['email'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $err = $this->auth->verifyPin($email, $pin, $ip);
        if ($err !== null) {
            $_SESSION['auth_error'] = $err;
            $_SESSION['auth_email'] = $email;
            header('Location: ' . url('/auth/verify?email=' . urlencode($email)));
            exit;
        }
        unset($_SESSION['pin_sent_to'], $_SESSION['auth_error'], $_SESSION['auth_email']);
        redirect_to_return_or('/');
    }

    public function googleCallback(): void
    {
        if (isset($_GET['error'])) {
            $_SESSION['auth_error'] = 'Google sign-in was cancelled or failed.';
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        if ($code === '') {
            header('Location: ' . url('/auth/login'));
            exit;
        }
        if ($state === 'calendar') {
            require_auth();
            $oauth = new \Hillmeet\Services\GoogleCalendarService(
                new \Hillmeet\Repositories\OAuthConnectionRepository(),
                new \Hillmeet\Repositories\GoogleCalendarSelectionRepository(),
                new \Hillmeet\Repositories\FreebusyCacheRepository()
            );
            $oauth->exchangeCodeForTokens($code, (int) $_SESSION['user']->id);
            header('Location: ' . url('/calendar'));
            exit;
        }
        if (empty($_SESSION['oauth2state']) || $state !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            $_SESSION['auth_error'] = 'Invalid state. Please try signing in again.';
            header('Location: ' . url('/auth/login'));
            exit;
        }
        unset($_SESSION['oauth2state']);
        $clientId = config('google.client_id');
        $clientSecret = config('google.client_secret');
        $redirectUri = config('google.redirect_uri') ?: (rtrim((string) config('app.url', ''), '/') . '/auth/google/callback');
        $provider = new \League\OAuth2\Client\Provider\Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUri,
        ]);
        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
            $owner = $provider->getResourceOwner($token);
        } catch (\Throwable $e) {
            $_SESSION['auth_error'] = 'Google sign-in failed. Try again or use email.';
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $email = $owner->getEmail();
        if ($email === null || $email === '') {
            $_SESSION['auth_error'] = 'Google did not provide an email. Try again or use email.';
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $user = $this->auth->findOrCreateUserFromGoogleProfile(
            (string) $owner->getId(),
            $email,
            $owner->getName() ?? $email,
            $owner->getAvatar()
        );
        if ($user === null) {
            $_SESSION['auth_error'] = 'Could not sign you in. Try again or use email.';
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $_SESSION['user'] = $user;
        session_write_close();
        redirect_to_return_or('/');
    }

    public function signOut(): void
    {
        $this->auth->signOut();
        header('Location: ' . url('/auth/login'));
        exit;
    }

    /** POST /settings/timezone — set current user's timezone from browser (e.g. when answering a poll). */
    public function setTimezone(): void
    {
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            exit;
        }
        if (!\Hillmeet\Support\Csrf::validate()) {
            http_response_code(403);
            exit;
        }
        $timezone = trim((string) ($_POST['timezone'] ?? ''));
        if ($timezone === '') {
            http_response_code(204);
            exit;
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            http_response_code(400);
            exit;
        }
        $userRepo = new UserRepository();
        $userRepo->setTimezone((int) $_SESSION['user']->id, $timezone);
        http_response_code(204);
        exit;
    }
}

function require_auth(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: ' . \Hillmeet\Support\url('/auth/login'));
        exit;
    }
}
