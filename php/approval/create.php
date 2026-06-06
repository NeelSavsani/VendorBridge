<?php
$auth = require_auth(['procurement_officer', 'admin', 'manager']);
$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['quotation_id'])) {
    json_response(['error' => 'quotation_id required'], 400);
}

$db = getDB();

// Check if approval already exists
$stmt = $db->prepare("SELECT id FROM approvals WHERE quotation_id = ?");
$stmt->execute([$body['quotation_id']]);
if ($stmt->fetch()) {
    json_response(['error' => 'Approval already exists for this quotation'], 409);
}

// Get a manager to assign
$stmt = $db->prepare("SELECT id FROM users WHERE role IN ('manager','admin') LIMIT 1");
$stmt->execute();
$mgr = $stmt->fetch();
$approver_id = $body['approver_id'] ?? ($mgr ? $mgr['id'] : $auth['id']);

$stmt = $db->prepare("INSERT INTO approvals (quotation_id, approver_id, status) VALUES (?,?,'pending')");
$stmt->execute([$body['quotation_id'], $approver_id]);
$id = $db->lastInsertId();

// Update quotation status
$db->prepare("UPDATE quotations SET status='under_review' WHERE id=?")->execute([$body['quotation_id']]);

log_activity($auth['id'], 'APPROVAL_REQUESTED', 'approval', $id, "Approval requested for quotation #{$body['quotation_id']}");
json_response(['id' => $id, 'message' => 'Approval request submitted'], 201);
