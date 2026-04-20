<?php

declare(strict_types=1);

function app_roles(): array
{
    return ['superadmin', 'admin', 'users'];
}

function app_find_user_by_email(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT id, name, email, role, parent_id, password_hash, settings, created_at, expiry_date, last_login_at, agent_contacts FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => strtolower(trim($email))]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function app_find_user_by_id(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT id, name, email, role, tier, parent_id, settings, created_at, expiry_date, last_login_at, agent_contacts FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function app_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    app_update_last_login((int) $user['id']);
}

function app_update_last_login(int $userId): void
{
    $pdo = app_db();
    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

function app_magic_login_token_ttl(): int
{
    return 300;
}

function app_get_magic_login_tokens(): array
{
    $tokens = $_SESSION['magic_login_tokens'] ?? [];

    return is_array($tokens) ? $tokens : [];
}

function app_store_magic_login_tokens(array $tokens): void
{
    $_SESSION['magic_login_tokens'] = $tokens;
}

function app_prune_magic_login_tokens(array $tokens, int $now): array
{
    foreach ($tokens as $token => $entry) {
        $expiresAt = (int) ($entry['expires_at'] ?? 0);
        if ($expiresAt <= $now) {
            unset($tokens[$token]);
        }
    }

    return $tokens;
}

function app_create_magic_login_token(int $userId): string
{
    $token = bin2hex(random_bytes(16));
    $now = time();
    $tokens = app_prune_magic_login_tokens(app_get_magic_login_tokens(), $now);

    $tokens[$token] = [
        'user_id' => $userId,
        'expires_at' => $now + app_magic_login_token_ttl()
    ];

    app_store_magic_login_tokens($tokens);

    return $token;
}

function app_consume_magic_login_token(string $token): ?int
{
    $now = time();
    $tokens = app_prune_magic_login_tokens(app_get_magic_login_tokens(), $now);

    if (!isset($tokens[$token])) {
        app_store_magic_login_tokens($tokens);
        return null;
    }

    $entry = $tokens[$token];
    unset($tokens[$token]);
    app_store_magic_login_tokens($tokens);

    $expiresAt = (int) ($entry['expires_at'] ?? 0);
    if ($expiresAt <= $now) {
        return null;
    }

    return (int) ($entry['user_id'] ?? 0) ?: null;
}

function app_current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        $user = null;

        return $user;
    }

    $user = app_find_user_by_id(app_db(), (int) $userId);

    return $user;
}

function app_get_parent_user(array $user): ?array
{
    if (($user['role'] ?? '') !== 'users') {
        return null;
    }

    $parentId = (int) ($user['parent_id'] ?? 0);
    if ($parentId <= 0) {
        return null;
    }

    return app_find_user_by_id(app_db(), $parentId);
}

function app_get_effective_user(array $user): array
{
    $parent = app_get_parent_user($user);

    return $parent ?: $user;
}

function app_is_expiry_date_expired(?string $expiryDate): bool
{
    if ($expiryDate === null || $expiryDate === '') {
        return false;
    }

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    return $expiryDate <= $today;
}

function app_is_admin_account_expired(array $user): bool
{
    if (($user['role'] ?? '') !== 'admin') {
        return false;
    }

    return app_is_expiry_date_expired($user['expiry_date'] ?? null);
}

function app_is_parent_admin_expired(array $user): bool
{
    $parent = app_get_parent_user($user);
    if ($parent === null) {
        return false;
    }

    return app_is_admin_account_expired($parent);
}

function app_logout_user(): void
{
    $user = app_current_user();
    $email = $user['email'] ?? 'unknown';
    app_log_audit('logout', [], $user);
    
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    
    app_log_auth($email, 'logout', true);
}

function app_require_auth(): void
{
    if (app_current_user() === null) {
        app_flash('error', 'Please sign in to continue.');
        app_redirect('/login');
    }

    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $path = $path === '' ? '/' : rtrim($path, '/');
    if ($path === '') {
        $path = '/';
    }

    if (in_array($path, ['/expired', '/restricted'], true)) {
        return;
    }

    $user = app_current_user();
    if ($user === null) {
        return;
    }

    if (app_is_admin_account_expired($user)) {
        app_redirect('/expired');
    }

    if (($user['role'] ?? '') === 'users' && app_is_parent_admin_expired($user)) {
        app_redirect('/restricted');
    }
}

function app_require_guest(): void
{
    if (app_current_user() !== null) {
        app_redirect('/welcome');
    }
}

function app_generate_mfa_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $maxIndex = strlen($alphabet) - 1;
    $secret = '';

    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, $maxIndex)];
    }

    return $secret;
}

function app_validate_mfa_otp(string $secret, string $otp): bool
{
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $otp = preg_replace('/\D+/', '', $otp);

    if ($secret === '' || $otp === '') {
        return false;
    }

    $url = 'https://kkbuddy.com/getotp.php?key=' . urlencode($secret) . '&format=api';
    $response = null;

    $ch = curl_init();
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return false;
        }
    } else {
        $response = @file_get_contents($url);
        if ($response === false) {
            return false;
        }
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded) || empty($decoded['code'])) {
        return false;
    }

    return hash_equals((string) $decoded['code'], (string) $otp);
}

function app_attempt_login(string $email, string $password, ?string $otp = null): bool
{
    $user = app_find_user_by_email(app_db(), $email);

    if ($user === null) {
        app_log_auth($email, 'login', false);
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        app_log_auth($email, 'login', false);
        return false;
    }

    $settings = [];
    if (!empty($user['settings'])) {
        $decodedSettings = json_decode($user['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $mfaEnabled = !empty($settings['mfa_enabled']);
    if ($mfaEnabled) {
        $mfaSecret = (string) ($settings['mfa_secret'] ?? '');
        $otp = is_string($otp) ? trim($otp) : '';

        if ($mfaSecret === '' || $otp === '') {
            app_log_auth($email, 'login', false);
            return false;
        }

        if (!app_validate_mfa_otp($mfaSecret, $otp)) {
            app_log_auth($email, 'login', false);
            return false;
        }
    }

    app_login_user($user);
    app_log_auth($email, 'login', true);
    app_log_audit('login', [], $user);

    return true;
}

function app_validate_invite_code(string $code): ?array
{
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT ui.*, u.id as created_by_id, u.name as created_by_name, u.role as created_by_role
        FROM user_invites ui
        LEFT JOIN users u ON ui.created_by = u.id
        WHERE ui.code = :code AND ui.used_at IS NULL AND ui.expires_at > NOW()
    ");
    $stmt->execute(['code' => $code]);
    $invite = $stmt->fetch();
    
    return $invite ?: null;
}

function app_use_invite_code(string $code, int $usedByUserId): bool
{
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE user_invites 
        SET used_by = :used_by, used_at = NOW(), current_uses = current_uses + 1
        WHERE code = :code AND used_at IS NULL AND expires_at > NOW() AND current_uses < max_uses
    ");
    return $stmt->execute(['code' => $code, 'used_by' => $usedByUserId]);
}

function app_register_user(string $name, string $email, string $password, string $role = 'users', ?string $inviteCode = null): array
{
    $trimmedName = trim($name);
    $trimmedEmail = strtolower(trim($email));
    $parentId = null;
    $expiryDate = null;

    if ($trimmedName === '') {
        return ['success' => false, 'message' => 'Name is required.'];
    }

    if (!filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Enter a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    $pdo = app_db();

    if (app_find_user_by_email($pdo, $trimmedEmail) !== null) {
        return ['success' => false, 'message' => 'That email is already registered.'];
    }

    // Validate invite code if provided
    if ($inviteCode !== null && $inviteCode !== '') {
        $invite = app_validate_invite_code($inviteCode);
        if (!$invite) {
            return ['success' => false, 'message' => 'Invalid or expired invite code.'];
        }
        
        // Force role to 'users' for invite-based registration
        $role = 'users';
        $parentId = $invite['created_by_id'];
        
        // Check if creator is admin or superadmin
        if (!in_array($invite['created_by_role'], ['admin', 'superadmin'])) {
            return ['success' => false, 'message' => 'Invite code must be created by an administrator.'];
        }
    } else {
        // No invite code - enforce admin role and 30-day expiry
        $role = 'admin';
        $expiryDate = (new DateTimeImmutable('today'))
            ->modify('+30 days')
            ->format('Y-m-d');
    }

    $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, parent_id, expiry_date) VALUES (:name, :email, :password_hash, :role, :parent_id, :expiry_date)');
    $statement->execute([
        'name' => $trimmedName,
        'email' => $trimmedEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'parent_id' => $parentId,
        'expiry_date' => $expiryDate,
    ]);

    $id = (int) $pdo->lastInsertId();
    $user = app_find_user_by_id($pdo, $id);

    if ($user === null) {
        return ['success' => false, 'message' => 'User was created but could not be loaded.'];
    }

    // Mark invite code as used if it was provided
    if ($inviteCode !== null && $inviteCode !== '' && $parentId !== null) {
        app_use_invite_code($inviteCode, $id);
    }

    app_login_user($user);
    app_log_auth($email, 'registration', true);

    return ['success' => true, 'message' => 'Registration complete.'];
}
