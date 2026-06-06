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

    $report = [];

    // ========================================
    // Vendor Summary
    // ========================================

    $stmt = $db->query("
        SELECT
            COUNT(*) AS total_vendors,

            SUM(
                CASE
                    WHEN status = 'active'
                    THEN 1
                    ELSE 0
                END
            ) AS active_vendors,

            SUM(
                CASE
                    WHEN status <> 'active'
                    THEN 1
                    ELSE 0
                END
            ) AS inactive_vendors

        FROM vendors
    ");

    $report['summary'] =
        $stmt->fetch();

    // ========================================
    // Vendor Performance
    // ========================================

    $stmt = $db->query("
        SELECT

            v.id,
            v.company_name,
            v.contact_person,
            v.email,
            v.phone,
            v.category,
            v.rating,
            v.status,

            COUNT(
                DISTINCT rv.rfq_id
            ) AS rfqs_received,

            COUNT(
                DISTINCT q.id
            ) AS quotations_submitted,

            COUNT(
                DISTINCT po.id
            ) AS purchase_orders,

            COALESCE(
                SUM(po.total_amount),
                0
            ) AS total_business

        FROM vendors v

        LEFT JOIN rfq_vendors rv
            ON rv.vendor_id = v.id

        LEFT JOIN quotations q
            ON q.vendor_id = v.id

        LEFT JOIN purchase_orders po
            ON po.vendor_id = v.id

        GROUP BY v.id

        ORDER BY total_business DESC
    ");

    $report['vendor_performance'] =
        $stmt->fetchAll();

    // ========================================
    // Top Vendors
    // ========================================

    $stmt = $db->query("
        SELECT

            v.company_name,

            COUNT(
                DISTINCT po.id
            ) AS total_pos,

            COALESCE(
                SUM(po.total_amount),
                0
            ) AS business_value,

            v.rating

        FROM vendors v

        LEFT JOIN purchase_orders po
            ON po.vendor_id = v.id

        GROUP BY v.id

        ORDER BY business_value DESC

        LIMIT 10
    ");

    $report['top_vendors'] =
        $stmt->fetchAll();

    // ========================================
    // Vendor Category Distribution
    // ========================================

    $stmt = $db->query("
        SELECT
            category,
            COUNT(*) AS total

        FROM vendors

        GROUP BY category

        ORDER BY total DESC
    ");

    $report['vendor_categories'] =
        $stmt->fetchAll();

    // ========================================
    // Average Vendor Rating
    // ========================================

    $stmt = $db->query("
        SELECT
            ROUND(
                AVG(rating),
                2
            ) AS average_rating

        FROM vendors

        WHERE rating IS NOT NULL
    ");

    $report['average_rating'] =
        $stmt->fetchColumn();

    // ========================================
    // Vendors By Status
    // ========================================

    $stmt = $db->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM vendors

        GROUP BY status
    ");

    $report['vendor_status_distribution'] =
        $stmt->fetchAll();

    // ========================================
    // Response
    // ========================================

    success_response(
        'Vendor report generated successfully',
        $report
    );

} catch (Exception $e) {

    error_response(
        $e->getMessage(),
        500
    );
}