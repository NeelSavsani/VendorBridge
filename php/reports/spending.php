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

    // ========================================
    // Date Filters
    // ========================================

    $fromDate =
        sanitize(
            $_GET['from_date']
            ?? date('Y-01-01')
        );

    $toDate =
        sanitize(
            $_GET['to_date']
            ?? date('Y-m-d')
        );

    $report = [];

    // ========================================
    // Total Spend
    // ========================================

    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_pos,
            COALESCE(
                SUM(total_amount),
                0
            ) AS total_spend
        FROM purchase_orders
        WHERE DATE(created_at)
        BETWEEN ? AND ?
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['summary'] =
        $stmt->fetch();

    // ========================================
    // Budget vs Actual
    // ========================================

    $stmt = $db->prepare("
        SELECT
            COALESCE(
                SUM(r.budget),
                0
            ) AS total_budget,

            COALESCE(
                SUM(po.total_amount),
                0
            ) AS actual_spend

        FROM purchase_orders po

        INNER JOIN rfqs r
            ON r.id = po.rfq_id

        WHERE DATE(po.created_at)
        BETWEEN ? AND ?
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $budgetData =
        $stmt->fetch();

    $budget =
        (float)$budgetData['total_budget'];

    $spend =
        (float)$budgetData['actual_spend'];

    $report['budget_analysis'] = [
        'budget' => $budget,
        'actual_spend' => $spend,
        'savings' => round(
            $budget - $spend,
            2
        ),
        'savings_percentage' =>
            $budget > 0
            ? round(
                (
                    ($budget - $spend)
                    / $budget
                ) * 100,
                2
            )
            : 0
    ];

    // ========================================
    // Spend By Category
    // ========================================

    $stmt = $db->prepare("
        SELECT
            r.category,
            COUNT(po.id) AS po_count,
            SUM(po.total_amount) AS spend

        FROM purchase_orders po

        INNER JOIN rfqs r
            ON r.id = po.rfq_id

        WHERE DATE(po.created_at)
        BETWEEN ? AND ?

        GROUP BY r.category

        ORDER BY spend DESC
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['spend_by_category'] =
        $stmt->fetchAll();

    // ========================================
    // Spend By Vendor
    // ========================================

    $stmt = $db->prepare("
        SELECT
            v.company_name,
            COUNT(po.id) AS total_orders,
            SUM(po.total_amount) AS spend

        FROM purchase_orders po

        INNER JOIN vendors v
            ON v.id = po.vendor_id

        WHERE DATE(po.created_at)
        BETWEEN ? AND ?

        GROUP BY v.id

        ORDER BY spend DESC

        LIMIT 10
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['top_vendors'] =
        $stmt->fetchAll();

    // ========================================
    // Monthly Trend
    // ========================================

    $stmt = $db->prepare("
        SELECT

            DATE_FORMAT(
                created_at,
                '%b %Y'
            ) AS month,

            SUM(total_amount) AS spend

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

    $report['monthly_trend'] =
        $stmt->fetchAll();

    // ========================================
    // Top Purchase Orders
    // ========================================

    $stmt = $db->prepare("
        SELECT
            po.po_number,
            po.total_amount,
            po.status,
            po.created_at,

            v.company_name,

            r.rfq_number

        FROM purchase_orders po

        INNER JOIN vendors v
            ON v.id = po.vendor_id

        INNER JOIN rfqs r
            ON r.id = po.rfq_id

        WHERE DATE(po.created_at)
        BETWEEN ? AND ?

        ORDER BY po.total_amount DESC

        LIMIT 10
    ");

    $stmt->execute([
        $fromDate,
        $toDate
    ]);

    $report['top_purchase_orders'] =
        $stmt->fetchAll();

    // ========================================
    // Response
    // ========================================

    success_response(
        'Spending report generated successfully',
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