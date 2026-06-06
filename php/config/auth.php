<?php

require_once __DIR__ . '/config.php';

// ========================================
// JWT Helpers
// ========================================

function base64url_encode($data)
{
    return rtrim(
        strtr(base64_encode($data), '+/', '-_'),
        '='
    );
}

function base64url_decode($data)
{
    $remainder = strlen($data) % 4;

    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(
        strtr($data, '-_', '+/')
    );
}

function jwt_encode($payload)
{
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    $payload['iat'] = time();
    $payload['exp'] = time() + (86400 * 7); // 7 days

    $headerEncoded = base64url_encode(
        json_encode($header)
    );

    $payloadEncoded = base64url_encode(
        json_encode($payload)
    );

    $signature = hash_hmac(
        'sha256',
        $headerEncoded . "." . $payloadEncoded,
        JWT_SECRET,
        true
    );

    $signatureEncoded = base64url_encode(
        $signature
    );

    return $headerEncoded .
        "." .
        $payloadEncoded .
        "." .
        $signatureEncoded;
}

function jwt_decode($token)
{
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;

    $expected = base64url_encode(
        hash_hmac(
            'sha256',
            $header . "." . $payload,
            JWT_SECRET,
            true
        )
    );

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $decodedPayload = json_decode(
        base64url_decode($payload),
        true
    );

    if (!$decodedPayload) {
        return null;
    }

    if (
        isset($decodedPayload['exp']) &&
        $decodedPayload['exp'] < time()
    ) {
        return null;
    }

    return $decodedPayload;
}

// ========================================
// Current Authenticated User
// ========================================

function get_auth_user()
{
    $headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

    $auth =
        $headers['Authorization']
        ?? $headers['authorization']
        ?? '';

    if (!$auth) {
        return null;
    }

    if (!str_starts_with($auth, 'Bearer ')) {
        return null;
    }

    $token = substr($auth, 7);

    return jwt_decode($token);
}

// ========================================
// Require Authentication
// ========================================

function require_auth($roles = [])
{
    $user = get_auth_user();

    if (!$user) {
        json_response([
            'success' => false,
            'error' => 'Unauthorized'
        ], 401);
    }

    if (
        !empty($roles) &&
        (
            !isset($user['role']) ||
            !in_array($user['role'], $roles)
        )
    ) {
        json_response([
            'success' => false,
            'error' => 'Forbidden'
        ], 403);
    }

    return $user;
}

// ========================================
// Optional Helper Functions
// ========================================

function is_admin()
{
    $user = get_auth_user();

    return $user &&
        isset($user['role']) &&
        $user['role'] === 'admin';
}

function is_vendor()
{
    $user = get_auth_user();

    return $user &&
        isset($user['role']) &&
        $user['role'] === 'vendor';
}

function is_procurement_officer()
{
    $user = get_auth_user();

    return $user &&
        isset($user['role']) &&
        $user['role'] === 'procurement_officer';
}

function is_manager()
{
    $user = get_auth_user();

    return $user &&
        isset($user['role']) &&
        $user['role'] === 'manager';
}
