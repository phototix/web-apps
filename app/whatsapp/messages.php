<?php

declare(strict_types=1);

// WhatsApp Messages Management

function app_whatsapp_send_message(int $groupId, string $message, ?string $mediaPath = null, ?string $mediaType = null): array {
    $group = app_whatsapp_get_group($groupId);
    if (!$group) {
        throw new Exception('Group not found');
    }
    
    $session = app_whatsapp_get_session($group['session_id']);
    
    $payload = [
        'chatId' => $group['group_id'],
        'text' => $message,
        'session' => $session['session_name']
    ];
    
    if ($mediaPath && $mediaType) {
        $mediaEndpoint = match($mediaType) {
            'image' => '/sendImage',
            'video' => '/sendVideo',
            'audio', 'voice' => '/sendVoice',
            'document', 'file' => '/sendFile',
            default => '/sendText'
        };
        
        $payload['file'] = [
            'url' => app_whatsapp_base_url() . $mediaPath,
            'filename' => basename($mediaPath)
        ];
    } else {
        $mediaEndpoint = '/sendText';
    }
    
    try {
        $response = app_whatsapp_api_post(
            "/api{$mediaEndpoint}",
            $payload,
            app_whatsapp_api_key()
        );
        
        // Store sent message
        $messageId = app_db_insert_group_message([
            'group_id' => $groupId,
            'message_id' => $response['messageId'] ?? uniqid('msg_', true),
            'sender_number' => $session['phone_number'] ?? 'system',
            'sender_name' => 'You',
            'message_type' => $mediaType ?: 'text',
            'content' => $message,
            'media_url' => $mediaPath,
            'media_caption' => $mediaPath ? $message : null,
            'is_from_me' => true,
            'timestamp' => time() * 1000
        ]);
        
        // Update group last message info
        app_db_update_group_last_message($groupId, $message, time() * 1000);
        
        // Create real-time update
        app_create_realtime_update($group['user_id'], 'message_sent', (string) $groupId, [
            'group_id' => $groupId,
            'message_id' => $messageId,
            'content' => $message,
            'timestamp' => time() * 1000
        ]);
        
        return [
            'success' => true,
            'message_id' => $response['messageId'] ?? '',
            'timestamp' => time() * 1000
        ];
        
    } catch (Exception $e) {
        app_log('Failed to send message: ' . $e->getMessage(), 'ERROR', [
            'group_id' => $groupId,
            'has_media' => !empty($mediaPath)
        ]);
        throw new Exception('Failed to send message: ' . $e->getMessage());
    }
}

function app_whatsapp_process_incoming_message(array $webhookData): void {
    $payload = $webhookData['payload'] ?? [];
    $sessionId = $webhookData['session_id'] ?? 0;
    
    if (empty($payload) || !$sessionId) {
        return;
    }
    
    $messageData = $payload['payload'] ?? [];
    $chatId = $messageData['chatId'] ?? '';
    
    // Check if this is a group message (ends with @g.us)
    if (!str_ends_with($chatId, '@g.us')) {
        return; // Not a group message
    }
    
    // Find group by WhatsApp group ID
    $group = app_whatsapp_find_group_by_whatsapp_id($sessionId, $chatId);
    if (!$group) {
        // Group not in database, try to sync
        try {
            app_whatsapp_sync_groups($sessionId);
            $group = app_whatsapp_find_group_by_whatsapp_id($sessionId, $chatId);
        } catch (Exception $e) {
            app_log('Failed to sync groups for incoming message: ' . $e->getMessage(), 'ERROR');
            return;
        }
        
        if (!$group) {
            app_log('Group not found for incoming message: ' . $chatId, 'WARNING');
            return;
        }
    }
    
    // Extract message details
    $messageId = $messageData['id'] ?? '';
    $sender = $messageData['from'] ?? '';
    $senderName = $messageData['senderName'] ?? $sender;
    $content = '';
    $mediaUrl = null;
    $mediaCaption = null;
    $messageType = 'text';
    
    if (isset($messageData['body'])) {
        $content = $messageData['body'];
    } elseif (isset($messageData['caption'])) {
        $content = $messageData['caption'];
        $mediaCaption = $messageData['caption'];
    }
    
    // Check for media
    if (isset($messageData['mediaUrl'])) {
        $mediaUrl = $messageData['mediaUrl'];
        $messageType = $messageData['mediaType'] ?? 'image';
    }
    
    // Check if message is from me
    $isFromMe = ($messageData['fromMe'] ?? false) === true;
    
    // Store message
    $dbMessageId = app_db_insert_group_message([
        'group_id' => $group['id'],
        'message_id' => $messageId,
        'sender_number' => $sender,
        'sender_name' => $senderName,
        'message_type' => $messageType,
        'content' => $content,
        'media_url' => $mediaUrl,
        'media_caption' => $mediaCaption,
        'is_from_me' => $isFromMe,
        'timestamp' => $messageData['timestamp'] ?? time() * 1000
    ]);
    
    // Update group last message info
    app_db_update_group_last_message($group['id'], $content, $messageData['timestamp'] ?? time() * 1000);
    
    // Increment unread count if not from me
    if (!$isFromMe) {
        app_db_increment_group_unread_count($group['id']);
    }
    
    // Create real-time update
    app_create_realtime_update($group['user_id'], 'new_message', $group['id'], [
        'group_id' => $group['id'],
        'message_id' => $dbMessageId,
        'sender_name' => $senderName,
        'sender_number' => $sender,
        'content' => $content,
        'has_media' => !empty($mediaUrl),
        'media_type' => $messageType,
        'timestamp' => $messageData['timestamp'] ?? time() * 1000,
        'is_from_me' => $isFromMe
    ]);
}

function app_whatsapp_find_group_by_whatsapp_id(int $sessionId, string $whatsappGroupId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM whatsapp_groups 
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    $stmt->execute([
        'session_id' => $sessionId,
        'group_id' => $whatsappGroupId
    ]);
    return $stmt->fetch() ?: null;
}

function app_db_insert_group_message(array $data): int {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        INSERT INTO group_messages 
        (group_id, message_id, sender_number, sender_name, message_type, 
         content, media_url, media_caption, is_from_me, timestamp, created_at)
        VALUES (:group_id, :message_id, :sender_number, :sender_name, :message_type,
                :content, :media_url, :media_caption, :is_from_me, :timestamp, NOW())
    ");
    
    $stmt->execute([
        'group_id' => $data['group_id'],
        'message_id' => $data['message_id'],
        'sender_number' => $data['sender_number'],
        'sender_name' => $data['sender_name'],
        'message_type' => $data['message_type'],
        'content' => $data['content'],
        'media_url' => $data['media_url'],
        'media_caption' => $data['media_caption'],
        'is_from_me' => $data['is_from_me'] ? 1 : 0,
        'timestamp' => $data['timestamp']
    ]);
    
    return (int) $pdo->lastInsertId();
}

function app_db_update_group_last_message(int $groupId, string $message, int $timestamp): bool {
    $preview = strlen($message) > 50 ? substr($message, 0, 47) . '...' : $message;
    
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET last_message_preview = :preview, 
            last_message_timestamp = :timestamp,
            updated_at = NOW()
        WHERE id = :id
    ");
    return $stmt->execute([
        'preview' => $preview,
        'timestamp' => $timestamp,
        'id' => $groupId
    ]);
}

function app_db_increment_group_unread_count(int $groupId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET unread_count = unread_count + 1,
            updated_at = NOW()
        WHERE id = :id
    ");
    return $stmt->execute(['id' => $groupId]);
}

function app_db_reset_group_unread_count(int $groupId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET unread_count = 0,
            updated_at = NOW()
        WHERE id = :id
    ");
    return $stmt->execute(['id' => $groupId]);
}

function app_db_get_group_message_by_whatsapp_id(string $whatsappMessageId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM group_messages 
        WHERE whatsapp_message_id = :whatsapp_message_id 
        LIMIT 1
    ");
    $stmt->execute(['whatsapp_message_id' => $whatsappMessageId]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_sync_group_messages(int $groupId): array {
    $group = app_whatsapp_get_group($groupId);
    if (!$group) {
        throw new Exception('Group not found');
    }
    
    $session = app_whatsapp_get_session($group['session_id']);
    if (!$session || $session['status'] !== 'active') {
        throw new Exception('Session is not active');
    }
    
    try {
        // Get messages from WAHA API
        // Note: WAHA API might have rate limits or pagination
        $messages = app_whatsapp_api_get(
            "/api/{$session['session_name']}/chats/{$group['group_id']}/messages?limit=100",
            app_whatsapp_api_key()
        );
        
        $synced = 0;
        $skipped = 0;
        
        foreach ($messages as $messageData) {
            // Check if message already exists
            $existing = app_db_get_group_message_by_whatsapp_id($messageData['id'] ?? '');
            
            if (!$existing) {
                // Extract sender info
                $sender = $messageData['sender'] ?? [];
                $senderNumber = '';
                $senderName = '';
                
                if (is_array($sender)) {
                    $senderNumber = $sender['user'] ?? '';
                    if ($senderNumber && isset($sender['server'])) {
                        $senderNumber .= '@' . $sender['server'];
                    }
                    $senderName = $sender['name'] ?? $senderNumber;
                } elseif (is_string($sender)) {
                    $senderNumber = $sender;
                    $senderName = $sender;
                }
                
                // Check if message is from the session user
                $isFromMe = false;
                if (isset($session['account_info']['id']) && $senderNumber === $session['account_info']['id']) {
                    $isFromMe = true;
                    $senderName = 'You';
                }
                
                // Extract message content
                $content = '';
                $mediaUrl = null;
                $mediaType = null;
                $mediaCaption = null;
                
                if (isset($messageData['body'])) {
                    $content = $messageData['body'];
                } elseif (isset($messageData['caption'])) {
                    $content = $messageData['caption'];
                    $mediaCaption = $messageData['caption'];
                }
                
                if (isset($messageData['mediaUrl'])) {
                    $mediaUrl = $messageData['mediaUrl'];
                    $mediaType = $messageData['type'] ?? 'image';
                }
                
                // Store message
                $messageId = app_db_insert_group_message([
                    'group_id' => $groupId,
                    'message_id' => $messageData['id'] ?? uniqid('msg_', true),
                    'whatsapp_message_id' => $messageData['id'] ?? '',
                    'sender_number' => $senderNumber,
                    'sender_name' => $senderName,
                    'message_type' => $messageData['type'] ?? 'text',
                    'content' => $content,
                    'media_url' => $mediaUrl,
                    'media_caption' => $mediaCaption,
                    'is_from_me' => $isFromMe,
                    'timestamp' => $messageData['timestamp'] ?? time() * 1000
                ]);
                
                if ($messageId) {
                    $synced++;
                    
                    // Update group last message if this is the newest
                    if (!$isFromMe) {
                        app_db_update_group_last_message($groupId, $content, $messageData['timestamp'] ?? time() * 1000);
                    }
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'success' => true,
            'synced' => $synced,
            'skipped' => $skipped,
            'total' => count($messages)
        ];
        
    } catch (Exception $e) {
        app_log('Failed to sync group messages: ' . $e->getMessage(), 'ERROR', [
            'group_id' => $groupId,
            'session_id' => $group['session_id']
        ]);
        throw new Exception('Failed to sync messages: ' . $e->getMessage());
    }
}