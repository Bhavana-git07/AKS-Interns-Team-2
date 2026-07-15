<?php
// api/verify_google_mfa.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';
require_once '../config/GoogleAuthenticator.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$code = trim($input['code'] ?? '');

if (empty($code)) {
    send_error("Verification code is required.");
}

if (empty($_SESSION['google_mfa_pending_admin'])) {
    send_error("No pending Google Authenticator session. Please log in again.");
}

$pending = $_SESSION['google_mfa_pending_admin'];
$admin_id = $pending['admin_id'];

// Fetch the secret key from database
$stmt = $conn->prepare("SELECT mfa_secret FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin || empty($admin['mfa_secret'])) {
    send_error("MFA secret not initialized. Please log in again.");
}

$ga = new GoogleAuthenticator();
if ($ga->verifyCode($admin['mfa_secret'], $code)) {
    // Correct code: Establish admin session!
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_name'] = $pending['admin_name'];
    
    log_activity('admin', $admin_id, 'Logged in successfully via Google Authenticator MFA');
    
    // Clean up temporary session data
    unset($_SESSION['google_mfa_pending_admin']);
    
    send_success("Login successful.", [
        "user_id" => $admin_id,
        "full_name" => $pending['admin_name'],
        "role" => $pending['role'],
        "first_login" => false
    ]);
} else {
    log_activity('admin', $admin_id, 'Failed Google Authenticator MFA login verification attempt');
    send_error("Invalid 6-digit code. Please verify the code on your Authenticator App.", 401);
}
?>
