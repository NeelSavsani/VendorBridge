<?php
$auth = require_auth();
$db = getDB();
$month = $_GET['month'] ?? date('Y-m');

$r = [];
// Total spend this month
$s=$db->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as count FROM purchase_orders WHERE DATE_FORMAT(created_at,'%Y-%m')=?");
$s->execute([$month]); $row=$s->fetch(); $r['total_spend']=$row['total']; $r['active_vendors']=$row['count'];

// Savings vs budget
$s=$db->prepare("SELECT COALESCE(SUM(rf.budget),0) as budget, COALESCE(SUM(po.total_amount),0) as spend
    FROM purchase_orders po JOIN rfqs rf ON rf.id=po.rfq_id WHERE DATE_FORMAT(po.created_at,'%Y-%m')=?");
$s->execute([$month]); $brow=$s->fetch();
$r['budget']=$brow['budget']; $r['savings_pct']=$brow['budget']>0?round(($brow['budget']-$brow['spend'])/$brow['budget']*100,1):0;
$r['invoices_count']=(int)$db->query("SELECT COUNT(*) as c FROM invoices")->fetch()['c'];

// Spend by category
$s=$db->prepare("SELECT rf.category, SUM(po.subtotal) as total FROM purchase_orders po JOIN rfqs rf ON rf.id=po.rfq_id
    WHERE DATE_FORMAT(po.created_at,'%Y-%m')=? GROUP BY rf.category ORDER BY total DESC");
$s->execute([$month]); $r['spend_by_category']=$s->fetchAll();

// Top vendors
$s=$db->prepare("SELECT v.company_name, SUM(po.total_amount) as spend, COUNT(po.id) as pos
    FROM purchase_orders po JOIN vendors v ON v.id=po.vendor_id WHERE DATE_FORMAT(po.created_at,'%Y-%m')=?
    GROUP BY v.id ORDER BY spend DESC LIMIT 5");
$s->execute([$month]); $r['top_vendors']=$s->fetchAll();

// Monthly trend
$s=$db->query("SELECT DATE_FORMAT(created_at,'%b') as m, SUM(total_amount) as total FROM purchase_orders
    WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY YEAR(created_at),MONTH(created_at) ORDER BY created_at");
$r['monthly_trend']=$s->fetchAll();

json_response($r);
