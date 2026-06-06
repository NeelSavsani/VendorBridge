<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

$body = get_json_input();

$name = sanitize($body['name'] ?? '');
$email = sanitize($body['email'] ?? '');
$password = $body['password'] ?? '';

$role = sanitize(
    $body['role'] ?? 'vendor'
);

$company = sanitize(
    $body['company'] ?? ''
);

$phone = sanitize(
    $body['phone'] ?? ''
);

$country = sanitize(
    $body['country'] ?? 'India'
);

// ========================================
// Validation
// ========================================

require_fields(
    $body,
    ['name', 'email', 'password']
);

if (!is_valid_email($email)) {

    error_response(
        'Invalid email address',
        400
    );
}

if (strlen($password) < 6) {

    error_response(
        'Password must be at least 6 characters',
        400
    );
}

if (
    !empty($phone) &&
    !is_valid_phone($phone)
) {
    error_response(
        'Invalid phone number',
        400
    );
}

// ========================================
// Role Validation
// ========================================

$allowed_roles = [
    'procurement_officer',
    'vendor',
    'manager'
];

if (!in_array($role, $allowed_roles)) {
    $role = 'vendor';
}

// ========================================
// Database
// ========================================

$db = getDB();

try {

    $db->beginTransaction();

    // Check duplicate email

    $stmt = $db->prepare("
        SELECT id
        FROM users
        WHERE email = ?
    ");

    $stmt->execute([$email]);

    if ($stmt->fetch()) {

        $db->rollBack();

        error_response(
            'Email already registered',
            409
        );
    }

    // Create user

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $stmt = $db->prepare("
        INSERT INTO users
        (
            name,
            email,
            password,
            role,
            company,
            phone,
            country
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $name,
        $email,
        $hashedPassword,
        $role,
        $company,
        $phone,
        $country
    ]);

    $userId = $db->lastInsertId();

    // ========================================
    // Vendor Creation
    // ========================================

    if ($role === 'vendor') {

        $gstNumber = sanitize(
            $body['gst_number'] ?? ''
        );

        $category = sanitize(
            $body['category'] ?? 'General'
        );

        $address = sanitize(
            $body['address'] ?? ''
        );

        $stmt = $db->prepare("
            INSERT INTO vendors
            (
                user_id,
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
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active'
            )
        ");

        $stmt->execute([
            $userId,
            $company ?: $name,
            $name,
            $email,
            $phone,
            $gstNumber,
            $category,
            $address,
            $country
        ]);
    }

    $db->commit();

    log_activity(
        $userId,
        'REGISTER',
        'user',
        $userId,
        "New user registered: {$email} ({$role})"
    );

    success_response(
        'Registration successful',
        [
            'user_id' => $userId,
            'role' => $role
        ]
    );

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_response(
        'Registration failed',
        500
    );
}
