<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include bootstrap
require_once 'app/bootstrap.php';

// Manually call what the router would do for '/'
echo "Looking for route '/'...\n";

// Check the router routes
$routes = [
    '/' => 'app_page_welcome',
    '/welcome' => 'app_page_welcome',
    '/login' => 'app_page_login',
    '/register' => 'app_page_register',
    '/whatsapp-connect' => 'app_page_whatsapp_connect',
    '/admin/users' => 'app_page_admin_users',
];

if (isset($routes['/'])) {
    $function = $routes['/'];
    echo "Found route: $function\n";
    
    if (function_exists($function)) {
        echo "Function exists. Calling it...\n";
        // Don't actually call it as it might output HTML
        echo "Function would be called.\n";
    } else {
        echo "ERROR: Function $function does not exist!\n";
    }
} else {
    echo "No route found for '/'\n";
}

// Also test app_page_welcome directly
echo "\nTesting if app_page_welcome exists: ";
echo function_exists('app_page_welcome') ? "YES\n" : "NO\n";
