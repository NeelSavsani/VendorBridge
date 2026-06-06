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
        'Invalid vendor ID',
        400
    );
}

$body = get_json_input();

$db = getDB();

// Check vendor exists

$stmt = $db->prepare("
    SELECT id
    FROM vendors
    WHERE id = ?
");

$stmt->execute([$id]);

if (!$stmt->fetch()) {
    error_response(
        'Vendor not found',
        404
    );
}

// Email validation

if (
    isset($body['email']) &&
    !is_valid_email($body['email'])
) {
    error_response(
        'Invalid email address',
        400
    );
}

// Prevent duplicate email

if (isset($body['email'])) {

    $stmt = $db->prepare("
        SELECT id
        FROM vendors
        WHERE email = ?
        AND id <> ?
    ");

    $stmt->execute([
        $body['email'],
        $id
    ]);

    if ($stmt->fetch()) {

        error_response(
            'Email already exists',
            409
        );
    }
}

$fields = [
    'company_name',
    'contact_person',
    'email',
    'phone',
    'gst_number',
    'category',
    'address',
    'country',
    'status',
    'rating'
];

$sets = [];
$values = [];

foreach ($fields as $field) {

    if (isset($body[$field])) {

        $sets[] = "$field = ?";
        $values[] = sanitize(
            $body[$field]
        );
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
    UPDATE vendors
    SET " . implode(', ', $sets) . "
    WHERE id = ?
";

$stmt = $db->prepare($sql);

$stmt->execute($values);

log_activity(
    $auth['id'],
    'VENDOR_UPDATED',
    'vendor',
    $id,
    'Vendor updated'
);

success_response(
    'Vendor updated successfully'
);