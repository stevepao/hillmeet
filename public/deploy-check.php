<?php

/**
 * Deployment check for IONOS. Visit https://meet.hillwork.net/deploy-check.php
 * Delete this file after fixing issues (or restrict by IP).
 */

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$checks = [];
$ok = true;

// PHP version
$phpVer = PHP_VERSION;
$checks['PHP version'] = [$phpVer, version_compare($phpVer, '8.2.0', '>=') ? true : 'PHP 8.2+ recommended'];

// config
$configPath = $root . '/config/config.php';
$configExists = is_file($configPath);
$checks['config/config.php exists'] = [$configExists ? 'yes' : 'no', $configExists];
if (!$configExists) {
    $ok = false;
}

// .env
$envPath = $root . '/.env';
$checks['.env exists'] = [is_file($envPath) ? 'yes' : 'no', is_file($envPath)];

// vendor
$vendorPath = $root . '/vendor/autoload.php';
$vendorExists = is_file($vendorPath);
$checks['vendor/autoload.php'] = [$vendorExists ? 'yes' : 'no', $vendorExists];
if (!$vendorExists) {
    $ok = false;
}

// Load env and config if we can
$dbOk = false;
$sessionsTableOk = false;
if ($configExists && $vendorExists) {
    require_once $root . '/config/env.php';
    $config = require $configPath;
    $db = $config['db'] ?? [];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'] ?? '', $db['name'] ?? '', $db['charset'] ?? 'utf8mb4');
    try {
        $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $checks['Database connection'] = ['ok', true];
        $dbOk = true;
        $stmt = $pdo->query("SHOW TABLES LIKE 'sessions'");
        $sessionsTableOk = $stmt && $stmt->fetch();
        $checks['sessions table exists'] = [$sessionsTableOk ? 'yes' : 'no - run: php bin/migrate.php', $sessionsTableOk];
        if (!$sessionsTableOk) {
            $ok = false;
        }
    } catch (Throwable $e) {
        $checks['Database connection'] = [$e->getMessage(), false];
        $ok = false;
    }
    $appUrl = $config['app']['url'] ?? '';
    $checks['APP_URL set'] = [$appUrl ?: '(empty - set in .env)', (bool) $appUrl];
    $encKey = $config['app']['encryption_key'] ?? '';
    $checks['ENCRYPTION_KEY set (64 hex)'] = [$encKey ? 'set (' . strlen($encKey) . ' chars)' : '(empty)', strlen($encKey) === 64 && ctype_xdigit($encKey)];
}

echo "Hillmeet deployment check – " . date('c') . "\n\n";
foreach ($checks as $label => list($detail, $pass)) {
    $status = $pass === true ? 'OK' : 'FAIL';
    echo "[$status] $label: $detail\n";
}
echo "\n";
if ($ok) {
    echo "All checks passed. You can delete public/deploy-check.php.\n";
} else {
    echo "Fix the FAIL items above. Common fixes:\n";
    echo "- No config: copy config/config.example.php to config/config.php\n";
    echo "- No vendor: run 'composer install' or upload vendor/\n";
    echo "- No .env: copy .env.example to .env and set DB_*, APP_URL, ENCRYPTION_KEY\n";
    echo "- DB connection: check DB_HOST, DB_NAME, DB_USER, DB_PASS in .env\n";
    echo "- No sessions table: run 'php bin/migrate.php' (e.g. via SSH or IONOS cron)\n";
}
