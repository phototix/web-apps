<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

/**
 * Add WAHA session "schedule_user_7" to user 8 with status "active"
 * 
 * This script:
 * 1. Validates user 8 exists and has session capacity
 * 2. Checks if session already exists for user 8
 * 3. Generates webhook secret
 * 4. Inserts session with status "active"
 * 5. Verifies the insertion
 */

function add_active_session_to_user(): void {
    $userId = 8;
    $sessionName = 'schedule_user_7';
    
    echo "=== Adding WAHA session '{$sessionName}' to user {$userId} ===\n\n";
    
    // 1. Validate user exists
    echo "1. Validating user {$userId}...\n";
    $pdo = app_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User {$userId} not found");
    }
    echo "   ✓ User found: {$user['email']} ({$user['name']})\n";
    echo "   ✓ Tier: {$user['tier']}, Max sessions: {$user['max_sessions']}\n";
    
    // 2. Check user's current session count
    $currentSessions = app_whatsapp_count_user_sessions($userId);
    echo "2. Checking session capacity...\n";
    echo "   ✓ Current sessions: {$currentSessions}/{$user['max_sessions']}\n";
    
    if ($currentSessions >= $user['max_sessions']) {
        throw new Exception("User {$userId} has reached session limit ({$currentSessions}/{$user['max_sessions']})");
    }
    
    // 3. Check if session already exists for this user
    echo "3. Checking if session '{$sessionName}' already exists for user...\n";
    $pdo = app_db();
    $stmt = $pdo->prepare("SELECT id FROM whatsapp_sessions WHERE user_id = :user_id AND session_name = :session_name");
    $stmt->execute(['user_id' => $userId, 'session_name' => $sessionName]);
    $existingSession = $stmt->fetch();
    
    if ($existingSession) {
        throw new Exception("Session '{$sessionName}' already exists for user {$userId} (ID: {$existingSession['id']})");
    }
    echo "   ✓ Session does not exist for this user\n";
    
    // 4. Check if session exists in WAHA API (optional verification)
    echo "4. Verifying session exists in WAHA API...\n";
    try {
        $sessionInfo = app_whatsapp_api_get("/api/sessions/{$sessionName}", app_whatsapp_api_key());
        $wahaStatus = strtolower($sessionInfo['status'] ?? 'stopped');
        echo "   ✓ Session found in WAHA API\n";
        echo "   ✓ WAHA status: {$wahaStatus}\n";
        
        // Map WAHA status to database status
        $dbStatus = 'pending';
        if ($wahaStatus === 'scan_qr_code') {
            $dbStatus = 'authenticating';
        } elseif ($wahaStatus === 'working') {
            $dbStatus = 'active';
        } elseif (in_array($wahaStatus, ['stopped', 'starting', 'failed'])) {
            $dbStatus = 'inactive';
        }
        
        if ($dbStatus !== 'active') {
            echo "   ⚠ Warning: WAHA status '{$wahaStatus}' maps to '{$dbStatus}', not 'active'\n";
            echo "   Proceeding with 'active' status as requested...\n";
        }
    } catch (Exception $e) {
        echo "   ⚠ Warning: Could not verify session in WAHA API: " . $e->getMessage() . "\n";
        echo "   Proceeding anyway...\n";
    }
    
    // 5. Generate webhook secret (64-character hex string)
    echo "5. Generating webhook secret...\n";
    $webhookSecret = bin2hex(random_bytes(32));
    echo "   ✓ Generated: {$webhookSecret}\n";
    
    // 6. Prepare session data
    $sessionData = [
        'user_id' => $userId,
        'session_name' => $sessionName,
        'api_key' => '', // Empty string like existing active session
        'endpoint_url' => app_whatsapp_api_endpoint(),
        'status' => 'active', // Force active status as requested
        'webhook_url' => 'https://n8n.ezy.chat/webhook/e8250965-f606-4d8e-9f55-47198bd88cf3/waha',
        'webhook_secret' => $webhookSecret
    ];
    
    echo "6. Inserting session into database...\n";
    echo "   Data to insert:\n";
    foreach ($sessionData as $key => $value) {
        $displayValue = $value;
        if ($key === 'webhook_secret') {
            $displayValue = substr($value, 0, 8) . '...' . substr($value, -8);
        }
        echo "   - {$key}: {$displayValue}\n";
    }
    
    // 7. Insert session
    try {
        $sessionId = app_db_insert_whatsapp_session($sessionData);
        echo "   ✓ Session inserted successfully! ID: {$sessionId}\n";
    } catch (Exception $e) {
        throw new Exception("Failed to insert session: " . $e->getMessage());
    }
    
    // 8. Verify insertion
    echo "7. Verifying insertion...\n";
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_sessions WHERE id = :id");
    $stmt->execute(['id' => $sessionId]);
    $insertedSession = $stmt->fetch();
    
    if (!$insertedSession) {
        throw new Exception("Failed to verify session insertion");
    }
    
    echo "   ✓ Verification successful!\n";
    echo "   - Session ID: {$insertedSession['id']}\n";
    echo "   - User ID: {$insertedSession['user_id']}\n";
    echo "   - Session Name: {$insertedSession['session_name']}\n";
    echo "   - Status: {$insertedSession['status']}\n";
    echo "   - Created: {$insertedSession['created_at']}\n";
    
    echo "\n=== Operation completed successfully! ===\n";
    echo "Session '{$sessionName}' has been added to user {$userId} with status 'active'.\n";
    echo "Session ID: {$sessionId}\n";
}

// Run the script
try {
    add_active_session_to_user();
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}