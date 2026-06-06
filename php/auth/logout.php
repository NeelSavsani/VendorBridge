<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication Required
// ========================================

$user = require_auth();

// ========================================
// Log Activity
// ========================================

log_activity(
    $user['id'],
    'LOGOUT',
    'user',
    $user['id'],
    'User logged out'
);

// ========================================
// Response
// ========================================

success_response(
    'Logout successful',
    [
        'logout' => true
    ]
);
