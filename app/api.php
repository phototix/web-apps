<?php

declare(strict_types=1);

function api_json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_success(string $message = 'Success', array $data = [], int $statusCode = 200): void
{
    api_json_response([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => time(),
    ], $statusCode);
}

function api_error(string $message = 'Error', array $errors = [], int $statusCode = 400): void
{
    api_json_response([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
        'timestamp' => time(),
    ], $statusCode);
}

function api_unauthorized(string $message = 'Unauthorized'): void
{
    api_error($message, [], 401);
}

function api_forbidden(string $message = 'Forbidden'): void
{
    api_error($message, [], 403);
}

function api_not_found(string $message = 'Not Found'): void
{
    api_error($message, [], 404);
}

function api_method_not_allowed(string $message = 'Method Not Allowed'): void
{
    api_error($message, [], 405);
}

function api_validation_error(array $errors, string $message = 'Validation failed'): void
{
    api_error($message, $errors, 422);
}

function api_internal_error(string $message = 'Internal Server Error'): void
{
    api_error($message, [], 500);
}

function api_get_json_input(): array
{
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        return [];
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        api_error('Invalid JSON input', [], 400);
    }
    
    return $data ?? [];
}

function api_require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        api_method_not_allowed();
    }
}

function api_require_auth(): array
{
    $user = app_current_user();
    
    if ($user === null) {
        api_unauthorized();
    }
    
    return $user;
}

function api_get_query_param(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function api_get_post_param(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

// API Endpoint Functions

function api_auth_login(): void
{
    api_require_method('POST');
    
    $input = api_get_json_input();
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        api_validation_error([
            'email' => empty($email) ? 'Email is required' : null,
            'password' => empty($password) ? 'Password is required' : null,
        ], 'Email and password are required');
    }
    
    try {
        if (app_attempt_login($email, $password)) {
            $user = app_current_user();
            
            // Remove sensitive data
            unset($user['password_hash']);
            
            api_success('Login successful', [
                'user' => $user,
                'session_id' => session_id(),
            ]);
        }
        
        api_error('Invalid email or password', [], 401);
    } catch (Throwable $exception) {
        app_log_error($exception);
        api_internal_error('Authentication service unavailable');
    }
}

function api_auth_register(): void
{
    api_require_method('POST');
    
    $input = api_get_json_input();
    
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? $password;
    
    $errors = [];
    
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (!empty($errors)) {
        api_validation_error($errors);
    }
    
    try {
        $result = app_register_user($name, $email, $password);
        
        if ($result['success'] === true) {
            $user = app_current_user();
            
            // Remove sensitive data
            unset($user['password_hash']);
            
            api_success('Registration successful', [
                'user' => $user,
                'session_id' => session_id(),
            ], 201);
        }
        
        api_error($result['message']);
    } catch (Throwable $exception) {
        app_log_error($exception);
        api_internal_error('Registration service unavailable');
    }
}

function api_auth_logout(): void
{
    api_require_method('POST');
    
    $user = app_current_user();
    $email = $user['email'] ?? 'unknown';
    
    app_logout_user();
    
    api_success('Logout successful', [
        'email' => $email,
    ]);
}

function api_auth_profile(): void
{
    api_require_method('GET');
    
    $user = api_require_auth();
    
    // Remove sensitive data
    unset($user['password_hash']);
    
    api_success('Profile retrieved', [
        'user' => $user,
    ]);
}

function api_auth_check(): void
{
    api_require_method('GET');
    
    $user = app_current_user();
    
    if ($user === null) {
        api_success('Not authenticated', [
            'authenticated' => false,
        ]);
    }
    
    // Remove sensitive data
    unset($user['password_hash']);
    
    api_success('Authenticated', [
        'authenticated' => true,
        'user' => $user,
    ]);
}

// WhatsApp API Endpoints

function api_whatsapp_get_sessions(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $sessions = app_whatsapp_get_user_sessions($user['id']);
    
    api_success('Sessions retrieved', [
        'sessions' => $sessions,
        'can_create' => app_can_create_session($user),
        'max_sessions' => app_get_session_limit($user)
    ]);
}

function api_whatsapp_create_session(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    try {
        $result = app_whatsapp_create_session($user['id']);
        api_success('Session created', $result, 201);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_get_qr(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $sessionParam = api_get_query_param('id');
    if (!$sessionParam) {
        api_validation_error(['id' => 'Session ID or name is required']);
    }
    
    // Try to get session by ID (numeric) first, then by name (string)
    $session = null;
    if (is_numeric($sessionParam)) {
        $sessionId = (int) $sessionParam;
        if ($sessionId > 0) {
            $session = app_whatsapp_get_session($sessionId);
        }
    }
    
    // If not found by ID, try by name
    if (!$session) {
        $session = app_whatsapp_get_session_by_name($sessionParam);
    }
    
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $qrCode = app_whatsapp_get_qr($session['id']);
        if ($qrCode === null) {
            api_success('No QR code available (session may not be in scan_qr_code state)', [
                'qr_code' => null,
                'session_id' => $session['id'],
                'session_name' => $session['session_name'],
                'available' => false
            ]);
        } else {
            api_success('QR code retrieved', [
                'qr_code' => $qrCode,
                'session_id' => $session['id'],
                'session_name' => $session['session_name'],
                'available' => true
            ]);
        }
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_get_session_status(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $sessionParam = api_get_query_param('id');
    if (!$sessionParam) {
        api_validation_error(['id' => 'Session ID or name is required']);
    }
    
    // Try to get session by ID (numeric) first, then by name (string)
    $session = null;
    if (is_numeric($sessionParam)) {
        $sessionId = (int) $sessionParam;
        if ($sessionId > 0) {
            $session = app_whatsapp_get_session($sessionId);
        }
    }
    
    // If not found by ID, try by name
    if (!$session) {
        $session = app_whatsapp_get_session_by_name($sessionParam);
    }
    
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $status = app_whatsapp_get_session_status($session['id']);
        api_success('Session status retrieved', $status);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_delete_session(): void {
    api_require_method('DELETE');
    $user = api_require_auth();
    
    $sessionParam = api_get_query_param('id');
    if (!$sessionParam) {
        api_validation_error(['id' => 'Session ID or name is required']);
    }
    
    // Try to get session by ID (numeric) first, then by name (string)
    $session = null;
    if (is_numeric($sessionParam)) {
        $sessionId = (int) $sessionParam;
        if ($sessionId > 0) {
            $session = app_whatsapp_get_session($sessionId);
        }
    }
    
    // If not found by ID, try by name
    if (!$session) {
        $session = app_whatsapp_get_session_by_name($sessionParam);
    }
    
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $success = app_whatsapp_delete_session($session['id']);
        if ($success) {
            api_success('Session deleted');
        } else {
            api_error('Failed to delete session');
        }
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_sync_groups(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $sessionParam = api_get_query_param('id');
    if (!$sessionParam) {
        api_validation_error(['id' => 'Session ID or name is required']);
    }
    
    // Try to get session by ID (numeric) first, then by name (string)
    $session = null;
    if (is_numeric($sessionParam)) {
        $sessionId = (int) $sessionParam;
        if ($sessionId > 0) {
            $session = app_whatsapp_get_session($sessionId);
        }
    }
    
    // If not found by ID, try by name
    if (!$session) {
        $session = app_whatsapp_get_session_by_name($sessionParam);
    }
    
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $result = app_whatsapp_sync_groups($session['id']);
        api_success('Groups synced successfully', $result);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_get_groups(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $groups = app_whatsapp_get_user_groups($user['id']);
    
    api_success('Groups retrieved', [
        'groups' => $groups,
        'can_create' => app_can_create_group($user)
    ]);
}

function api_whatsapp_create_group(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    if (!app_can_create_group($user)) {
        api_forbidden('You do not have permission to create groups');
    }
    
    $input = api_get_json_input();
    $sessionId = (int) ($input['session_id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $participants = $input['participants'] ?? [];
    
    if (!$sessionId) {
        api_validation_error(['session_id' => 'Session ID is required']);
    }
    if (empty($name)) {
        api_validation_error(['name' => 'Group name is required']);
    }
    
    // Verify session belongs to user
    $session = app_whatsapp_get_session($sessionId);
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $result = app_whatsapp_create_group($sessionId, $name, $participants);
        api_success('Group created', $result, 201);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_get_group_messages(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $groupIdParam = api_get_query_param('id');
    $limit = min(100, (int) api_get_query_param('limit', 50));
    $before = api_get_query_param('before') ? (int) api_get_query_param('before') : null;
    
    if (!$groupIdParam) {
        api_validation_error(['id' => 'Group ID is required']);
    }
    
    // Handle both numeric group ID (backward compatibility) and string group ID with session
    $group = null;
    $sessionId = null;
    $groupId = null;
    
    if (is_numeric($groupIdParam)) {
        // Backward compatibility: numeric group ID
        $numericGroupId = (int) $groupIdParam;
        $group = app_whatsapp_get_group($numericGroupId);
        if ($group) {
            $sessionId = $group['session_id'];
            $groupId = $group['group_id']; // Get string group ID
        }
    } else {
        // New way: string group ID with session_id query parameter
        $sessionId = (int) api_get_query_param('session_id');
        $groupId = $groupIdParam;
        
        if ($sessionId) {
            $group = app_whatsapp_get_group_by_session_and_id($sessionId, $groupId);
        }
    }
    
    if (!$group || $group['user_id'] !== $user['id']) {
        api_forbidden('Group not found or access denied');
    }
    
    if (!$sessionId || !$groupId) {
        api_error('Unable to determine session or group');
    }
    
    $messages = app_whatsapp_get_group_messages($sessionId, $groupId, $limit, $before);
    
    // Reset unread count when fetching messages
    app_db_reset_group_unread_count($sessionId, $groupId);
    
    api_success('Messages retrieved', [
        'messages' => $messages,
        'group_id' => $groupId,
        'session_id' => $sessionId,
        'has_more' => count($messages) === $limit
    ]);
}

function api_whatsapp_send_message(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $input = api_get_json_input();
    $groupIdParam = $input['group_id'] ?? 0;
    $message = trim($input['message'] ?? '');
    $mediaPath = $input['media_path'] ?? null;
    $mediaType = $input['media_type'] ?? null;
    
    if (!$groupIdParam) {
        api_validation_error(['group_id' => 'Group ID is required']);
    }
    if (empty($message) && empty($mediaPath)) {
        api_validation_error(['message' => 'Message or media is required']);
    }
    
    // Handle both numeric group ID (backward compatibility) and string group ID with session
    $group = null;
    $sessionId = null;
    $groupId = null;
    
    if (is_numeric($groupIdParam)) {
        // Backward compatibility: numeric group ID
        $numericGroupId = (int) $groupIdParam;
        $group = app_whatsapp_get_group($numericGroupId);
        if ($group) {
            $sessionId = $group['session_id'];
            $groupId = $group['group_id']; // Get string group ID
        }
    } else {
        // New way: string group ID with session_id
        $sessionId = (int) ($input['session_id'] ?? 0);
        $groupId = trim($groupIdParam);
        
        if ($sessionId) {
            $group = app_whatsapp_get_group_by_session_and_id($sessionId, $groupId);
        }
    }
    
    if (!$group || $group['user_id'] !== $user['id']) {
        api_forbidden('Group not found or access denied');
    }
    
    if (!$sessionId || !$groupId) {
        api_error('Unable to determine session or group');
    }
    
    try {
        $result = app_whatsapp_send_message($sessionId, $groupId, $message, $mediaPath, $mediaType);
        api_success('Message sent', $result);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_realtime_updates(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $lastUpdateId = (int) api_get_query_param('last_id', 0);
    $timeout = min(30, (int) api_get_query_param('timeout', 10));
    
    $startTime = time();
    
    // Poll for new updates
    while ((time() - $startTime) < $timeout) {
        $updates = app_get_realtime_updates($user['id'], $lastUpdateId);
        
        if (!empty($updates)) {
            api_success('Updates available', [
                'updates' => $updates,
                'last_id' => end($updates)['id'] ?? $lastUpdateId
            ]);
        }
        
        // Sleep to reduce database queries
        usleep(500000); // 0.5 seconds
    }
    
    // Timeout with empty response
    api_success('No updates', [
        'updates' => [],
        'last_id' => $lastUpdateId
    ]);
}
function api_mark_update_read(): void
{
    api_require_method('POST');
    $user = api_require_auth();
    
    $input = api_get_json_input();
    $updateId = (int) ($input['update_id'] ?? 0);
    
    if ($updateId > 0) {
        app_mark_update_read($user['id'], $updateId);
    }
    
    api_success('Update marked as read');
}

// API Key Authentication Functions

function api_verify_api_key(): bool
{
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (empty($apiKey)) {
        return false;
    }
    
    $envApiKey = getenv('WHATSAPP_API_KEY');
    return hash_equals($envApiKey, $apiKey);
}

function api_require_api_key(): void
{
    if (!api_verify_api_key()) {
        api_unauthorized('Invalid or missing API key');
    }
}

// WhatsApp Incoming Messages API

function api_whatsapp_incoming_message(): void
{
    api_require_method('POST');
    api_require_api_key();
    
    $input = api_get_json_input();
    
    // Validate required fields
    $sessionName = trim($input['session_name'] ?? '');
    $chatId = trim($input['chat_id'] ?? '');
    $messageId = trim($input['message_id'] ?? '');
    $sender = trim($input['sender'] ?? '');
    $content = trim($input['content'] ?? '');
    $messageType = trim($input['message_type'] ?? '');
    $isFromMe = (bool) ($input['is_from_me'] ?? false);
    
    $errors = [];
    
    if (empty($sessionName)) {
        $errors['session_name'] = 'Session name is required';
    }
    
    if (empty($chatId)) {
        $errors['chat_id'] = 'Chat ID is required';
    } elseif (!str_ends_with($chatId, '@g.us')) {
        $errors['chat_id'] = 'Chat ID must be a group ID (ending with @g.us)';
    }
    
    if (empty($messageId)) {
        $errors['message_id'] = 'Message ID is required';
    }
    
    if (empty($sender)) {
        $errors['sender'] = 'Sender is required';
    }
    
    if (empty($content) && empty($input['media_url'])) {
        $errors['content'] = 'Content or media URL is required';
    }
    
    if (empty($messageType)) {
        $errors['message_type'] = 'Message type is required';
    } elseif (!in_array($messageType, ['text', 'image', 'video', 'audio', 'document', 'sticker'])) {
        $errors['message_type'] = 'Invalid message type. Must be: text, image, video, audio, document, sticker';
    }
    
    if (!empty($errors)) {
        api_validation_error($errors);
    }
    
    // Get session by name
    $session = app_whatsapp_get_session_by_name($sessionName);
    if (!$session) {
        api_error('Session not found', [], 404);
    }
    
    // Check if session is active
    if ($session['status'] !== 'active') {
        api_error('Session is not active', [], 403);
    }
    
    // Prepare message data
    $messageData = [
        'session_id' => $session['id'],
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'sender' => $sender,
        'sender_name' => trim($input['sender_name'] ?? $sender),
        'content' => $content,
        'message_type' => $messageType,
        'timestamp' => (int) ($input['timestamp'] ?? time() * 1000),
        'is_from_me' => $isFromMe,
        'quoted_message_id' => trim($input['quoted_message_id'] ?? ''),
        'media_url' => trim($input['media_url'] ?? ''),
        'media_caption' => trim($input['media_caption'] ?? ''),
        'media_type' => trim($input['media_type'] ?? ''),
        'media_size' => (int) ($input['media_size'] ?? 0),
    ];
    
    try {
        // Store the message
        $result = app_whatsapp_store_incoming_message($messageData);
        
        api_success('Message stored successfully', [
            'message_id' => $result['id'],
            'group_id' => $chatId,
            'session_name' => $sessionName,
            'session_id' => $session['id'],
            'stored_at' => date('Y-m-d H:i:s')
        ], 201);
        
    } catch (Exception $e) {
        api_error('Failed to store message: ' . $e->getMessage());
    }
}

function api_whatsapp_incoming_messages_batch(): void
{
    api_require_method('POST');
    api_require_api_key();
    
    $input = api_get_json_input();
    $messages = $input['messages'] ?? [];
    
    if (empty($messages) || !is_array($messages)) {
        api_validation_error(['messages' => 'Messages array is required']);
    }
    
    $storedCount = 0;
    $failedCount = 0;
    $failedMessages = [];
    
    foreach ($messages as $index => $message) {
        try {
            // Validate required fields for each message
            $sessionName = trim($message['session_name'] ?? '');
            $chatId = trim($message['chat_id'] ?? '');
            $messageId = trim($message['message_id'] ?? '');
            $sender = trim($message['sender'] ?? '');
            $content = trim($message['content'] ?? '');
            $messageType = trim($message['message_type'] ?? '');
            $isFromMe = (bool) ($message['is_from_me'] ?? false);
            
            if (empty($sessionName) || empty($chatId) || empty($messageId) || empty($sender) || 
                (empty($content) && empty($message['media_url'])) || empty($messageType)) {
                throw new Exception('Missing required fields');
            }
            
            if (!str_ends_with($chatId, '@g.us')) {
                throw new Exception('Chat ID must be a group ID');
            }
            
            // Get session by name
            $session = app_whatsapp_get_session_by_name($sessionName);
            if (!$session || $session['status'] !== 'active') {
                throw new Exception('Session not found or not active');
            }
            
            // Prepare message data
            $messageData = [
                'session_id' => $session['id'],
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'sender' => $sender,
                'sender_name' => trim($message['sender_name'] ?? $sender),
                'content' => $content,
                'message_type' => $messageType,
                'timestamp' => (int) ($message['timestamp'] ?? time() * 1000),
                'is_from_me' => $isFromMe,
                'quoted_message_id' => trim($message['quoted_message_id'] ?? ''),
                'media_url' => trim($message['media_url'] ?? ''),
                'media_caption' => trim($message['media_caption'] ?? ''),
                'media_type' => trim($message['media_type'] ?? ''),
                'media_size' => (int) ($message['media_size'] ?? 0),
            ];
            
            // Store the message
            app_whatsapp_store_incoming_message($messageData);
            $storedCount++;
            
        } catch (Exception $e) {
            $failedCount++;
            $failedMessages[] = [
                'index' => $index,
                'message_id' => $message['message_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }
    
    api_success("{$storedCount} messages stored successfully", [
        'stored_count' => $storedCount,
        'failed_count' => $failedCount,
        'failed_messages' => $failedMessages,
        'session_name' => !empty($messages[0]['session_name']) ? $messages[0]['session_name'] : null
    ], 201);
}

function api_whatsapp_incoming_verify(): void
{
    api_require_method('GET');
    api_require_api_key();
    
    // Get all active sessions
    $pdo = app_db();
    $stmt = $pdo->query("
        SELECT id, session_name, user_id, status, created_at 
        FROM whatsapp_sessions 
        WHERE status = 'active'
        ORDER BY created_at DESC
    ");
    $sessions = $stmt->fetchAll() ?: [];
    
    // Get WhatsApp endpoint from environment
    $whatsappEndpoint = getenv('WHATSAPP_API_ENDPOINT') ?: 'http://localhost:3000';
    
    api_success('API key is valid', [
        'valid' => true,
        'api_key' => '***' . substr($_SERVER['HTTP_X_API_KEY'] ?? '', -4),
        'whatsapp_endpoint' => $whatsappEndpoint,
        'available_sessions' => array_map(function($session) {
            return [
                'id' => (int) $session['id'],
                'session_name' => $session['session_name'],
                'user_id' => (int) $session['user_id'],
                'status' => $session['status'],
                'created_at' => $session['created_at']
            ];
        }, $sessions)
    ]);
}