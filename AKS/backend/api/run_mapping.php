<?php
include '../config/database.php';
require_once '../config/auth.php';

verify_csrf_token();

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid or missing assessment ID."]));
}

$stmt_assess = $conn->prepare("SELECT * FROM assessments WHERE assessment_id = ?");
$stmt_assess->bind_param("i", $assessment_id);
$stmt_assess->execute();
$assessment = $stmt_assess->get_result()->fetch_assoc();

$current = $assessment['current_framework_id'];
$target = $assessment['target_framework_id'];

$stmt_target = $conn->prepare("
    SELECT c.control_id, cm.master_control_id
    FROM controls c
    JOIN control_mappings cm ON c.control_id = cm.control_id
    WHERE c.framework_id = ?
");
$stmt_target->bind_param("i", $target);
$stmt_target->execute();
$target_controls = $stmt_target->get_result();

$total = 0;
$matched = 0;

$stmt_exists = $conn->prepare("
    SELECT *
    FROM controls c
    JOIN control_mappings cm ON c.control_id = cm.control_id
    WHERE c.framework_id = ? AND cm.master_control_id = ?
");

while($row = $target_controls->fetch_assoc())
{
    $total++;

    $master = $row['master_control_id'];
    $control = $row['control_id'];

    $stmt_exists->bind_param("ii", $current, $master);
    $stmt_exists->execute();
    $exists = $stmt_exists->get_result();

    $status = "Missing";

    if($exists->num_rows > 0)
    {
        $status = "Matched";
        $matched++;
    }

    $stmt =
    $conn->prepare(
    "INSERT INTO assessment_controls
    (assessment_id,control_id,status)
    VALUES (?,?,?)"
    );

    $stmt->bind_param(
    "iis",
    $assessment_id,
    $control,
    $status
    );

    $stmt->execute();
}

$percentage = 0;

if($total > 0)
{
    $percentage = ($matched/$total)*100;
}

$stmt_update = $conn->prepare("
    UPDATE assessments
    SET compliance_percentage = ?
    WHERE assessment_id = ?
");
$stmt_update->bind_param("di", $percentage, $assessment_id);
$stmt_update->execute();

echo json_encode([
    "matched_controls"=>$matched,
    "missing_controls"=>$total-$matched,
    "compliance_percentage"=>$percentage
]);
?>