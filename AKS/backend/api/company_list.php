<?php
// api/company_list.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';

require_admin_login();

$result = $conn->query("SELECT company_id, company_name, registration_number, industry, address, contact_email, contact_phone, created_at FROM companies ORDER BY created_at DESC");

$companies = [];
while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
}

send_success("Companies retrieved.", $companies);
