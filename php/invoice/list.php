<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

$auth = require_auth();

$db = getDB();

$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = ['1=1'];
$binds = [];

if ($status) {
    $where[] = "i.status = ?";
    $binds[] = $status;
}

if ($search) {

    $where[] = "(
        i.invoice_number LIKE ?
        OR po.po_number LIKE ?
        OR v.company_name LIKE ?
    )";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

$sql = "
SELECT
    i.id,
    i.invoice_number,
    i.po_id,
    i.vendor_id,
    i.subtotal,
    i.cgst,
    i.sgst,
    i.igst,
    i.total_amount,
    i.due_date,
    i.status,
    i.created_at,

    po.po_number,

    v.company_name,
    v.company_name AS vendor_name,
    v.email AS vendor_email,
    v.phone AS vendor_phone,
    v.address AS vendor_address,
    v.gst_number,

    r.rfq_number,
    r.title AS rfq_title

FROM invoices i

LEFT JOIN purchase_orders po
    ON po.id = i.po_id

LEFT JOIN vendors v
    ON v.id = i.vendor_id

LEFT JOIN rfqs r
    ON r.id = po.rfq_id

WHERE " . implode(' AND ', $where) . "

ORDER BY i.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($binds);

$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summary = [
    'total_records' => count($invoices),
    'draft' => 0,
    'sent' => 0,
    'paid' => 0,
    'overdue' => 0,
    'total_amount' => 0
];

foreach ($invoices as $invoice) {

    $statusValue = strtolower($invoice['status']);

    if (isset($summary[$statusValue])) {
        $summary[$statusValue]++;
    }

    $summary['total_amount'] += (float)$invoice['total_amount'];
}

success_response(
    'Invoices retrieved successfully',
    [
        'invoices' => $invoices,
        'summary' => $summary
    ]
);