<?php
// api/control_list.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    send_error("Unauthorized. Please log in.", 401);
}

$result = $conn->query("SELECT control_id, control_code, control_name, description, framework_id FROM controls ORDER BY control_code ASC");

$controls = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $controls[] = $row;
    }
}

send_success("Controls retrieved.", $controls);
?>
