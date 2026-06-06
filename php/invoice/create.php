<?php
$auth = require_auth(['procurement_officer','admin']);
$body = json_decode(file_get_contents('php://input'), true);
if (empty($body['po_id'])) json_response(['error'=>'po_id required'],400);

$db = getDB();
// Check if invoice already exists
$s=$db->prepare("SELECT id FROM invoices WHERE po_id=?"); $s->execute([$body['po_id']]);
if($s->fetch()) json_response(['error'=>'Invoice already exists'],409);

$s=$db->prepare("SELECT * FROM purchase_orders WHERE id=?"); $s->execute([$body['po_id']]);
$po=$s->fetch();
if(!$po) json_response(['error'=>'PO not found'],404);

$year=date('Y');
$s=$db->query("SELECT COUNT(*) as c FROM invoices WHERE YEAR(created_at)=$year");
$count=$s->fetch()['c']+1;
$inv_num="INV-$year-".str_pad($count,3,'0',STR_PAD_LEFT);

$subtotal=$po['subtotal'];
// GST split - same state = CGST+SGST, different = IGST
$inter_state = $body['inter_state'] ?? false;
$tax_amt=$po['tax_amount'];
$cgst = $inter_state ? 0 : $tax_amt/2;
$sgst = $inter_state ? 0 : $tax_amt/2;
$igst = $inter_state ? $tax_amt : 0;
$total=$subtotal+$tax_amt;

$due=date('Y-m-d', strtotime('+30 days'));
$s=$db->prepare("INSERT INTO invoices (invoice_number,po_id,vendor_id,subtotal,cgst,sgst,igst,total_amount,due_date,status) VALUES (?,?,?,?,?,?,?,?,?,'draft')");
$s->execute([$inv_num,$body['po_id'],$po['vendor_id'],$subtotal,$cgst,$sgst,$igst,$total,$due]);
$inv_id=$db->lastInsertId();

$db->prepare("UPDATE invoices SET status='sent' WHERE id=?")->execute([$inv_id]);
log_activity($auth['id'],'INVOICE_GENERATED','invoice',$inv_id,"Invoice $inv_num generated for PO #{$body['po_id']}");
json_response(['id'=>$inv_id,'invoice_number'=>$inv_num,'total_amount'=>$total,'message'=>'Invoice generated'],201);
