<?php
// api/mfa_enable.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';
require_once '../config/GoogleAuthenticator.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$code = trim($input['code'] ?? '');

if (empty($code)) {
    send_error("Verification code is required.");
}

$secret = $_SESSION['temp_mfa_secret'] ?? '';
if (empty($secret)) {
    send_error("MFA setup session expired. Please refresh setup QR code.");
}

$ga = new GoogleAuthenticator();
if ($ga->verifyCode($secret, $code)) {
    $admin_id = $_SESSION['admin_id'];
    
    // Save to database
    $stmt = $conn->prepare("UPDATE admins SET mfa_secret = ?, mfa_enabled = 1 WHERE admin_id = ?");
    $stmt->bind_param("si", $secret, $admin_id);
    
    if ($stmt->execute()) {
        unset($_SESSION['temp_mfa_secret']);
        log_activity('admin', $admin_id, 'Enabled Multi-factor Authentication (MFA)');
        send_success("MFA has been successfully enabled.");
    } else {
        send_error("Failed to update database.", 500);
    }
} else {
    send_error("Invalid 6-digit verification code. Please check your authenticator app.");
}
?>
