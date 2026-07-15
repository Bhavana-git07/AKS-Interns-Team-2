<?php
include '../config/database.php';

$audit_id = $_GET['audit_id'];

$stmt = $conn->prepare("SELECT progress, status FROM audits WHERE audit_id = ?");
$stmt->bind_param("i", $audit_id);
$stmt->execute();
$result = $stmt->get_result();

$row = $result->fetch_assoc();

echo json_encode($row);
?>