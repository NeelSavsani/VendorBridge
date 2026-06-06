<?php
$auth = require_auth(['manager', 'admin']);
$db = getDB();

$stmt = $db->prepare("SELECT a.*, q.quotation_number, q.total_amount,
    v.company_name as vendor_name, r.title as rfq_title, r.rfq_number,
    u.name as approver_name
    FROM approvals a
    JOIN quotations q ON q.id=a.quotation_id
    JOIN vendors v ON v.id=q.vendor_id
    JOIN rfqs r ON r.id=q.rfq_id
    JOIN users u ON u.id=a.approver_id
    ORDER BY a.created_at DESC");
$stmt->execute();
json_response($stmt->fetchAll());
