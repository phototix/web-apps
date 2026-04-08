<?php

declare(strict_types=1);

function app_log(string $message, string $level = 'INFO', ?array $context = null): void
{
    $logDir = dirname(__DIR__) . '/logs/';
    
    // Ensure log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . date('Y-m-d') . '.log';
    
    // Format context if provided
    $contextStr = '';
    if ($context !== null && count($context) > 0) {
        $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    $logEntry = sprintf(
        "[%s] %s: %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $contextStr
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also log errors to PHP error log
    if (in_array(strtoupper($level), ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])) {
        error_log($message . $contextStr);
    }
}

function app_log_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    app_log("Request: {$method} {$uri}", 'INFO', [
        'ip' => $ip,
        'user_agent' => $userAgent,
        'timestamp' => time()
    ]);
}

function app_log_error(Throwable $exception): void
{
    app_log($exception->getMessage(), 'ERROR', [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
}

function app_log_auth(string $email, string $action, bool $success = true): void
{
    $level = $success ? 'INFO' : 'WARNING';
    $status = $success ? 'success' : 'failed';
    
    app_log("Authentication {$action} {$status} for {$email}", $level, [
        'email' => $email,
        'action' => $action,
        'success' => $success,
        'timestamp' => time()
    ]);
}

function app_log_database(string $query, ?array $params = null, ?string $error = null): void
{
    $level = $error ? 'ERROR' : 'DEBUG';
    
    $context = [
        'query' => $query,
        'timestamp' => time()
    ];
    
    if ($params !== null) {
        $context['params'] = $params;
    }
    
    if ($error !== null) {
        $context['error'] = $error;
    }
    
    app_log("Database query executed", $level, $context);
}