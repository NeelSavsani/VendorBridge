<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

$auth = require_auth();

$db = getDB();

$search = sanitize(
    $_GET['search'] ?? ''
);

$category = sanitize(
    $_GET['category'] ?? ''
);

$status = sanitize(
    $_GET['status'] ?? ''
);

$pagination = get_pagination();

$where = ['1=1'];
$binds = [];

if ($search) {

    $where[] = "
        (
            v.company_name LIKE ?
            OR v.email LIKE ?
            OR v.contact_person LIKE ?
        )
    ";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

if ($category) {

    $where[] = "v.category = ?";
    $binds[] = $category;
}

if ($status) {

    $where[] = "v.status = ?";
    $binds[] = $status;
}

// Vendors only see themselves

if (
    isset($auth['role']) &&
    $auth['role'] === 'vendor'
) {

    $where[] = "v.user_id = ?";
    $binds[] = $auth['id'];
}

$sql = "
    SELECT
        v.id,
        v.company_name,
        v.contact_person,
        v.email,
        v.phone,
        v.category,
        v.country,
        v.status,
        v.rating,
        v.created_at,
        COUNT(DISTINCT rv.rfq_id) AS rfq_count
    FROM vendors v
    LEFT JOIN rfq_vendors rv
        ON rv.vendor_id = v.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY v.id
    ORDER BY v.created_at DESC
    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$vendors = $stmt->fetchAll();

success_response(
    'Vendors retrieved successfully',
    [
        'vendors' => $vendors,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);