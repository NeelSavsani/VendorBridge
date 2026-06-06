<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

$auth = require_auth(['admin']);

$db = getDB();

$stmt = $db->prepare("
    SELECT
        id,
        name,
        email,
        role,
        phone,
        country,
        created_at
    FROM users
    ORDER BY created_at DESC
");

$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

success_response(
    'Users retrieved successfully',
    [
        'users' => $users
    ]
);