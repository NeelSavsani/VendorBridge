<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

$db = getDB();

// ========================================
// Filters
// ========================================

$search = sanitize(
    $_GET['search'] ?? ''
);

$isRead = $_GET['is_read'] ?? '';

$pagination = get_pagination();

$where = ['1=1'];
$binds = [];

// ========================================
// User Restriction
// ========================================

if ($auth['role'] !== 'admin') {

    $where[] = "n.user_id = ?";
    $binds[] = $auth['id'];
}

// ========================================
// Search Filter
// ========================================

if ($search) {

    $where[] = "
        (
            n.title LIKE ?
            OR n.message LIKE ?
        )
    ";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

// ========================================
// Read Filter
// ========================================

if (
    $isRead !== '' &&
    in_array($isRead, ['0', '1'])
) {

    $where[] = "n.is_read = ?";
    $binds[] = $isRead;
}

// ========================================
// Query
// ========================================

$sql = "
    SELECT
        n.id,
        n.user_id,
        n.title,
        n.message,
        n.is_read,
        n.created_at,

        u.name AS user_name,
        u.email AS user_email

    FROM notifications n

    INNER JOIN users u
        ON u.id = n.user_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY n.created_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$notifications = $stmt->fetchAll();

// ========================================
// Summary
// ========================================

$summary = [
    'total_records' => count($notifications),
    'read' => 0,
    'unread' => 0
];

foreach ($notifications as $notification) {

    if ($notification['is_read']) {
        $summary['read']++;
    } else {
        $summary['unread']++;
    }
}

// ========================================
// Response
// ========================================

success_response(
    'Notifications retrieved successfully',
    [
        'notifications' => $notifications,
        'summary' => $summary,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);