<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../config/database.php';
require_once '../config/auth.php';

verify_csrf_token();

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    die("No JSON data received");
}

$company_id = isset($data['company_id']) ? intval($data['company_id']) : 0;
$document_id = isset($data['document_id']) ? intval($data['document_id']) : 0;

if ($company_id <= 0 || $document_id <= 0) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid company ID or document ID"]));
}

/* Insert into audits table */
$stmt = $conn->prepare(
    "INSERT INTO audits (company_id, document_id)
     VALUES (?, ?)"
);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ii", $company_id, $document_id);

if (!$stmt->execute()) {
    die("Audit insert failed: " . $stmt->error);
}

$audit_id = $conn->insert_id;

/* Default checklist items */
$items = [
    "Policy Uploaded",
    "Document Reviewed",
    "Risk Assessment",
    "Control Verification",
    "Final Approval"
];

/* Insert checklist items */
foreach ($items as $item) {

    $stmt2 = $conn->prepare(
        "INSERT INTO audit_checklist
        (audit_id, checklist_item)
        VALUES (?, ?)"
    );

    if (!$stmt2) {
        die("Checklist prepare failed: " . $conn->error);
    }

    $stmt2->bind_param("is", $audit_id, $item);

    if (!$stmt2->execute()) {
        die("Checklist insert failed: " . $stmt2->error);
    }
}

echo json_encode([
    "success" => true,
    "audit_id" => $audit_id
]);
?>