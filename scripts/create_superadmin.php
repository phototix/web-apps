<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$email = 'brandon@kkbuddy.com';
$password = '#Quidents64#';

$pdo = app_db();

$statement = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role)
     VALUES (:name, :email, :password_hash, :role)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        password_hash = VALUES(password_hash),
        role = VALUES(role)'
);

$statement->execute([
    'name' => 'Brandon Superadmin',
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'superadmin',
]);

fwrite(STDOUT, "Superadmin account is ready for {$email}.\n");