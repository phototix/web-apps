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

function app_audit_action_labels(): array
{
    return [
        'login' => 'Login',
        'logout' => 'Logout',
        'delete_message' => 'Delete Messages',
        'delete_page' => 'Delete Pages',
        'archive_group' => 'Archive Group',
        'unarchive_group' => 'Unarchive Group',
        'edit_page' => 'Edit Pages',
        'create_session' => 'Create New Session',
        'delete_session' => 'Delete Session',
        'delete_user' => 'Delete User',
        'delete_account' => 'Delete Account',
        'edit_category' => 'Edit Category',
        'delete_category' => 'Delete Category',
        'send_message' => 'Send Message',
    ];
}

function app_log_audit(string $action, array $context = [], ?array $actor = null): void
{
    $actorData = $actor ?? app_current_user();
    $requestIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $baseContext = [
        'audit' => true,
        'action' => $action,
        'actor_id' => $actorData['id'] ?? null,
        'actor_name' => $actorData['name'] ?? null,
        'actor_email' => $actorData['email'] ?? null,
        'actor_role' => $actorData['role'] ?? null,
        'actor_parent_id' => $actorData['parent_id'] ?? null,
        'ip' => $requestIp,
        'user_agent' => $userAgent,
    ];

    app_log('Audit ' . $action, 'INFO', array_merge($baseContext, $context));
}

function app_parse_audit_log_line(string $line): ?array
{
    $trimmed = trim($line);
    if ($trimmed === '') {
        return null;
    }

    if (!preg_match('/^\[(.+?)\]\s+(\w+):\s+(.*)$/', $trimmed, $matches)) {
        return null;
    }

    $timestamp = $matches[1];
    $level = $matches[2];
    $rest = $matches[3];

    $context = null;
    $message = $rest;
    $jsonMatch = [];
    if (preg_match('/\s(\{.*\})$/', $rest, $jsonMatch)) {
        $json = $jsonMatch[1];
        $message = trim(substr($rest, 0, -strlen($json)));
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $context = $decoded;
        }
    }

    if (!is_array($context) || empty($context['audit'])) {
        return null;
    }

    $action = $context['action'] ?? null;
    if (!is_string($action) || $action === '') {
        return null;
    }

    return [
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'action' => $action,
        'context' => $context,
    ];
}

function app_get_audit_entries_for_user(array $viewer, int $limit = 200): array
{
    $role = $viewer['role'] ?? '';
    if (!in_array($role, ['admin', 'superadmin'], true)) {
        return [];
    }

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        return [];
    }

    $files = glob($logDir . '/*.log') ?: [];
    rsort($files);

    $entries = [];
    $viewerId = (int) ($viewer['id'] ?? 0);

    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        $lines = array_reverse($lines);
        foreach ($lines as $line) {
            $entry = app_parse_audit_log_line($line);
            if ($entry === null) {
                continue;
            }

            $context = $entry['context'] ?? [];
            $actorId = (int) ($context['actor_id'] ?? 0);
            $actorParentId = (int) ($context['actor_parent_id'] ?? 0);

            if ($role === 'admin') {
                if ($actorId !== $viewerId && $actorParentId !== $viewerId) {
                    continue;
                }
            }

            $entries[] = $entry;
            if (count($entries) >= $limit) {
                return $entries;
            }
        }
    }

    return $entries;
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
