<?php

declare(strict_types=1);

// WhatsApp Webhooks and Real-time Updates

function app_create_realtime_update(int $userId, string $updateType, string $entityId, array $data): int {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        INSERT INTO realtime_updates 
        (user_id, update_type, entity_id, data, created_at, expires_at)
        VALUES (:user_id, :update_type, :entity_id, :data, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    
    $stmt->execute([
        'user_id' => $userId,
        'update_type' => $updateType,
        'entity_id' => $entityId,
        'data' => json_encode($data)
    ]);
    
    return (int) $pdo->lastInsertId();
}

function app_get_realtime_updates(int $userId, int $lastUpdateId = 0): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT id, update_type, entity_id, data, created_at
        FROM realtime_updates 
        WHERE user_id = :user_id 
        AND id > :last_id
        AND expires_at > NOW()
        ORDER BY id ASC
        LIMIT 50
    ");
    
    $stmt->execute([
        'user_id' => $userId,
        'last_id' => $lastUpdateId
    ]);
    
    $updates = $stmt->fetchAll();
    
    // Format updates
    $formatted = [];
    foreach ($updates as $update) {
        $formatted[] = [
            'id' => (int) $update['id'],
            'update_type' => $update['update_type'],
            'entity_id' => $update['entity_id'],
            'data' => json_decode($update['data'], true),
            'created_at' => $update['created_at']
        ];
    }
    
    return $formatted;
}

function app_mark_update_read(int $userId, int $updateId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE realtime_updates 
        SET is_read = TRUE 
        WHERE id = :id AND user_id = :user_id
    ");
    return $stmt->execute(['id' => $updateId, 'user_id' => $userId]);
}

function app_cleanup_old_updates(): int {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        DELETE FROM realtime_updates 
        WHERE expires_at <= NOW()
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

function app_queue_webhook_event(int $sessionId, array $data): int {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        INSERT INTO webhook_events_queue 
        (session_id, event_id, event_type, payload, status, created_at)
        VALUES (:session_id, :event_id, :event_type, :payload, 'pending', NOW())
    ");
    
    $stmt->execute([
        'session_id' => $sessionId,
        'event_id' => $data['id'] ?? uniqid('evt_', true),
        'event_type' => $data['event'] ?? 'unknown',
        'payload' => json_encode($data)
    ]);
    
    return (int) $pdo->lastInsertId();
}

function app_process_webhook_event(array $event): void {
    $eventType = $event['event_type'];
    $payload = json_decode($event['payload'], true);
    
    switch ($eventType) {
        case 'message':
            app_whatsapp_process_incoming_message([
                'session_id' => $event['session_id'],
                'payload' => $payload
            ]);
            break;
            
        case 'session.status':
            // Update session status
            $status = $payload['payload']['status'] ?? 'unknown';
            $pdo = app_db();
            $stmt = $pdo->prepare("
                UPDATE whatsapp_sessions 
                SET status = :status, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['status' => $status, 'id' => $event['session_id']]);
            
            // Get user ID for real-time update
            $session = app_whatsapp_get_session($event['session_id']);
            if ($session) {
                app_create_realtime_update($session['user_id'], 'session_status', $event['session_id'], [
                    'session_id' => $event['session_id'],
                    'status' => $status
                ]);
            }
            break;
            
        case 'group.v2.join':
        case 'group.v2.leave':
        case 'group.v2.update':
        case 'group.v2.participants':
            // Trigger group sync
            try {
                app_whatsapp_sync_groups($event['session_id']);
                
                $session = app_whatsapp_get_session($event['session_id']);
                if ($session) {
                    app_create_realtime_update($session['user_id'], 'group_update', $event['session_id'], [
                        'session_id' => $event['session_id'],
                        'event_type' => $eventType
                    ]);
                }
            } catch (Exception $e) {
                app_log('Failed to sync groups after webhook: ' . $e->getMessage(), 'ERROR');
            }
            break;
            
        case 'message.reaction':
            // Handle message reactions
            // Could update message in database if needed
            break;
    }
}

function app_get_pending_webhook_events(int $limit = 10): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM webhook_events_queue 
        WHERE status = 'pending' 
        AND (next_retry_at IS NULL OR next_retry_at <= NOW())
        ORDER BY created_at ASC 
        LIMIT :limit
    ");
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_mark_webhook_event_processing(int $eventId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE webhook_events_queue 
        SET status = 'processing' 
        WHERE id = :id
    ");
    return $stmt->execute(['id' => $eventId]);
}

function app_mark_webhook_event_completed(int $eventId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE webhook_events_queue 
        SET status = 'completed', processed_at = NOW() 
        WHERE id = :id
    ");
    return $stmt->execute(['id' => $eventId]);
}

function app_mark_webhook_event_failed(int $eventId, int $maxAttempts = 3): bool {
    $pdo = app_db();
    
    // Get current attempts
    $stmt = $pdo->prepare("SELECT attempts FROM webhook_events_queue WHERE id = :id");
    $stmt->execute(['id' => $eventId]);
    $result = $stmt->fetch();
    $attempts = (int) ($result['attempts'] ?? 0) + 1;
    
    if ($attempts >= $maxAttempts) {
        // Mark as failed permanently
        $stmt = $pdo->prepare("
            UPDATE webhook_events_queue 
            SET status = 'failed', attempts = :attempts, processed_at = NOW() 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $eventId, 'attempts' => $attempts]);
    } else {
        // Schedule retry with exponential backoff
        $nextRetrySeconds = pow(2, $attempts) * 60; // 2^attempts minutes
        $nextRetry = date('Y-m-d H:i:s', time() + $nextRetrySeconds);
        
        $stmt = $pdo->prepare("
            UPDATE webhook_events_queue 
            SET status = 'pending', attempts = :attempts, next_retry_at = :next_retry 
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $eventId,
            'attempts' => $attempts,
            'next_retry' => $nextRetry
        ]);
    }
}