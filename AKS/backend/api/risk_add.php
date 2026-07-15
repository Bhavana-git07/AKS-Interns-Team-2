<?php
// api/risk_add.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

// 1. Verify CSRF Token
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);

$company_id = intval($input['company_id'] ?? 0);
$title = trim($input['risk_title'] ?? '');
$description = trim($input['risk_description'] ?? '');
$likelihood = intval($input['likelihood'] ?? 0);
$impact = intval($input['impact'] ?? 0);
$mitigation = trim($input['mitigation_strategy'] ?? '');
$status = trim($input['status'] ?? 'Open');

// 2. Validate parameters
if ($company_id <= 0 || empty($title)) {
    send_error("Company and Risk Title are required.");
}

if ($likelihood < 1 || $likelihood > 5 || $impact < 1 || $impact > 5) {
    send_error("Likelihood and Impact must be integers between 1 and 5.");
}

// 3. XSS Protection: prevent HTML tag insertion
if (has_html_tags($title) || has_html_tags($description) || has_html_tags($mitigation) || has_html_tags($status)) {
    send_error("HTML tags are not allowed in inputs.", 400);
}

// 4. Calculate Risk Score
$risk_score = $likelihood * $impact;

// 5. Verify company exists
$stmt_check = $conn->prepare("SELECT company_id FROM companies WHERE company_id = ?");
$stmt_check->bind_param("i", $company_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows === 0) {
    send_error("Selected company does not exist.");
}

// 6. Insert Risk
$stmt = $conn->prepare("
    INSERT INTO risks (company_id, risk_title, risk_description, likelihood, impact, risk_score, mitigation_strategy, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("issiiiss", $company_id, $title, $description, $likelihood, $impact, $risk_score, $mitigation, $status);

if ($stmt->execute()) {
    send_success("Risk added successfully.", [
        "risk_id" => $conn->insert_id,
        "risk_score" => $risk_score
    ]);
} else {
    send_error("Database failure: failed to create risk entry.");
}
?>
