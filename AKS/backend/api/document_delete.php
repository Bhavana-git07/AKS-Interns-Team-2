<?php
// api/document_delete.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

start_secure_session();
verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$document_id = (int)($input['document_id'] ?? 0);

if ($document_id <= 0) {
    send_error("Document ID is required.");
}

// Fetch file path to delete physical file
$stmt = $conn->prepare("SELECT file_path FROM documents WHERE document_id = ?");
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error("Document not found.", 404);
}

$doc = $result->fetch_assoc();
$file_path = $doc['file_path'];

// Delete physical file
// Since path is stored as '../uploads/filename', let's delete it.
if (file_exists($file_path)) {
    unlink($file_path);
}

// Delete database row
$del = $conn->prepare("DELETE FROM documents WHERE document_id = ?");
$del->bind_param("i", $document_id);

if ($del->execute()) {
    send_success("Document deleted successfully.");
} else {
    send_error("Failed to delete document from database.", 500);
}
?>
