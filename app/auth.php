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
    $statement = $pdo->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = :id LIMIT 1');
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
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
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
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    app_login_user($user);

    return true;
}

function app_register_user(string $name, string $email, string $password, string $role = 'users'): array
{
    $trimmedName = trim($name);
    $trimmedEmail = strtolower(trim($email));

    if ($trimmedName === '') {
        return ['success' => false, 'message' => 'Name is required.'];
    }

    if (!filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Enter a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    if (!in_array($role, app_roles(), true)) {
        return ['success' => false, 'message' => 'Invalid role selected.'];
    }

    $pdo = app_db();

    if (app_find_user_by_email($pdo, $trimmedEmail) !== null) {
        return ['success' => false, 'message' => 'That email is already registered.'];
    }

    $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)');
    $statement->execute([
        'name' => $trimmedName,
        'email' => $trimmedEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    $id = (int) $pdo->lastInsertId();
    $user = app_find_user_by_id($pdo, $id);

    if ($user === null) {
        return ['success' => false, 'message' => 'User was created but could not be loaded.'];
    }

    app_login_user($user);

    return ['success' => true, 'message' => 'Registration complete.'];
}