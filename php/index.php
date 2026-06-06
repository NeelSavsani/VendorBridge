<?php

require_once __DIR__ . '/config/config.php';

// ========================================
// Error Handling
// ========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ========================================
// Request Info
// ========================================

$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

// ========================================
// Localhost Base Path Fix
// ========================================

$basePath = '/vendorbridge/php/index.php';

if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

$uri = rtrim($uri, '/');

if ($uri === '') {
    $uri = '/';
}

// ========================================
// API Home
// ========================================

if (
    $uri === '/api'
    || $uri === '/api/'
    || $uri === '/'
) {

    json_response([
        'success' => true,
        'application' => APP_NAME,
        'version' => APP_VERSION,
        'message' => 'VendorBridge API Running'
    ]);
}

// ========================================
// Routes
// ========================================

$routes = [

    // AUTH

    'POST /api/auth/login' => 'auth/login',
    'POST /api/auth/register' => 'auth/register',
    'POST /api/auth/logout' => 'auth/logout',
    'GET /api/auth/me' => 'auth/me',

    // VENDOR

    'GET /api/vendors' => 'vendor/list',
    'POST /api/vendors' => 'vendor/create',
    'GET /api/vendors/{id}' => 'vendor/get',
    'PUT /api/vendors/{id}' => 'vendor/update',
    'DELETE /api/vendors/{id}' => 'vendor/delete',

    // RFQ

    'GET /api/rfqs' => 'rfq/list',
    'POST /api/rfqs' => 'rfq/create',
    'GET /api/rfqs/{id}' => 'rfq/get',
    'PUT /api/rfqs/{id}' => 'rfq/update',

    'POST /api/rfqs/{id}/assign-vendor'
        => 'rfq/assign_vendor',

    'POST /api/rfqs/{id}/attachment'
        => 'rfq/upload_attachment',

    // QUOTATION

    'GET /api/quotations' => 'quotation/list',
    'POST /api/quotations' => 'quotation/create',
    'POST /api/quotations/submit'
        => 'quotation/submit',

    'GET /api/quotations/{id}'
        => 'quotation/get',

    'GET /api/quotations/compare/{rfq_id}'
        => 'quotation/compare',

    // APPROVAL

    'POST /api/approvals'
        => 'approval/create',

    'GET /api/approvals'
        => 'approval/list',

    'PUT /api/approvals/{id}/approve'
        => 'approval/approve',

    'PUT /api/approvals/{id}/reject'
        => 'approval/reject',

    // PURCHASE ORDER

    'GET /api/purchase-orders'
        => 'purchase_order/list',

    'POST /api/purchase-orders'
        => 'purchase_order/create',

    'GET /api/purchase-orders/{id}'
        => 'purchase_order/get',

    'GET /api/purchase-orders/{id}/generate'
        => 'purchase_order/generate',

    // INVOICE

    'GET /api/invoices'
        => 'invoice/list',

    'POST /api/invoices'
        => 'invoice/create',

    'GET /api/invoices/{id}'
        => 'invoice/get',

    'GET /api/invoices/{id}/pdf'
        => 'invoice/pdf',

    'POST /api/invoices/{id}/email'
        => 'invoice/email',

    // NOTIFICATIONS

    'POST /api/notifications'
        => 'notifications/create',

    'GET /api/notifications'
        => 'notifications/list',

    'PUT /api/notifications/{id}/read'
        => 'notifications/mark_read',

    // LOGS

    'GET /api/logs'
        => 'logs/list',

    'GET /api/logs/stats'
        => 'logs/stats',

    'GET /api/logs/analytics'
        => 'logs/analytics',

    // REPORTS

    'GET /api/reports/spending'
        => 'reports/spending',

    'GET /api/reports/vendors'
        => 'reports/vendors',

    'GET /api/reports/procurement'
        => 'reports/procurement',
];

// ========================================
// Route Matching
// ========================================

$matched = false;

foreach ($routes as $pattern => $handler) {

    [$routeMethod, $routePath] =
        explode(' ', $pattern, 2);

    if ($routeMethod !== $method) {
        continue;
    }

    $regex = preg_replace(
        '/\{(\w+)\}/',
        '([^\/]+)',
        $routePath
    );

    $regex = '#^' . $regex . '$#';

    if (
        preg_match(
            $regex,
            $uri,
            $matches
        )
    ) {

        $params = [];

        preg_match_all(
            '/\{(\w+)\}/',
            $routePath,
            $paramNames
        );

        foreach (
            $paramNames[1]
            as $index => $name
        ) {

            $params[$name] =
                $matches[$index + 1];
        }

        $_REQUEST['_params'] =
            $params;

        $file =
            __DIR__ .
            '/' .
            $handler .
            '.php';

        if (!file_exists($file)) {

            json_response([
                'success' => false,
                'error' => 'Handler not found',
                'handler' => $handler
            ], 500);
        }

        try {

            require $file;

        } catch (Throwable $e) {

            json_response([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }

        $matched = true;
        exit;
    }
}

// ========================================
// Route Not Found
// ========================================

json_response([
    'success' => false,
    'error' => 'Route not found',
    'method' => $method,
    'uri' => $uri
], 404);