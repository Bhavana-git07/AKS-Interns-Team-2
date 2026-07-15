<?php
include '../config/database.php';

$assessment_id = $_GET['assessment_id'];

$stmt = $conn->prepare("SELECT compliance_percentage FROM assessments WHERE assessment_id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

$data =
$result->fetch_assoc();

$percentage =
$data['compliance_percentage'];

$status = '';

if($percentage >= 80){
    $status = 'Ready';
}
elseif($percentage >= 50){
    $status = 'Partially Ready';
}
else{
    $status = 'Not Ready';
}

echo json_encode([
    "compliance_percentage"=>$percentage,
    "readiness"=>$status
]);
?>