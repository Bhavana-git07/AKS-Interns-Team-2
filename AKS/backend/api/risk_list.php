<?php
// api/risk_list.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error("Invalid request method.", 405);
}

$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if ($company_id > 0) {
    $stmt = $conn->prepare("
        SELECT r.*, c.company_name 
        FROM risks r 
        JOIN companies c ON r.company_id = c.company_id 
        WHERE r.company_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $company_id);
} else {
    $stmt = $conn->prepare("
        SELECT r.*, c.company_name 
        FROM risks r 
        JOIN companies c ON r.company_id = c.company_id 
        ORDER BY r.created_at DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();
$risks = [];

while ($row = $result->fetch_assoc()) {
    $risks[] = [
        "risk_id" => intval($row['risk_id']),
        "company_id" => intval($row['company_id']),
        "company_name" => $row['company_name'],
        "risk_title" => $row['risk_title'],
        "risk_description" => $row['risk_description'],
        "likelihood" => intval($row['likelihood']),
        "impact" => intval($row['impact']),
        "risk_score" => intval($row['risk_score']),
        "mitigation_strategy" => $row['mitigation_strategy'],
        "status" => $row['status'],
        "created_at" => $row['created_at']
    ];
}

send_success("Risks retrieved successfully.", $risks);
?>
