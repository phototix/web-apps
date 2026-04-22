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

function api_handle_exception(Throwable $e): void
{
    error_log('API Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    api_error('Internal server error: ' . $e->getMessage(), [], 500);
}

function api_execute_safely(callable $handler): void
{
    // Start output buffering to catch any stray output
    ob_start();
    
    try {
        $handler();
    } catch (Throwable $e) {
        // Clean any output that might have been generated
        ob_end_clean();
        api_handle_exception($e);
        return;
    }
    
    // Get any buffered output
    $output = ob_get_clean();
    
    // If there was any output, log it and return error
    if ($output && trim($output) !== '') {
        error_log('Stray output in API response: ' . substr($output, 0, 500));
        api_error('Internal server error: Unexpected output', [], 500);
        return;
    }
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

function api_get_effective_user_id(array $user): int
{
    $effectiveUser = app_get_effective_user($user);

    return (int) ($effectiveUser['id'] ?? 0);
}

function api_get_query_param(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function api_get_post_param(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function api_delete_directory(string $directory): bool
{
    if (!is_dir($directory)) {
        return true;
    }

    $items = scandir($directory);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            if (!api_delete_directory($path)) {
                return false;
            }
        } else {
            if (!unlink($path)) {
                return false;
            }
        }
    }

    return rmdir($directory);
}

// API Endpoint Functions

function api_auth_login(): void
{
    api_require_method('POST');
    
    $input = api_get_json_input();
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $otp = isset($input['otp']) ? (string) $input['otp'] : null;
    
    if (empty($email) || empty($password)) {
        api_validation_error([
            'email' => empty($email) ? 'Email is required' : null,
            'password' => empty($password) ? 'Password is required' : null,
        ], 'Email and password are required');
    }
    
    try {
        if (app_attempt_login($email, $password, $otp)) {
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
        $inviteCode = $input['invite_code'] ?? null;
        $result = app_register_user($name, $email, $password, 'admin', $inviteCode);
        
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
    if (($user['role'] ?? '') === 'users') {
        api_error('Access denied.', [], 403);
    }
    
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    $sessions = app_whatsapp_get_user_sessions($effectiveUserId);
    
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
        $effectiveUserId = api_get_effective_user_id($user);
        $result = app_whatsapp_create_session($effectiveUserId);
        app_log_audit('create_session', [
            'session_id' => $result['session_id'] ?? null,
            'session_name' => $result['session_name'] ?? null,
        ], $user);
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$session || $session['user_id'] !== $effectiveUserId) {
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$session || $session['user_id'] !== $effectiveUserId) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $status = app_whatsapp_get_session_status($session['id']);
        api_success('Session status retrieved', $status);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_get_screenshot(): void {
    api_require_method('GET');
    $user = api_require_auth();

    $sessionParam = api_get_query_param('id');
    if (!$sessionParam) {
        api_validation_error(['id' => 'Session ID or name is required']);
    }

    $session = null;
    if (is_numeric($sessionParam)) {
        $sessionId = (int) $sessionParam;
        if ($sessionId > 0) {
            $session = app_whatsapp_get_session($sessionId);
        }
    }

    if (!$session) {
        $session = app_whatsapp_get_session_by_name($sessionParam);
    }

    $effectiveUserId = api_get_effective_user_id($user);
    if (!$session || $session['user_id'] !== $effectiveUserId) {
        api_forbidden('Session not found or access denied');
    }

    try {
        $endpoint = '/api/screenshot?session=' . urlencode((string) $session['session_name']);
        $screenshot = app_whatsapp_api_get_binary_with_headers(
            $endpoint,
            ['Accept: image/jpeg'],
            app_whatsapp_api_key()
        );

        app_clear_output_buffers();
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($screenshot));
        echo $screenshot;
        exit;
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$session || $session['user_id'] !== $effectiveUserId) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $success = app_whatsapp_delete_session($session['id']);
        if ($success) {
            app_log_audit('delete_session', [
                'session_id' => $session['id'] ?? null,
                'session_name' => $session['session_name'] ?? null,
            ], $user);
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$session || $session['user_id'] !== $effectiveUserId) {
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    $groups = app_whatsapp_get_user_groups($effectiveUserId);
    
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
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$session || $session['user_id'] !== $effectiveUserId) {
        api_forbidden('Session not found or access denied');
    }
    
    try {
        $result = app_whatsapp_create_group($sessionId, $name, $participants);
        api_success('Group created', $result, 201);
    } catch (Exception $e) {
        api_error($e->getMessage());
    }
}

function api_whatsapp_set_group_status(): void {
    api_require_method('POST');
    $user = api_require_auth();

    $groupIdParam = api_get_query_param('id');
    if (!$groupIdParam) {
        api_validation_error(['id' => 'Group ID is required']);
    }

    $input = api_get_json_input();
    $status = $input['status'] ?? null;
    if (!$status && isset($input['action'])) {
        $status = $input['action'] === 'unarchive' ? 'active' : 'archived';
    }
    if (!$status) {
        $status = 'archived';
    }

    if (!in_array($status, ['active', 'archived'], true)) {
        api_validation_error(['status' => 'Invalid status']);
    }

    $group = null;
    $sessionId = null;
    $groupId = null;

    if (is_numeric($groupIdParam)) {
        $numericGroupId = (int) $groupIdParam;
        $group = app_whatsapp_get_group($numericGroupId);
        if ($group) {
            $sessionId = (int) $group['session_id'];
            $groupId = $group['group_id'];
        }
    } else {
        $sessionId = (int) ($input['session_id'] ?? api_get_query_param('session_id'));
        $groupId = $groupIdParam;
        if ($sessionId) {
            $group = app_whatsapp_get_group_by_session_and_id($sessionId, $groupId);
        }
    }

    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || $group['user_id'] !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }

    try {
        $success = false;
        if (isset($group['id']) && is_numeric($groupIdParam)) {
            $success = app_whatsapp_set_group_status_by_id((int) $group['id'], $status);
        } else {
            $success = app_whatsapp_set_group_status((int) $sessionId, (string) $groupId, $status);
        }

        if ($success) {
            $auditAction = $status === 'archived' ? 'archive_group' : 'unarchive_group';
            app_log_audit($auditAction, [
                'group_id' => $groupId,
                'session_id' => $sessionId,
                'status' => $status,
            ], $user);
            api_success('Group status updated', [
                'status' => $status,
                'group_id' => $groupId,
                'session_id' => $sessionId
            ]);
        } else {
            api_error('Failed to update group status');
        }
    } catch (Exception $e) {
        api_error('Failed to update group status: ' . $e->getMessage());
    }
}

function api_whatsapp_set_group_schedule_summary(): void {
    api_require_method('POST');
    $user = api_require_auth();

    $groupIdParam = api_get_query_param('id');
    if (!$groupIdParam) {
        api_validation_error(['id' => 'Group ID is required']);
    }

    $input = api_get_json_input();
    $sessionId = (int) ($input['session_id'] ?? 0);
    $frequency = strtolower(trim((string) ($input['frequency'] ?? '')));
    $summaryTime = trim((string) ($input['summary_time'] ?? $input['summary_schedule'] ?? ''));
    $summaryWeekday = strtolower(trim((string) ($input['summary_weekday'] ?? '')));
    $summaryMonthDay = trim((string) ($input['summary_month_day'] ?? ''));
    $prompt = trim((string) ($input['prompt'] ?? ''));
    $includeNonAssignedRaw = $input['include_non_assigned'] ?? 0;

    $errors = [];
    if ($sessionId <= 0) {
        $errors['session_id'] = 'Session ID is required';
    }
    if (!in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
        $errors['frequency'] = 'Frequency must be daily, weekly, or monthly';
    }
    if ($summaryTime === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $summaryTime)) {
        $errors['summary_time'] = 'Summary time must be in HH:MM format';
    }
    if ($frequency === 'weekly') {
        $validWeekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        if (!in_array($summaryWeekday, $validWeekdays, true)) {
            $errors['summary_weekday'] = 'Summary day must be selected';
        }
    }
    if ($frequency === 'monthly') {
        $validMonthDays = ['1', '15', 'end'];
        if (!in_array($summaryMonthDay, $validMonthDays, true)) {
            $errors['summary_month_day'] = 'Summary day must be 1st, 15th, or end of month';
        }
    }
    if ($prompt === '') {
        $errors['prompt'] = 'Summary prompt is required';
    }
    $includeNonAssigned = filter_var($includeNonAssignedRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($includeNonAssigned === null) {
        $errors['include_non_assigned'] = 'Include Non-assigned must be yes or no';
    }
    if (!empty($errors)) {
        api_validation_error($errors);
    }

    $group = app_whatsapp_get_group_by_session_and_id($sessionId, (string) $groupIdParam);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || $group['user_id'] !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }

    $sessionName = (string) ($group['whatsapp_session_name'] ?? $group['session_name'] ?? '');
    $groupName = (string) ($group['name'] ?? '');

    $scheduleData = [
        'time' => $summaryTime,
    ];
    if ($frequency === 'weekly') {
        $scheduleData['weekday'] = $summaryWeekday;
    }
    if ($frequency === 'monthly') {
        $scheduleData['month_day'] = $summaryMonthDay;
    }

    $summarySchedule = json_encode($scheduleData, JSON_UNESCAPED_SLASHES);

    try {
        $success = app_db_upsert_group_summary([
            'user_id' => $effectiveUserId,
            'session_id' => $sessionId,
            'session_name' => $sessionName,
            'group_id' => (string) $groupIdParam,
            'group_name' => $groupName,
            'frequency' => $frequency,
            'summary_schedule' => $summarySchedule,
            'prompt' => $prompt,
            'include_non_assigned' => $includeNonAssigned ? 1 : 0
        ]);

        if ($success) {
            api_success('Schedule summary saved', [
                'group_id' => (string) $groupIdParam,
                'session_id' => $sessionId,
                'frequency' => $frequency,
                'summary_schedule' => $summarySchedule
            ]);
        }

        api_error('Failed to save schedule summary');
    } catch (Exception $e) {
        api_error('Failed to save schedule summary: ' . $e->getMessage());
    }
}

function api_whatsapp_delete_group_schedule_summary(): void {
    api_require_method('POST');
    $user = api_require_auth();

    $groupIdParam = api_get_query_param('id');
    if (!$groupIdParam) {
        api_validation_error(['id' => 'Group ID is required']);
    }

    $input = api_get_json_input();
    $sessionId = (int) ($input['session_id'] ?? 0);

    $errors = [];
    if ($sessionId <= 0) {
        $errors['session_id'] = 'Session ID is required';
    }
    if (!empty($errors)) {
        api_validation_error($errors);
    }

    $group = app_whatsapp_get_group_by_session_and_id($sessionId, (string) $groupIdParam);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || $group['user_id'] !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }

    try {
        $success = app_db_delete_group_summary($effectiveUserId, $sessionId, (string) $groupIdParam);
        if ($success) {
            api_success('Schedule summary removed', [
                'group_id' => (string) $groupIdParam,
                'session_id' => $sessionId
            ]);
        }

        api_error('Failed to remove schedule summary');
    } catch (Exception $e) {
        api_error('Failed to remove schedule summary: ' . $e->getMessage());
    }
}

function api_whatsapp_set_group_summary_latest(): void {
    api_require_method('POST');
    $user = api_require_auth();

    $groupIdParam = api_get_query_param('id');
    if (!$groupIdParam) {
        api_validation_error(['id' => 'Group ID is required']);
    }

    $input = api_get_json_input();
    $sessionId = (int) ($input['session_id'] ?? 0);
    $latestSummary = trim((string) ($input['latest_summary'] ?? ''));

    $errors = [];
    if ($sessionId <= 0) {
        $errors['session_id'] = 'Session ID is required';
    }
    if ($latestSummary === '') {
        $errors['latest_summary'] = 'Latest summary is required';
    }
    if (!empty($errors)) {
        api_validation_error($errors);
    }

    $group = app_whatsapp_get_group_by_session_and_id($sessionId, (string) $groupIdParam);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || $group['user_id'] !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }

    try {
        $success = app_db_update_group_summary_latest($effectiveUserId, $sessionId, (string) $groupIdParam, $latestSummary);
        if ($success) {
            api_success('Latest summary saved', [
                'group_id' => (string) $groupIdParam,
                'session_id' => $sessionId
            ]);
        }

        api_error('Failed to save latest summary');
    } catch (Exception $e) {
        api_error('Failed to save latest summary: ' . $e->getMessage());
    }
}

function api_whatsapp_get_group_messages(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $groupIdParam = api_get_query_param('id');
    $limit = min(100, (int) api_get_query_param('limit', 50));
    $before = api_get_query_param('before') ? (int) api_get_query_param('before') : null;
    $categoryId = api_get_query_param('category_id') ? (int) api_get_query_param('category_id') : null;
    
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || $group['user_id'] !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }
    
    if (!$sessionId || !$groupId) {
        api_error('Unable to determine session or group');
    }
    
    $messages = app_whatsapp_get_group_messages($sessionId, $groupId, $limit, $before, $categoryId);
    
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
    
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || $group['user_id'] !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }
    
    if (!$sessionId || !$groupId) {
        api_error('Unable to determine session or group');
    }
    
    try {
        $result = app_whatsapp_send_message($sessionId, $groupId, $message, $mediaPath, $mediaType);
        $preview = trim((string) $message);
        if (strlen($preview) > 120) {
            $preview = substr($preview, 0, 120) . '...';
        }
        app_log_audit('send_message', [
            'session_id' => $sessionId,
            'group_id' => $groupId,
            'message_id' => $result['message_id'] ?? null,
            'message_preview' => $preview,
            'has_media' => !empty($mediaPath)
        ], $user);
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
        $effectiveUserId = api_get_effective_user_id($user);
        $updates = app_get_realtime_updates($effectiveUserId, $lastUpdateId);
        
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
        $effectiveUserId = api_get_effective_user_id($user);
        app_mark_update_read($effectiveUserId, $updateId);
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
    $mediaUrl = trim($input['media_url'] ?? $input['mediaUrl'] ?? '');
    $mediaCaption = trim($input['media_caption'] ?? $input['mediaCaption'] ?? '');
    $caption = trim($input['caption'] ?? '');
    $mediaType = trim($input['media_type'] ?? $input['mediaType'] ?? '');
    $mediaSize = (int) ($input['media_size'] ?? $input['mediaSize'] ?? 0);
    
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
    
    if (empty($content) && empty($mediaUrl)) {
        $errors['content'] = 'Content or media URL is required';
    }
    
    if ($messageType === 'ptt') {
        $messageType = 'audio';
    }

    if (empty($messageType)) {
        $errors['message_type'] = 'Message type is required';
    } elseif (!in_array($messageType, ['chat', 'image', 'video', 'audio', 'document', 'sticker'])) {
        $errors['message_type'] = 'Invalid message type. Must be: chat, image, video, audio, document, sticker, ptt';
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
        'media_url' => $mediaUrl,
        'media_caption' => $mediaCaption,
        'caption' => $caption,
        'media_type' => $mediaType,
        'media_size' => $mediaSize,
    ];
    
    try {
        // Store the message
        $result = app_whatsapp_store_incoming_message($messageData);
        
        $responseData = [
            'message_id' => $result['id'],
            'group_id' => $chatId,
            'session_name' => $sessionName,
            'session_id' => $session['id'],
            'user_id' => (int) $session['user_id'],
            'stored_at' => date('Y-m-d H:i:s'),
            'mode' => app_whatsapp_get_file_handling_category_assignment((int) $session['id'])
        ];
        if (!empty($result['category_prompt'])) {
            $responseData['category_prompt'] = $result['category_prompt'];
        }

        api_success('Message stored successfully', $responseData, 201);
        
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
            $mediaUrl = trim($message['media_url'] ?? $message['mediaUrl'] ?? '');
            $mediaCaption = trim($message['media_caption'] ?? $message['mediaCaption'] ?? '');
            $caption = trim($message['caption'] ?? '');
            $mediaType = trim($message['media_type'] ?? $message['mediaType'] ?? '');
            $mediaSize = (int) ($message['media_size'] ?? $message['mediaSize'] ?? 0);
            
            if (empty($sessionName) || empty($chatId) || empty($messageId) || empty($sender) || 
                (empty($content) && empty($mediaUrl)) || empty($messageType)) {
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
                'media_url' => $mediaUrl,
                'media_caption' => $mediaCaption,
                'caption' => $caption,
                'media_type' => $mediaType,
                'media_size' => $mediaSize,
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

// Category API Functions

function api_whatsapp_get_categories(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    try {
        $parentId = api_get_query_param('parent_id');
        $parentId = ($parentId !== null && $parentId !== '') ? (int) $parentId : null;
        
        $all = api_get_query_param('all') === 'true';
        
        $effectiveUserId = api_get_effective_user_id($user);
        $categories = app_whatsapp_get_user_categories($effectiveUserId, $parentId, $all);
        
        api_success('Categories retrieved', [
            'categories' => array_map(function($category) {
                return [
                    'id' => (int) $category['id'],
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'color' => $category['color'],
                    'parent_id' => $category['parent_id'] ? (int) $category['parent_id'] : null,
                    'parent_name' => $category['parent_name'],
                    'subcategory_count' => (int) $category['subcategory_count'],
                    'message_count' => (int) $category['message_count'],
                    'group_count' => (int) $category['group_count'],
                    'sort_order' => (int) $category['sort_order'],
                    'is_active' => (bool) $category['is_active'],
                    'created_at' => $category['created_at'],
                    'updated_at' => $category['updated_at']
                ];
            }, $categories)
        ]);
    } catch (Exception $e) {
        app_log('Failed to get categories: ' . $e->getMessage(), 'ERROR');
        api_error('Failed to retrieve categories: ' . $e->getMessage());
    }
}

function api_whatsapp_get_category_tree(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $groupIdParam = api_get_query_param('group_id');
    $whatsappGroupId = null;
    $sessionId = null;
    $internalGroupId = null;
    
    try {
        // If group_id is provided, resolve the WhatsApp group ID and session
        if ($groupIdParam) {
            $group = null;
            if (is_numeric($groupIdParam)) {
                // Backward compatibility: numeric internal group ID
                $internalGroupId = (int) $groupIdParam;
                $group = app_whatsapp_get_group($internalGroupId);
                $effectiveUserId = api_get_effective_user_id($user);
                if (!$group || $group['user_id'] !== $effectiveUserId) {
                    api_forbidden('Group not found or access denied');
                }
                $whatsappGroupId = $group['group_id'];
                $sessionId = $group['session_id'];
            } else {
                // Preferred: WhatsApp group ID with session_id
                $sessionId = (int) api_get_query_param('session_id');
                $whatsappGroupId = trim((string) $groupIdParam);
                if (!$sessionId) {
                    api_validation_error(['session_id' => 'Session ID is required when using WhatsApp group ID']);
                }
                $group = app_whatsapp_get_group_by_session_and_id($sessionId, $whatsappGroupId);
                $effectiveUserId = api_get_effective_user_id($user);
                if (!$group || $group['user_id'] !== $effectiveUserId) {
                    api_forbidden('Group not found or access denied');
                }
                $internalGroupId = (int) $group['id'];
            }
        }
        
        $effectiveUserId = api_get_effective_user_id($user);
        $categories = app_whatsapp_get_category_tree($effectiveUserId, $whatsappGroupId, $sessionId, $internalGroupId);
        
        api_success('Category tree retrieved', [
            'categories' => $categories
        ]);
    } catch (Exception $e) {
        app_log('Failed to get category tree: ' . $e->getMessage(), 'ERROR');
        api_error('Failed to retrieve category tree: ' . $e->getMessage());
    }
}

function api_whatsapp_get_category(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $categoryId = (int) api_get_query_param('id');
    
    $category = app_whatsapp_get_category($categoryId);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$category || $category['user_id'] !== $effectiveUserId) {
        api_forbidden('Category not found or access denied');
    }
    
    api_success('Category retrieved', [
        'category' => [
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'keywords' => $category['keywords'],
            'prompt' => $category['prompt'],
            'color' => $category['color'],
            'parent_id' => $category['parent_id'] ? (int) $category['parent_id'] : null,
            'parent_name' => $category['parent_name'],
            'subcategory_count' => (int) $category['subcategory_count'],
            'message_count' => (int) $category['message_count'],
            'group_count' => (int) $category['group_count'],
            'sort_order' => (int) $category['sort_order'],
            'is_active' => (bool) $category['is_active'],
            'created_at' => $category['created_at'],
            'updated_at' => $category['updated_at']
        ]
    ]);
}

function api_whatsapp_create_category(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $data = api_get_json_input();
    
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $keywords = trim($data['keywords'] ?? '');
    $prompt = trim($data['prompt'] ?? '');
    $color = trim($data['color'] ?? '');
    $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
    
    if (empty($name)) {
        api_validation_error(['name' => 'Category name is required']);
    }
    
    try {
        $effectiveUserId = api_get_effective_user_id($user);
        $categoryId = app_whatsapp_create_category($effectiveUserId, $name, $description, $keywords ?: null, $prompt ?: null, $color ?: null, $parentId);
        
        $category = app_whatsapp_get_category($categoryId);
        
        api_success('Category created', [
            'category' => [
                'id' => (int) $category['id'],
                'name' => $category['name'],
                'description' => $category['description'],
                'keywords' => $category['keywords'],
                'prompt' => $category['prompt'],
                'color' => $category['color'],
                'parent_id' => $category['parent_id'] ? (int) $category['parent_id'] : null,
                'created_at' => $category['created_at']
            ]
        ], 201);
        
    } catch (Exception $e) {
        api_error('Failed to create category: ' . $e->getMessage());
    }
}

function api_whatsapp_update_category(): void {
    api_require_method('PUT');
    $user = api_require_auth();

    if (($user['role'] ?? '') === 'users') {
        api_forbidden('You do not have permission to edit categories');
    }
    
    $categoryId = (int) api_get_query_param('id');
    $data = api_get_json_input();
    
    try {
        $effectiveUserId = api_get_effective_user_id($user);
        $success = app_whatsapp_update_category($categoryId, $effectiveUserId, $data);
        
        if ($success) {
            $category = app_whatsapp_get_category($categoryId);
            app_log_audit('edit_category', [
                'category_id' => $categoryId,
                'category_name' => $category['name'] ?? '',
                'parent_id' => $category['parent_id'] ?? null,
                'changed_fields' => array_keys($data)
            ], $user);
            api_success('Category updated', [
                'category' => [
                    'id' => (int) $category['id'],
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'keywords' => $category['keywords'],
                    'prompt' => $category['prompt'],
                    'color' => $category['color'],
                    'parent_id' => $category['parent_id'] ? (int) $category['parent_id'] : null,
                    'sort_order' => (int) $category['sort_order'],
                    'is_active' => (bool) $category['is_active'],
                    'updated_at' => $category['updated_at']
                ]
            ]);
        } else {
            api_error('No changes made to category');
        }
        
    } catch (Exception $e) {
        api_error('Failed to update category: ' . $e->getMessage());
    }
}

function api_whatsapp_delete_category(): void {
    api_require_method('DELETE');
    $user = api_require_auth();

    if (($user['role'] ?? '') === 'users') {
        api_forbidden('You do not have permission to delete categories');
    }
    
    $categoryId = (int) api_get_query_param('id');
    
    try {
        $effectiveUserId = api_get_effective_user_id($user);
        $category = app_whatsapp_get_category($categoryId);
        if (!$category || ($category['user_id'] ?? null) !== $effectiveUserId) {
            api_forbidden('Category not found or access denied');
        }
        $success = app_whatsapp_delete_category($categoryId, $effectiveUserId);
        
        if ($success) {
            app_log_audit('delete_category', [
                'category_id' => $categoryId,
                'category_name' => $category['name'] ?? '',
                'parent_id' => $category['parent_id'] ?? null
            ], $user);
            api_success('Category deleted');
        } else {
            api_error('Failed to delete category');
        }
        
    } catch (Exception $e) {
        api_error('Failed to delete category: ' . $e->getMessage());
    }
}

function api_whatsapp_get_category_messages(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $categoryId = (int) api_get_query_param('id');
    $limit = min(100, (int) api_get_query_param('limit', 50));
    $before = api_get_query_param('before') ? (int) api_get_query_param('before') : null;
    
    // Verify category belongs to user
    $category = app_whatsapp_get_category($categoryId);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$category || $category['user_id'] !== $effectiveUserId) {
        api_forbidden('Category not found or access denied');
    }
    
    $effectiveUserId = api_get_effective_user_id($user);
    $messages = app_whatsapp_get_messages_by_category($effectiveUserId, $categoryId, $limit, $before);
    
    api_success('Category messages retrieved', [
        'messages' => array_map(function($message) {
            return [
                'id' => (int) $message['id'],
                'session_id' => (int) $message['session_id'],
                'group_id' => $message['group_id'],
                'group_name' => $message['group_name'],
                'session_name' => $message['session_name'],
                'message_id' => $message['message_id'],
                'sender_number' => $message['sender_number'],
                'sender_name' => $message['sender_name'],
                'message_type' => $message['message_type'],
                'content' => $message['content'],
                'media_url' => $message['media_url'],
                'media_caption' => $message['media_caption'],
                'caption' => $message['caption'] ?? null,
                'category_id' => $message['category_id'] ? (int) $message['category_id'] : null,
                'is_from_me' => (bool) $message['is_from_me'],
                'timestamp' => (int) $message['timestamp'],
                'created_at' => $message['created_at']
            ];
        }, $messages)
    ]);
}

function api_whatsapp_get_category_groups(): void {
    api_require_method('GET');
    $user = api_require_auth();
    
    $categoryId = (int) api_get_query_param('id');
    
    // Verify category belongs to user
    $category = app_whatsapp_get_category($categoryId);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$category || $category['user_id'] !== $effectiveUserId) {
        api_forbidden('Category not found or access denied');
    }
    
    $effectiveUserId = api_get_effective_user_id($user);
    $groups = app_whatsapp_get_groups_by_category($effectiveUserId, $categoryId);
    
    api_success('Category groups retrieved', [
        'groups' => array_map(function($group) {
            return [
                'id' => (int) $group['id'],
                'session_id' => (int) $group['session_id'],
                'group_id' => $group['group_id'],
                'name' => $group['name'],
                'description' => $group['description'],
                'participant_count' => (int) $group['participant_count'],
                'category_id' => $group['category_id'] ? (int) $group['category_id'] : null,
                'is_archived' => (bool) $group['is_archived'],
                'last_message_timestamp' => $group['last_message_timestamp'] ? (int) $group['last_message_timestamp'] : null,
                'last_message_preview' => $group['last_message_preview'],
                'unread_count' => (int) $group['unread_count'],
                'whatsapp_session_name' => $group['whatsapp_session_name'],
                'created_at' => $group['created_at'],
                'updated_at' => $group['updated_at']
            ];
        }, $groups)
    ]);
}

function api_whatsapp_assign_message_category(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $messageId = (int) api_get_query_param('id');
    $data = api_get_json_input();
    
    $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
    
    try {
        $effectiveUserId = api_get_effective_user_id($user);
        $success = app_whatsapp_assign_message_category($messageId, $categoryId, $effectiveUserId);
        
        if ($success) {
            api_success('Message category assigned');
        } else {
            api_error('Failed to assign message category');
        }
        
    } catch (Exception $e) {
        api_error('Failed to assign message category: ' . $e->getMessage());
    }
}

function api_whatsapp_delete_message(): void {
    api_require_method('DELETE');
    $user = api_require_auth();

    if (!in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) {
        api_error('Access denied. Only administrators can delete messages.', [], 403);
    }

    $messageIdParam = api_get_query_param('id');
    if (!$messageIdParam || !is_numeric($messageIdParam)) {
        api_validation_error(['id' => 'Message ID is required']);
    }

    $messageId = (int) $messageIdParam;

    try {
        $effectiveUserId = api_get_effective_user_id($user);
        $message = app_whatsapp_get_message_for_user($messageId, $effectiveUserId);
        if (!$message) {
            api_forbidden('Message not found or access denied');
        }

        $sessionName = (string) ($message['session_name'] ?? '');
        $groupId = (string) ($message['group_id'] ?? '');
        $remoteMessageId = (string) ($message['message_id'] ?? '');

        if ($sessionName === '' || $groupId === '' || $remoteMessageId === '') {
            throw new Exception('Message metadata missing for remote deletion');
        }

        app_whatsapp_delete_remote_message($sessionName, $groupId, $remoteMessageId);

        $deleted = app_whatsapp_delete_message_by_id($messageId);
        if (!$deleted) {
            api_error('Failed to delete message');
        }

        app_log_audit('delete_message', [
            'message_id' => $messageId,
            'group_id' => $groupId,
            'session_id' => $message['session_id'] ?? null,
        ], $user);

        $latestMessage = app_whatsapp_get_latest_group_message((int) $message['session_id'], $groupId);
        if ($latestMessage) {
            $previewContent = $latestMessage['content'] ?? $latestMessage['media_caption'] ?? $latestMessage['caption'] ?? '';
            app_db_update_group_last_message((int) $message['session_id'], $groupId, (string) $previewContent, (int) $latestMessage['timestamp']);
        } else {
            app_whatsapp_clear_group_last_message((int) $message['session_id'], $groupId);
        }

        api_success('Message deleted');
    } catch (Exception $e) {
        api_error('Failed to delete message: ' . $e->getMessage());
    }
}

function api_whatsapp_assign_group_category(): void {
    api_require_method('POST');
    $user = api_require_auth();
    
    $groupId = (int) api_get_query_param('id');
    $data = api_get_json_input();
    
    $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
    
    try {
        $effectiveUserId = api_get_effective_user_id($user);
        $success = app_whatsapp_assign_group_category($groupId, $categoryId, $effectiveUserId);
        
        if ($success) {
            api_success('Group category assigned');
        } else {
            api_error('Failed to assign group category');
        }
        
    } catch (Exception $e) {
        api_error('Failed to assign group category: ' . $e->getMessage());
    }
}

function api_pages_list(): void
{
    api_require_method('GET');
    $user = api_require_auth();

    $effectiveUserId = api_get_effective_user_id($user);
    $pages = app_pages_list_for_user($effectiveUserId);

    api_success('Pages retrieved', [
        'pages' => $pages,
        'count' => count($pages),
        'max' => app_pages_max_limit(),
    ]);
}

function api_pages_create(): void
{
    api_require_method('POST');
    $user = api_require_auth();

    $effectiveUserId = api_get_effective_user_id($user);
    $input = api_get_json_input();

    $token = trim((string) ($input['token'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));
    $isPublic = !empty($input['is_public']) ? 1 : 0;

    $errors = [];
    if ($token === '') {
        $token = app_pages_generate_token();
    }

    if (preg_match('/^[A-Za-z0-9_-]+$/', $token) !== 1) {
        $errors['token'] = 'Token must contain letters, numbers, hyphen, or underscore only.';
    }
    if ($title === '') {
        $errors['title'] = 'Title is required.';
    }

    if (!empty($errors)) {
        api_validation_error($errors);
    }

    if (app_pages_count_for_user($effectiveUserId) >= app_pages_max_limit()) {
        api_error('Page limit reached. You can create up to 3 pages.', [], 403);
    }

    if (app_pages_find_by_token($token) !== null) {
        $token = app_pages_generate_token();
        if (app_pages_find_by_token($token) !== null) {
            api_validation_error(['token' => 'Token already exists. Please try again.']);
        }
    }

    $page = app_pages_create($effectiveUserId, $token, $title, $isPublic);

    $baseDir = dirname(__DIR__) . '/pages';
    $pageDir = $baseDir . '/' . $token;
    if (!is_dir($pageDir)) {
        if (!mkdir($pageDir, 0775, true) && !is_dir($pageDir)) {
            api_error('Page folder could not be created. Check permissions.', [], 500);
        }
    }

    api_success('Page created', ['page' => $page]);
}

function api_reports_create_page_from_csv(): void
{
    api_require_method('POST');
    $user = api_require_auth();

    $effectiveUserId = api_get_effective_user_id($user);
    $input = api_get_json_input();

    $csv = (string) ($input['csv'] ?? '');
    $prompt = trim((string) ($input['prompt'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));

    if (trim($csv) === '') {
        api_validation_error(['csv' => 'CSV content is required.']);
    }

    if (strlen($csv) > 5 * 1024 * 1024) {
        api_validation_error(['csv' => 'CSV content exceeds the 5MB limit.']);
    }

    if ($prompt === '') {
        $prompt = 'Generate an interactive website with this data.';
    }

    if ($title === '') {
        $title = 'Reports Data';
    }

    if (app_pages_count_for_user($effectiveUserId) >= app_pages_max_limit()) {
        api_error('Page limit reached. You can create up to 3 pages.', [], 403);
    }

    $token = app_pages_generate_token();
    if (app_pages_find_by_token($token) !== null) {
        $token = app_pages_generate_token();
        if (app_pages_find_by_token($token) !== null) {
            api_error('Failed to generate a unique page token.', [], 500);
        }
    }

    $page = app_pages_create($effectiveUserId, $token, $title, 0);

    $baseDir = dirname(__DIR__) . '/pages';
    $pageDir = $baseDir . '/' . $token;
    if (!is_dir($pageDir)) {
        if (!mkdir($pageDir, 0775, true) && !is_dir($pageDir)) {
            api_error('Page folder could not be created. Check permissions.', [], 500);
        }
    }

    $csvPath = $pageDir . '/reports.csv';
    $promptPath = $pageDir . '/prompt.txt';

    if (file_put_contents($csvPath, $csv) === false) {
        api_error('Failed to save CSV data.', [], 500);
    }

    if (file_put_contents($promptPath, $prompt) === false) {
        api_error('Failed to save prompt data.', [], 500);
    }

    if (!is_file($promptPath) || !is_file($csvPath)) {
        api_error('Required files are missing before generation.', [], 500);
    }

    api_success('Page created', ['page' => $page, 'token' => $token]);
}

function api_reports_generate_page_from_csv(): void
{
    api_require_method('POST');
    $user = api_require_auth();

    $effectiveUserId = api_get_effective_user_id($user);
    $input = api_get_json_input();

    $csv = (string) ($input['csv'] ?? '');
    $prompt = trim((string) ($input['prompt'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));

    if (trim($csv) === '') {
        api_validation_error(['csv' => 'CSV content is required.']);
    }

    if (strlen($csv) > 5 * 1024 * 1024) {
        api_validation_error(['csv' => 'CSV content exceeds the 5MB limit.']);
    }

    if ($prompt === '') {
        $prompt = 'Generate an interactive website with this data.';
    }

    if ($title === '') {
        $title = 'Reports Data';
    }

    if (app_pages_count_for_user($effectiveUserId) >= app_pages_max_limit()) {
        api_error('Page limit reached. You can create up to 3 pages.', [], 403);
    }

    $token = app_pages_generate_token();
    if (app_pages_find_by_token($token) !== null) {
        $token = app_pages_generate_token();
        if (app_pages_find_by_token($token) !== null) {
            api_error('Failed to generate a unique page token.', [], 500);
        }
    }

    $page = app_pages_create($effectiveUserId, $token, $title, 0);

    $baseDir = dirname(__DIR__) . '/pages';
    $pageDir = $baseDir . '/' . $token;
    if (!is_dir($pageDir)) {
        if (!mkdir($pageDir, 0775, true) && !is_dir($pageDir)) {
            api_error('Page folder could not be created. Check permissions.', [], 500);
        }
    }

    $csvPath = $pageDir . '/reports.csv';
    $csvAltPath = $pageDir . '/report.csv';
    $promptPath = $pageDir . '/prompt.txt';

    if (file_put_contents($csvPath, $csv) === false) {
        api_error('Failed to save CSV data.', [], 500);
    }

    if (file_put_contents($csvAltPath, $csv) === false) {
        api_error('Failed to save CSV data.', [], 500);
    }

    if (file_put_contents($promptPath, $prompt) === false) {
        api_error('Failed to save prompt data.', [], 500);
    }

    $scriptPath = dirname(__DIR__) . '/scripts/generate_page.sh';
    if (!is_file($scriptPath)) {
        api_error('Generator script not found on server.', [], 500);
    }

    if (!function_exists('exec')) {
        api_error('Command execution is disabled on this server.', [], 500);
    }

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            api_error('Log folder could not be created.', [], 500);
        }
    }

    $logFile = $logDir . '/page_generate_' . $token . '.log';
    $command = 'bash ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($token)
        . ' > ' . escapeshellarg($logFile) . ' 2>&1 &';

    exec($command, $output, $statusCode);
    if ($statusCode !== 0) {
        api_error('Failed to start page generation.', [], 500);
    }

    api_success('Page generation started', ['page' => $page, 'token' => $token]);
}

function api_pages_update(): void
{
    api_require_method('PUT');
    $user = api_require_auth();

    $pageId = (int) api_get_query_param('id');
    if ($pageId <= 0) {
        api_validation_error(['id' => 'Page ID is required']);
    }

    $effectiveUserId = api_get_effective_user_id($user);
    $page = app_pages_find_for_user($effectiveUserId, $pageId);
    if ($page === null) {
        api_forbidden('Page not found or access denied');
    }

    $input = api_get_json_input();
    $titleProvided = array_key_exists('title', $input);
    $publicProvided = array_key_exists('is_public', $input);

    $title = $titleProvided ? trim((string) $input['title']) : (string) ($page['title'] ?? '');
    if ($title === '') {
        api_validation_error(['title' => 'Title is required.']);
    }
    $isPublic = $publicProvided ? (!empty($input['is_public']) ? 1 : 0) : (int) $page['is_public'];

    $updated = app_pages_update($effectiveUserId, $pageId, $title, $isPublic);
    if ($updated === null) {
        api_error('Failed to update page');
    }

    app_log_audit('edit_page', [
        'page_id' => $pageId,
        'title' => $updated['title'] ?? null,
    ], $user);

    api_success('Page updated', ['page' => $updated]);
}

function api_pages_delete(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method !== 'DELETE' && $method !== 'POST') {
        api_method_not_allowed();
    }
    $user = api_require_auth();

    $pageId = (int) api_get_query_param('id');
    if ($pageId <= 0) {
        api_validation_error(['id' => 'Page ID is required']);
    }

    $effectiveUserId = api_get_effective_user_id($user);
    $page = app_pages_find_for_user($effectiveUserId, $pageId);
    if ($page === null) {
        api_forbidden('Page not found or access denied');
    }

    $token = trim((string) ($page['token'] ?? ''));
    if ($token === '' || preg_match('/^[A-Za-z0-9_-]+$/', $token) !== 1) {
        api_error('Invalid page token.', [], 500);
    }

    $baseDir = dirname(__DIR__) . '/pages';
    $resolvedBaseDir = realpath($baseDir) ?: $baseDir;
    $pageDir = $baseDir . '/' . $token;
    $resolvedPageDir = realpath($pageDir);

    if ($resolvedPageDir !== false && str_starts_with($resolvedPageDir, rtrim($resolvedBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        if (!api_delete_directory($resolvedPageDir)) {
            api_error('Failed to delete page files.', [], 500);
        }
    }

    if (!app_pages_delete($effectiveUserId, $pageId)) {
        api_error('Failed to delete page');
    }

    app_log_audit('delete_page', [
        'page_id' => $pageId,
        'token' => $token,
        'title' => $page['title'] ?? null,
    ], $user);

    api_success('Page deleted');
}

function api_pages_generate_existing(): void
{
    api_require_method('POST');
    $user = api_require_auth();

    $pageId = (int) api_get_query_param('id');
    if ($pageId <= 0) {
        api_validation_error(['id' => 'Page ID is required']);
    }

    $effectiveUserId = api_get_effective_user_id($user);
    $page = app_pages_find_for_user($effectiveUserId, $pageId);
    if ($page === null) {
        api_forbidden('Page not found or access denied');
    }

    $token = trim((string) ($page['token'] ?? ''));
    if ($token === '' || preg_match('/^[A-Za-z0-9_-]+$/', $token) !== 1) {
        api_error('Invalid page token.', [], 500);
    }

    $baseDir = dirname(__DIR__) . '/pages';
    $pageDir = $baseDir . '/' . $token;
    $promptPath = $pageDir . '/prompt.txt';
    $csvPath = $pageDir . '/reports.csv';
    $csvAltPath = $pageDir . '/report.csv';

    if (!is_dir($pageDir)) {
        api_error('Page folder does not exist.', [], 500);
    }

    if (!is_file($promptPath) || (!is_file($csvPath) && !is_file($csvAltPath))) {
        api_error('Required files are missing. Ensure prompt.txt and reports.csv exist.', [], 500);
    }

    $scriptPath = dirname(__DIR__) . '/scripts/generate_page.sh';
    if (!is_file($scriptPath)) {
        api_error('Generator script not found on server.', [], 500);
    }

    if (!function_exists('exec')) {
        api_error('Command execution is disabled on this server.', [], 500);
    }

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            api_error('Log folder could not be created.', [], 500);
        }
    }

    $statusFile = $logDir . '/page_generate_' . $token . '.status';
    if (is_file($statusFile)) {
        $currentStatus = trim((string) file_get_contents($statusFile));
        if ($currentStatus === 'running') {
            api_error('Generation is already running.', [], 409);
        }
    }

    $logFile = $logDir . '/page_generate_' . $token . '.log';
    $command = 'bash ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($token)
        . ' > ' . escapeshellarg($logFile) . ' 2>&1 &';

    exec($command, $output, $statusCode);
    if ($statusCode !== 0) {
        api_error('Failed to start page generation.', [], 500);
    }

    api_success('Page generation started', ['page' => $page, 'token' => $token]);
}

function api_require_whatsapp_access(): array {
    $user = api_require_auth();
    
    if ($user['role'] === 'users') {
        api_error('Access denied. WhatsApp features are only available for administrators.', [], 403);
    }
    
    return $user;
}

function api_cases_export(): void
{
    api_require_method('POST');
    $user = api_require_auth();

    $groupIdParam = api_get_query_param('id');
    if (!$groupIdParam) {
        api_validation_error(['id' => 'Group ID is required']);
    }

    $input = api_get_json_input();
    $sessionId = (int) ($input['session_id'] ?? 0);
    if ($sessionId <= 0) {
        api_validation_error(['session_id' => 'Session ID is required']);
    }

    $groupId = (string) $groupIdParam;
    $group = app_whatsapp_get_group_by_session_and_id($sessionId, $groupId);
    $effectiveUserId = api_get_effective_user_id($user);
    if (!$group || (int) ($group['user_id'] ?? 0) !== $effectiveUserId) {
        api_forbidden('Group not found or access denied');
    }

    $pdo = app_db();
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $pdo->prepare('
        INSERT INTO case_exports
        (user_id, session_id, group_id, group_name, status, created_at, expires_at)
        VALUES (:user_id, :session_id, :group_id, :group_name, :status, :created_at, :expires_at)
    ');
    $stmt->execute([
        'user_id' => $effectiveUserId,
        'session_id' => $sessionId,
        'group_id' => $groupId,
        'group_name' => $group['name'] ?? null,
        'status' => 'queued',
        'created_at' => $now,
        'expires_at' => $expiresAt,
    ]);

    $exportId = (int) $pdo->lastInsertId();

    $deleteStmt = $pdo->prepare('
        DELETE FROM realtime_updates
        WHERE user_id = :user_id
          AND entity_id = :entity_id
          AND update_type IN ("case_export_ready", "case_export_failed")
    ');
    $deleteStmt->execute([
        'user_id' => $effectiveUserId,
        'entity_id' => $groupId,
    ]);

    app_log_audit('case_export_started', [
        'export_id' => $exportId,
        'group_id' => $groupId,
        'group_name' => $group['name'] ?? '',
        'session_id' => $sessionId,
    ], $user);

    $scriptPath = dirname(__DIR__) . '/scripts/export_case.php';
    if (!is_file($scriptPath)) {
        api_error('Export script not found on server.', [], 500);
    }

    if (!function_exists('exec')) {
        api_error('Command execution is disabled on this server.', [], 500);
    }

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            api_error('Log folder could not be created.', [], 500);
        }
    }

    $logFile = $logDir . '/case_export_' . $exportId . '.log';
    $command = 'php ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string) $exportId)
        . ' > ' . escapeshellarg($logFile) . ' 2>&1 &';

    $updateStmt = $pdo->prepare('UPDATE case_exports SET status = :status WHERE id = :id');
    $updateStmt->execute(['status' => 'processing', 'id' => $exportId]);

    exec($command, $output, $statusCode);
    if ($statusCode !== 0) {
        $errorMessage = 'Failed to start case export.';
        $updateStmt = $pdo->prepare('UPDATE case_exports SET status = :status, error_message = :error WHERE id = :id');
        $updateStmt->execute([
            'status' => 'failed',
            'error' => $errorMessage,
            'id' => $exportId
        ]);

        app_log_audit('case_export_failed', [
            'export_id' => $exportId,
            'group_id' => $groupId,
            'group_name' => $group['name'] ?? '',
            'session_id' => $sessionId,
            'error_message' => $errorMessage,
        ], $user);

        api_error($errorMessage, [], 500);
    }

    api_success('Case export started', [
        'export_id' => $exportId,
        'status' => 'processing',
        'expires_at' => $expiresAt,
    ]);
}

function api_cases_download_export(): void
{
    api_require_method('GET');
    $user = api_require_auth();

    $exportIdParam = api_get_query_param('id');
    if (!$exportIdParam || !is_numeric($exportIdParam)) {
        api_validation_error(['id' => 'Export ID is required']);
    }

    $exportId = (int) $exportIdParam;
    $effectiveUserId = api_get_effective_user_id($user);
    $pdo = app_db();
    $stmt = $pdo->prepare('
        SELECT * FROM case_exports
        WHERE id = :id AND user_id = :user_id
        LIMIT 1
    ');
    $stmt->execute(['id' => $exportId, 'user_id' => $effectiveUserId]);
    $export = $stmt->fetch();

    if (!$export) {
        api_forbidden('Export not found or access denied');
    }

    if (($export['status'] ?? '') !== 'ready') {
        api_error('Export is not ready yet.', [], 409);
    }

    $zipPath = (string) ($export['zip_path'] ?? '');
    if ($zipPath === '' || !is_file($zipPath) || !is_readable($zipPath)) {
        api_not_found('Export file not found');
    }

    $storageDir = realpath(dirname(__DIR__) . '/storage/case-exports');
    $realZip = realpath($zipPath);
    if ($storageDir && $realZip && strpos($realZip, $storageDir) !== 0) {
        api_forbidden('Invalid export path');
    }

    $filename = (string) ($export['zip_filename'] ?? ('case-export-' . $exportId . '.zip'));

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    exit;
}

function api_cases_export_notifications(): void
{
    api_require_method('GET');
    $user = api_require_auth();

    $effectiveUserId = api_get_effective_user_id($user);
    $pdo = app_db();
    $stmt = $pdo->prepare('
        SELECT id, update_type, entity_id, data, created_at
        FROM realtime_updates
        WHERE user_id = :user_id
          AND update_type IN ("case_export_ready", "case_export_failed")
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 3
    ');
    $stmt->execute(['user_id' => $effectiveUserId]);
    $rows = $stmt->fetchAll();

    $notifications = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        $notifications[] = [
            'id' => (int) $row['id'],
            'update_type' => $row['update_type'],
            'entity_id' => $row['entity_id'],
            'data' => $data,
            'created_at' => $row['created_at'],
        ];
    }

    api_success('Notifications retrieved', [
        'notifications' => $notifications,
    ]);
}

function api_webbycloud_files(): void
{
    api_require_method('GET');
    $user = api_require_auth();

    $settings = [];
    if (!empty($user['settings'])) {
        $decodedSettings = json_decode($user['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $webbycloud = $settings['webbycloud'] ?? [];
    if (empty($webbycloud['connected']) || empty($webbycloud['access_token'])) {
        api_error('WebbyCloud is not connected.', [], 403);
    }

    $config = app_webbycloud_config();
    $accessToken = (string) $webbycloud['access_token'];
    $cloudUserId = (string) ($webbycloud['user_id'] ?? '');

    try {
        $files = app_webbycloud_list_files($config, $accessToken, $cloudUserId);
        api_success('Files retrieved', [
            'files' => $files,
            'count' => count($files),
        ]);
    } catch (Throwable $e) {
        api_error('Failed to fetch files: ' . $e->getMessage());
    }
}
