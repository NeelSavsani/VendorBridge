<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

$body = get_json_input();

$email = sanitize($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) {
    error_response(
        'Email and password required',
        400
    );
}

$db = getDB();

$stmt = $db->prepare("
    SELECT *
    FROM users
    WHERE email = ?
    AND is_active = 1
");

$stmt->execute([$email]);

$user = $stmt->fetch();

if (
    !$user ||
    !password_verify(
        $password,
        $user['password']
    )
) {
    error_response(
        'Invalid credentials',
        401
    );
}

$token = jwt_encode([
    'id' => $user['id'],
    'email' => $user['email'],
    'role' => $user['role'],
    'name' => $user['name']
]);

log_activity(
    $user['id'],
    'LOGIN',
    'user',
    $user['id'],
    'User logged in'
);

unset($user['password']);

success_response(
    'Login successful',
    [
        'token' => $token,
        'user' => $user
    ]
);