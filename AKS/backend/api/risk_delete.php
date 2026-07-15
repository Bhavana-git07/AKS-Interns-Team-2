<?php
// api/risk_delete.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

// 1. Verify CSRF Token
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$risk_id = intval($input['risk_id'] ?? 0);

if ($risk_id <= 0) {
    send_error("Risk ID is required.");
}

$stmt = $conn->prepare("DELETE FROM risks WHERE risk_id = ?");
$stmt->bind_param("i", $risk_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        send_success("Risk deleted successfully.");
    } else {
        send_error("Risk not found or already deleted.");
    }
} else {
    send_error("Database failure: failed to delete risk entry.");
}
?>
