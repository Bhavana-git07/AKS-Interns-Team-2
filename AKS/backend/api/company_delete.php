<?php
// api/company_delete.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$company_id = (int)($input['company_id'] ?? 0);

if ($company_id <= 0) {
    send_error("Valid company ID is required.");
}

// Schema has ON DELETE CASCADE on users.company_id, so this will also
// remove that company's users. If you DON'T want that behavior,
// change the FK constraint in schema.sql to RESTRICT and check for
// existing users here first instead.
$stmt = $conn->prepare("DELETE FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        send_error("Company not found.", 404);
    }
    log_activity('admin', $_SESSION['admin_id'], "Deleted company ID: $company_id");
    send_success("Company deleted successfully.");
} else {
    send_error("Failed to delete company.", 500);
}
