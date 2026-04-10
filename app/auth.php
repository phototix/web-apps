<?php

declare(strict_types=1);

function app_roles(): array
{
    return ['superadmin', 'admin', 'users'];
}

function app_find_user_by_email(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT id, name, email, role, password_hash, created_at FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => strtolower(trim($email))]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function app_find_user_by_id(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT id, name, email, role, tier, created_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function app_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
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

function app_logout_user(): void
{
    $user = app_current_user();
    $email = $user['email'] ?? 'unknown';
    
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
}

function app_require_guest(): void
{
    if (app_current_user() !== null) {
        app_redirect('/welcome');
    }
}

function app_attempt_login(string $email, string $password): bool
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

    app_login_user($user);
    app_log_auth($email, 'login', true);

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
        // No invite code - check if role is valid
        if (!in_array($role, app_roles(), true)) {
            return ['success' => false, 'message' => 'Invalid role selected.'];
        }
    }

    $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, parent_id) VALUES (:name, :email, :password_hash, :role, :parent_id)');
    $statement->execute([
        'name' => $trimmedName,
        'email' => $trimmedEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'parent_id' => $parentId,
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