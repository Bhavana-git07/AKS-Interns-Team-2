<?php
// config/response.php
// Every API endpoint should use these so Member 1's frontend can rely on
// ONE consistent JSON shape: {success, message, data}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // tighten this to your frontend's actual origin before going live
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");

// Handle preflight requests from the browser
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function send_success($message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        "success" => true,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        "success" => false,
        "message" => $message,
        "data" => null
    ]);
    exit();
}
