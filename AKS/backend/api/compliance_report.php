<?php
include '../config/database.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid or missing assessment ID."]));
}

$stmt_assess = $conn->prepare("SELECT assessment_id, compliance_percentage FROM assessments WHERE assessment_id = ?");
$stmt_assess->bind_param("i", $assessment_id);
$stmt_assess->execute();
$result = $stmt_assess->get_result();
$assessment = $result->fetch_assoc();

$stmt_matched = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Matched'");
$stmt_matched->bind_param("i", $assessment_id);
$stmt_matched->execute();
$matched = $stmt_matched->get_result()->fetch_assoc();

$stmt_missing = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Missing'");
$stmt_missing->bind_param("i", $assessment_id);
$stmt_missing->execute();
$missing = $stmt_missing->get_result()->fetch_assoc();

echo json_encode([
    "assessment_id"=>$assessment_id,
    "matched_controls"=>$matched['total'],
    "missing_controls"=>$missing['total'],
    "compliance_percentage"=>$assessment['compliance_percentage']
]);
?>