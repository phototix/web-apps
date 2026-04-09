<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Simulate what the homepage does
require_once 'app/bootstrap.php';

// Check what route the homepage uses
echo "Testing homepage route...\n";

// The homepage is probably '/' which routes to app_page_welcome or similar
// Let me check the router
require_once 'app/router.php';

// Actually, let me just call the bootstrap dispatch function
try {
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    app_dispatch_request();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
