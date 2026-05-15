<?php

declare(strict_types=1);

// WhatsApp Sessions Management

function app_generate_session_name(int $userId, int $sessionNumber): string {
    // Generate short UUID (8 characters)
    $shortUuid = bin2hex(random_bytes(4));
    
    // Format: userid_sessionnumber_shortuuid
    return "user{$userId}_session{$sessionNumber}_{$shortUuid}";
}

function app_whatsapp_create_session(int $userId): array {
    $user = app_find_user_by_id(app_db(), $userId);
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Check tier limits
    $currentSessions = app_whatsapp_count_user_sessions($userId);
    $maxSessions = app_get_session_limit($user);
    
    if ($currentSessions >= $maxSessions) {
        throw new Exception("Session limit reached for your tier (max: {$maxSessions})");
    }
    
    // Generate session name: userid_sessionnumber_shortuuid
    $sessionNumber = $currentSessions + 1;
    $sessionName = app_generate_session_name($userId, $sessionNumber);
    
    // Generate webhook URL with HMAC secret
    $webhookSecret = bin2hex(random_bytes(32));
    $webhookUrl = "https://n8n.ezy.chat/webhook/e8250965-f606-4d8e-9f55-47198bd88cf3/waha";
    
    try {
        // Call WAHA API to create and start session
        $response = app_whatsapp_api_post('/api/sessions', [
            'name' => $sessionName,
            'start' => true,
            'config' => [
                'webhooks' => [[
                    'url' => $webhookUrl,
                    'events' => ['message', 'poll.vote'],
                    'hmac' => ['key' => $webhookSecret]
                ]]
            ]
        ], null, $userId);
        
        // Map WAHA status to database status
        $wahaStatus = strtolower($response['status'] ?? 'stopped');
        $dbStatus = 'pending'; // default
        
        if ($wahaStatus === 'scan_qr_code') {
            $dbStatus = 'authenticating';
        } elseif ($wahaStatus === 'working') {
            $dbStatus = 'active';
        } elseif (in_array($wahaStatus, ['stopped', 'starting', 'failed'])) {
            $dbStatus = 'inactive';
        }
        
        // Store in database
        $sessionId = app_db_insert_whatsapp_session([
            'user_id' => $userId,
            'session_name' => $sessionName,
            'api_key' => $response['apiKey'] ?? '',
            'endpoint_url' => app_whatsapp_api_endpoint($userId),
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $webhookSecret,
            'status' => $dbStatus
        ]);
        
        return [
            'success' => true,
            'session_id' => $sessionId,
            'session_name' => $sessionName,
            'qr_url' => "/api/whatsapp/{$sessionId}/qr"
        ];
        
    } catch (Exception $e) {
        app_log('Failed to create WhatsApp session: ' . $e->getMessage(), 'ERROR', [
            'user_id' => $userId,
            'session_name' => $sessionName
        ]);
        throw new Exception('Failed to create WhatsApp session: ' . $e->getMessage());
    }
}

function app_whatsapp_get_qr(int $sessionId): ?string {
    $session = app_whatsapp_get_session($sessionId);
    if (!$session) {
        throw new Exception('Session not found');
    }
    
    try {
        // First check if session is in scan_qr_code state
        $statusInfo = app_whatsapp_get_session_status($sessionId);
        
        if ($statusInfo['waha_status'] !== 'scan_qr_code') {
            // Session is not ready for QR code (might be working, stopped, etc.)
            app_log('Session not in scan_qr_code state', 'INFO', [
                'session_id' => $sessionId,
                'current_status' => $statusInfo['waha_status']
            ]);
            return null;
        }
        
        // Always fetch fresh QR code from WAHA (QR codes expire quickly)
        $qrImage = app_whatsapp_api_get_binary(
            "/api/{$session['session_name']}/auth/qr?format=image",
            $session['api_key'] ?: null,
            (int) ($session['user_id'] ?? 0),
            $session['endpoint_url'] ?? null
        );
        
        // Check if we got a valid image
        if (empty($qrImage) || strlen($qrImage) < 100) {
            app_log('Invalid QR image received from WAHA', 'WARNING', [
                'session_id' => $sessionId,
                'image_size' => strlen($qrImage ?? '')
            ]);
            return null;
        }
        
        $qrCode = base64_encode($qrImage);
        
        if ($qrCode) {
            // Update last QR update timestamp (but don't store the QR code itself)
            app_db_update_qr_timestamp($sessionId);
            
            // Create real-time update
            app_create_realtime_update($session['user_id'], 'qr_update', (string) $sessionId, [
                'qr_code' => 'data:image/png;base64,' . $qrCode,
                'session_id' => $sessionId
            ]);
        }
        
        return $qrCode ? 'data:image/png;base64,' . $qrCode : null;
        
    } catch (Exception $e) {
        app_log('Failed to get QR code: ' . $e->getMessage(), 'ERROR', [
            'session_id' => $sessionId
        ]);
        return null;
    }
}

function app_whatsapp_get_session(int $sessionId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM whatsapp_sessions 
        WHERE id = :id
    ");
    $stmt->execute(['id' => $sessionId]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_get_session_by_name(string $sessionName): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM whatsapp_sessions 
        WHERE session_name = :session_name
    ");
    $stmt->execute(['session_name' => $sessionName]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_get_user_sessions(int $userId): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT * FROM whatsapp_sessions 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $sessions = $stmt->fetchAll();
    
    // Sync status for each session and get account info
    foreach ($sessions as &$session) {
        try {
            $statusInfo = app_whatsapp_get_session_status($session['id']);
            $session['status'] = $statusInfo['status'];
            $wahaStatus = $statusInfo['waha_status'] ?? '';
            $session['can_view_screenshot'] = ($wahaStatus === 'working');
            $session['can_show_screenshot'] = ($wahaStatus === 'scan_qr_code');
            
            // Store session data including account info if available
            if (isset($statusInfo['session_data'])) {
                $session['session_data'] = $statusInfo['session_data'];
                
                // Extract account info from me object if available
                if (isset($statusInfo['session_data']['me'])) {
                    $session['account_info'] = $statusInfo['session_data']['me'];
                }
            }
        } catch (Exception $e) {
            // Log error but continue
            app_log('Failed to sync session status: ' . $e->getMessage(), 'WARNING', [
                'session_id' => $session['id'],
                'user_id' => $userId
            ]);
        }
    }
    
    return $sessions;
}

function app_whatsapp_count_user_sessions(int $userId): int {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM whatsapp_sessions 
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return (int) ($result['count'] ?? 0);
}

function app_whatsapp_delete_session(int $sessionId): bool {
    $session = app_whatsapp_get_session($sessionId);
    if (!$session) {
        return false;
    }
    
    try {
        // Delete from WAHA API
        app_whatsapp_api_delete(
            "/api/sessions/{$session['session_name']}",
            $session['api_key'] ?: null,
            (int) ($session['user_id'] ?? 0),
            $session['endpoint_url'] ?? null
        );
    } catch (Exception $e) {
        app_log('Failed to delete session from WAHA: ' . $e->getMessage(), 'WARNING', [
            'session_id' => $sessionId
        ]);
    }
    
    // Delete from database (cascade will delete groups and messages)
    $pdo = app_db();
    $stmt = $pdo->prepare("DELETE FROM whatsapp_sessions WHERE id = :id");
    return $stmt->execute(['id' => $sessionId]);
}

function app_whatsapp_get_session_status(int $sessionId): array {
    $session = app_whatsapp_get_session($sessionId);
    if (!$session) {
        throw new Exception('Session not found');
    }
    
    try {
        // Get session status from WAHA
        $wahaSession = app_whatsapp_api_get(
            "/api/sessions/{$session['session_name']}",
            $session['api_key'] ?: null,
            (int) ($session['user_id'] ?? 0),
            $session['endpoint_url'] ?? null
        );
        
        // Map WAHA status to database status
        $wahaStatus = strtolower($wahaSession['status'] ?? 'stopped');
        $dbStatus = 'pending'; // default
        
        if ($wahaStatus === 'scan_qr_code') {
            $dbStatus = 'authenticating';
        } elseif ($wahaStatus === 'working') {
            $dbStatus = 'active';
        } elseif (in_array($wahaStatus, ['stopped', 'starting', 'failed'])) {
            $dbStatus = 'inactive';
        }
        
        // Update session status in database if changed
        if ($dbStatus !== $session['status']) {
            $pdo = app_db();
            $stmt = $pdo->prepare("
                UPDATE whatsapp_sessions 
                SET status = :status, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['status' => $dbStatus, 'id' => $sessionId]);
            
            // Create real-time update
            app_create_realtime_update($session['user_id'], 'session_status', (string) $sessionId, [
                'session_id' => $sessionId,
                'status' => $dbStatus,
                'waha_status' => $wahaStatus
            ]);
        }
        
        return [
            'session_id' => $sessionId,
            'status' => $dbStatus,
            'waha_status' => $wahaStatus,
            'session_data' => $wahaSession
        ];
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $shouldRetry = $e->getCode() === 404
            || stripos($errorMessage, 'not found') !== false
            || stripos($errorMessage, 'does not exist') !== false;

        if ($shouldRetry) {
            $defaultEndpoint = trim((string) app_env('WHATSAPP_API_ENDPOINT', 'http://localhost:3000'));
            $defaultApiKey = trim((string) app_env('WHATSAPP_API_KEY', ''));
            $currentEndpoint = trim((string) ($session['endpoint_url'] ?? ''));
            $currentApiKey = trim((string) ($session['api_key'] ?? ''));

            if ($defaultEndpoint !== '' && ($currentEndpoint !== $defaultEndpoint || $currentApiKey !== $defaultApiKey)) {
                try {
                    $fallbackSession = app_whatsapp_api_get(
                        "/api/sessions/{$session['session_name']}",
                        $defaultApiKey !== '' ? $defaultApiKey : null,
                        (int) ($session['user_id'] ?? 0),
                        $defaultEndpoint
                    );

                    $pdo = app_db();
                    $stmt = $pdo->prepare("
                        UPDATE whatsapp_sessions
                        SET endpoint_url = :endpoint_url,
                            api_key = :api_key,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'endpoint_url' => $defaultEndpoint,
                        'api_key' => $defaultApiKey,
                        'id' => $sessionId
                    ]);

                    $wahaStatus = strtolower($fallbackSession['status'] ?? 'stopped');
                    $dbStatus = 'pending';

                    if ($wahaStatus === 'scan_qr_code') {
                        $dbStatus = 'authenticating';
                    } elseif ($wahaStatus === 'working') {
                        $dbStatus = 'active';
                    } elseif (in_array($wahaStatus, ['stopped', 'starting', 'failed'])) {
                        $dbStatus = 'inactive';
                    }

                    if ($dbStatus !== $session['status']) {
                        $stmt = $pdo->prepare("
                            UPDATE whatsapp_sessions
                            SET status = :status, updated_at = NOW()
                            WHERE id = :id
                        ");
                        $stmt->execute(['status' => $dbStatus, 'id' => $sessionId]);

                        app_create_realtime_update($session['user_id'], 'session_status', (string) $sessionId, [
                            'session_id' => $sessionId,
                            'status' => $dbStatus,
                            'waha_status' => $wahaStatus
                        ]);
                    }

                    return [
                        'session_id' => $sessionId,
                        'status' => $dbStatus,
                        'waha_status' => $wahaStatus,
                        'session_data' => $fallbackSession
                    ];
                } catch (Exception $fallbackException) {
                    $errorMessage = $fallbackException->getMessage();
                }
            }
        }

        app_log('Failed to get session status: ' . $errorMessage, 'ERROR', [
            'session_id' => $sessionId
        ]);

        $status = 'error';
        if ($e->getCode() === 404 || stripos($errorMessage, 'not found') !== false) {
            $status = 'invalid';
        }

        if ($status !== $session['status']) {
            $pdo = app_db();
            $stmt = $pdo->prepare("
                UPDATE whatsapp_sessions
                SET status = :status, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['status' => $status, 'id' => $sessionId]);
        }

        return ['status' => $status, 'message' => $errorMessage];
    }
}

function app_db_insert_whatsapp_session(array $data): int {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_sessions 
        (user_id, session_name, api_key, endpoint_url, webhook_url, webhook_secret, status, created_at)
        VALUES (:user_id, :session_name, :api_key, :endpoint_url, :webhook_url, :webhook_secret, :status, NOW())
    ");
    
    $stmt->execute([
        'user_id' => $data['user_id'],
        'session_name' => $data['session_name'],
        'api_key' => $data['api_key'],
        'endpoint_url' => $data['endpoint_url'],
        'webhook_url' => $data['webhook_url'],
        'webhook_secret' => $data['webhook_secret'],
        'status' => $data['status']
    ]);
    
    return (int) $pdo->lastInsertId();
}

function app_db_update_qr_code(int $sessionId, ?string $qrCode): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_sessions 
        SET qr_code = :qr_code, last_qr_update = NOW(), updated_at = NOW()
        WHERE id = :id
    ");
    return $stmt->execute(['qr_code' => $qrCode, 'id' => $sessionId]);
}

function app_db_update_qr_timestamp(int $sessionId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE whatsapp_sessions 
        SET last_qr_update = NOW(), updated_at = NOW()
        WHERE id = :id
    ");
    return $stmt->execute(['id' => $sessionId]);
}
