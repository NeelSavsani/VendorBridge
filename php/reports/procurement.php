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

    $fromDate = sanitize(
        $_GET['from_date']
        ?? date('Y-01-01')
    );

    $toDate = sanitize(
        $_GET['to_date']
        ?? date('Y-m-d')
    );

    $report = [];

    // ========================================
    // Procurement Summary
    // ========================================

    $report['summary'] = [

        'total_rfqs' =>
            (int)$db->query("
                SELECT COUNT(*)
                FROM rfqs
            ")->fetchColumn(),

        'total_quotations' =>
            (int)$db->query("
                SELECT COUNT(*)
                FROM quotations
            ")->fetchColumn(),

        'total_purchase_orders' =>
            (int)$db->query("
                SELECT COUNT(*)
                FROM purchase_orders
            ")->fetchColumn(),

        'total_invoices' =>
            (int)$db->query("
                SELECT COUNT(*)
                FROM invoices
            ")->fetchColumn()
    ];

    // ========================================
    // RFQ Status Distribution
    // ========================================

    $stmt = $db->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM rfqs

        GROUP BY status
    ");

    $report['rfq_status'] =
        $stmt->fetchAll();

    // ========================================
    // Quotation Status Distribution
    // ========================================

    $stmt = $db->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM quotations

        GROUP BY status
    ");

    $report['quotation_status'] =
        $stmt->fetchAll();

    // ========================================
    // Approval Status
    // ========================================

    $stmt = $db->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM approvals

        GROUP BY status
    ");

    $report['approval_status'] =
        $stmt->fetchAll();

    // ========================================
    // Purchase Order Status
    // ========================================

    $stmt = $db->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM purchase_orders

        GROUP BY status
    ");

    $report['purchase_order_status'] =
        $stmt->fetchAll();

    // ========================================
    // Invoice Status
    // ========================================

    $stmt = $db->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM invoices

        GROUP BY status
    ");

    $report['invoice_status'] =
        $stmt->fetchAll();

    // ========================================
    // Average Quotations per RFQ
    // ========================================

    $stmt = $db->query("
        SELECT
            ROUND(
                AVG(q_count),
                2
            ) AS avg_quotes

        FROM
        (
            SELECT
                rfq_id,
                COUNT(*) AS q_count

            FROM quotations

            GROUP BY rfq_id
        ) t
    ");

    $report['average_quotes_per_rfq'] =
        (float)$stmt->fetchColumn();

    // ========================================
    // Procurement Cycle Time
    // ========================================

    $stmt = $db->query("
        SELECT
            ROUND(
                AVG(
                    DATEDIFF(
                        po.created_at,
                        rf.created_at
                    )
                ),
                2
            ) AS avg_days

        FROM purchase_orders po

        INNER JOIN rfqs rf
            ON rf.id = po.rfq_id
    ");

    $report['average_procurement_cycle_days'] =
        (float)$stmt->fetchColumn();

    // ========================================
    // Monthly RFQ Trend
    // ========================================

    $stmt = $db->prepare("
        SELECT

            DATE_FORMAT(
                created_at,
                '%b %Y'
            ) AS month,

            COUNT(*) AS total

        FROM rfqs

        WHERE DATE(created_at)
        BETWEEN ? AND ?

        GROUP BY
            YEAR(created_at),
            MONTH(created_at)

        ORDER BY
            YEAR(created_at),
            MONTH(created_at)
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['rfq_trend'] =
        $stmt->fetchAll();

    // ========================================
    // Monthly PO Trend
    // ========================================

    $stmt = $db->prepare("
        SELECT

            DATE_FORMAT(
                created_at,
                '%b %Y'
            ) AS month,

            COUNT(*) AS total

        FROM purchase_orders

        WHERE DATE(created_at)
        BETWEEN ? AND ?

        GROUP BY
            YEAR(created_at),
            MONTH(created_at)

        ORDER BY
            YEAR(created_at),
            MONTH(created_at)
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['purchase_order_trend'] =
        $stmt->fetchAll();

    // ========================================
    // Top Procurement Categories
    // ========================================

    $stmt = $db->prepare("
        SELECT

            category,

            COUNT(*) AS total_rfqs

        FROM rfqs

        WHERE DATE(created_at)
        BETWEEN ? AND ?

        GROUP BY category

        ORDER BY total_rfqs DESC

        LIMIT 10
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['top_categories'] =
        $stmt->fetchAll();

    // ========================================
    // Top Procurement Officers
    // ========================================

    $stmt = $db->query("
        SELECT

            u.name,

            COUNT(r.id) AS total_rfqs

        FROM users u

        INNER JOIN rfqs r
            ON r.created_by = u.id

        WHERE u.role =
            'procurement_officer'

        GROUP BY u.id

        ORDER BY total_rfqs DESC

        LIMIT 10
    ");

    $report['top_procurement_officers'] =
        $stmt->fetchAll();

    // ========================================
    // Recent Procurement Activities
    // ========================================

    $stmt = $db->query("
        SELECT
            action,
            entity_type,
            description,
            created_at

        FROM activity_logs

        WHERE entity_type IN
        (
            'rfq',
            'quotation',
            'approval',
            'purchase_order',
            'invoice'
        )

        ORDER BY created_at DESC

        LIMIT 20
    ");

    $report['recent_activities'] =
        $stmt->fetchAll();

    // ========================================
    // Response
    // ========================================

    success_response(
        'Procurement report generated successfully',
        [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'report' => $report
        ]
    );

} catch (Exception $e) {

    error_response(
        $e->getMessage(),
        500
    );
}