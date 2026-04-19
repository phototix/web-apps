<?php

declare(strict_types=1);

// WhatsApp Groups Management

function app_whatsapp_sync_groups(int $sessionId): array {
    $session = app_whatsapp_get_session($sessionId);
    if (!$session) {
        throw new Exception('Session not found');
    }
    
    try {
        $groups = app_whatsapp_api_get(
            "/api/{$session['session_name']}/groups",
            app_whatsapp_api_key()
        );
        
        $synced = 0;
        foreach ($groups as $group) {
            // Extract group ID from WAHA response (could be object or string)
            $groupIdValue = $group['id'];
            if (is_array($groupIdValue) && isset($groupIdValue['_serialized'])) {
                $groupIdValue = $groupIdValue['_serialized'];
            } elseif (is_array($groupIdValue) && isset($groupIdValue['user'])) {
                $groupIdValue = $groupIdValue['user'] . '@' . $groupIdValue['server'];
            }
            
            // Extract participant count
            $participantCount = 0;
            if (isset($group['groupMetadata']['size'])) {
                $participantCount = $group['groupMetadata']['size'];
            } elseif (isset($group['groupMetadata']['participants'])) {
                $participantCount = count($group['groupMetadata']['participants']);
            }
            
            $groupId = app_db_upsert_whatsapp_group([
                'session_id' => $sessionId,
                'group_id' => $groupIdValue,
                'name' => $group['name'],
                'description' => $group['description'] ?? null,
                'participant_count' => $participantCount
            ]);
            
            if ($groupId) {
                $synced++;
            }
        }
        
        return [
            'success' => true,
            'synced' => $synced,
            'total' => count($groups)
        ];
        
    } catch (Exception $e) {
        app_log('Failed to sync groups: ' . $e->getMessage(), 'ERROR', [
            'session_id' => $sessionId
        ]);
        throw new Exception('Failed to sync groups: ' . $e->getMessage());
    }
}

function app_whatsapp_get_user_groups(int $userId): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT wg.*, 
               ws.session_name as whatsapp_session_name,
               c.name as category_name,
               c.color as category_color
        FROM whatsapp_groups wg
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        LEFT JOIN categories c ON wg.category_id = c.id
        WHERE ws.user_id = :user_id
        ORDER BY 
            CASE WHEN wg.last_message_timestamp IS NULL THEN 1 ELSE 0 END,
            wg.last_message_timestamp DESC,
            wg.updated_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function app_whatsapp_get_group(int $groupId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT wg.*, 
               ws.session_name as whatsapp_session_name, 
               ws.user_id,
               c.name as category_name,
               c.color as category_color
        FROM whatsapp_groups wg
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        LEFT JOIN categories c ON wg.category_id = c.id
        WHERE wg.id = :id
    ");
    $stmt->execute(['id' => $groupId]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_get_group_by_session_and_id(int $sessionId, string $groupId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT wg.*, ws.session_name as whatsapp_session_name, ws.user_id 
        FROM whatsapp_groups wg
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        WHERE wg.session_id = :session_id AND wg.group_id = :group_id
    ");
    $stmt->execute(['session_id' => $sessionId, 'group_id' => $groupId]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_get_group_messages(int $sessionId, string $groupId, int $limit = 50, ?int $beforeTimestamp = null, ?int $categoryId = null): array {
    try {
        $pdo = app_db();
        
        $query = "
            SELECT * FROM group_messages 
            WHERE session_id = :session_id AND group_id = :group_id
        ";
        
        $params = ['session_id' => $sessionId, 'group_id' => $groupId];
        
        if ($beforeTimestamp) {
            $query .= " AND timestamp < :before_timestamp";
            $params['before_timestamp'] = $beforeTimestamp;
        }
        
        if ($categoryId !== null) {
            $query .= " AND category_id = :category_id";
            $params['category_id'] = $categoryId;
        }
        
        $query .= " ORDER BY timestamp DESC LIMIT :limit";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue('session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue('group_id', $groupId, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        if ($beforeTimestamp) {
            $stmt->bindValue('before_timestamp', $beforeTimestamp, PDO::PARAM_INT);
        }
        if ($categoryId !== null) {
            $stmt->bindValue('category_id', $categoryId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching group messages: ' . $e->getMessage() . ' for session ' . $sessionId . ', group ' . $groupId);
        return [];
    }
}

function app_whatsapp_create_group(int $sessionId, string $name, array $participants = []): array {
    $session = app_whatsapp_get_session($sessionId);
    if (!$session) {
        throw new Exception('Session not found');
    }
    
    try {
        $response = app_whatsapp_api_post(
            "/api/{$session['session_name']}/groups",
            [
                'subject' => $name,
                'participants' => $participants
            ],
            app_whatsapp_api_key()
        );
        
        // Store in database
        $groupId = app_db_upsert_whatsapp_group([
            'session_id' => $sessionId,
            'group_id' => $response['id'],
            'name' => $response['name'] ?? $name,
            'description' => $response['description'] ?? null,
            'participant_count' => count($participants) + 1 // +1 for creator
        ]);
        
        return [
            'success' => true,
            'group_id' => $groupId,
            'whatsapp_group_id' => $response['id'],
            'name' => $response['name'] ?? $name
        ];
        
    } catch (Exception $e) {
        app_log('Failed to create group: ' . $e->getMessage(), 'ERROR', [
            'session_id' => $sessionId,
            'name' => $name
        ]);
        throw new Exception('Failed to create group: ' . $e->getMessage());
    }
}

function app_whatsapp_update_group_info(int $groupId, array $updates): bool {
    $group = app_whatsapp_get_group($groupId);
    if (!$group) {
        throw new Exception('Group not found');
    }
    
    $session = app_whatsapp_get_session($group['session_id']);
    
    try {
        if (isset($updates['subject'])) {
            app_whatsapp_api_put(
                "/api/{$session['session_name']}/groups/{$group['group_id']}/subject",
                ['subject' => $updates['subject']],
                app_whatsapp_api_key()
            );
        }
        
        if (isset($updates['description'])) {
            app_whatsapp_api_put(
                "/api/{$session['session_name']}/groups/{$group['group_id']}/description",
                ['description' => $updates['description']],
                app_whatsapp_api_key()
            );
        }
        
        // Update local database
        $pdo = app_db();
        $setClauses = [];
        $params = ['id' => $groupId];
        
        if (isset($updates['subject'])) {
            $setClauses[] = 'name = :name';
            $params['name'] = $updates['subject'];
        }
        
        if (isset($updates['description'])) {
            $setClauses[] = 'description = :description';
            $params['description'] = $updates['description'];
        }
        
        if (!empty($setClauses)) {
            $query = "UPDATE whatsapp_groups SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }
        
        return true;
        
    } catch (Exception $e) {
        app_log('Failed to update group info: ' . $e->getMessage(), 'ERROR', [
            'group_id' => $groupId
        ]);
        throw new Exception('Failed to update group info: ' . $e->getMessage());
    }
}

function app_db_upsert_whatsapp_group(array $data): int {
    $pdo = app_db();
    
    // Check if group exists
    $stmt = $pdo->prepare("
        SELECT id FROM whatsapp_groups 
        WHERE session_id = :session_id AND group_id = :group_id
    ");
    $stmt->execute([
        'session_id' => $data['session_id'],
        'group_id' => $data['group_id']
    ]);
    
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing group
        $stmt = $pdo->prepare("
            UPDATE whatsapp_groups 
            SET name = :name, description = :description, 
                participant_count = :participant_count, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $existing['id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'participant_count' => $data['participant_count']
        ]);
        return (int) $existing['id'];
    } else {
        // Insert new group
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_groups 
            (session_id, group_id, name, description, participant_count, created_at)
            VALUES (:session_id, :group_id, :name, :description, :participant_count, NOW())
        ");
        $stmt->execute([
            'session_id' => $data['session_id'],
            'group_id' => $data['group_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'participant_count' => $data['participant_count']
        ]);
        return (int) $pdo->lastInsertId();
    }
}

function app_whatsapp_set_group_status_by_id(int $groupId, string $status): bool {
    $pdo = app_db();
    $isArchived = $status === 'archived' ? 1 : 0;
    $stmt = $pdo->prepare('
        UPDATE whatsapp_groups
        SET status = :status, is_archived = :is_archived, updated_at = NOW()
        WHERE id = :id
    ');
    return $stmt->execute([
        'status' => $status,
        'is_archived' => $isArchived,
        'id' => $groupId
    ]);
}

function app_whatsapp_set_group_status(int $sessionId, string $groupId, string $status): bool {
    $pdo = app_db();
    $isArchived = $status === 'archived' ? 1 : 0;
    $stmt = $pdo->prepare('
        UPDATE whatsapp_groups
        SET status = :status, is_archived = :is_archived, updated_at = NOW()
        WHERE session_id = :session_id AND group_id = :group_id
    ');
    return $stmt->execute([
        'status' => $status,
        'is_archived' => $isArchived,
        'session_id' => $sessionId,
        'group_id' => $groupId
    ]);
}

function app_db_upsert_group_summary(array $data): bool {
    $pdo = app_db();

    $stmt = $pdo->prepare('
        INSERT INTO whatsapp_group_summaries
            (user_id, session_id, session_name, group_id, group_name, frequency, summary_schedule, prompt, created_at, updated_at)
        VALUES
            (:user_id, :session_id, :session_name, :group_id, :group_name, :frequency, :summary_schedule, :prompt, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            session_name = VALUES(session_name),
            group_name = VALUES(group_name),
            frequency = VALUES(frequency),
            summary_schedule = VALUES(summary_schedule),
            prompt = VALUES(prompt),
            updated_at = NOW()
    ');

    return $stmt->execute([
        'user_id' => $data['user_id'],
        'session_id' => $data['session_id'],
        'session_name' => $data['session_name'],
        'group_id' => $data['group_id'],
        'group_name' => $data['group_name'],
        'frequency' => $data['frequency'],
        'summary_schedule' => $data['summary_schedule'],
        'prompt' => $data['prompt']
    ]);
}
