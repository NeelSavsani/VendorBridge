<?php
// PDF generation for invoice - outputs HTML that browser can print/save as PDF
$auth = require_auth();
$id = $_REQUEST['_params']['id'] ?? 0;
$db = getDB();
$stmt=$db->prepare("SELECT i.*, v.company_name, v.email as vendor_email, v.gst_number, v.address as vendor_address, v.phone as vendor_phone,
    po.po_number, po.delivery_date, po.tax_percent, r.title as rfq_title
    FROM invoices i JOIN vendors v ON v.id=i.vendor_id JOIN purchase_orders po ON po.id=i.po_id
    JOIN rfqs r ON r.id=po.rfq_id JOIN quotations q ON q.id=po.quotation_id WHERE i.id=?");
$stmt->execute([$id]);
$inv=$stmt->fetch();
if(!$inv) json_response(['error'=>'Invoice not found'],404);
$stmt=$db->prepare("SELECT qi.* FROM quotation_items qi JOIN quotations q ON q.id=qi.quotation_id WHERE q.id=?");
$stmt->execute([$inv['quotation_id']]);
$items=$stmt->fetchAll();

// Return JSON with invoice data for frontend PDF rendering
json_response(['invoice' => $inv, 'items' => $items]);
