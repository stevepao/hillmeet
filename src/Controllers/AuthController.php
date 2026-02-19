<?php

declare(strict_types=1);

namespace Hillmeet\Controllers;

use Hillmeet\Repositories\EmailLoginPinRepository;
use Hillmeet\Repositories\UserRepository;
use Hillmeet\Services\AuthService;
use Hillmeet\Services\EmailService;
use function Hillmeet\Support\config;
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
        header('Location: ' . url('/'));
        exit;
    }

    public function googleToken(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        $idToken = $input['credential'] ?? $input['token'] ?? '';
        if ($idToken === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing token']);
            exit;
        }
        $user = $this->auth->verifyGoogleIdToken($idToken);
        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        $_SESSION['user'] = $user;
        header('Content-Type: application/json');
        echo json_encode(['redirect' => url('/')]);
        exit;
    }

    public function googleCallback(): void
    {
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $userRepo = new UserRepository();
        $oauth = new \Hillmeet\Services\GoogleCalendarService(
            new \Hillmeet\Repositories\OAuthConnectionRepository(),
            new \Hillmeet\Repositories\GoogleCalendarSelectionRepository(),
            new \Hillmeet\Repositories\FreebusyCacheRepository()
        );
        $state = $_GET['state'] ?? '';
        if ($state === 'calendar') {
            require_auth();
            $oauth->exchangeCodeForTokens($code, (int) $_SESSION['user']->id);
            header('Location: ' . url('/calendar'));
            exit;
        }
        $clientId = config('google.client_id');
        $clientSecret = config('google.client_secret');
        $redirectUri = config('google.redirect_uri') ?: (rtrim((string) config('app.url', ''), '/') . '/auth/google/callback');
        $res = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ]),
            ],
        ]));
        if ($res === false) {
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $data = json_decode($res, true);
        $idToken = $data['id_token'] ?? '';
        if ($idToken === '') {
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $user = $this->auth->verifyGoogleIdToken($idToken);
        if ($user === null) {
            header('Location: ' . url('/auth/login'));
            exit;
        }
        $_SESSION['user'] = $user;
        header('Location: ' . url('/'));
        exit;
    }

    public function signOut(): void
    {
        $this->auth->signOut();
        header('Location: ' . url('/auth/login'));
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
