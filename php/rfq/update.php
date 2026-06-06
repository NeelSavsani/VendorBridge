<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

$auth = require_auth([
    'admin',
    'procurement_officer'
]);

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid RFQ ID',
        400
    );
}

$body = get_json_input();

$db = getDB();

// ========================================
// Check RFQ Exists
// ========================================

$stmt = $db->prepare("
    SELECT
        id,
        rfq_number
    FROM rfqs
    WHERE id = ?
");

$stmt->execute([$id]);

$rfq = $stmt->fetch();

if (!$rfq) {

    error_response(
        'RFQ not found',
        404
    );
}

// ========================================
// Validate Deadline
// ========================================

if (
    isset($body['deadline']) &&
    strtotime($body['deadline']) === false
) {
    error_response(
        'Invalid deadline',
        400
    );
}

// ========================================
// Update Fields
// ========================================

$fields = [
    'title',
    'description',
    'category',
    'deadline',
    'budget',
    'status'
];

$sets = [];
$values = [];

foreach ($fields as $field) {

    if (isset($body[$field])) {

        $sets[] = "$field = ?";

        if (
            in_array(
                $field,
                ['budget', 'deadline']
            )
        ) {
            $values[] = $body[$field];
        } else {
            $values[] = sanitize(
                $body[$field]
            );
        }
    }
}

if (empty($sets)) {

    error_response(
        'No fields to update',
        400
    );
}

$values[] = $id;

$sql = "
    UPDATE rfqs
    SET " . implode(', ', $sets) . "
    WHERE id = ?
";

$stmt = $db->prepare($sql);

$stmt->execute($values);

// ========================================
// Activity Log
// ========================================

log_activity(
    $auth['id'],
    'RFQ_UPDATED',
    'rfq',
    $id,
    'RFQ updated: ' .
    $rfq['rfq_number']
);

// ========================================
// Response
// ========================================

success_response(
    'RFQ updated successfully',
    [
        'rfq_id' => $id,
        'rfq_number' => $rfq['rfq_number']
    ]
);