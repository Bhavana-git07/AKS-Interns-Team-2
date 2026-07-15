<?php
// api/ai_chat.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/auth.php';
require_once '../config/ai_config.php';

// 1. Verify CSRF Token
verify_csrf_token();

start_secure_session();

// Ensure user or admin is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    send_error("Unauthorized access. Please log in.", 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Invalid request method.", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
$user_message = trim($input['message'] ?? '');

if (empty($user_message)) {
    send_error("Message is required.");
}

// RAG: Dynamic backfill check for existing documents that do not have text extracted yet
$res_backfill = $conn->query("SELECT document_id, file_path FROM documents WHERE extracted_text IS NULL OR extracted_text = ''");
if ($res_backfill) {
    while ($row = $res_backfill->fetch_assoc()) {
        $doc_id = intval($row['document_id']);
        $file_path = __DIR__ . '/../' . str_replace('../', '', $row['file_path']);
        if (file_exists($file_path)) {
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
                $stmt_up = $conn->prepare("UPDATE documents SET extracted_text = ? WHERE document_id = ?");
                $stmt_up->bind_param("si", $extracted_text, $doc_id);
                $stmt_up->execute();
            }
        }
    }
}

// RAG: Search relevant documents matching user message.
$user_company_id = isset($_SESSION['admin_id']) ? null : ($_SESSION['company_id'] ?? null);
$matched_docs = search_relevant_documents($user_message, $user_company_id);

// Format retrieved context
$retrieved_context = "";
if (!empty($matched_docs)) {
    $retrieved_context .= "\n\nRetrieved Context from Company Evidence Vault:\n";
    foreach ($matched_docs as $doc) {
        $retrieved_context .= "--- Document: " . $doc['file_name'] . " (Framework: " . $doc['framework'] . ", Control: " . $doc['control_code'] . ") ---\n";
        $retrieved_context .= substr($doc['extracted_text'], 0, 800) . "\n\n";
    }
}

// 2. Fetch current compliance statistics from database to feed context
$company_count = 0;
$doc_count = 0;
$active_audits = 0;
$completed_audits = 0;
$avg_progress = 0.0;
$risk_count = 0;
$avg_risk_score = 0.0;

$res = $conn->query("SELECT COUNT(*) as count FROM companies");
if ($res) { $company_count = intval($res->fetch_assoc()['count']); }

$res = $conn->query("SELECT COUNT(*) as count FROM documents");
if ($res) { $doc_count = intval($res->fetch_assoc()['count']); }

$res = $conn->query("SELECT compliance_percentage, COUNT(*) as count FROM assessments GROUP BY compliance_percentage");
if ($res) {
    $total_audits = 0;
    $sum_progress = 0.0;
    while ($row = $res->fetch_assoc()) {
        $pct = floatval($row['compliance_percentage']);
        $cnt = intval($row['count']);
        if ($pct >= 100) {
            $completed_audits += $cnt;
        } else {
            $active_audits += $cnt;
        }
        $sum_progress += ($pct * $cnt);
        $total_audits += $cnt;
    }
    if ($total_audits > 0) {
        $avg_progress = round($sum_progress / $total_audits, 1);
    }
}

$res = $conn->query("SELECT COUNT(*) as count, AVG(risk_score) as avg_score FROM risks");
if ($res) {
    $row = $res->fetch_assoc();
    $risk_count = intval($row['count']);
    $avg_risk_score = $row['avg_score'] ? round(floatval($row['avg_score']), 1) : 0.0;
}

// 3. Build AI Context
$system_context = "You are the AKS Compliance Platform AI assistant. Here is the current system database state:\n";
$system_context .= "- Total registered companies: $company_count\n";
$system_context .= "- Total uploaded evidence documents: $doc_count\n";
$system_context .= "- Total assessments: " . ($active_audits + $completed_audits) . " (active: $active_audits, completed: $completed_audits, average progress: $avg_progress%)\n";
$system_context .= "- Total registered risks: $risk_count (average risk score: $avg_risk_score/25)\n\n";
$system_context .= "Answer the user's questions utilizing this database state when appropriate. Respond professionally as a Lead Cybersecurity Compliance Auditor. Format responses in clean Markdown.";

$is_summary_req = false;
$q_lower = strtolower($user_message);
if (strpos($q_lower, 'executive summary') !== false || strpos($q_lower, 'generate summary') !== false || strpos($q_lower, 'compliance summary') !== false || strpos($q_lower, 'platform report') !== false) {
    $is_summary_req = true;
}

// 4. Query execution using Gemini or local analyzer fallback
if ($ai_api_key && $ai_provider === 'gemini') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . urlencode($ai_api_key);
    $prompt = $system_context . $retrieved_context . "\n\nUser Request: " . $user_message;
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $res_data = json_decode($response, true);
        $ai_text = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!empty($ai_text)) {
            send_success("AI Response generated.", ["response" => $ai_text]);
        }
    }
}

// Local Compliance Chatbot Analyzer Fallback
$ai_text = "";

// Intent 1: Greeting
if (preg_match('/\b(hello|hi|hey|greetings|hola|good morning|good afternoon)\b/', $q_lower)) {
    $ai_text = "### 👋 Welcome to the AI Compliance Assistant!\n\n";
    $ai_text .= "I am your conversational compliance auditor. I am currently running offline using the local system analyzer and have access to your live database records.\n\n";
    $ai_text .= "Here are some live queries you can ask me:\n";
    $ai_text .= "- 📊 `Generate an Executive Summary` to review framework progress.\n";
    $ai_text .= "- 🏢 `Show registered companies` to see client accounts.\n";
    $ai_text .= "- 👥 `Show user directory` to audit user listings.\n";
    $ai_text .= "- 📎 `What evidence files are uploaded?` to verify documents.\n";
    $ai_text .= "- 📋 Query framework standards (e.g., `MAS TRM guidelines`, `BNM RMiT compliance`, or `PayNet TPA`).\n\n";
    $ai_text .= "*Tip: You can also search for specific names (e.g., `who is Sarah?` or `tell me about Bank of Malaya`).*";
}
// Intent 2: Capabilities / Help
elseif (preg_match('/\b(help|commands|what can you do|capabilities|options)\b/', $q_lower)) {
    $ai_text = "### 🛡️ AI Audit Assistant Commands & Guidance\n\n";
    $ai_text .= "I can analyze and trace your workspace assets. Here is what you can ask me:\n\n";
    $ai_text .= "* **System Audits**: `system status`, `compliance summary`, or `statistics`.\n";
    $ai_text .= "* **Company Lookup**: Query any company name directly (e.g. `describe NexaFin` or `details on CyberGuard`).\n";
    $ai_text .= "* **User Directory**: Ask about a user (e.g. `who is Sarah Ahmed?`).\n";
    $ai_text .= "* **Evidence Vault**: Ask about `documents` or `uploaded files`.\n";
    $ai_text .= "* **Standards Reference**: Ask for guidelines on compliance standards (e.g. `what is ISO 27001?` or `BNM RMiT rules`).\n\n";
    $ai_text .= "*You can configure a Gemini API key in `backend/.env` under `AI_API_KEY` to connect this chat directly to Google's LLM model.*";
}
// Intent 3: Executive Summary
elseif ($is_summary_req) {
    $ai_text = "### 📊 Executive Compliance Report\n\n";
    $ai_text .= "**Status Date:** " . date('d M Y, H:i') . "\n";
    $ai_text .= "**Assessment Engine:** Local AI Audit Simulator\n\n";
    $ai_text .= "---\n\n";
    $ai_text .= "#### 🏢 1. Client Portfolio\n";
    $ai_text .= "There are **{$company_count} companies** registered. Compliance coverage is being mapped for these organizations.\n\n";
    
    $ai_text .= "#### 📋 2. Assessment Health\n";
    $ai_text .= "- **Active Audit Frameworks:** {$active_audits}\n";
    $ai_text .= "- **Completed Audit Frameworks:** {$completed_audits}\n";
    $ai_text .= "- **Average Compliance Level:** `{$avg_progress}%`\n\n";
    if ($avg_progress >= 80) {
        $ai_text .= "🟢 *Framework health is strong.* The majority of target compliance controls are successfully mapped.\n\n";
    } elseif ($avg_progress >= 50) {
        $ai_text .= "🟡 *Framework health is moderate.* Gaps exist. Focus on gathering outstanding evidence checklists.\n\n";
    } else {
        $ai_text .= "🔴 *Action Required:* Low average progress. Map your security controls and upload evidence records.\n\n";
    }
    
    $ai_text .= "#### 🛡️ 3. Threat & Risk Landscape\n";
    $ai_text .= "- **Logged Risks:** {$risk_count}\n";
    $ai_text .= "- **Average Risk Rating:** `{$avg_risk_score} / 25`\n\n";
    if ($risk_count > 0 && $avg_risk_score > 12) {
        $ai_text .= "⚠️ *Risk Warning:* High average risk ratings. Add mitigating controls under the Risk Register.\n\n";
    } else {
        $ai_text .= "✅ *Risk levels are within acceptable thresholds.*\n\n";
    }
    
    $ai_text .= "#### 📂 4. Evidence Vault\n";
    $ai_text .= "The vault stores **{$doc_count} files** of compliance proofs (documents, policies, audit logs).";
}
// Intent 4: Show Companies
elseif (preg_match('/\b(companies|company list|show companies|what companies|list companies)\b/', $q_lower)) {
    $ai_text = "### 🏢 Registered Companies Portfolio\n\n";
    $ai_text .= "The database contains **{$company_count} client accounts**:\n\n";
    
    $res_comp = $conn->query("SELECT company_id, company_name, registration_number, industry, status FROM companies ORDER BY company_name ASC");
    if ($res_comp && $res_comp->num_rows > 0) {
        $ai_text .= "| ID | Company Name | Reg Number | Industry | Status |\n";
        $ai_text .= "|---|---|---|---|---|\n";
        while ($c = $res_comp->fetch_assoc()) {
            $ai_text .= "| `{$c['company_id']}` | **{$c['company_name']}** | `{$c['registration_number']}` | {$c['industry']} | `{$c['status']}` |\n";
        }
        $ai_text .= "\n💡 *Tip:* Search for a company name directly (e.g., `describe NexaFin`) to check its compliance metrics!";
    } else {
        $ai_text .= "No companies found. Create a company profile via the Company Management panel.";
    }
}
// Intent 5: Show Users
elseif (preg_match('/\b(users|user list|show users|who are the users|directory)\b/', $q_lower)) {
    $ai_text = "### 👥 Platform User Directory\n\n";
    
    $res_usr = $conn->query("SELECT u.full_name, u.email, u.role, u.first_login, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.company_id ORDER BY u.full_name ASC");
    if ($res_usr && $res_usr->num_rows > 0) {
        $ai_text .= "The following user accounts are registered for metadata tracking:\n\n";
        $ai_text .= "| Full Name | Email Address | Mapped Company | Access Role | Status |\n";
        $ai_text .= "|---|---|---|---|---|\n";
        while ($u = $res_usr->fetch_assoc()) {
            $compName = $u['company_name'] ?: '— Platform Admin';
            $statusText = $u['first_login'] ? '🔒 Temp PW (Pending)' : '✅ Active';
            $ai_text .= "| **{$u['full_name']}** | `{$u['email']}` | {$compName} | `{$u['role']}` | *{$statusText}* |\n";
        }
    } else {
        $ai_text .= "No users registered. Add a user contact via the User Management screen.";
    }
}
// Intent 6: Show Uploaded Documents
elseif (preg_match('/\b(documents|files|evidence|uploaded files|vault)\b/', $q_lower)) {
    $ai_text = "### 📂 Evidence Document Vault\n\n";
    $ai_text .= "There are **{$doc_count} files** currently uploaded as audit evidence:\n\n";
    
    $res_docs = $conn->query("SELECT d.file_name, d.framework, d.control_code, d.status, c.company_name FROM documents d JOIN companies c ON d.company_id = c.company_id LIMIT 10");
    if ($res_docs && $res_docs->num_rows > 0) {
        $ai_text .= "| File Name | Associated Company | Target Framework | Control Code | Status |\n";
        $ai_text .= "|---|---|---|---|---|\n";
        while ($d = $res_docs->fetch_assoc()) {
            $fw = $d['framework'] ?: 'General';
            $code = $d['control_code'] ?: 'Unassigned';
            $ai_text .= "| `{$d['file_name']}` | {$d['company_name']} | *{$fw}* | **{$code}** | `{$d['status']}` |\n";
        }
        if ($doc_count > 10) {
            $ai_text .= "\n*(Showing first 10 documents. Visit the Documents vault to view the complete list)*";
        }
    } else {
        $ai_text .= "No documents found. Upload evidence files under the Documents upload page.";
    }
}
// Entity Searches (Dynamic database mapping)
else {
    $matched_company = null;
    $res_comp = $conn->query("SELECT * FROM companies");
    if ($res_comp) {
        while ($c = $res_comp->fetch_assoc()) {
            $name_clean = strtolower($c['company_name']);
            $name_clean_short = preg_replace('/\b(bhd|sdn bhd|ltd|corp|inc|technologies|solutions)\b/i', '', $name_clean);
            $name_clean_short = trim($name_clean_short);
            
            if (strpos($q_lower, $name_clean_short) !== false) {
                $matched_company = $c;
                break;
            }
        }
    }
    
    $matched_user = null;
    $res_usr = $conn->query("SELECT u.*, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.company_id");
    if ($res_usr) {
        while ($u = $res_usr->fetch_assoc()) {
            $user_name_lower = strtolower($u['full_name']);
            $parts = explode(' ', $user_name_lower);
            if (strpos($q_lower, $user_name_lower) !== false || (count($parts) > 0 && in_array($parts[0], explode(' ', preg_replace('/[^a-z ]/', '', $q_lower))))) {
                $matched_user = $u;
                break;
            }
        }
    }

    if ($matched_company) {
        $cid = intval($matched_company['company_id']);
        $ai_text = "### 🏢 Live Company Lookup: {$matched_company['company_name']}\n\n";
        $ai_text .= "* **Registration Number:** `{$matched_company['registration_number']}`\n";
        $ai_text .= "* **Industry Sector:** {$matched_company['industry']}\n";
        $ai_text .= "* **Contact Email:** `{$matched_company['contact_email']}`\n";
        $ai_text .= "* **Status:** `{$matched_company['status']}`\n\n";
        
        $res_assess = $conn->query("SELECT * FROM assessments WHERE company_id = $cid");
        if ($res_assess && $res_assess->num_rows > 0) {
            $ai_text .= "#### Active Audits:\n";
            while ($a = $res_assess->fetch_assoc()) {
                $fwNameMap = [1 => 'PayNet TPA', 2 => 'BNM RMiT', 3 => 'MAS TRM', 4 => 'NACSA NC-II'];
                $target = $fwNameMap[$a['target_framework_id']] ?? 'Unknown';
                $pct = round($a['compliance_percentage'], 1);
                $ai_text .= "- Framework **{$target}** | Verification Readiness Score: **{$pct}%**\n";
            }
        } else {
            $ai_text .= "⚠️ No active compliance assessments are registered for this company.";
        }
    } 
    elseif ($matched_user) {
        $compName = $matched_user['company_name'] ?: 'Platform Admin';
        $ai_text = "### 👥 Live User Lookup: {$matched_user['full_name']}\n\n";
        $ai_text .= "* **Email Address:** `{$matched_user['email']}`\n";
        $ai_text .= "* **Mapped Account:** *{$compName}*\n";
        $ai_text .= "* **Access Role:** `{$matched_user['role']}`\n";
        $ai_text .= "* **Password State:** " . ($matched_user['first_login'] ? "❌ Temporary Password (Pending Login)" : "✅ Active") . "\n";
    }
    // Compliance Standards Advisories
    elseif (strpos($q_lower, 'rmit') !== false || strpos($q_lower, 'bnm') !== false) {
        $ai_text = "### 📋 BNM RMiT Advisory Checklist\n\n";
        $ai_text .= "The Bank Negara Malaysia (BNM) Risk Management in Technology (RMiT) standard governs cybersecurity resilience for financial institutions.\n\n";
        $ai_text .= "#### Critical Domains:\n";
        $ai_text .= "1. **Governance & Oversight**: Board-level ownership of technology risk parameters.\n";
        $ai_text .= "2. **Technology Risk Management**: Identification, threat modeling, and control maps for critical systems.\n";
        $ai_text .= "3. **Cyber Operations**: Deployment of a 24/7 Security Operations Center (SOC) and proactive incident playbooks.\n\n";
        $ai_text .= "💡 *Remediation Tip:* Ensure you link security policies to controls like **RMIT-10.53** under the Control Mapping page.";
    } 
    elseif (strpos($q_lower, 'tpa') !== false || strpos($q_lower, 'paynet') !== false) {
        $ai_text = "### 💳 PayNet TPA Security Guidelines\n\n";
        $ai_text .= "The PayNet Third-Party Acquirer (TPA) guidelines mandate operational data security for payment endpoints.\n\n";
        $ai_text .= "#### Compliance Benchmarks:\n";
        $ai_text .= "* **TPA-1.1 (Governance)**: Review of disaster recovery plans, encryption keys, and vendor access agreements.\n";
        $ai_text .= "* **TPA-1.2 (Security Measures)**: Implementation of TLS 1.3, strict network segmentation, and quarterly penetration testing.\n\n";
        $ai_text .= "🔍 *Vault Status:* Your vault currently holds **{$doc_count} documents**. Audit logs and penetration test reports should be uploaded as PayNet proof.";
    } 
    elseif (strpos($q_lower, 'trm') !== false || strpos($q_lower, 'mas') !== false) {
        $ai_text = "### 🇸🇬 MAS TRM Cybersecurity Standards\n\n";
        $ai_text .= "The Monetary Authority of Singapore (MAS) Technology Risk Management (TRM) guidelines establish parameters for financial institutions in Singapore.\n\n";
        $ai_text .= "#### Key Requirements:\n";
        $ai_text .= "* Enforce Multi-Factor Authentication (MFA) for administrative logins.\n";
        $ai_text .= "* Deploy robust network boundary systems and database transaction logging.\n";
        $ai_text .= "* Complete annual disaster recovery validation exercises (BCP/DRP).\n\n";
        $ai_text .= "🛡️ *Risk Alignment:* You have **{$risk_count} risks** in your register. High-scoring infrastructure threats should be mapped directly to TRM guidelines.";
    } 
    elseif (strpos($q_lower, 'iso') !== false) {
        $ai_text = "### 🌐 ISO/IEC 27001 Compliance Guidance\n\n";
        $ai_text .= "ISO 27001 is the international standard for Information Security Management Systems (ISMS).\n\n";
        $ai_text .= "#### Key Phases:\n";
        $ai_text .= "1. **Context of Organization**: Defining the scope of the ISMS.\n";
        $ai_text .= "2. **Risk Assessment**: Documenting threat levels and mapping mitigating controls from Annex A.\n";
        $ai_text .= "3. **Annex A Controls**: Managing access control, physical security, data classification, and incident logging.";
    }
    elseif (strpos($q_lower, 'risk') !== false || strpos($q_lower, 'score') !== false) {
        $ai_text = "### ⚠️ Risk Mitigation Strategy\n\n";
        $ai_text .= "Your risk register has **{$risk_count} active records** with an average risk score of **{$avg_risk_score}**.\n\n";
        $ai_text .= "#### Action Items:\n";
        $ai_text .= "1. **Prioritize Red Risks**: Remediate any risks with a rating > 12 (Likelihood × Impact).\n";
        $ai_text .= "2. **Mitigation Linkage**: Upload policy evidence or server logs that verify controls mitigating these threats.\n";
        $ai_text .= "3. **Risk Update**: Once mitigated, transition status from 'Open' to 'Mitigated' in the database.";
    } 
    else {
        $ai_text = "### 🤖 AI Compliance Assistant\n\n";
        $ai_text .= "I received your message: *\"" . htmlspecialchars($user_message) . "\"*.\n\n";
        $ai_text .= "As a local assistant, I can check live platform data. How can I help you today? Try querying about:\n";
        $ai_text .= "- 📊 `Generate an Executive Summary` to compile readiness levels.\n";
        $ai_text .= "- 🏢 `Show companies` to print all active client directories.\n";
        $ai_text .= "- 👥 `Show user list` to verify platform accounts.\n";
        $ai_text .= "- 📋 Guidelines on compliance standards (`BNM RMiT`, `MAS TRM`, or `PayNet TPA`).\n";
    }
}

// RAG: Append matched document snippets to the final AI response to show retrieval working
if (!empty($matched_docs)) {
    $ai_text .= "\n\n### 📂 Retrieved Evidence Context (RAG)\n";
    $ai_text .= "I found relevant evidence details in your uploaded documents matching your query:\n\n";
    foreach ($matched_docs as $doc) {
        $ai_text .= "📎 **Document:** `{$doc['file_name']}` (Framework: *{$doc['framework']}*, Control: *{$doc['control_code']}*)\n";
        $snippet = substr($doc['extracted_text'], 0, 300) . "...";
        $ai_text .= "> {$snippet}\n\n";
    }
}

send_success("AI response generated.", ["response" => $ai_text]);
?>
