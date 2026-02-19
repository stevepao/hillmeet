<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$root = dirname(__DIR__);

try {
    require_once $root . '/config/env.php';
    require_once $root . '/vendor/autoload.php';

    $configPath = $root . '/config/config.php';
    if (!is_file($configPath)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Configuration not found. Copy config.example.php to config.php and set values.';
        exit;
    }

    \Hillmeet\Support\SessionHandler::configure();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    date_default_timezone_set(\Hillmeet\Support\config('app.timezone', 'UTC'));
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Setup error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (" . $e->getLine() . ")\n";
    echo "\nTrace:\n" . $e->getTraceAsString();
    exit;
}
