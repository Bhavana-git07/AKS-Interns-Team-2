<?php
// api/resend_otp.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';
require_once '../config/mailer.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

if (empty($_SESSION['otp_pending_user'])) {
    send_error("No pending login session. Please log in again.");
}

$user = $_SESSION['otp_pending_user'];

// Generate new OTP
$otp = sprintf("%06d", random_int(100000, 999999));
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expiry'] = time() + 300; // 5 minutes
$_SESSION['otp_attempts'] = 0;

// Send verification code via mailer
SimpleMailer::send($user['email'], "Your OTP Verification Code (Resent)", $otp);

send_success("A new verification code has been sent.", [
    "email" => $user['email']
]);
?>
