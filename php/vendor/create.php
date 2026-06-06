<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

$auth = require_auth([
    'admin',
    'procurement_officer'
]);

$body = get_json_input();

require_fields(
    $body,
    [
        'company_name',
        'email'
    ]
);

if (!is_valid_email($body['email'])) {

    error_response(
        'Invalid email address',
        400
    );
}

$db = getDB();

$stmt = $db->prepare("
    SELECT id
    FROM vendors
    WHERE email = ?
");

$stmt->execute([
    $body['email']
]);

if ($stmt->fetch()) {

    error_response(
        'Vendor already exists',
        409
    );
}

$stmt = $db->prepare("
    INSERT INTO vendors
    (
        company_name,
        contact_person,
        email,
        phone,
        gst_number,
        category,
        address,
        country,
        status
    )
    VALUES
    (
        ?,?,?,?,?,?,?,?,?
    )
");

$stmt->execute([
    sanitize($body['company_name']),
    sanitize($body['contact_person'] ?? ''),
    sanitize($body['email']),
    sanitize($body['phone'] ?? ''),
    sanitize($body['gst_number'] ?? ''),
    sanitize($body['category'] ?? 'General'),
    sanitize($body['address'] ?? ''),
    sanitize($body['country'] ?? 'India'),
    sanitize($body['status'] ?? 'active')
]);

$vendorId = $db->lastInsertId();

log_activity(
    $auth['id'],
    'VENDOR_CREATED',
    'vendor',
    $vendorId,
    'Vendor created: ' .
    $body['company_name']
);

success_response(
    'Vendor created successfully',
    [
        'vendor_id' => $vendorId
    ]
);