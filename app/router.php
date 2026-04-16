<?php

declare(strict_types=1);

function app_request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) parse_url($uri, PHP_URL_PATH);

    if ($path === '') {
        return '/';
    }

    return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
}

function app_dispatch_request(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $path = app_request_path();

    // Check if this is an API request
    if (str_starts_with($path, '/api/')) {
        require_once __DIR__ . '/api.php';
        app_dispatch_api_request($method, $path);
        return;
    }

    $routes = [
        'GET' => [
            '/' => 'app_page_home',
            '/login' => 'app_page_login',
            '/register' => 'app_page_register',
            '/welcome' => 'app_page_welcome',
            '/logout' => 'app_page_logout',
            '/whatsapp-connect' => 'app_page_whatsapp_connect',
            '/groups' => 'app_page_groups',
            '/cases' => 'app_page_cases',
            '/settings' => 'app_page_settings',
            '/admin/users' => 'app_page_admin_users',
            '/admin/users/magic-login' => 'app_page_admin_users_magic_login',
            '/admin/my-users' => 'app_page_admin_my_users',
        ],
        'POST' => [
            '/login' => 'app_page_login',
            '/register' => 'app_page_register',
            '/logout' => 'app_page_logout',
            '/whatsapp-connect' => 'app_page_whatsapp_connect',
            '/admin/users' => 'app_page_admin_users',
            '/admin/my-users' => 'app_page_admin_my_users',
        ],
    ];

    $handler = $routes[$method][$path] ?? null;

    if ($handler === null) {
        app_page_not_found();
        return;
    }

    $handler();
}

function app_dispatch_api_request(string $method, string $path): void
{
    // Check for webhook requests first
    if (str_starts_with($path, '/api/webhooks/whatsapp/')) {
        app_handle_whatsapp_webhook($path);
        return;
    }
    
    $apiRoutes = [
        'GET' => [
            '/api/auth/profile' => 'api_auth_profile',
            '/api/auth/check' => 'api_auth_check',
            '/api/whatsapp/sessions' => 'api_whatsapp_get_sessions',
            '/api/whatsapp/sessions/{id}/qr' => 'api_whatsapp_get_qr',
            '/api/whatsapp/sessions/{id}/status' => 'api_whatsapp_get_session_status',
            '/api/whatsapp/groups' => 'api_whatsapp_get_groups',
            '/api/whatsapp/groups/{id}/messages' => 'api_whatsapp_get_group_messages',
            '/api/realtime/updates' => 'api_realtime_updates',
            '/api/whatsapp/incoming/verify' => 'api_whatsapp_incoming_verify',
            '/api/whatsapp/categories' => 'api_whatsapp_get_categories',
            '/api/whatsapp/categories/tree' => 'api_whatsapp_get_category_tree',
            '/api/whatsapp/categories/{id}' => 'api_whatsapp_get_category',
            '/api/whatsapp/categories/{id}/messages' => 'api_whatsapp_get_category_messages',
            '/api/whatsapp/categories/{id}/groups' => 'api_whatsapp_get_category_groups',
        ],
        'POST' => [
            '/api/auth/login' => 'api_auth_login',
            '/api/auth/register' => 'api_auth_register',
            '/api/auth/logout' => 'api_auth_logout',
            '/api/whatsapp/sessions' => 'api_whatsapp_create_session',
            '/api/whatsapp/sessions/{id}/sync' => 'api_whatsapp_sync_groups',
            '/api/whatsapp/groups/{id}/sync-messages' => 'api_whatsapp_sync_group_messages',
            '/api/whatsapp/groups' => 'api_whatsapp_create_group',
            '/api/whatsapp/messages' => 'api_whatsapp_send_message',
            '/api/whatsapp/groups/{id}/archive' => 'api_whatsapp_set_group_status',
            '/api/realtime/mark-read' => 'api_mark_update_read',
            '/api/whatsapp/incoming/message' => 'api_whatsapp_incoming_message',
            '/api/whatsapp/incoming/messages/batch' => 'api_whatsapp_incoming_messages_batch',
            '/api/whatsapp/categories' => 'api_whatsapp_create_category',
            '/api/whatsapp/messages/{id}/category' => 'api_whatsapp_assign_message_category',
            '/api/whatsapp/groups/{id}/category' => 'api_whatsapp_assign_group_category',
        ],
        'PUT' => [
            '/api/whatsapp/categories/{id}' => 'api_whatsapp_update_category',
        ],
        'DELETE' => [
            '/api/whatsapp/sessions/{id}' => 'api_whatsapp_delete_session',
            '/api/whatsapp/categories/{id}' => 'api_whatsapp_delete_category',
        ],
    ];

    // Check for dynamic routes with parameters
    $handler = $apiRoutes[$method][$path] ?? null;
    
    // Try to match dynamic routes
    if ($handler === null) {
        foreach ($apiRoutes[$method] ?? [] as $route => $routeHandler) {
            if (str_contains($route, '{')) {
                $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
                $pattern = str_replace('/', '\/', $pattern);
                
                if (preg_match('/^' . $pattern . '$/', $path, $matches)) {
                    $handler = $routeHandler;
                    $_GET['id'] = urldecode($matches[1] ?? '');
                    break;
                }
            }
        }
    }

    if ($handler === null) {
        api_not_found();
        return;
    }

    // Execute the handler with error handling
    api_execute_safely($handler);
}
