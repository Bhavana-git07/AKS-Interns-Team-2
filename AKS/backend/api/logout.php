<?php
// api/logout.php
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();

if (isset($_SESSION['admin_id'])) {
    log_activity('admin', $_SESSION['admin_id'], 'Logged out successfully');
} elseif (isset($_SESSION['user_id'])) {
    log_activity('user', $_SESSION['user_id'], 'Logged out successfully');
}

$_SESSION = [];
session_destroy();

send_success("Logged out successfully.");
