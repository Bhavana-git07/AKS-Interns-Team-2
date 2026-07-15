<?php
include '../config/database.php';
require_once '../config/auth.php';

verify_csrf_token();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $company_id = (int)$_POST['company_id'];
    $framework = isset($_POST['framework']) ? trim($_POST['framework']) : null;
    $control_code = isset($_POST['control_code']) ? trim($_POST['control_code']) : null;

    if ($company_id <= 0) {
        die(json_encode(["success" => false, "message" => "Invalid Company ID"]));
    }

    if (($framework && has_html_tags($framework)) || ($control_code && has_html_tags($control_code))) {
        die(json_encode(["success" => false, "message" => "Inputs must not contain HTML/script tags."]));
    }

    // Backend File Validation (Whitelisted extensions, Max 10MB size)
    $allowed_extensions = ['pdf', 'xlsx', 'xls', 'docx', 'doc', 'png', 'jpg', 'jpeg', 'txt'];
    $extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
        die(json_encode(["success" => false, "message" => "Invalid file type. Allowed formats: PDF, XLSX, XLS, DOCX, DOC, PNG, JPG, TXT."]));
    }
    
    if ($_FILES['document']['size'] > 10 * 1024 * 1024) { // 10MB
        die(json_encode(["success" => false, "message" => "File is too large. Maximum allowed size is 10MB."]));
    }

    $folder = "../uploads/";

    // Ensure uploads directory exists
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }

    // Sanitize filename to prevent XSS and Directory Traversal
    $orig_name = basename($_FILES['document']['name']);
    $clean_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
    $file_name = time() . "_" . $clean_name;

    $file_path = $folder . $file_name;

    if (move_uploaded_file(
        $_FILES['document']['tmp_name'],
        $file_path
    )) {

        $stmt = $conn->prepare(
            "INSERT INTO documents
            (company_id, file_name, file_path, framework, control_code)
            VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issss",
            $company_id,
            $file_name,
            $file_path,
            $framework,
            $control_code
        );

        $stmt->execute();
        $doc_id = $conn->insert_id;

        // RAG: Extract and save document text for semantic search context
        $extracted_text = "";
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($ext === 'txt') {
            $extracted_text = file_get_contents($file_path);
        } elseif ($ext === 'pdf') {
            $extracted_text = extract_pdf_text($file_path);
        } elseif ($ext === 'docx') {
            $extracted_text = extract_docx_text($file_path);
        } elseif ($ext === 'xlsx') {
            $extracted_text = extract_xlsx_text($file_path);
        }

        if (!empty($extracted_text)) {
            $stmt_update = $conn->prepare("UPDATE documents SET extracted_text = ? WHERE document_id = ?");
            $stmt_update->bind_param("si", $extracted_text, $doc_id);
            $stmt_update->execute();
        }

        $actor_type = isset($_SESSION['admin_id']) ? 'admin' : 'user';
        $actor_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
        log_activity($actor_type, $actor_id, "Uploaded evidence document: $file_name");
 
        echo json_encode([
            "success"=>true
        ]);
    } else {
        echo json_encode([
            "success"=>false,
            "message"=>"Failed to move uploaded file."
        ]);
    }
}
?>