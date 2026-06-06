<?php

require_once __DIR__ . '/config.php';

// ========================================
// Input Helpers
// ========================================

function get_json_input()
{
    $input = file_get_contents('php://input');

    if (empty($input)) {
        return [];
    }

    $decoded = json_decode($input, true);

    return is_array($decoded)
        ? $decoded
        : [];
}

function sanitize($value)
{
    return trim(
        htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

// ========================================
// Validation Helpers
// ========================================

function is_valid_email($email)
{
    return filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) !== false;
}

function is_valid_phone($phone)
{
    return preg_match(
        '/^[0-9]{10,15}$/',
        $phone
    ) === 1;
}

function require_fields($data, $fields)
{
    foreach ($fields as $field) {

        if (
            !array_key_exists($field, $data) ||
            (
                is_string($data[$field]) &&
                trim($data[$field]) === ''
            )
        ) {
            error_response(
                "$field is required",
                400
            );
        }
    }
}

// ========================================
// Number Formatting
// ========================================

function format_currency($amount)
{
    return number_format(
        (float)$amount,
        2,
        '.',
        ''
    );
}

function calculate_gst(
    $amount,
    $rate = 18
) {
    return round(
        ($amount * $rate) / 100,
        2
    );
}

// ========================================
// Date Helpers
// ========================================

function current_datetime()
{
    return date('Y-m-d H:i:s');
}

function current_date()
{
    return date('Y-m-d');
}

// ========================================
// Reference Number Generators
// ========================================

function generate_rfq_number()
{
    return 'RFQ-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );
}

function generate_po_number()
{
    return 'PO-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );
}

function generate_invoice_number()
{
    return 'INV-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );
}

function generate_quotation_number()
{
    return 'QT-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );
}

// ========================================
// Pagination
// ========================================

function get_pagination()
{
    $page = max(
        1,
        (int)($_GET['page'] ?? 1)
    );

    $limit = min(
        100,
        max(
            1,
            (int)($_GET['limit'] ?? 10)
        )
    );

    $offset = ($page - 1) * $limit;

    return [
        'page'   => $page,
        'limit'  => $limit,
        'offset' => $offset
    ];
}

// ========================================
// File Upload Helper
// ========================================

function upload_file($file)
{
    if (
        !isset($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        return null;
    }

    $allowedExtensions = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'doc',
        'docx',
        'xls',
        'xlsx'
    ];

    $extension = strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );

    if (
        !in_array(
            $extension,
            $allowedExtensions
        )
    ) {
        return null;
    }

    $filename =
        uniqid('file_') .
        '.' .
        $extension;

    $destination =
        UPLOAD_PATH .
        $filename;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        return null;
    }

    return [
        'filename' => $filename,
        'path' => $destination,
        'original_name' => $file['name'],
        'size' => $file['size']
    ];
}

// ========================================
// API Success Response
// ========================================

function success_response(
    $message,
    $data = []
) {
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

// ========================================
// API Error Response
// ========================================

function error_response(
    $message,
    $code = 400
) {
    json_response([
        'success' => false,
        'error' => $message
    ], $code);
}
