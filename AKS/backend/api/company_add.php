<?php
// api/company_add.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$company_name = trim($input['company_name'] ?? '');
$industry = trim($input['industry'] ?? '');
$address = trim($input['address'] ?? '');
$contact_email = trim($input['contact_email'] ?? '');
$contact_phone = trim($input['contact_phone'] ?? '');

if (empty($company_name)) {
    send_error("Company name is required.");
}

// XSS Validation: Reject any input containing HTML tags
if (has_html_tags($company_name) || has_html_tags($industry) || has_html_tags($address) || has_html_tags($contact_phone)) {
    send_error("Inputs must not contain HTML/script tags.");
}

if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    send_error("Invalid contact email.");
}

// Start Transaction to avoid race conditions
$conn->begin_transaction();

try {
    $year = date('Y');
    $likePattern = $year . '%';

    // Lock the highest registration number matching the current year's pattern
    $stmt_reg = $conn->prepare("SELECT registration_number FROM companies WHERE registration_number LIKE ? ORDER BY registration_number DESC LIMIT 1 FOR UPDATE");
    $stmt_reg->bind_param("s", $likePattern);
    $stmt_reg->execute();
    $res_reg = $stmt_reg->get_result();

    $registration_number = "";
    if ($res_reg && $res_reg->num_rows > 0) {
        $row = $res_reg->fetch_assoc();
        $last_reg = $row['registration_number'];

        // Expected format YYYYNN-XXXXX (e.g. 202301-00123)
        if (preg_match('/^(\d{6})-(\d{5})$/', $last_reg, $matches)) {
            $prefix = $matches[1];
            $seq = intval($matches[2]);
            $next_seq = $seq + 1;
            $registration_number = $prefix . '-' . str_pad($next_seq, 5, '0', STR_PAD_LEFT);
        } else {
            // Default prefix YYYY01 if existing rows for the current year are not in the standard format
            $registration_number = $year . '01-00001';
        }
    } else {
        // Start sequence at 00001 for the current year
        $registration_number = $year . '01-00001';
    }
    $stmt_reg->close();

    $stmt = $conn->prepare("INSERT INTO companies (company_name, registration_number, industry, address, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $company_name, $registration_number, $industry, $address, $contact_email, $contact_phone);

    if ($stmt->execute()) {
        $company_id = $stmt->insert_id;
        $stmt->close();

        log_activity('admin', $_SESSION['admin_id'], "Created company: $company_name");
        $conn->commit();

        send_success("Company added successfully.", [
            "company_id" => $company_id,
            "registration_number" => $registration_number
        ], 201);
    } else {
        $stmt->close();
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    send_error("Failed to add company: " . $e->getMessage(), 500);
}
?>
