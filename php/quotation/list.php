<?php
$auth = require_auth();
$db = getDB();
$where = ['1=1']; $binds = [];

$rfq_id = $_GET['rfq_id'] ?? '';
$vendor_id = $_GET['vendor_id'] ?? '';

if ($rfq_id) { $where[] = "q.rfq_id = ?"; $binds[] = $rfq_id; }
if ($vendor_id) { $where[] = "q.vendor_id = ?"; $binds[] = $vendor_id; }

if ($auth['role'] === 'vendor') {
    $stmt2 = $db->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt2->execute([$auth['id']]);
    $v = $stmt2->fetch();
    if ($v) { $where[] = "q.vendor_id = ?"; $binds[] = $v['id']; }
    else json_response([]);
}

$sql = "SELECT q.*, v.company_name as vendor_name, r.title as rfq_title, r.rfq_number
        FROM quotations q
        JOIN vendors v ON v.id = q.vendor_id
        JOIN rfqs r ON r.id = q.rfq_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY q.submitted_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($binds);
json_response($stmt->fetchAll());
