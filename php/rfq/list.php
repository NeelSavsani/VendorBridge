<?php
$auth = require_auth();
$db = getDB();

$status = $_GET['status'] ?? '';
$where = ['1=1'];
$binds = [];

if ($status) { $where[] = "r.status = ?"; $binds[] = $status; }

// Vendors see only RFQs assigned to them
if ($auth['role'] === 'vendor') {
    $stmt2 = $db->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt2->execute([$auth['id']]);
    $v = $stmt2->fetch();
    if ($v) {
        $where[] = "EXISTS (SELECT 1 FROM rfq_vendors rv WHERE rv.rfq_id = r.id AND rv.vendor_id = ?)";
        $binds[] = $v['id'];
    } else {
        json_response([]);
    }
}

$sql = "SELECT r.*, u.name as created_by_name,
        COUNT(DISTINCT qi.id) as item_count,
        COUNT(DISTINCT q.id) as quotation_count
        FROM rfqs r
        LEFT JOIN users u ON u.id = r.created_by
        LEFT JOIN rfq_items qi ON qi.rfq_id = r.id
        LEFT JOIN quotations q ON q.rfq_id = r.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY r.id
        ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($binds);
json_response($stmt->fetchAll());
