<?php
// api/login.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';
require_once '../config/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    send_error("Email and password are required.");
}

check_account_lockout($email);

// Prepared statement — NEVER concatenate user input into SQL.
$stmt = $conn->prepare("SELECT admin_id, name, email, password FROM admins WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    record_failed_attempt($email, 'admin');
}

$admin = $result->fetch_assoc();

if (!password_verify($password, $admin['password'])) {
    record_failed_attempt($email, 'admin');
}

clear_login_attempts($email);

start_secure_session();
session_regenerate_id(true);

$otp = sprintf("%06d", random_int(100000, 999999));
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expiry'] = time() + 300; // 5 minutes
$_SESSION['otp_attempts'] = 0;
$_SESSION['otp_pending_user'] = [
    "user_id" => $admin['admin_id'],
    "type" => "admin",
    "full_name" => $admin['name'],
    "email" => $admin['email'],
    "role" => "admin",
    "first_login" => false
];

// Send verification code via mailer
SimpleMailer::send($admin['email'], "Your OTP Verification Code", $otp);

send_success("OTP sent to email.", [
    "otp_required" => true,
    "email" => $admin['email']
]);
