<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'manager',
    'procurement_officer'
]);

$db = getDB();

$month = sanitize(
    $_GET['month'] ?? date('Y-m')
);

try {

    $analytics = [];

    // ========================================
    // Monthly Spend
    // ========================================

    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_amount),0) AS total_spend,
            COUNT(*) AS purchase_orders
        FROM purchase_orders
        WHERE DATE_FORMAT(created_at,'%Y-%m') = ?
    ");

    $stmt->execute([
        $month
    ]);

    $spendData = $stmt->fetch();

    $analytics['total_spend'] =
        (float)$spendData['total_spend'];

    $analytics['purchase_orders'] =
        (int)$spendData['purchase_orders'];

    // ========================================
    // Active Vendors
    // ========================================

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT vendor_id)
        AS active_vendors

        FROM purchase_orders

        WHERE DATE_FORMAT(created_at,'%Y-%m') = ?
    ");

    $stmt->execute([
        $month
    ]);

    $analytics['active_vendors'] =
        (int)$stmt->fetch()['active_vendors'];

    // ========================================
    // Budget vs Spend
    // ========================================

    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(r.budget),0) AS budget,
            COALESCE(SUM(po.total_amount),0) AS spend

        FROM purchase_orders po

        INNER JOIN rfqs r
            ON r.id = po.rfq_id

        WHERE DATE_FORMAT(
            po.created_at,
            '%Y-%m'
        ) = ?
    ");

    $stmt->execute([
        $month
    ]);

    $budgetData = $stmt->fetch();

    $budget =
        (float)$budgetData['budget'];

    $spend =
        (float)$budgetData['spend'];

    $analytics['budget'] =
        $budget;

    $analytics['actual_spend'] =
        $spend;

    $analytics['savings'] =
        round(
            $budget - $spend,
            2
        );

    $analytics['savings_percentage'] =
        $budget > 0
        ? round(
            (($budget - $spend) / $budget) * 100,
            2
        )
        : 0;

    // ========================================
    // System Counts
    // ========================================

    $analytics['total_vendors'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM vendors
        ")->fetchColumn();

    $analytics['total_rfqs'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM rfqs
        ")->fetchColumn();

    $analytics['total_quotations'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM quotations
        ")->fetchColumn();

    $analytics['total_purchase_orders'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM purchase_orders
        ")->fetchColumn();

    $analytics['total_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
        ")->fetchColumn();

    // ========================================
    // Spend By Category
    // ========================================

    $stmt = $db->prepare("
        SELECT
            r.category,
            SUM(po.subtotal) AS total

        FROM purchase_orders po

        INNER JOIN rfqs r
            ON r.id = po.rfq_id

        WHERE DATE_FORMAT(
            po.created_at,
            '%Y-%m'
        ) = ?

        GROUP BY r.category

        ORDER BY total DESC
    ");

    $stmt->execute([
        $month
    ]);

    $analytics['spend_by_category'] =
        $stmt->fetchAll();

    // ========================================
    // Top Vendors
    // ========================================

    $stmt = $db->prepare("
        SELECT
            v.company_name,
            COUNT(po.id) AS po_count,
            SUM(po.total_amount) AS spend

        FROM purchase_orders po

        INNER JOIN vendors v
            ON v.id = po.vendor_id

        WHERE DATE_FORMAT(
            po.created_at,
            '%Y-%m'
        ) = ?

        GROUP BY v.id

        ORDER BY spend DESC

        LIMIT 5
    ");

    $stmt->execute([
        $month
    ]);

    $analytics['top_vendors'] =
        $stmt->fetchAll();

    // ========================================
    // Monthly Trend
    // ========================================

    $stmt = $db->query("
        SELECT
            DATE_FORMAT(
                created_at,
                '%b %Y'
            ) AS month,

            SUM(total_amount) AS total

        FROM purchase_orders

        WHERE created_at >=
        DATE_SUB(
            NOW(),
            INTERVAL 6 MONTH
        )

        GROUP BY
            YEAR(created_at),
            MONTH(created_at)

        ORDER BY
            YEAR(created_at),
            MONTH(created_at)
    ");

    $analytics['monthly_trend'] =
        $stmt->fetchAll();

    // ========================================
    // Invoice Statistics
    // ========================================

    $analytics['paid_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
            WHERE status='paid'
        ")->fetchColumn();

    $analytics['pending_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
            WHERE status IN
            ('draft','sent')
        ")->fetchColumn();

    $analytics['overdue_invoices'] =
        (int)$db->query("
            SELECT COUNT(*)
            FROM invoices
            WHERE status='overdue'
        ")->fetchColumn();

    // ========================================
    // Response
    // ========================================

    success_response(
        'Analytics retrieved successfully',
        $analytics
    );

} catch (Exception $e) {

    error_response(
        $e->getMessage(),
        500
    );
}