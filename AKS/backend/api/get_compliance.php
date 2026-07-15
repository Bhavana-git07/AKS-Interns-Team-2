<?php
include '../config/database.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid or missing assessment ID."]));
}

$stmt = $conn->prepare("SELECT compliance_percentage FROM assessments WHERE assessment_id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(
$result->fetch_assoc()
);
?>