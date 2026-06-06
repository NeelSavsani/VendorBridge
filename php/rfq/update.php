<?php
$auth = require_auth(['admin', 'procurement_officer']);
$id = $_REQUEST['_params']['id'] ?? 0;
$body = json_decode(file_get_contents('php://input'), true);
$db = getDB();
$fields = ['title','description','category','deadline','budget','status'];
$sets=[]; $vals=[];
foreach ($fields as $f) { if (isset($body[$f])) { $sets[]="$f=?"; $vals[]=$body[$f]; } }
if (empty($sets)) json_response(['error'=>'No fields to update'],400);
$vals[]=$id;
$db->prepare("UPDATE rfqs SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
log_activity($auth['id'],'RFQ_UPDATED','rfq',$id,"RFQ updated");
json_response(['message'=>'RFQ updated']);
