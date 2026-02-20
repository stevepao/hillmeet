<?php

declare(strict_types=1);

// Temporary: add ?debug=1 to any URL to see the actual PHP error (remove in production)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . trim($path, '/');
if ($path !== '/' && $path !== '') {
    $path = rtrim($path, '/') ?: '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    'GET' => [
        '/' => [\Hillmeet\Controllers\HomeController::class, 'index'],
        '/auth/login' => [\Hillmeet\Controllers\AuthController::class, 'loginPage'],
        '/auth/google' => [\Hillmeet\Controllers\AuthController::class, 'googleRedirect'],
        '/auth/email' => [\Hillmeet\Controllers\AuthController::class, 'emailPage'],
        '/auth/verify' => [\Hillmeet\Controllers\AuthController::class, 'verifyPage'],
        '/auth/google/callback' => [\Hillmeet\Controllers\AuthController::class, 'googleCallback'],
        '/auth/signout' => [\Hillmeet\Controllers\AuthController::class, 'signOut'],
        '/poll/new' => [\Hillmeet\Controllers\PollController::class, 'newPoll'],
        '/poll/create' => [\Hillmeet\Controllers\PollController::class, 'createStep1'],
        '/poll/{slug}/edit' => [\Hillmeet\Controllers\PollController::class, 'edit'],
        '/poll/{slug}/options' => [\Hillmeet\Controllers\PollController::class, 'options'],
        '/poll/{slug}/share' => [\Hillmeet\Controllers\PollController::class, 'share'],
        '/poll/{slug}' => [\Hillmeet\Controllers\PollController::class, 'view'],
        '/poll/{slug}/results' => [\Hillmeet\Controllers\PollController::class, 'resultsFragment'],
        '/calendar' => [\Hillmeet\Controllers\CalendarController::class, 'settings'],
        '/calendar/connect' => [\Hillmeet\Controllers\CalendarController::class, 'connect'],
        '/calendar/callback' => [\Hillmeet\Controllers\CalendarController::class, 'callback'],
    ],
    'POST' => [
        '/auth/send-pin' => [\Hillmeet\Controllers\AuthController::class, 'sendPin'],
        '/auth/verify-pin' => [\Hillmeet\Controllers\AuthController::class, 'verifyPin'],
        '/poll/create' => [\Hillmeet\Controllers\PollController::class, 'createPost'],
        '/poll/{slug}/options' => [\Hillmeet\Controllers\PollController::class, 'optionsPost'],
        '/poll/{slug}/share' => [\Hillmeet\Controllers\PollController::class, 'sharePost'],
        '/poll/{slug}/vote' => [\Hillmeet\Controllers\PollController::class, 'vote'],
        '/poll/{slug}/vote-batch' => [\Hillmeet\Controllers\PollController::class, 'voteBatch'],
        '/poll/{slug}/lock' => [\Hillmeet\Controllers\PollController::class, 'lock'],
        '/poll/{slug}/create-event' => [\Hillmeet\Controllers\PollController::class, 'createEvent'],
        '/poll/{slug}/check-availability' => [\Hillmeet\Controllers\PollController::class, 'checkAvailability'],
        '/calendar/save' => [\Hillmeet\Controllers\CalendarController::class, 'save'],
    ],
];

// Optional: show deploy check for debugging (remove or restrict in production)
if (($path === '/deploy-check' || $path === '/deploy-check.php') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require dirname(__DIR__) . '/public/deploy-check.php';
    exit;
}

$handler = null;
$params = [];
foreach ($routes[$method] ?? [] as $route => $target) {
    if (strpos($route, '{') !== false) {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
        if (preg_match('#^' . $pattern . '$#', $path, $m)) {
            array_shift($m);
            $params = $m;
            $handler = $target;
            break;
        }
    } elseif ($route === $path) {
        $handler = $target;
        break;
    }
}

if ($handler === null) {
    http_response_code(404);
    require dirname(__DIR__) . '/views/errors/404.php';
    exit;
}

if ($method === 'POST' && !\Hillmeet\Middleware\CsrfMiddleware::validate()) {
    http_response_code(403);
    require dirname(__DIR__) . '/views/errors/403.php';
    exit;
}

[$class, $action] = $handler;
try {
    $controller = new $class();
    $controller->$action(...$params);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Application error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (" . $e->getLine() . ")\n";
    echo "\nTrace:\n" . $e->getTraceAsString();
    exit;
}
