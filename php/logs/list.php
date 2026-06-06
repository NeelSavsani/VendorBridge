<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin'
]);

$db = getDB();

// ========================================
// Filters
// ========================================

$search = sanitize(
    $_GET['search'] ?? ''
);

$userId = (int)(
    $_GET['user_id'] ?? 0
);

$entityType = sanitize(
    $_GET['entity_type'] ?? ''
);

$pagination =
    get_pagination();

$where = ['1=1'];
$binds = [];

// ========================================
// Search
// ========================================

if ($search) {

    $where[] = "
        (
            al.action LIKE ?
            OR al.description LIKE ?
            OR u.name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

// ========================================
// User Filter
// ========================================

if ($userId > 0) {

    $where[] = "al.user_id = ?";
    $binds[] = $userId;
}

// ========================================
// Entity Filter
// ========================================

if ($entityType) {

    $where[] = "al.entity_type = ?";
    $binds[] = $entityType;
}

// ========================================
// Query
// ========================================

$sql = "
    SELECT
        al.id,
        al.user_id,
        al.action,
        al.entity_type,
        al.entity_id,
        al.description,
        al.ip_address,
        al.created_at,

        u.name,
        u.email,
        u.role

    FROM activity_logs al

    LEFT JOIN users u
        ON u.id = al.user_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY al.created_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$logs = $stmt->fetchAll();

// ========================================
// Summary
// ========================================

$summary = [
    'total_records' => count($logs)
];

$actions = [];

foreach ($logs as $log) {

    $action = $log['action'];

    if (!isset($actions[$action])) {
        $actions[$action] = 0;
    }

    $actions[$action]++;
}

$summary['actions'] = $actions;

// ========================================
// Response
// ========================================

success_response(
    'Activity logs retrieved successfully',
    [
        'logs' => $logs,
        'summary' => $summary,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);