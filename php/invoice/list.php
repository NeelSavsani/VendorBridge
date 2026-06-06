<?php
$auth = require_auth();
$db = getDB();
$where=['1=1']; $binds=[];
if ($auth['role']==='vendor') {
    $s=$db->prepare("SELECT id FROM vendors WHERE user_id=?"); $s->execute([$auth['id']]); $v=$s->fetch();
    if($v){$where[]="i.vendor_id=?";$binds[]=$v['id'];}else json_response([]);
}
$sql="SELECT i.*, v.company_name as vendor_name, po.po_number, r.title as rfq_title
    FROM invoices i JOIN vendors v ON v.id=i.vendor_id JOIN purchase_orders po ON po.id=i.po_id
    JOIN rfqs r ON r.id=po.rfq_id WHERE ".implode(' AND ',$where)." ORDER BY i.created_at DESC";
$stmt=$db->prepare($sql); $stmt->execute($binds);
json_response($stmt->fetchAll());
