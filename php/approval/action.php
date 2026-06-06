<?php
$auth = require_auth(['manager', 'admin']);
$id = $_REQUEST['_params']['id'] ?? 0;
$body = json_decode(file_get_contents('php://input'), true);

$action = $body['status'] ?? '';
if (!in_array($action, ['approved', 'rejected'])) {
    json_response(['error' => 'Status must be approved or rejected'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT a.*, q.rfq_id, q.vendor_id FROM approvals a JOIN quotations q ON q.id=a.quotation_id WHERE a.id=?");
$stmt->execute([$id]);
$approval = $stmt->fetch();
if (!$approval) json_response(['error' => 'Approval not found'], 404);
if ($approval['status'] !== 'pending') json_response(['error' => 'Already actioned'], 400);

// Update approval
$db->prepare("UPDATE approvals SET status=?, remarks=?, approver_id=?, action_at=NOW() WHERE id=?")
   ->execute([$action, $body['remarks'] ?? '', $auth['id'], $id]);

// Update quotation status
$qStatus = $action === 'approved' ? 'selected' : 'rejected';
$db->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$qStatus, $approval['quotation_id']]);

// If approved, reject other quotations for same RFQ
if ($action === 'approved') {
    $db->prepare("UPDATE quotations SET status='rejected' WHERE rfq_id=? AND id!=? AND status NOT IN ('selected')")
       ->execute([$approval['rfq_id'], $approval['quotation_id']]);
    // Close RFQ
    $db->prepare("UPDATE rfqs SET status='closed' WHERE id=?")->execute([$approval['rfq_id']]);
}

log_activity($auth['id'], 'APPROVAL_' . strtoupper($action), 'approval', $id, "Approval $action by {$auth['name']}");
json_response(['message' => "Quotation $action successfully"]);
