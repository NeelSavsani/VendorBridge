<?php
$auth = require_auth();
$id = $_REQUEST['_params']['id'] ?? 0;
$db = getDB();
$stmt = $db->prepare("SELECT q.*, v.company_name, v.email as vendor_email, v.rating, v.gst_number,
    r.title as rfq_title, r.rfq_number
    FROM quotations q JOIN vendors v ON v.id=q.vendor_id JOIN rfqs r ON r.id=q.rfq_id WHERE q.id=?");
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) json_response(['error'=>'Not found'],404);
$stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id=?");
$stmt->execute([$id]);
$q['items'] = $stmt->fetchAll();
json_response($q);
