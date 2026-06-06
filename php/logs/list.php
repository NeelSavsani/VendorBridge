<?php
$auth = require_auth();
$db = getDB();
$limit = min((int)($_GET['limit'] ?? 50), 100);

$stmt=$db->prepare("SELECT al.*, u.name as user_name, u.role as user_role
    FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT ?");
$stmt->execute([$limit]);
json_response($stmt->fetchAll());
