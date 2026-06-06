<?php
$auth = require_auth();
$db = getDB();
$where = ['1=1']; $binds = [];

if ($auth['role'] === 'vendor') {
    $stmt2 = $db->prepare("SELECT id FROM vendors WHERE user_id=?");
    $stmt2->execute([$auth['id']]);
    $v = $stmt2->fetch();
    if ($v) { $where[]="po.vendor_id=?"; $binds[]=$v['id']; }
    else json_response([]);
}

$sql = "SELECT po.*, v.company_name as vendor_name, r.title as rfq_title, r.rfq_number,
    q.quotation_number, u.name as created_by_name
    FROM purchase_orders po
    JOIN vendors v ON v.id=po.vendor_id
    JOIN rfqs r ON r.id=po.rfq_id
    JOIN quotations q ON q.id=po.quotation_id
    JOIN users u ON u.id=po.created_by
    WHERE ".implode(' AND ',$where)."
    ORDER BY po.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($binds);
json_response($stmt->fetchAll());
