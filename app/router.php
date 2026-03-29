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

    $routes = [
        'GET' => [
            '/' => 'app_page_home',
            '/login' => 'app_page_login',
            '/register' => 'app_page_register',
            '/welcome' => 'app_page_welcome',
            '/logout' => 'app_page_logout',
        ],
        'POST' => [
            '/login' => 'app_page_login',
            '/register' => 'app_page_register',
            '/logout' => 'app_page_logout',
        ],
    ];

    $handler = $routes[$method][$path] ?? null;

    if ($handler === null) {
        app_page_not_found();

        return;
    }

    $handler();
}