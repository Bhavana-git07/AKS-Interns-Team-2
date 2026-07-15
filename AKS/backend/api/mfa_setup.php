<?php
// api/mfa_setup.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';
require_once '../config/GoogleAuthenticator.php';

require_admin_login();

$admin_id = $_SESSION['admin_id'];

// Get current admin details
$stmt = $conn->prepare("SELECT email, mfa_enabled, mfa_secret FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    send_error("Administrator not found.", 404);
}

if ($admin['mfa_enabled']) {
    send_success("MFA is already active.", [
        "mfa_enabled" => true
    ]);
}

$ga = new GoogleAuthenticator();
$secret = $ga->createSecret();

// Store temporary secret in session so we don't save to DB until they verify it
$_SESSION['temp_mfa_secret'] = $secret;

$email = $admin['email'] ?? 'admin@complianceaudit.com';
$qr_uri = "otpauth://totp/ComplianceAudit:" . $email . "?secret=" . $secret . "&issuer=ComplianceAudit";

send_success("MFA registration key initialized.", [
    "mfa_enabled" => false,
    "secret" => $secret,
    "qr_uri" => $qr_uri
]);
?>
