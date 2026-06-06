<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'manager',
    'procurement_officer'
]);

$db = getDB();

try {

    $stats = [];

    // ========================================
    // User Statistics
    // ========================================

    $stats['total_users'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM users
        ")->fetchColumn();

    $stats['total_vendors'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM vendors
        ")->fetchColumn();

    $stats['active_vendors'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM vendors
            WHERE status = 'active'
        ")->fetchColumn();

    // ========================================
    // RFQ Statistics
    // ========================================

    $stats['total_rfqs'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM rfqs
        ")->fetchColumn();

    $stats['open_rfqs'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM rfqs
            WHERE status = 'open'
        ")->fetchColumn();

    $stats['awarded_rfqs'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM rfqs
            WHERE status = 'awarded'
        ")->fetchColumn();

    // ========================================
    // Quotation Statistics
    // ========================================

    $stats['total_quotations'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM quotations
        ")->fetchColumn();

    $stats['approved_quotations'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM quotations
            WHERE status = 'approved'
        ")->fetchColumn();

    $stats['pending_quotations'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM quotations
            WHERE status IN
            ('submitted','under_review')
        ")->fetchColumn();

    // ========================================
    // Approval Statistics
    // ========================================

    $stats['pending_approvals'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM approvals
            WHERE status = 'pending'
        ")->fetchColumn();

    $stats['approved_approvals'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM approvals
            WHERE status = 'approved'
        ")->fetchColumn();

    $stats['rejected_approvals'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM approvals
            WHERE status = 'rejected'
        ")->fetchColumn();

    // ========================================
    // Purchase Order Statistics
    // ========================================

    $stats['total_purchase_orders'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM purchase_orders
        ")->fetchColumn();

    $stats['pending_purchase_orders'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM purchase_orders
            WHERE status = 'pending'
        ")->fetchColumn();

    $stats['completed_purchase_orders'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM purchase_orders
            WHERE status = 'completed'
        ")->fetchColumn();

    // ========================================
    // Invoice Statistics
    // ========================================

    $stats['total_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
        ")->fetchColumn();

    $stats['paid_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
            WHERE status = 'paid'
        ")->fetchColumn();

    $stats['pending_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
            WHERE status IN
            ('draft','sent')
        ")->fetchColumn();

    $stats['overdue_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
            WHERE status = 'overdue'
        ")->fetchColumn();

    // ========================================
    // Financial Statistics
    // ========================================

    $stats['total_spend'] =
        (float)$db->query("
            SELECT COALESCE(
                SUM(total_amount),
                0
            )
            FROM purchase_orders
        ")->fetchColumn();

    $stats['total_invoice_amount'] =
        (float)$db->query("
            SELECT COALESCE(
                SUM(total_amount),
                0
            )
            FROM invoices
        ")->fetchColumn();

    // ========================================
    // Recent Activity
    // ========================================

    $stmt = $db->query("
        SELECT
            action,
            entity_type,
            description,
            created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $stats['recent_activity'] =
        $stmt->fetchAll();

    // ========================================
    // Response
    // ========================================

    success_response(
        'Dashboard statistics retrieved successfully',
        $stats
    );

} catch (Exception $e) {

    error_response(
        $e->getMessage(),
        500
    );
}