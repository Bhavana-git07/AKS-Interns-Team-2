<?php
include '../config/database.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid or missing assessment ID."]));
}

$stmt_assess = $conn->prepare("SELECT * FROM assessments WHERE assessment_id = ?");
$stmt_assess->bind_param("i", $assessment_id);
$stmt_assess->execute();
$assessment = $stmt_assess->get_result()->fetch_assoc();

$framework =
$assessment['target_framework_id'];

$stmt_total = $conn->prepare("SELECT COUNT(*) AS total FROM controls WHERE framework_id = ?");
$stmt_total->bind_param("i", $framework);
$stmt_total->execute();
$total = $stmt_total->get_result()->fetch_assoc();

$stmt_covered = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Matched'");
$stmt_covered->bind_param("i", $assessment_id);
$stmt_covered->execute();
$covered = $stmt_covered->get_result()->fetch_assoc();

$percentage = 0;

if($total['total'] > 0){
    $percentage =
    ($covered['total']/$total['total'])*100;
}

echo json_encode([
    "total_controls"=>$total['total'],
    "covered_controls"=>$covered['total'],
    "coverage_percentage"=>$percentage
]);
?>