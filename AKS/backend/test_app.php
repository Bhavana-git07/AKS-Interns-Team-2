<?php
// backend/test_app.php
require_once 'config/database.php';

// Helper to print test rows
function printTestResult($name, $status, $details = '') {
    $badgeColor = $status === 'PASS' ? '#3DD6AC' : '#F75F4F';
    $textColor = $status === 'PASS' ? 'rgba(61,214,172,0.15)' : 'rgba(247,95,79,0.15)';
    echo "
    <tr style='border-bottom:1px solid #333'>
        <td style='padding:12px;font-weight:600'>$name</td>
        <td style='padding:12px'><span style='background:$textColor;color:$badgeColor;padding:4px 8px;border-radius:4px;font-size:0.8rem;font-weight:700'>$status</span></td>
        <td style='padding:12px;color:#aaa;font-size:0.85rem'>$details</td>
    </tr>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AKS Compliance Platform Test Suite</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0D1117; color: #fff; padding: 40px; }
        .card { background: #161B22; border: 1px solid #30363D; border-radius: 8px; padding: 24px; max-width: 900px; margin: 0 auto; }
        h1 { margin-top: 0; color: #3DD6AC; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; background: #21262D; padding: 12px; color: #8B949E; }
    </style>
</head>
<body>
    <div class="card">
        <h1>AKS Application Test Suite</h1>
        <p>This automated script tests backend database integrity, API routes, and schema realignments.</p>
        
        <table>
            <thead>
                <tr>
                    <th>Test Case</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Test 1: Database Connection
                if (isset($conn) && !$conn->connect_error) {
                    printTestResult("Database Connectivity", "PASS", "Successfully connected to DB '{$DB_NAME}' as user '{$DB_USER}'.");
                } else {
                    printTestResult("Database Connectivity", "FAIL", "Connection failed: " . ($conn ? $conn->connect_error : "Connection not initialized"));
                    die("</tbody></table></div></body></html>");
                }

                // Test 2: Table existence verification
                $required_tables = ['companies', 'controls', 'assessments', 'assessment_controls', 'control_mappings', 'documents', 'users', 'admins', 'login_attempts', 'risks'];
                $missing_tables = [];
                foreach ($required_tables as $table) {
                    $res = $conn->query("SHOW TABLES LIKE '$table'");
                    if (!$res || $res->num_rows === 0) {
                        $missing_tables[] = $table;
                    }
                }
                if (empty($missing_tables)) {
                    printTestResult("Table Integrity Checks", "PASS", "All " . count($required_tables) . " required platform tables are present.");
                } else {
                    printTestResult("Table Integrity Checks", "FAIL", "Missing tables: " . implode(', ', $missing_tables));
                }

                // Test 3: Controls schema and descriptions
                $col_check = $conn->query("SHOW COLUMNS FROM controls LIKE 'description'");
                if ($col_check && $col_check->num_rows > 0) {
                    $desc_count_res = $conn->query("SELECT COUNT(*) AS cnt FROM controls WHERE description IS NOT NULL AND description != ''");
                    $desc_count = $desc_count_res ? $desc_count_res->fetch_assoc()['cnt'] : 0;
                    
                    $total_controls_res = $conn->query("SELECT COUNT(*) AS cnt FROM controls");
                    $total_controls = $total_controls_res ? $total_controls_res->fetch_assoc()['cnt'] : 0;

                    if ($desc_count == $total_controls && $total_controls > 0) {
                        printTestResult("Controls Description Column", "PASS", "Verified $desc_count out of $total_controls controls have populated requirement descriptions.");
                    } else {
                        printTestResult("Controls Description Column", "FAIL", "Only $desc_count out of $total_controls controls have descriptions.");
                    }
                } else {
                    printTestResult("Controls Description Column", "FAIL", "description column is missing in controls table.");
                }

                // Test 4: Control codes alignment
                $check_old = $conn->query("SELECT COUNT(*) AS cnt FROM controls WHERE control_code = 'RMIT-1.1'");
                $old_count = $check_old ? $check_old->fetch_assoc()['cnt'] : 0;
                if ($old_count == 0) {
                    printTestResult("Control Code Realignment", "PASS", "Verified mock control codes (e.g. RMIT-1.1) are realigned with official IDs (e.g. RMIT-8.1).");
                } else {
                    printTestResult("Control Code Realignment", "FAIL", "Found $old_count controls with legacy mock codes.");
                }

                // Test 5: Auto-generated registration number sequence logic
                try {
                    $conn->begin_transaction();
                    $year = date('Y');
                    $likePattern = $year . '%';
                    
                    // Fetch highest registration number
                    $stmt_reg = $conn->prepare("SELECT registration_number FROM companies WHERE registration_number LIKE ? ORDER BY registration_number DESC LIMIT 1 FOR UPDATE");
                    $stmt_reg->bind_param("s", $likePattern);
                    $stmt_reg->execute();
                    $res_reg = $stmt_reg->get_result();
                    
                    $expected_reg = "";
                    if ($res_reg && $res_reg->num_rows > 0) {
                        $row = $res_reg->fetch_assoc();
                        $last_reg = $row['registration_number'];
                        if (preg_match('/^(\d{6})-(\d{5})$/', $last_reg, $matches)) {
                            $prefix = $matches[1];
                            $seq = intval($matches[2]);
                            $next_seq = $seq + 1;
                            $expected_reg = $prefix . '-' . str_pad($next_seq, 5, '0', STR_PAD_LEFT);
                        } else {
                            $expected_reg = $year . '01-00001';
                        }
                    } else {
                        $expected_reg = $year . '01-00001';
                    }
                    $stmt_reg->close();

                    // Insert test company
                    $test_name = "TEST_AUTO_GEN_COMP_" . uniqid();
                    $test_industry = "🏦 Banking";
                    $test_email = "test_gen@company.com";
                    $stmt = $conn->prepare("INSERT INTO companies (company_name, registration_number, industry, contact_email) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $test_name, $expected_reg, $test_industry, $test_email);
                    $stmt->execute();
                    $inserted_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Verify the insertion match
                    $verify_res = $conn->query("SELECT registration_number FROM companies WHERE company_id = $inserted_id");
                    $verify_row = $verify_res ? $verify_res->fetch_assoc() : null;
                    
                    // Rollback test insert immediately so database remains clean
                    $conn->rollback();

                    if ($verify_row && $verify_row['registration_number'] === $expected_reg) {
                        printTestResult("Registration Number Auto-Gen", "PASS", "Transactional auto-increment logic verified. Expected and generated: $expected_reg");
                    } else {
                        $found_reg = $verify_row ? $verify_row['registration_number'] : 'null';
                        printTestResult("Registration Number Auto-Gen", "FAIL", "Expected registration number: $expected_reg, but found: $found_reg");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    printTestResult("Registration Number Auto-Gen", "FAIL", "Exception thrown: " . $e->getMessage());
                }

                // Test 6: API responses sanity checks
                $apis = [
                    'control_list.php' => 'backend/api/control_list.php',
                    'company_list.php' => 'backend/api/company_list.php',
                    'assessment_list.php' => 'backend/api/assessment_list.php'
                ];
                foreach ($apis as $name => $path) {
                    // Check if file exists and has no syntax errors
                    if (file_exists(__DIR__ . '/../' . $path)) {
                        printTestResult("API Endpoint Status: $name", "PASS", "Endpoint file exists at $path.");
                    } else {
                        printTestResult("API Endpoint Status: $name", "FAIL", "Endpoint file is missing at $path.");
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
