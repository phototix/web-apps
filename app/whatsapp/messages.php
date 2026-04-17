<?php

declare(strict_types=1);

// WhatsApp Messages Management

function app_whatsapp_send_message(int $sessionId, string $groupId, string $message, ?string $mediaPath = null, ?string $mediaType = null): array {
    $group = app_whatsapp_get_group_by_session_and_id($sessionId, $groupId);
    if (!$group) {
        throw new Exception('Group not found');
    }
    
    $session = app_whatsapp_get_session($sessionId);
    
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
            'session_id' => $sessionId,
            'group_id' => $groupId,
            'message_id' => $response['messageId'] ?? uniqid('msg_', true),
            'sender_number' => $session['phone_number'] ?? 'system',
            'sender_name' => 'You',
            'message_type' => $mediaType ?: 'chat',
            'content' => $message,
            'media_url' => $mediaPath,
            'media_caption' => $mediaPath ? $message : null,
            'is_from_me' => true,
            'timestamp' => time() * 1000
        ]);
        
        // Update group last message info
        app_db_update_group_last_message($sessionId, $groupId, $message, time() * 1000);
        
        // Create real-time update
        app_create_realtime_update($group['user_id'], 'message_sent', $groupId, [
            'session_id' => $sessionId,
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
    $mediaType = null;
    $mediaSize = null;
    $quotedMessageId = null;
    $messageType = 'chat';
    
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
        $mediaType = $messageData['mediaType'] ?? null;
        $mediaSize = $messageData['fileSize'] ?? null;
    }
    
    // Check for quoted message
    if (isset($messageData['quotedMsg']) && isset($messageData['quotedMsg']['id'])) {
        $quotedMessageId = $messageData['quotedMsg']['id'];
    }
    
    // Check if message is from me
    $isFromMe = ($messageData['fromMe'] ?? false) === true;
    
    // Store message
    $dbMessageId = app_db_insert_group_message([
        'session_id' => $sessionId,
        'group_id' => $group['group_id'],
        'message_id' => $messageId,
        'sender_number' => $sender,
        'sender_name' => $senderName,
        'message_type' => $messageType,
        'content' => $content,
        'media_url' => $mediaUrl,
        'media_caption' => $mediaCaption,
        'quoted_message_id' => $quotedMessageId,
        'media_type' => $mediaType,
        'media_size' => $mediaSize,
        'is_from_me' => $isFromMe,
        'timestamp' => $messageData['timestamp'] ?? time() * 1000
    ]);
    
    // Update group last message info
    app_db_update_group_last_message($sessionId, $group['group_id'], $content, $messageData['timestamp'] ?? time() * 1000);
    
    // Increment unread count if not from me
    if (!$isFromMe) {
        app_db_increment_group_unread_count($sessionId, $group['group_id']);
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
        (session_id, group_id, message_id, sender_number, sender_name, message_type, 
         content, media_url, media_caption, quoted_message_id, media_type, media_size,
         is_from_me, timestamp, created_at)
        VALUES (:session_id, :group_id, :message_id, :sender_number, :sender_name, :message_type,
                :content, :media_url, :media_caption, :quoted_message_id, :media_type, :media_size,
                :is_from_me, :timestamp, NOW())
    ");
    
    $stmt->execute([
        'session_id' => $data['session_id'],
        'group_id' => $data['group_id'],
        'message_id' => $data['message_id'],
        'sender_number' => $data['sender_number'],
        'sender_name' => $data['sender_name'],
        'message_type' => $data['message_type'],
        'content' => $data['content'],
        'media_url' => $data['media_url'] ?? null,
        'media_caption' => $data['media_caption'] ?? null,
        'quoted_message_id' => $data['quoted_message_id'] ?? null,
        'media_type' => $data['media_type'] ?? null,
        'media_size' => $data['media_size'] ?? null,
        'is_from_me' => $data['is_from_me'] ? 1 : 0,
        'timestamp' => $data['timestamp']
    ]);
    
    return (int) $pdo->lastInsertId();
}

function app_db_update_group_last_message(int $sessionId, string $groupId, string $message, int $timestamp): bool {
    $preview = strlen($message) > 50 ? substr($message, 0, 47) . '...' : $message;
    
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET last_message_preview = :preview, 
            last_message_timestamp = :timestamp,
            updated_at = NOW()
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    return $stmt->execute([
        'preview' => $preview,
        'timestamp' => $timestamp,
        'session_id' => $sessionId,
        'group_id' => $groupId
    ]);
}

function app_db_increment_group_unread_count(int $sessionId, string $groupId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET unread_count = unread_count + 1,
            updated_at = NOW()
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    return $stmt->execute(['session_id' => $sessionId, 'group_id' => $groupId]);
}

function app_db_reset_group_unread_count(int $sessionId, string $groupId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET unread_count = 0,
            updated_at = NOW()
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    return $stmt->execute(['session_id' => $sessionId, 'group_id' => $groupId]);
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

function app_whatsapp_get_message_for_user(int $messageId, int $userId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT gm.*, ws.user_id, ws.session_name
        FROM group_messages gm
        JOIN whatsapp_sessions ws ON gm.session_id = ws.id
        WHERE gm.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $messageId]);
    $message = $stmt->fetch();

    if (!$message || (int) $message['user_id'] !== $userId) {
        return null;
    }

    return $message;
}

function app_whatsapp_delete_message_by_id(int $messageId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("DELETE FROM group_messages WHERE id = :id");
    return $stmt->execute(['id' => $messageId]);
}

function app_whatsapp_get_latest_group_message(int $sessionId, string $groupId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT content, media_caption, caption, timestamp
        FROM group_messages
        WHERE session_id = :session_id AND group_id = :group_id
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([
        'session_id' => $sessionId,
        'group_id' => $groupId
    ]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_clear_group_last_message(int $sessionId, string $groupId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups
        SET last_message_preview = NULL,
            last_message_timestamp = NULL,
            updated_at = NOW()
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    return $stmt->execute([
        'session_id' => $sessionId,
        'group_id' => $groupId
    ]);
}

function app_whatsapp_delete_remote_message(string $sessionName, string $chatId, string $messageId): void {
    $endpoints = [
        "/api/{$sessionName}/messages/{$messageId}",
        "/api/{$sessionName}/chats/{$chatId}/messages/{$messageId}"
    ];

    $lastException = null;
    foreach ($endpoints as $endpoint) {
        try {
            app_whatsapp_api_delete($endpoint, app_whatsapp_api_key());
            return;
        } catch (Exception $e) {
            $lastException = $e;
        }
    }

    if ($lastException) {
        throw $lastException;
    }
}

function app_whatsapp_sync_group_messages(int $sessionId, string $groupId): array {
    $group = app_whatsapp_get_group_by_session_and_id($sessionId, $groupId);
    if (!$group) {
        throw new Exception('Group not found');
    }
    
    $session = app_whatsapp_get_session($sessionId);
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
                    'session_id' => $group['session_id'],
                    'group_id' => $group['group_id'],
                    'message_id' => $messageData['id'] ?? uniqid('msg_', true),
                    'whatsapp_message_id' => $messageData['id'] ?? '',
                    'sender_number' => $senderNumber,
                    'sender_name' => $senderName,
                    'message_type' => $messageData['type'] ?? 'chat',
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
                        app_db_update_group_last_message($group['session_id'], $group['group_id'], $content, $messageData['timestamp'] ?? time() * 1000);
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

function app_whatsapp_store_incoming_message(array $messageData): array
{
    $pdo = app_db();

    if (!isset($messageData['category_id'])) {
        $messageData['category_id'] = app_whatsapp_detect_message_category(
            (int) $messageData['session_id'],
            (string) $messageData['message_type'],
            (string) $messageData['content'],
            (string) $messageData['media_caption'],
            (string) $messageData['caption']
        );
    }
    
    // Check if message already exists
    $stmt = $pdo->prepare("
        SELECT id FROM group_messages 
        WHERE session_id = :session_id 
        AND group_id = :group_id 
        AND message_id = :message_id
    ");
    $stmt->execute([
        'session_id' => $messageData['session_id'],
        'group_id' => $messageData['chat_id'],
        'message_id' => $messageData['message_id']
    ]);
    
    $existing = $stmt->fetch();
    if ($existing) {
        return ['id' => $existing['id'], 'existing' => true, 'category_id' => null, 'category_prompt' => null];
    }
    
    // Check if group exists
    $stmt = $pdo->prepare("
        SELECT id FROM whatsapp_groups 
        WHERE session_id = :session_id 
        AND group_id = :group_id
    ");
    $stmt->execute([
        'session_id' => $messageData['session_id'],
        'group_id' => $messageData['chat_id']
    ]);
    
    $groupExists = $stmt->fetch();
    if (!$groupExists) {
        // Try to sync groups
        try {
            app_whatsapp_sync_groups($messageData['session_id']);
            
            // Check again
            $stmt->execute([
                'session_id' => $messageData['session_id'],
                'group_id' => $messageData['chat_id']
            ]);
            $groupExists = $stmt->fetch();
            
            if (!$groupExists) {
                // Create a placeholder group
                $stmt = $pdo->prepare("
                    INSERT INTO whatsapp_groups 
                    (session_id, group_id, name, participant_count, created_at, updated_at)
                    VALUES (:session_id, :group_id, :name, 0, NOW(), NOW())
                ");
                $stmt->execute([
                    'session_id' => $messageData['session_id'],
                    'group_id' => $messageData['chat_id'],
                    'name' => 'Unknown Group (' . substr($messageData['chat_id'], 0, 10) . '...)'
                ]);
            }
        } catch (Exception $e) {
            throw new Exception('Group not found and failed to sync: ' . $e->getMessage());
        }
    }
    
    // Insert new message
    $stmt = $pdo->prepare("
        INSERT INTO group_messages 
        (session_id, group_id, message_id, sender_number, sender_name, message_type, 
         content, media_url, media_caption, category_id, caption, is_from_me, timestamp, created_at,
         quoted_message_id, media_type, media_size)
        VALUES (:session_id, :group_id, :message_id, :sender_number, :sender_name, :message_type,
                :content, :media_url, :media_caption, :category_id, :caption, :is_from_me, :timestamp, NOW(),
                :quoted_message_id, :media_type, :media_size)
    ");
    
    $stmt->execute([
        'session_id' => $messageData['session_id'],
        'group_id' => $messageData['chat_id'],
        'message_id' => $messageData['message_id'],
        'sender_number' => $messageData['sender'],
        'sender_name' => $messageData['sender_name'],
        'message_type' => $messageData['message_type'],
        'content' => $messageData['content'],
        'media_url' => $messageData['media_url'] ?: null,
        'media_caption' => $messageData['media_caption'] ?: null,
        'category_id' => $messageData['category_id'] ?: null,
        'caption' => $messageData['caption'] ?: null,
        'is_from_me' => $messageData['is_from_me'] ? 1 : 0,
        'timestamp' => $messageData['timestamp'],
        'quoted_message_id' => $messageData['quoted_message_id'] ?: null,
        'media_type' => $messageData['media_type'] ?: null,
        'media_size' => $messageData['media_size'] ?: null
    ]);
    
    $messageId = (int) $pdo->lastInsertId();
    $categoryPrompt = null;
    if (!empty($messageData['category_id'])) {
        $category = app_whatsapp_get_category((int) $messageData['category_id']);
        if (!empty($category['prompt'])) {
            $categoryPrompt = $category['prompt'];
        }
    }

    if (!empty($messageData['category_id']) && empty($messageData['is_from_me'])) {
        app_whatsapp_send_category_assignment_notification(
            (int) $messageData['session_id'],
            (string) $messageData['chat_id'],
            (int) $messageData['category_id'],
            (string) $messageData['message_id']
        );
    }
    
    // Update group's last message
    $preview = strlen($messageData['content']) > 50 ? substr($messageData['content'], 0, 47) . '...' : $messageData['content'];
    if (!empty($messageData['media_caption'])) {
        $preview = $messageData['media_caption'];
    } elseif (!empty($messageData['media_url'])) {
        $preview = "[{$messageData['message_type']}]";
    }
    
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET last_message_preview = :preview, 
            last_message_timestamp = :timestamp,
            unread_count = unread_count + 1,
            updated_at = NOW()
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    $stmt->execute([
        'preview' => $preview,
        'timestamp' => $messageData['timestamp'],
        'session_id' => $messageData['session_id'],
        'group_id' => $messageData['chat_id']
    ]);
    
    // Create real-time update
    $stmt = $pdo->prepare("
        INSERT INTO realtime_updates 
        (user_id, update_type, entity_id, data, created_at)
        SELECT ws.user_id, 'new_message', :entity_id, :data, NOW()
        FROM whatsapp_sessions ws
        WHERE ws.id = :session_id
    ");
    $stmt->execute([
        'entity_id' => $messageData['chat_id'],
        'data' => json_encode([
            'session_id' => $messageData['session_id'],
            'group_id' => $messageData['chat_id'],
            'message_id' => $messageId,
            'sender' => $messageData['sender'],
            'sender_name' => $messageData['sender_name'],
            'content' => $messageData['content'],
            'message_type' => $messageData['message_type'],
            'category_id' => $messageData['category_id'],
            'timestamp' => $messageData['timestamp'],
            'is_from_me' => $messageData['is_from_me']
        ]),
        'session_id' => $messageData['session_id']
    ]);
    
    return [
        'id' => $messageId,
        'existing' => false,
        'category_id' => $messageData['category_id'] ?: null,
        'category_prompt' => $categoryPrompt
    ];
}

function app_whatsapp_detect_message_category(int $sessionId, string $messageType, string $content, string $mediaCaption, string $caption): ?int
{
    $messageType = strtolower(trim($messageType));
    $text = '';

    if ($messageType === 'chat') {
        $text = trim($content);
    } elseif (in_array($messageType, ['image', 'document'], true)) {
        $text = trim($mediaCaption ?: $caption ?: $content);
    }

    if ($text === '') {
        return null;
    }

    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT c.id, c.keywords
        FROM categories c
        JOIN whatsapp_sessions ws ON c.user_id = ws.user_id
        WHERE ws.id = :session_id
          AND c.is_active = TRUE
          AND c.keywords IS NOT NULL
          AND c.keywords <> ''
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $stmt->execute(['session_id' => $sessionId]);
    $categories = $stmt->fetchAll();

    $haystack = strtolower($text);

    foreach ($categories as $category) {
        $keywords = app_whatsapp_parse_keywords((string) $category['keywords']);
        foreach ($keywords as $keyword) {
            $needle = strtolower($keyword);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return (int) $category['id'];
            }
        }
    }

    return null;
}

function app_whatsapp_parse_keywords(string $keywords): array
{
    $parts = preg_split('/[\r\n,;]+/', $keywords) ?: [];
    $cleaned = [];

    foreach ($parts as $part) {
        $value = trim($part);
        if ($value !== '') {
            $cleaned[] = $value;
        }
    }

    return $cleaned;
}

function app_whatsapp_send_category_assignment_notification(int $sessionId, string $chatId, int $categoryId, string $messageId): void
{
    if (!str_ends_with($chatId, '@g.us')) {
        return;
    }

    $session = app_whatsapp_get_session($sessionId);
    if (!$session) {
        return;
    }

    $category = app_whatsapp_get_category($categoryId);
    if (!$category) {
        return;
    }

    $message = 'Assigned to: ' . $category['name'];

    try {
        app_whatsapp_api_post(
            '/api/sendText',
            [
                'chatId' => $chatId,
                'text' => $message,
                'session' => $session['session_name'],
                'reply_to' => $messageId
            ],
            app_whatsapp_api_key()
        );
    } catch (Exception $e) {
        app_log('Failed to send category assignment notice: ' . $e->getMessage(), 'WARNING', [
            'session_id' => $sessionId,
            'chat_id' => $chatId,
            'category_id' => $categoryId
        ]);
    }
}
