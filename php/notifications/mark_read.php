<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid notification ID',
        400
    );
}

$db = getDB();

try {

    // ========================================
    // Get Notification
    // ========================================

    $stmt = $db->prepare("
        SELECT
            id,
            user_id,
            title,
            is_read
        FROM notifications
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);

    $notification = $stmt->fetch();

    if (!$notification) {

        throw new Exception(
            'Notification not found'
        );
    }

    // ========================================
    // Permission Check
    // ========================================

    if (
        $auth['role'] !== 'admin' &&
        $notification['user_id'] != $auth['id']
    ) {

        throw new Exception(
            'Access denied'
        );
    }

    // ========================================
    // Already Read
    // ========================================

    if ((int)$notification['is_read'] === 1) {

        success_response(
            'Notification already marked as read',
            [
                'notification_id' => $id
            ]
        );
    }

    // ========================================
    // Update Notification
    // ========================================

    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'NOTIFICATION_READ',
        'notification',
        $id,
        'Notification marked as read'
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Notification marked as read',
        [
            'notification_id' => $id,
            'is_read' => true
        ]
    );

} catch (Exception $e) {

    error_response(
        $e->getMessage(),
        400
    );
}