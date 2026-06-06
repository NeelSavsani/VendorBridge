<?php
require_once __DIR__ . '/config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Route map
$routes = [
    'POST /api/auth/login'            => 'auth/login',
    'POST /api/auth/register'         => 'auth/register',
    'GET /api/auth/me'                => 'auth/me',

    'GET /api/vendors'                => 'vendors/list',
    'POST /api/vendors'               => 'vendors/create',
    'GET /api/vendors/{id}'           => 'vendors/get',
    'PUT /api/vendors/{id}'           => 'vendors/update',

    'GET /api/rfqs'                   => 'rfqs/list',
    'POST /api/rfqs'                  => 'rfqs/create',
    'GET /api/rfqs/{id}'              => 'rfqs/get',
    'PUT /api/rfqs/{id}'              => 'rfqs/update',

    'GET /api/quotations'             => 'quotations/list',
    'POST /api/quotations'            => 'quotations/create',
    'GET /api/quotations/{id}'        => 'quotations/get',
    'GET /api/quotations/compare/{rfq_id}' => 'quotations/compare',

    'POST /api/approvals'             => 'approvals/create',
    'PUT /api/approvals/{id}'         => 'approvals/action',
    'GET /api/approvals'              => 'approvals/list',

    'GET /api/purchase-orders'        => 'purchase_orders/list',
    'POST /api/purchase-orders'       => 'purchase_orders/create',
    'GET /api/purchase-orders/{id}'   => 'purchase_orders/get',

    'GET /api/invoices'               => 'invoices/list',
    'POST /api/invoices'              => 'invoices/create',
    'GET /api/invoices/{id}'          => 'invoices/get',
    'GET /api/invoices/{id}/pdf'      => 'invoices/pdf',

    'GET /api/activity-logs'          => 'activity/list',
    'GET /api/dashboard'              => 'dashboard/stats',
    'GET /api/reports'                => 'reports/analytics',
    'GET /api/users'                  => 'users/list',
];

// Match route with params
$matched = false;
$params = [];

foreach ($routes as $pattern => $handler) {
    [$routeMethod, $routePath] = explode(' ', $pattern, 2);
    if ($routeMethod !== $method) continue;

    // Convert {param} to regex
    $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $routePath);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $matches)) {
        // Extract param names
        preg_match_all('/\{(\w+)\}/', $routePath, $paramNames);
        foreach ($paramNames[1] as $i => $name) {
            $params[$name] = $matches[$i + 1];
        }
        $_REQUEST['_params'] = $params;
        
        // Load handler
        $file = __DIR__ . '/api/controllers/' . $handler . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            json_response(['error' => 'Handler not found: ' . $handler], 500);
        }
        $matched = true;
        break;
    }
}

if (!$matched) {
    json_response(['error' => 'Route not found', 'uri' => $uri, 'method' => $method], 404);
}
