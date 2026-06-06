<?php

// ========================================
// VendorBridge Configuration
// ========================================

date_default_timezone_set('Asia/Kolkata');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vendorbridge');

// Application Configuration
define('APP_NAME', 'VendorBridge');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');

// Security
define(
    'JWT_SECRET',
    'vb_9F#kL82@xQp7!Vn$4ZrW1mJc6YtE3sA'
);

// File Uploads
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');

if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// ========================================
// Database Connection
// ========================================

function getDB()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {

            http_response_code(500);

            die(json_encode([
                'success' => false,
                'error' => 'Database connection failed'
            ]));
        }
    }

    return $pdo;
}

// ========================================
// JSON Response Helper
// ========================================

function json_response($data, $code = 200)
{
    http_response_code($code);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    exit;
}

// ========================================
// Activity Logger
// ========================================

function log_activity(
    $user_id,
    $action,
    $entity_type = null,
    $entity_id = null,
    $description = null
) {
    try {

        $db = getDB();

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO activity_logs
            (
                user_id,
                action,
                entity_type,
                entity_id,
                description,
                ip_address
            )
            VALUES (?,?,?,?,?,?)
        ");

        $stmt->execute([
            $user_id,
            $action,
            $entity_type,
            $entity_id,
            $description,
            $ip
        ]);
    } catch (Exception $e) {
        // Silent fail
    }
}

// ========================================
// Notifications
// ========================================

function create_notification(
    $user_id,
    $title,
    $message
) {
    try {

        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO notifications
            (
                user_id,
                title,
                message
            )
            VALUES (?,?,?)
        ");

        $stmt->execute([
            $user_id,
            $title,
            $message
        ]);
    } catch (Exception $e) {
        // Silent fail
    }
}

// ========================================
// Email Logs
// ========================================

function log_email(
    $invoice_id,
    $recipient_email,
    $status
) {
    try {

        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO email_logs
            (
                invoice_id,
                recipient_email,
                status
            )
            VALUES (?,?,?)
        ");

        $stmt->execute([
            $invoice_id,
            $recipient_email,
            $status
        ]);
    } catch (Exception $e) {
        // Silent fail
    }
}

// ========================================
// CORS
// ========================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
