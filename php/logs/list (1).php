<?php
$auth = require_auth(['admin','manager']);
$db = getDB();
$stmt=$db->query("SELECT id,name,email,role,company,phone,country,is_active,created_at FROM users ORDER BY created_at DESC");
json_response($stmt->fetchAll());
