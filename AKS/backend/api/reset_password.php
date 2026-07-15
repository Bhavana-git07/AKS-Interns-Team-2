<?php
// api/reset_password.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$user_id = (int)($input['user_id'] ?? 0);

if ($user_id <= 0) {
    send_error("User ID is required.");
}

$tempPassword = trim($input['password'] ?? '');
if (empty($tempPassword) || $tempPassword === '—') {
    $tempPassword = generate_temp_password();
}
$hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ?, first_login = TRUE WHERE user_id = ?");
$stmt->bind_param("si", $hashedPassword, $user_id);

if ($stmt->execute()) {
    send_success("Password reset successfully.", [
        "temporary_password" => $tempPassword
    ]);
} else {
    send_error("Failed to reset password.", 500);
}
?>
