<?php
/**
 * Step-by-step diagnostic. Visit https://meet.hillwork.net/diagnose.php
 * Copy the FULL output and paste it for debugging. Delete this file when done.
 */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);

function step($label, $fn) {
    echo "\n--- $label ---\n";
    try {
        $fn();
        echo "OK\n";
        return true;
    } catch (Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " (" . $e->getLine() . ")\n";
        echo $e->getTraceAsString() . "\n";
        return false;
    }
}

echo "PHP " . PHP_VERSION . " (" . PHP_SAPI . ")";
echo "\nRoot: $root";

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\n*** FATAL ERROR (shutdown) ***\n";
        echo $err['message'] . "\n";
        echo $err['file'] . " (" . $err['line'] . ")\n";
    }
});

step('1. env.php', function () use ($root) {
    require_once $root . '/config/env.php';
    if (!function_exists('env')) {
        throw new Exception('env() not defined after loading env.php');
    }
});

step('2. config.php', function () use ($root) {
    $path = $root . '/config/config.php';
    if (!is_file($path)) {
        throw new Exception('config.php not found');
    }
    $config = require $path;
    if (!is_array($config)) {
        throw new Exception('config did not return array');
    }
});

step('3. vendor/autoload.php', function () use ($root) {
    $path = $root . '/vendor/autoload.php';
    if (!is_file($path)) {
        throw new Exception('vendor/autoload.php not found');
    }
    require_once $path;
});

step('4. bootstrap.php (full app init)', function () use ($root) {
    require_once $root . '/src/bootstrap.php';
});

step('5. config() helper', function () {
    $url = \Hillmeet\Support\config('app.url', '');
    if ($url === null) {
        throw new Exception('config("app.url") returned null');
    }
});

step('6. Simulate GET /auth/login (AuthController->loginPage)', function () use ($root) {
    $loginView = $root . '/views/auth/login.php';
    $layout = $root . '/views/layouts/main.php';
    if (!is_file($loginView)) {
        throw new Exception("Login view missing: $loginView");
    }
    if (!is_file($layout)) {
        throw new Exception("Layout missing: $layout");
    }
    unset($_SESSION['user']);
    $controller = new \Hillmeet\Controllers\AuthController();
    ob_start();
    $controller->loginPage();
    ob_end_clean();
});

step('7. Email / SMTP (PIN emails)', function () {
    $host = \Hillmeet\Support\config('smtp.host', '');
    echo "SMTP host: " . ($host !== '' ? $host : '(not set – set SMTP_HOST in .env)') . "\n";
    if ($host === '') {
        echo "Skipping send test – configure SMTP_* in .env first.\n";
        return;
    }
    set_time_limit(25);
    $emailService = new \Hillmeet\Services\EmailService();
    $sent = $emailService->sendPinEmail('diagnose-test@example.com', '123456');
    if ($sent) {
        echo "Test send: OK\n";
    } else {
        $err = $emailService->getLastError();
        $recipientRejected = (stripos($err, 'recipients failed') !== false && stripos($err, 'example.com') !== false)
            || stripos($err, 'domain does not accept mail') !== false;
        if ($recipientRejected) {
            echo "Test send: OK (connection and auth succeeded; example.com rejects test addresses – real PIN emails should work).\n";
        } else {
            echo "Test send: FAIL\n";
            echo "Last error: " . $err . "\n";
            throw new Exception('SMTP test send failed: ' . $err);
        }
    }
});

echo "\n--- Done ---\n";
echo "\nIf you see FAIL above, copy this entire output and paste it for debugging.\n";
