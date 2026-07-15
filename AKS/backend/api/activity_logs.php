<?php
// api/activity_logs.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();

$query = "
    SELECT 
        l.log_id,
        l.actor_type,
        l.actor_id,
        l.action,
        l.ip_address,
        l.created_at,
        CASE 
            WHEN l.actor_type = 'admin' THEN a.name
            WHEN l.actor_type = 'user' THEN u.full_name
            ELSE 'System'
        END as actor_name
    FROM activity_logs l
    LEFT JOIN admins a ON l.actor_type = 'admin' AND l.actor_id = a.admin_id
    LEFT JOIN users u ON l.actor_type = 'user' AND l.actor_id = u.user_id
    ORDER BY l.created_at DESC
    LIMIT 200
";

$result = $conn->query($query);
$logs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

send_success("Activity logs retrieved.", $logs);
?>
