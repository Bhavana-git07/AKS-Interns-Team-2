<?php
// api/document_list.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();

$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

// If a regular user is logged in, restrict to their company
if (isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    $company_id = $_SESSION['company_id'];
}

if ($company_id) {
    $stmt = $conn->prepare("SELECT document_id, company_id, file_name, file_path, framework, control_code, status, created_at FROM documents WHERE company_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT document_id, company_id, file_name, file_path, framework, control_code, status, created_at FROM documents ORDER BY created_at DESC");
}

$documents = [];
while ($row = $result->fetch_assoc()) {
    $absolutePath = __DIR__ . '/../' . str_replace('../', '', $row['file_path']);
    $row['file_size'] = file_exists($absolutePath) ? filesize($absolutePath) : 0;
    $documents[] = $row;
}

send_success("Documents retrieved.", $documents);
?>
