<?php
$auth = require_auth();
$rfq_id = $_REQUEST['_params']['rfq_id'] ?? 0;
$db = getDB();

// Get RFQ details
$stmt = $db->prepare("SELECT * FROM rfqs WHERE id=?");
$stmt->execute([$rfq_id]);
$rfq = $stmt->fetch();
if (!$rfq) json_response(['error'=>'RFQ not found'],404);

// Get all quotations for this RFQ
$stmt = $db->prepare("SELECT q.*, v.company_name, v.rating, v.gst_number, v.email as vendor_email
    FROM quotations q JOIN vendors v ON v.id=q.vendor_id
    WHERE q.rfq_id=? ORDER BY q.total_amount ASC");
$stmt->execute([$rfq_id]);
$quotations = $stmt->fetchAll();

// Get items for each quotation
foreach ($quotations as &$q) {
    $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id=?");
    $stmt->execute([$q['id']]);
    $q['items'] = $stmt->fetchAll();
}

// Mark best quote (lowest total)
if (!empty($quotations)) {
    $quotations[0]['is_best_price'] = true;
    // Mark fastest delivery
    $fastest = array_reduce($quotations, function($carry, $q) {
        return ($carry === null || $q['delivery_days'] < $carry['delivery_days']) ? $q : $carry;
    }, null);
    foreach ($quotations as &$q) {
        $q['is_fastest'] = ($q['id'] === $fastest['id']);
    }
}

json_response(['rfq' => $rfq, 'quotations' => $quotations]);
