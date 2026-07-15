<?php
// api/change_password.php
// This is the endpoint the frontend calls from the "Change Password"
// page that users get redirected to when first_login = TRUE.
// It is intentionally NOT behind block_if_first_login(), since a
// first-login user needs to reach exactly this endpoint to escape
// that state. Every OTHER user-facing API should call
// block_if_first_login() right after require_user_login().

require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_user_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    send_error("Current and new password are required.");
}

if (!is_password_strong($new_password)) {
    send_error("Password must be 8+ characters and include uppercase, lowercase, a number, and a special character.");
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error("User not found.", 404);
}

$user = $result->fetch_assoc();

if (!password_verify($current_password, $user['password'])) {
    send_error("Current password is incorrect.", 401);
}

$newHashed = password_hash($new_password, PASSWORD_DEFAULT);

$update = $conn->prepare("UPDATE users SET password = ?, first_login = FALSE WHERE user_id = ?");
$update->bind_param("si", $newHashed, $user_id);

if ($update->execute()) {
    $_SESSION['first_login'] = false; // keep session flag in sync
    send_success("Password changed successfully.");
} else {
    send_error("Failed to change password.", 500);
}
