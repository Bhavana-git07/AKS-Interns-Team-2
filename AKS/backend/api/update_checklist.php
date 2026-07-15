<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../config/database.php';
require_once '../config/auth.php';

verify_csrf_token();

$data = json_decode(file_get_contents("php://input"), true);

$checklist_id = isset($data['checklist_id']) ? intval($data['checklist_id']) : 0;
$is_completed = isset($data['is_completed']) ? intval($data['is_completed']) : 0;

if ($checklist_id <= 0) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid checklist ID"]));
}

/* Update checklist item */
$stmt = $conn->prepare(
    "UPDATE audit_checklist
     SET is_completed = ?
     WHERE checklist_id = ?"
);

$stmt->bind_param("ii", $is_completed, $checklist_id);
$stmt->execute();

/* Get audit_id */
$stmt_audit = $conn->prepare("SELECT audit_id FROM audit_checklist WHERE checklist_id = ?");
$stmt_audit->bind_param("i", $checklist_id);
$stmt_audit->execute();
$row = $stmt_audit->get_result()->fetch_assoc();
$audit_id = $row['audit_id'];

/* Total items */
$stmt_total = $conn->prepare("SELECT COUNT(*) AS total FROM audit_checklist WHERE audit_id = ?");
$stmt_total->bind_param("i", $audit_id);
$stmt_total->execute();
$total = $stmt_total->get_result()->fetch_assoc()['total'];

/* Completed items */
$stmt_completed = $conn->prepare("SELECT COUNT(*) AS completed FROM audit_checklist WHERE audit_id = ? AND is_completed = 1");
$stmt_completed->bind_param("i", $audit_id);
$stmt_completed->execute();
$completed = $stmt_completed->get_result()->fetch_assoc()['completed'];

$progress = ($completed / $total) * 100;

/* Update progress */
$stmt2 = $conn->prepare(
    "UPDATE audits
     SET progress = ?
     WHERE audit_id = ?"
);

$stmt2->bind_param("ii", $progress, $audit_id);
$stmt2->execute();

/* If completed */
if ($progress == 100) {
    $stmt_status = $conn->prepare("UPDATE audits SET status = 'Completed' WHERE audit_id = ?");
    $stmt_status->bind_param("i", $audit_id);
    $stmt_status->execute();
}

echo json_encode([
    "success" => true,
    "progress" => $progress
]);
?>