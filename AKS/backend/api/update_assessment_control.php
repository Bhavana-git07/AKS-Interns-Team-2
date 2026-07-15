<?php
// api/update_assessment_control.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$assessment_id = (int)($input['assessment_id'] ?? 0);
$control_code = trim($input['control_code'] ?? '');
$status = trim($input['status'] ?? 'Matched');

if ($assessment_id <= 0 || empty($control_code)) {
    send_error("Assessment ID and Control Code are required.");
}

// Find control_id from control_code
$stmt = $conn->prepare("SELECT control_id FROM controls WHERE control_code = ?");
$stmt->bind_param("s", $control_code);
$stmt->execute();
$cRes = $stmt->get_result();
if ($cRes->num_rows === 0) {
    send_error("Control code not found.");
}
$control = $cRes->fetch_assoc();
$control_id = $control['control_id'];

// Update status in assessment_controls
$up = $conn->prepare("UPDATE assessment_controls SET status = ? WHERE assessment_id = ? AND control_id = ?");
$up->bind_param("sii", $status, $assessment_id, $control_id);
$up->execute();

if ($up->affected_rows === 0) {
    // If not exists, insert it
    $ins = $conn->prepare("INSERT INTO assessment_controls (assessment_id, control_id, status) VALUES (?, ?, ?)");
    $ins->bind_param("iis", $assessment_id, $control_id, $status);
    $ins->execute();
}

// Recalculate compliance percentage
// 1. Get target framework of this assessment
$aStmt = $conn->prepare("SELECT target_framework_id FROM assessments WHERE assessment_id = ?");
$aStmt->bind_param("i", $assessment_id);
$aStmt->execute();
$aRes = $aStmt->get_result()->fetch_assoc();
$target_framework_id = $aRes['target_framework_id'];

// 2. Count total controls in target framework
$totStmt = $conn->prepare("SELECT COUNT(*) AS total FROM controls WHERE framework_id = ?");
$totStmt->bind_param("i", $target_framework_id);
$totStmt->execute();
$total = $totStmt->get_result()->fetch_assoc()['total'];

// 3. Count matched controls in assessment_controls
$matchStmt = $conn->prepare("SELECT COUNT(*) AS matched FROM assessment_controls WHERE assessment_id = ? AND status = 'Matched'");
$matchStmt->bind_param("i", $assessment_id);
$matchStmt->execute();
$matched = $matchStmt->get_result()->fetch_assoc()['matched'];

$percentage = 0;
if ($total > 0) {
    $percentage = ($matched / $total) * 100;
}

// Update compliance percentage in assessments table
$upPct = $conn->prepare("UPDATE assessments SET compliance_percentage = ? WHERE assessment_id = ?");
$upPct->bind_param("di", $percentage, $assessment_id);
$upPct->execute();

send_success("Control status updated successfully.", [
    "compliance_percentage" => $percentage,
    "matched_count" => $matched,
    "total_count" => $total
]);
?>
