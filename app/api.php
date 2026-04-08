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
    
    $sessionName = api_get_query_param('id');
    if (!$sessionName) {
        api_validation_error(['id' => 'Session name is required']);
    }
    
    // Verify session belongs to user
    $session = app_whatsapp_get_session_by_name($sessionName);
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $qrCode = app_whatsapp_get_qr($session['id']);
        if ($qrCode === null) {
            api_success('No QR code available (session may not be in scan_qr_code state)', [
                'qr_code' => null,
                'session_name' => $sessionName,
                'available' => false
            ]);
        } else {
            api_success('QR code retrieved', [
                'qr_code' => $qrCode,
                'session_name' => $sessionName,
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
    
    $sessionName = api_get_query_param('id');
    if (!$sessionName) {
        api_validation_error(['id' => 'Session name is required']);
    }
    
    // Verify session belongs to user
    $session = app_whatsapp_get_session_by_name($sessionName);
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
    
    $sessionName = api_get_query_param('id');
    if (!$sessionName) {
        api_validation_error(['id' => 'Session name is required']);
    }
    
    // Verify session belongs to user
    $session = app_whatsapp_get_session_by_name($sessionName);
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
    
    $sessionName = api_get_query_param('id');
    if (!$sessionName) {
        api_validation_error(['id' => 'Session name is required']);
    }
    
    // Verify session belongs to user
    $session = app_whatsapp_get_session_by_name($sessionName);
    if (!$session || $session['user_id'] !== $user['id']) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $result = app_whatsapp_sync_groups($session['id']);
        api_success('Groups synced', $result);
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
    
    $groupId = (int) api_get_query_param('id');
    $limit = min(100, (int) api_get_query_param('limit', 50));
    $before = api_get_query_param('before') ? (int) api_get_query_param('before') : null;
    
    if (!$groupId) {
        api_validation_error(['id' => 'Group ID is required']);
    }
    
    // Verify group belongs to user
    $group = app_whatsapp_get_group($groupId);
    if (!$group || $group['user_id'] !== $user['id']) {
        api_forbidden('Group not found or access denied');
    }
    
    $messages = app_whatsapp_get_group_messages($groupId, $limit, $before);
    
    // Reset unread count when fetching messages
    app_db_reset_group_unread_count($groupId);
    
    api_success('Messages retrieved', [
        'messages' => $messages,
        'group_id' => $groupId,
        'has_more' => count($messages) === $limit
    ]);
}

function api_whatsapp_send_message(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $input = api_get_json_input();
    $groupId = (int) ($input['group_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    $mediaPath = $input['media_path'] ?? null;
    $mediaType = $input['media_type'] ?? null;
    
    if (!$groupId) {
        api_validation_error(['group_id' => 'Group ID is required']);
    }
    if (empty($message) && empty($mediaPath)) {
        api_validation_error(['message' => 'Message or media is required']);
    }
    
    // Verify group belongs to user
    $group = app_whatsapp_get_group($groupId);
    if (!$group || $group['user_id'] !== $user['id']) {
        api_forbidden('Group not found or access denied');
    }
    
    try {
        $result = app_whatsapp_send_message($groupId, $message, $mediaPath, $mediaType);
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

function api_mark_update_read(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $input = api_get_json_input();
    $updateId = (int) ($input['update_id'] ?? 0);
    
    if ($updateId > 0) {
        app_mark_update_read($user['id'], $updateId);
    }
    
    api_success('Update marked as read');
}