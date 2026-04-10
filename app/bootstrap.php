<?php

declare(strict_types=1);

function app_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#') || !str_contains($trimmedLine, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmedLine, 2);

        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

app_load_env_file(dirname(__DIR__) . '/.env');

// Configure error handling based on environment
$isProduction = getenv('APP_ENV') === 'production' || !in_array(getenv('APP_ENV'), ['development', 'local', 'test']);
if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Set error log
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/template.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/logging.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/webhooks.php';

// Log incoming request
app_log_request();