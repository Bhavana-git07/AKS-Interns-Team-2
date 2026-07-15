<?php
// api/assessment_list.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();

// Optional filter by company_id
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

if (isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    $company_id = $_SESSION['company_id'];
}

if ($company_id) {
    $stmt = $conn->prepare("SELECT a.assessment_id, a.company_id, c.company_name, a.current_framework_id, a.target_framework_id, a.compliance_percentage, a.created_at FROM assessments a JOIN companies c ON a.company_id = c.company_id WHERE a.company_id = ? ORDER BY a.created_at DESC");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT a.assessment_id, a.company_id, c.company_name, a.current_framework_id, a.target_framework_id, a.compliance_percentage, a.created_at FROM assessments a JOIN companies c ON a.company_id = c.company_id ORDER BY a.created_at DESC");
}

$assessments = [];
while ($row = $result->fetch_assoc()) {
    $assessments[] = $row;
}

send_success("Assessments retrieved.", $assessments);
?>
