<?php

declare(strict_types=1);

// Webhook Handler for WhatsApp Integration

function app_handle_whatsapp_webhook(string $path): void {
    // Extract session/user ID from path: /api/webhooks/whatsapp/{user_id}
    $parts = explode('/', $path);
    $userId = (int) end($parts);
    
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook URL']);
        exit;
    }
    
    // Get request signature
    $signature = $_SERVER['HTTP_X_WAHA_SIGNATURE'] ?? '';
    $payload = file_get_contents('php://input');
    
    // Find session for this user
    $session = app_find_session_by_user_id($userId);
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        exit;
    }
    
    // Verify HMAC signature if secret is set
    if ($session['webhook_secret'] && !app_verify_webhook_signature($signature, $payload, $session['webhook_secret'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
    
    // Parse payload
    $data = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    // Queue for processing
    $eventId = app_queue_webhook_event($session['id'], $data);
    
    // Immediate response (async processing)
    http_response_code(202);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'queued',
        'event_id' => $eventId,
        'user_id' => $userId,
        'timestamp' => time()
    ]);
}

function app_find_session_by_user_id(int $userId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM whatsapp_sessions 
        WHERE user_id = :user_id 
        AND status = 'active'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch() ?: null;
}

function app_verify_webhook_signature(string $signature, string $payload, string $secret): bool {
    if (empty($signature) || empty($secret)) {
        return true; // Skip verification if not configured
    }
    
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expectedSignature, $signature);
}

// Background webhook processor (to be called from cron)
function app_process_webhook_queue(int $batchSize = 10): array {
    $events = app_get_pending_webhook_events($batchSize);
    $processed = 0;
    $failed = 0;
    
    foreach ($events as $event) {
        try {
            // Mark as processing
            app_mark_webhook_event_processing($event['id']);
            
            // Process the event
            app_process_webhook_event($event);
            
            // Mark as completed
            app_mark_webhook_event_completed($event['id']);
            $processed++;
            
        } catch (Exception $e) {
            app_log('Failed to process webhook event: ' . $e->getMessage(), 'ERROR', [
                'event_id' => $event['id'],
                'event_type' => $event['event_type']
            ]);
            
            app_mark_webhook_event_failed($event['id']);
            $failed++;
        }
    }
    
    // Cleanup old real-time updates
    $cleaned = app_cleanup_old_updates();
    
    return [
        'processed' => $processed,
        'failed' => $failed,
        'cleaned_updates' => $cleaned,
        'timestamp' => time()
    ];
}
