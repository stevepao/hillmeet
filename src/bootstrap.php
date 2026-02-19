<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo 'Configuration not found. Copy config.example.php to config.php and set values.';
    exit;
}

\Hillmeet\Support\SessionHandler::configure();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(\Hillmeet\Support\config('app.timezone', 'UTC'));
