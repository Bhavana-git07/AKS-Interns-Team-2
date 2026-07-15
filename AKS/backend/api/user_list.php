<?php
// api/user_list.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();

$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

if ($company_id) {
    $stmt = $conn->prepare("SELECT user_id, company_id, full_name, email, role, first_login, created_at FROM users WHERE company_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT user_id, company_id, full_name, email, role, first_login, created_at FROM users ORDER BY created_at DESC");
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $row['first_login'] = (bool)$row['first_login'];
    $users[] = $row;
}

send_success("Users retrieved.", $users);
