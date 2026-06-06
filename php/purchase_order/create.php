<?php
$auth = require_auth(['procurement_officer', 'admin']);
$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['quotation_id'])) json_response(['error' => 'quotation_id required'], 400);

$db = getDB();
$stmt = $db->prepare("SELECT q.*, v.id as vid FROM quotations q JOIN vendors v ON v.id=q.vendor_id WHERE q.id=? AND q.status='selected'");
$stmt->execute([$body['quotation_id']]);
$q = $stmt->fetch();
if (!$q) json_response(['error' => 'Quotation not found or not approved'], 404);

// Check PO doesn't exist already
$stmt = $db->prepare("SELECT id FROM purchase_orders WHERE quotation_id=?");
$stmt->execute([$body['quotation_id']]);
if ($stmt->fetch()) json_response(['error' => 'PO already exists for this quotation'], 409);

$year = date('Y');
$stmt = $db->query("SELECT COUNT(*) as c FROM purchase_orders WHERE YEAR(created_at)=$year");
$count = $stmt->fetch()['c'] + 1;
$po_number = "PO-$year-" . str_pad($count, 3, '0', STR_PAD_LEFT);

$subtotal = $q['total_amount'];
$tax_pct = $body['tax_percent'] ?? 18.00;
$tax_amt = round($subtotal * $tax_pct / 100, 2);
$total = $subtotal + $tax_amt;

$delivery_days = $q['delivery_days'] ?? 14;
$delivery_date = date('Y-m-d', strtotime("+$delivery_days days"));

$stmt = $db->prepare("INSERT INTO purchase_orders (po_number, quotation_id, rfq_id, vendor_id, subtotal, tax_percent, tax_amount, total_amount, delivery_date, status, created_by)
    VALUES (?,?,?,?,?,?,?,?,?,'pending',?)");
$stmt->execute([$po_number, $body['quotation_id'], $q['rfq_id'], $q['vendor_id'], $subtotal, $tax_pct, $tax_amt, $total, $delivery_date, $auth['id']]);
$po_id = $db->lastInsertId();

log_activity($auth['id'], 'PO_GENERATED', 'purchase_order', $po_id, "PO $po_number generated from quotation #{$body['quotation_id']}");
json_response(['id' => $po_id, 'po_number' => $po_number, 'total_amount' => $total, 'message' => 'Purchase Order created'], 201);
