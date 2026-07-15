<?php
// api/company_update.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$company_id = (int)($input['company_id'] ?? 0);
$company_name = trim($input['company_name'] ?? '');
$registration_number = trim($input['registration_number'] ?? '');
$industry = trim($input['industry'] ?? '');
$address = trim($input['address'] ?? '');
$contact_email = trim($input['contact_email'] ?? '');
$contact_phone = trim($input['contact_phone'] ?? '');

if ($company_id <= 0 || empty($company_name)) {
    send_error("Company ID and name are required.");
}

// XSS Validation: Reject any input containing HTML tags
if (has_html_tags($company_name) || has_html_tags($registration_number) || has_html_tags($industry) || has_html_tags($address) || has_html_tags($contact_phone)) {
    send_error("Inputs must not contain HTML/script tags.");
}

if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    send_error("Invalid contact email.");
}

$stmt = $conn->prepare("UPDATE companies SET company_name = ?, registration_number = ?, industry = ?, address = ?, contact_email = ?, contact_phone = ? WHERE company_id = ?");
$stmt->bind_param("ssssssi", $company_name, $registration_number, $industry, $address, $contact_email, $contact_phone, $company_id);

if ($stmt->execute()) {
    log_activity('admin', $_SESSION['admin_id'], "Updated company ID $company_id: $company_name");
    send_success("Company updated successfully.");
} else {
    send_error("Failed to update company: " . $stmt->error, 500);
}
