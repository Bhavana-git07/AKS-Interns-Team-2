<?php
include '../config/database.php';
require_once '../config/auth.php';

verify_csrf_token();

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare(
"INSERT INTO assessments
(company_id,current_framework_id,target_framework_id)
VALUES (?,?,?)"
);

$stmt->bind_param(
"iii",
$data['company_id'],
$data['current_framework_id'],
$data['target_framework_id']
);

$stmt->execute();

echo json_encode([
    "success" => true,
    "assessment_id" => $conn->insert_id
]);
?>