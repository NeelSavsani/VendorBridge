<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {
    die('Invalid Invoice ID');
}

$db = getDB();

// ========================================
// Get Invoice
// ========================================

$stmt = $db->prepare("
    SELECT
        i.*,

        v.company_name,
        v.contact_person,
        v.email AS vendor_email,
        v.phone AS vendor_phone,
        v.gst_number,
        v.address AS vendor_address,

        po.po_number,
        po.delivery_date,
        po.tax_percent,

        r.rfq_number,
        r.title AS rfq_title,

        q.id AS quotation_id,
        q.quotation_number

    FROM invoices i

    INNER JOIN vendors v
        ON v.id = i.vendor_id

    INNER JOIN purchase_orders po
        ON po.id = i.po_id

    INNER JOIN rfqs r
        ON r.id = po.rfq_id

    INNER JOIN quotations q
        ON q.id = po.quotation_id

    WHERE i.id = ?
");

$stmt->execute([$id]);

$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found');
}

// ========================================
// Vendor Security
// ========================================

if (
    isset($auth['role']) &&
    $auth['role'] === 'vendor'
) {

    $stmt = $db->prepare("
        SELECT id
        FROM vendors
        WHERE user_id = ?
    ");

    $stmt->execute([$auth['id']]);

    $vendor = $stmt->fetch();

    if (
        !$vendor ||
        $vendor['id'] != $invoice['vendor_id']
    ) {
        http_response_code(403);
        exit('Access denied');
    }
}

// ========================================
// Invoice Items
// ========================================

$stmt = $db->prepare("
    SELECT
        item_name,
        quantity,
        unit_price,
        total_price

    FROM quotation_items

    WHERE quotation_id = ?

    ORDER BY id ASC
");

$stmt->execute([
    $invoice['quotation_id']
]);

$items = $stmt->fetchAll();

log_activity(
    $auth['id'],
    'INVOICE_PDF_VIEWED',
    'invoice',
    $id,
    'Invoice PDF viewed'
);

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #333;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .company {
            font-size: 28px;
            font-weight: bold;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 10px;
        }

        table th {
            background: #f5f5f5;
        }

        .right {
            text-align: right;
        }

        .summary {
            width: 350px;
            margin-left: auto;
            margin-top: 20px;
        }

        .summary td {
            border: none;
            padding: 6px;
        }

        .total {
            font-size: 18px;
            font-weight: bold;
        }

        .print-btn {
            margin-top: 30px;
        }

        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>

</head>

<body>

    <div class="header">

        <div>
            <div class="company">
                VendorBridge
            </div>

            <div>
                Procurement Management System
            </div>
        </div>

        <div>
            <div class="invoice-title">
                TAX INVOICE
            </div>

            <p>
                Invoice No:
                <?= htmlspecialchars($invoice['invoice_number']) ?>
            </p>

            <p>
                Date:
                <?= date('d-m-Y', strtotime($invoice['created_at'])) ?>
            </p>

            <p>
                Due Date:
                <?= date('d-m-Y', strtotime($invoice['due_date'])) ?>
            </p>
        </div>

    </div>

    <hr>

    <h3>Vendor Details</h3>

    <p>
        <strong><?= htmlspecialchars($invoice['company_name']) ?></strong><br>

        <?= nl2br(htmlspecialchars($invoice['vendor_address'])) ?><br>

        GST:
        <?= htmlspecialchars($invoice['gst_number']) ?><br>

        Email:
        <?= htmlspecialchars($invoice['vendor_email']) ?><br>

        Phone:
        <?= htmlspecialchars($invoice['vendor_phone']) ?>
    </p>

    <hr>

    <h3>Purchase Information</h3>

    <p>
        PO Number:
        <strong><?= htmlspecialchars($invoice['po_number']) ?></strong>
        <br>

        RFQ Number:
        <strong><?= htmlspecialchars($invoice['rfq_number']) ?></strong>
        <br>

        Quotation Number:
        <strong><?= htmlspecialchars($invoice['quotation_number']) ?></strong>
    </p>

    <table>

        <tr>
            <th>#</th>
            <th>Item</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Total</th>
        </tr>

        <?php foreach ($items as $index => $item): ?>

            <tr>
                <td><?= $index + 1 ?></td>

                <td><?= htmlspecialchars($item['item_name']) ?></td>

                <td><?= $item['quantity'] ?></td>

                <td class="right">
                    ₹<?= number_format($item['unit_price'], 2) ?>
                </td>

                <td class="right">
                    ₹<?= number_format($item['total_price'], 2) ?>
                </td>
            </tr>

        <?php endforeach; ?>

    </table>

    <table class="summary">

        <tr>
            <td>Subtotal</td>
            <td class="right">
                ₹<?= number_format($invoice['subtotal'], 2) ?>
            </td>
        </tr>

        <tr>
            <td>CGST</td>
            <td class="right">
                ₹<?= number_format($invoice['cgst'], 2) ?>
            </td>
        </tr>

        <tr>
            <td>SGST</td>
            <td class="right">
                ₹<?= number_format($invoice['sgst'], 2) ?>
            </td>
        </tr>

        <tr>
            <td>IGST</td>
            <td class="right">
                ₹<?= number_format($invoice['igst'], 2) ?>
            </td>
        </tr>

        <tr class="total">
            <td>Grand Total</td>
            <td class="right">
                ₹<?= number_format($invoice['total_amount'], 2) ?>
            </td>
        </tr>

    </table>

    <div style="margin-top:50px;">
        <strong>Status:</strong>
        <?= strtoupper($invoice['status']) ?>
    </div>

    <div class="print-btn">
        <button onclick="window.print()">
            Print / Save PDF
        </button>
    </div>

</body>

</html>