<?php
// api/user_update.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$user_id = (int)($input['user_id'] ?? 0);
$full_name = trim($input['full_name'] ?? '');
$email = trim($input['email'] ?? '');
$role = trim($input['role'] ?? 'user');

if ($user_id <= 0 || empty($full_name) || empty($email)) {
    send_error("User ID, full name, and email are required.");
}

// XSS & Input Validation: Check full_name doesn't contain HTML, and role is allowed
if (has_html_tags($full_name)) {
    send_error("Full name must not contain HTML/script tags.");
}

$allowed_roles = ['admin', 'user'];
if (!in_array($role, $allowed_roles)) {
    send_error("Invalid role value.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_error("Invalid email address.");
}

// Check duplicate email on a DIFFERENT user
$dup = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$dup->bind_param("si", $email, $user_id);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    send_error("Another user already uses this email.");
}

$stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?");
$stmt->bind_param("sssi", $full_name, $email, $role, $user_id);

if ($stmt->execute()) {
    send_success("User updated successfully.");
} else {
    send_error("Failed to update user.", 500);
}
