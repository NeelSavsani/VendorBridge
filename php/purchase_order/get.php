<?php
$auth = require_auth();
$id = $_REQUEST['_params']['id'] ?? 0;
$db = getDB();
$stmt = $db->prepare("SELECT po.*, v.company_name, v.email as vendor_email, v.gst_number, v.address as vendor_address,
    r.title as rfq_title, r.rfq_number, q.quotation_number, q.delivery_days, q.notes,
    u.name as created_by_name
    FROM purchase_orders po
    JOIN vendors v ON v.id=po.vendor_id JOIN rfqs r ON r.id=po.rfq_id
    JOIN quotations q ON q.id=po.quotation_id JOIN users u ON u.id=po.created_by
    WHERE po.id=?");
$stmt->execute([$id]);
$po = $stmt->fetch();
if (!$po) json_response(['error'=>'PO not found'],404);
$stmt = $db->prepare("SELECT qi.* FROM quotation_items qi JOIN quotations q ON q.id=qi.quotation_id WHERE q.id=?");
$stmt->execute([$po['quotation_id']]);
$po['items'] = $stmt->fetchAll();
json_response($po);
