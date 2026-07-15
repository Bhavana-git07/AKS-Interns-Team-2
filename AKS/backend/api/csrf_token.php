<?php
// api/csrf_token.php
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

send_success("CSRF token retrieved.", [
    "csrf_token" => $_SESSION['csrf_token']
]);
?>
