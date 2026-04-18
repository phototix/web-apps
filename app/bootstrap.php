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
require_once __DIR__ . '/webbycloud.php';

function app_is_api_request(): bool
{
    $path = app_request_path();

    return str_starts_with($path, '/api/');
}

function app_clear_output_buffers(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function app_render_fallback_server_error(): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Server Error</title></head>';
    echo '<body><h1>500 - Server Error</h1><p>Something went wrong.</p></body></html>';
}

function app_handle_exception(Throwable $exception): void
{
    static $handling = false;

    if ($handling) {
        return;
    }

    $handling = true;
    $errorId = bin2hex(random_bytes(6));

    app_log('Unhandled exception', 'ERROR', [
        'error_id' => $errorId,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ]);

    if (app_is_api_request()) {
        app_clear_output_buffers();
        require_once __DIR__ . '/api.php';
        api_internal_error('Internal server error');
        return;
    }

    app_clear_output_buffers();

    if (function_exists('app_page_server_error')) {
        app_page_server_error($errorId);
        return;
    }

    app_render_fallback_server_error();
}

function app_handle_shutdown(): void
{
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    $exception = new ErrorException(
        $error['message'] ?? 'Fatal error',
        0,
        $error['type'] ?? E_ERROR,
        $error['file'] ?? 'unknown',
        $error['line'] ?? 0
    );

    app_handle_exception($exception);
}

if (php_sapi_name() !== 'cli') {
    ob_start();
    set_exception_handler('app_handle_exception');
    register_shutdown_function('app_handle_shutdown');
}

// Log incoming request
app_log_request();
