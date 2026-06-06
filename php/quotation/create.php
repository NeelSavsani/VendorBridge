<?php
$auth = require_auth(['vendor', 'procurement_officer', 'admin']);
$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['rfq_id']) || empty($body['total_amount'])) {
    json_response(['error' => 'rfq_id and total_amount required'], 400);
}

$db = getDB();

// Get vendor id
if ($auth['role'] === 'vendor') {
    $stmt = $db->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$auth['id']]);
    $v = $stmt->fetch();
    if (!$v) json_response(['error' => 'Vendor profile not found'], 404);
    $vendor_id = $v['id'];
} else {
    $vendor_id = $body['vendor_id'] ?? null;
    if (!$vendor_id) json_response(['error' => 'vendor_id required'], 400);
}

// Generate quotation number
$year = date('Y');
$stmt = $db->query("SELECT COUNT(*) as c FROM quotations WHERE YEAR(submitted_at) = $year");
$count = $stmt->fetch()['c'] + 1;
$q_number = "QT-$year-" . str_pad($count, 3, '0', STR_PAD_LEFT);

$stmt = $db->prepare("INSERT INTO quotations (quotation_number, rfq_id, vendor_id, total_amount, delivery_days, validity_days, notes, status)
    VALUES (?,?,?,?,?,?,?,'submitted')");
$stmt->execute([
    $q_number, $body['rfq_id'], $vendor_id,
    $body['total_amount'], $body['delivery_days'] ?? 14,
    $body['validity_days'] ?? 30, $body['notes'] ?? ''
]);
$q_id = $db->lastInsertId();

// Insert items
if (!empty($body['items'])) {
    $istmt = $db->prepare("INSERT INTO quotation_items (quotation_id, rfq_item_id, item_name, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)");
    foreach ($body['items'] as $item) {
        $total = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
        $istmt->execute([$q_id, $item['rfq_item_id'] ?? null, $item['item_name'], $item['quantity'] ?? 1, $item['unit_price'] ?? 0, $total]);
    }
}

log_activity($auth['id'], 'QUOTATION_SUBMITTED', 'quotation', $q_id, "Quotation submitted: $q_number for RFQ #{$body['rfq_id']}");
json_response(['id' => $q_id, 'quotation_number' => $q_number, 'message' => 'Quotation submitted'], 201);
