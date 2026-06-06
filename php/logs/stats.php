<?php
$auth = require_auth();
$db = getDB();

$stats = [];

// Active RFQs
$s=$db->query("SELECT COUNT(*) as c FROM rfqs WHERE status='open'"); $stats['active_rfqs']=$s->fetch()['c'];
// Pending approvals
$s=$db->query("SELECT COUNT(*) as c FROM approvals WHERE status='pending'"); $stats['pending_approvals']=$s->fetch()['c'];
// This month spend
$s=$db->query("SELECT COALESCE(SUM(total_amount),0) as total FROM purchase_orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$stats['monthly_spend']=$s->fetch()['total'];
// Active vendors
$s=$db->query("SELECT COUNT(*) as c FROM vendors WHERE status='active'"); $stats['active_vendors']=$s->fetch()['c'];
// Total POs
$s=$db->query("SELECT COUNT(*) as c FROM purchase_orders"); $stats['total_pos']=$s->fetch()['c'];
// Total invoices
$s=$db->query("SELECT COUNT(*) as c FROM invoices"); $stats['total_invoices']=$s->fetch()['c'];

// Recent RFQs
$s=$db->query("SELECT r.*, u.name as created_by_name FROM rfqs r LEFT JOIN users u ON u.id=r.created_by ORDER BY r.created_at DESC LIMIT 5");
$stats['recent_rfqs']=$s->fetchAll();

// Recent activity
$s=$db->query("SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 8");
$stats['recent_activity']=$s->fetchAll();

// Recent POs
$s=$db->query("SELECT po.*, v.company_name FROM purchase_orders po JOIN vendors v ON v.id=po.vendor_id ORDER BY po.created_at DESC LIMIT 5");
$stats['recent_pos']=$s->fetchAll();

// Monthly spend trend (last 6 months)
$s=$db->query("SELECT DATE_FORMAT(created_at,'%b %Y') as month, COALESCE(SUM(total_amount),0) as total
    FROM purchase_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY created_at");
$stats['spend_trend']=$s->fetchAll();

json_response($stats);
