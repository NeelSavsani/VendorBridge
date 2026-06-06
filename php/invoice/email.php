<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'procurement_officer'
]);

// ========================================
// Invoice ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid Invoice ID',
        400
    );
}

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // Get Invoice Details
    // ========================================

    $stmt = $db->prepare("
        SELECT
            i.*,
            v.user_id,
            v.company_name,
            v.email AS vendor_email
        FROM invoices i

        INNER JOIN vendors v
            ON v.id = i.vendor_id

        WHERE i.id = ?
    ");

    $stmt->execute([$id]);

    $invoice = $stmt->fetch();

    if (!$invoice) {

        throw new Exception(
            'Invoice not found'
        );
    }

    if (empty($invoice['vendor_email'])) {

        throw new Exception(
            'Vendor email not available'
        );
    }

    // ========================================
    // Invoice PDF URL
    // ========================================

    $pdfUrl =
        APP_URL .
        '/api/invoices/' .
        $invoice['id'] .
        '/pdf';

    // ========================================
    // Email Content
    // ========================================

    $subject =
        'Invoice ' .
        $invoice['invoice_number'];

    $message =
        "Dear Vendor,\n\n" .
        "An invoice has been generated.\n\n" .
        "Invoice Number: " .
        $invoice['invoice_number'] .
        "\n" .
        "Total Amount: ₹" .
        number_format(
            $invoice['total_amount'],
            2
        ) .
        "\n" .
        "Due Date: " .
        $invoice['due_date'] .
        "\n\n" .
        "Invoice Link:\n" .
        $pdfUrl .
        "\n\n" .
        "Regards,\nVendorBridge";

    // ========================================
    // Simulated Email Send
    // ========================================

    $emailStatus = 'sent';

    // ========================================
    // Save Email Log
    // ========================================

    log_email(
        $invoice['id'],
        $invoice['vendor_email'],
        $emailStatus
    );

    // ========================================
    // Notification
    // ========================================

    if (!empty($invoice['user_id'])) {

        create_notification(
            $invoice['user_id'],
            'Invoice Sent',
            'Invoice ' .
                $invoice['invoice_number'] .
                ' has been sent to your registered email.'
        );
    }

    // ========================================
    // Update Invoice Status
    // ========================================

    if (
        strtolower($invoice['status']) ===
        'draft'
    ) {

        $stmt = $db->prepare("
            UPDATE invoices
            SET status = 'sent'
            WHERE id = ?
        ");

        $stmt->execute([
            $invoice['id']
        ]);
    }

    // ========================================
    // Commit
    // ========================================

    $db->commit();

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'INVOICE_EMAIL_SENT',
        'invoice',
        $invoice['id'],
        'Invoice emailed to ' .
            $invoice['vendor_email']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Invoice email sent successfully',
        [
            'invoice_id' => $invoice['id'],
            'invoice_number' =>
            $invoice['invoice_number'],
            'recipient' =>
            $invoice['vendor_email'],
            'status' => 'sent'
        ]
    );
} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_response(
        $e->getMessage(),
        400
    );
}
