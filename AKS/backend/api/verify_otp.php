<?php
// api/verify_otp.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$otp = trim($input['otp'] ?? '');

if (empty($otp)) {
    send_error("Verification code is required.");
}

if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_pending_user'])) {
    send_error("No pending login session. Please log in again.");
}

// 1. Check expiration (5 minutes)
if (time() > $_SESSION['otp_expiry']) {
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_expiry']);
    unset($_SESSION['otp_attempts']);
    unset($_SESSION['otp_pending_user']);
    send_error("Verification code has expired. Please log in again.");
}

// 2. Verify code
if ($_SESSION['otp_code'] === $otp) {
    // Correct OTP: Establish session!
    $user = $_SESSION['otp_pending_user'];
    
    if ($user['type'] === 'admin') {
        $stmt_mfa = $conn->prepare("SELECT mfa_enabled FROM admins WHERE admin_id = ?");
        $stmt_mfa->bind_param("i", $user['user_id']);
        $stmt_mfa->execute();
        $res_mfa = $stmt_mfa->get_result()->fetch_assoc();
        
        if ($res_mfa && $res_mfa['mfa_enabled']) {
            $_SESSION['google_mfa_pending_admin'] = [
                "admin_id" => $user['user_id'],
                "admin_name" => $user['full_name'],
                "role" => $user['role']
            ];
            unset($_SESSION['otp_code']);
            unset($_SESSION['otp_expiry']);
            unset($_SESSION['otp_attempts']);
            unset($_SESSION['otp_pending_user']);
            
            send_success("Google MFA verification required.", [
                "google_mfa_required" => true
            ]);
        }
        
        $_SESSION['admin_id'] = $user['user_id'];
        $_SESSION['admin_name'] = $user['full_name'];
        log_activity('admin', $user['user_id'], 'Logged in successfully via MFA');
    } else {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['first_login'] = $user['first_login'];
        log_activity('user', $user['user_id'], 'Logged in successfully via MFA');
    }

    // Clean up OTP session data
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_expiry']);
    unset($_SESSION['otp_attempts']);
    unset($_SESSION['otp_pending_user']);

    send_success("Login successful.", [
        "user_id" => $user['user_id'],
        "full_name" => $user['full_name'],
        "role" => $user['role'],
        "first_login" => (bool)$user['first_login']
    ]);
} else {
    // Incorrect OTP: Increment attempts
    $_SESSION['otp_attempts']++;
    $remaining = 3 - $_SESSION['otp_attempts'];
    $user = $_SESSION['otp_pending_user'];

    if ($remaining <= 0) {
        log_activity($user['type'], $user['user_id'], "MFA verification locked out due to exceeding maximum attempts");
        // Exceeded limit: lock out OTP session
        unset($_SESSION['otp_code']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['otp_attempts']);
        unset($_SESSION['otp_pending_user']);
        send_error("Too many failed verification attempts. Please log in again.", 403);
    } else {
        log_activity($user['type'], $user['user_id'], "Failed MFA verification attempt (Remaining attempts: $remaining)");
        send_error("Invalid verification code. You have $remaining attempts remaining.", 401);
    }
}
?>
