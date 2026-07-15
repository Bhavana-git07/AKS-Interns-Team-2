<?php
// api/mfa_disable.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$admin_id = $_SESSION['admin_id'];

// Remove from database
$stmt = $conn->prepare("UPDATE admins SET mfa_secret = NULL, mfa_enabled = 0 WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);

if ($stmt->execute()) {
    log_activity('admin', $admin_id, 'Disabled Multi-factor Authentication (MFA)');
    send_success("MFA has been successfully disabled.");
} else {
    send_error("Failed to update database.", 500);
}
?>
