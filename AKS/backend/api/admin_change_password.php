<?php
// api/admin_change_password.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
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

$admin_id = $_SESSION['admin_id'];

$stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error("Admin user not found.", 404);
}

$admin = $result->fetch_assoc();

if (!password_verify($current_password, $admin['password'])) {
    send_error("Current password is incorrect.", 401);
}

$newHashed = password_hash($new_password, PASSWORD_DEFAULT);

$update = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
$update->bind_param("si", $newHashed, $admin_id);

if ($update->execute()) {
    log_activity('admin', $admin_id, 'Changed administrator password');
    send_success("Password changed successfully.");
} else {
    send_error("Failed to change password.", 500);
}
?>
