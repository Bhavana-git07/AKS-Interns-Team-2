<?php
// api/user_delete.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$user_id = (int)($input['user_id'] ?? 0);

if ($user_id <= 0) {
    send_error("Valid user ID is required.");
}

$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        send_error("User not found.", 404);
    }
    send_success("User deleted successfully.");
} else {
    send_error("Failed to delete user.", 500);
}
