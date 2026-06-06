<?php
$auth = require_auth();
$id = $_REQUEST['_params']['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("SELECT r.*, u.name as created_by_name FROM rfqs r LEFT JOIN users u ON u.id = r.created_by WHERE r.id = ?");
$stmt->execute([$id]);
$rfq = $stmt->fetch();
if (!$rfq) json_response(['error' => 'RFQ not found'], 404);

// Get items
$stmt = $db->prepare("SELECT * FROM rfq_items WHERE rfq_id = ?");
$stmt->execute([$id]);
$rfq['items'] = $stmt->fetchAll();

// Get assigned vendors
$stmt = $db->prepare("SELECT v.* FROM vendors v JOIN rfq_vendors rv ON rv.vendor_id = v.id WHERE rv.rfq_id = ?");
$stmt->execute([$id]);
$rfq['vendors'] = $stmt->fetchAll();

json_response($rfq);
