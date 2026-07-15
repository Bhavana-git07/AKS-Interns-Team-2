<?php
// config/auth.php
// Shared auth helpers. Require this in any controller that needs
// session checks or password rules, instead of rewriting the logic.

function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        
        $secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            session_set_cookie_params(
                0,
                '/; SameSite=Lax',
                '',
                $secure,
                true
            );
        }
        
        session_start();
    }

    // Session Timeout check (15 minutes = 900 seconds)
    $timeout_duration = 900;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        
        session_start();
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

// Use this at the top of ANY admin-only API endpoint
function require_admin_login() {
    start_secure_session();
    if (!isset($_SESSION['admin_id'])) {
        send_error("Unauthorized. Please log in.", 401);
    }
}

// Use this at the top of ANY user-only API endpoint
function require_user_login() {
    start_secure_session();
    if (!isset($_SESSION['user_id'])) {
        send_error("Unauthorized. Please log in.", 401);
    }
}

// Blocks access to everything except the change-password endpoint
// until the user has changed their temporary password.
function block_if_first_login() {
    start_secure_session();
    if (isset($_SESSION['first_login']) && $_SESSION['first_login'] == 1) {
        send_error("Password change required before continuing.", 403);
    }
}

// Centralized password complexity rule — reuse this everywhere
// a password is created or changed, so the rule never drifts.
function is_password_strong($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[\W_]/', $password)) return false; // special char
    return true;
}

// Generates a random temporary password that ALSO satisfies the
// complexity rule above (the original md5(rand()) approach doesn't
// guarantee uppercase/lowercase/number/symbol every time).
function generate_temp_password($length = 10) {
    $upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    $lower = "abcdefghijkmnpqrstuvwxyz";
    $number = "23456789";
    $symbol = "!@#$%&*";

    $password = $upper[random_int(0, strlen($upper) - 1)]
              . $lower[random_int(0, strlen($lower) - 1)]
              . $number[random_int(0, strlen($number) - 1)]
              . $symbol[random_int(0, strlen($symbol) - 1)];

    $all = $upper . $lower . $number . $symbol;
    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

function verify_csrf_token() {
    $headers = getallheaders();
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (empty($token)) {
        $input = json_decode(file_get_contents("php://input"), true);
        $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    }

    if (empty($token)) {
        $token = $_GET['csrf_token'] ?? '';
    }

    start_secure_session();

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die(json_encode([
            "success" => false,
            "message" => "CSRF validation failed."
        ]));
    }
}

function check_account_lockout($email) {
    global $conn;
    $window_minutes = 15;
    $max_attempts = 5;

    // Clean up expired attempts
    $stmt_cleanup = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt_cleanup->bind_param("i", $window_minutes);
    $stmt_cleanup->execute();

    // Count failed attempts
    $stmt_count = $conn->prepare("SELECT COUNT(*) AS failed_count FROM login_attempts WHERE email = ?");
    $stmt_count->bind_param("s", $email);
    $stmt_count->execute();
    $failed_count = $stmt_count->get_result()->fetch_assoc()['failed_count'];

    if ($failed_count >= $max_attempts) {
        http_response_code(403);
        die(json_encode([
            "success" => false,
            "message" => "This account has been temporarily locked due to too many failed login attempts. Please try again after 15 minutes."
        ]));
    }
}

function clear_login_attempts($email) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

function record_failed_attempt($email, $actor_type = 'admin') {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $window_minutes = 15;
    $max_attempts = 5;

    // Record attempt
    $stmt_log = $conn->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt_log->bind_param("ss", $email, $ip);
    $stmt_log->execute();

    // Log to audit log
    log_activity($actor_type, 0, "Failed login attempt for email: $email");

    // Get new count
    $stmt_count = $conn->prepare("SELECT COUNT(*) AS failed_count FROM login_attempts WHERE email = ?");
    $stmt_count->bind_param("s", $email);
    $stmt_count->execute();
    $failed_count = $stmt_count->get_result()->fetch_assoc()['failed_count'];

    $remaining = $max_attempts - $failed_count;
    if ($remaining > 0) {
        http_response_code(401);
        die(json_encode([
            "success" => false,
            "message" => "Invalid email or password. You have $remaining attempts remaining before account lockout."
        ]));
    } else {
        http_response_code(403);
        die(json_encode([
            "success" => false,
            "message" => "Invalid email or password. This account has now been temporarily locked for 15 minutes."
        ]));
    }
}

function log_activity($actor_type, $actor_id, $action) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $conn->prepare("INSERT INTO activity_logs (actor_type, actor_id, action, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $actor_type, $actor_id, $action, $ip);
    $stmt->execute();
}

// RAG text extraction: PDF format parser
function extract_pdf_text($filename) {
    $content = file_exists($filename) ? file_get_contents($filename) : "";
    if (empty($content)) return "";
    
    // Direct text extraction from PDF stream objects
    preg_match_all("/\((.*?)\)\s*Tj/s", $content, $matches);
    $text = "";
    if (!empty($matches[1])) {
        foreach ($matches[1] as $m) {
            $text .= str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $m) . " ";
        }
    }
    
    preg_match_all("/\[(.*?)\]\s*TJ/s", $content, $matches_tj);
    if (!empty($matches_tj[1])) {
        foreach ($matches_tj[1] as $m) {
            preg_match_all("/\((.*?)\)/", $m, $sub_matches);
            if (!empty($sub_matches[1])) {
                foreach ($sub_matches[1] as $sm) {
                    $text .= str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $sm) . " ";
                }
            }
        }
    }
    
    $text = preg_replace('/[[:cntrl:]]/', '', $text);
    return trim($text);
}

// RAG text extraction: Word format parser
function extract_docx_text($filename) {
    if (!class_exists('ZipArchive') || !file_exists($filename)) return "";
    $zip = new ZipArchive();
    if ($zip->open($filename) === true) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml) {
            return trim(strip_tags($xml));
        }
    }
    return "";
}

// RAG text extraction: Excel format parser
function extract_xlsx_text($filename) {
    if (!class_exists('ZipArchive') || !file_exists($filename)) return "";
    $zip = new ZipArchive();
    if ($zip->open($filename) === true) {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        if ($xml) {
            return trim(strip_tags($xml));
        }
    }
    return "";
}

// RAG search: keyword-based query similarity ranker
function search_relevant_documents($query, $company_id = null) {
    global $conn;
    
    // Get all documents containing text
    $sql = "SELECT document_id, file_name, extracted_text, framework, control_code FROM documents WHERE extracted_text IS NOT NULL AND extracted_text != ''";
    if ($company_id) {
        $sql .= " AND company_id = " . intval($company_id);
    }
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return [];
    }
    
    // Clean query and extract keywords
    $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'about', 'by', 'what', 'how', 'who', 'tell', 'show', 'list'];
    $words = preg_split('/\s+/', strtolower($query));
    $keywords = [];
    foreach ($words as $w) {
        $w = preg_replace('/[^a-z0-9]/', '', $w);
        if (!empty($w) && !in_array($w, $stop_words)) {
            $keywords[] = $w;
        }
    }
    
    if (empty($keywords)) {
        return [];
    }
    
    $scored_docs = [];
    while ($row = $result->fetch_assoc()) {
        $text_lower = strtolower($row['extracted_text']);
        $score = 0;
        foreach ($keywords as $kw) {
            $score += substr_count($text_lower, $kw);
        }
        
        if ($score > 0) {
            $row['score'] = $score;
            $scored_docs[] = $row;
        }
    }
    
    // Sort by score descending
    usort($scored_docs, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($scored_docs, 0, 2);
}

// Global XSS check: checks if string contains HTML tags
function has_html_tags($str) {
    return strip_tags($str) !== $str;
}
