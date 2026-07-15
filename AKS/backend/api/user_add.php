<?php
// api/user_add.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$company_id = (int)($input['company_id'] ?? 0);
$full_name = trim($input['full_name'] ?? '');
$email = trim($input['email'] ?? '');
$role = trim($input['role'] ?? 'user');

if ($company_id <= 0 || empty($full_name) || empty($email)) {
    send_error("Company, full name, and email are required.");
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

// Check company exists
$check = $conn->prepare("SELECT company_id FROM companies WHERE company_id = ?");
$check->bind_param("i", $company_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    send_error("Company does not exist.");
}

// Check duplicate email
$dup = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$dup->bind_param("s", $email);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    send_error("A user with this email already exists.");
}

$tempPassword = trim($input['password'] ?? '');
if (empty($tempPassword) || $tempPassword === '—') {
    $tempPassword = generate_temp_password();
}
$hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (company_id, full_name, email, password, role, first_login) VALUES (?, ?, ?, ?, ?, TRUE)");
$stmt->bind_param("issss", $company_id, $full_name, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    // Returning the temp password here ONLY so the admin can hand it to
    // the user (email it, show it once on screen, etc). Do not log this
    // anywhere persistent.
    send_success("User created successfully.", [
        "user_id" => $stmt->insert_id,
        "temporary_password" => $tempPassword
    ], 201);
} else {
    send_error("Failed to create user.", 500);
}
