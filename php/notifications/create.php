<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'procurement_officer'
]);

// ========================================
// Input
// ========================================

$body = get_json_input();

require_fields(
    $body,
    [
        'user_id',
        'title',
        'message'
    ]
);

$userId = (int)$body['user_id'];

if ($userId <= 0) {

    error_response(
        'Invalid user ID',
        400
    );
}

$db = getDB();

try {

    // ========================================
    // Check User Exists
    // ========================================

    $stmt = $db->prepare("
        SELECT
            id,
            name,
            email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $user = $stmt->fetch();

    if (!$user) {

        throw new Exception(
            'User not found'
        );
    }

    // ========================================
    // Create Notification
    // ========================================

    create_notification(
        $userId,
        sanitize($body['title']),
        sanitize($body['message'])
    );

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'NOTIFICATION_CREATED',
        'notification',
        $userId,
        'Notification sent to user: ' .
        $user['email']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Notification created successfully',
        [
            'user_id' => $userId,
            'title' => sanitize(
                $body['title']
            )
        ]
    );

} catch (Exception $e) {

    error_response(
        $e->getMessage(),
        400
    );
}