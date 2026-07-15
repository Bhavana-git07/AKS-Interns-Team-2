<?php
// config/database.php
// Centralized DB connection. Never hardcode credentials in every file —
// require this file instead, everywhere you need $conn.

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "aks";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    // Don't leak DB details to the client in production.
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]));
}

$conn->set_charset("utf8mb4");

// Auto-initialize login_attempts table for account lockout tracking
$conn->query("
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Auto-initialize risks table for Risk Register module
$conn->query("
CREATE TABLE IF NOT EXISTS risks (
    risk_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    risk_title VARCHAR(255) NOT NULL,
    risk_description TEXT,
    likelihood INT NOT NULL,
    impact INT NOT NULL,
    risk_score INT NOT NULL,
    mitigation_strategy TEXT,
    status VARCHAR(50) DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Auto-initialize documents table alteration for RAG module
$col_check = $conn->query("SHOW COLUMNS FROM documents LIKE 'extracted_text'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE documents ADD COLUMN extracted_text LONGTEXT DEFAULT NULL");
}

// Auto-initialize login_attempts index for faster lockout checks
$idx_check = $conn->query("SHOW INDEX FROM login_attempts WHERE Key_name = 'email'");
if ($idx_check && $idx_check->num_rows === 0) {
    $conn->query("ALTER TABLE login_attempts ADD INDEX (email)");
}

// Auto-initialize admins columns for MFA integration
$col_check_mfa = $conn->query("SHOW COLUMNS FROM admins LIKE 'mfa_secret'");
if ($col_check_mfa && $col_check_mfa->num_rows === 0) {
    $conn->query("ALTER TABLE admins ADD COLUMN mfa_secret VARCHAR(32) DEFAULT NULL, ADD COLUMN mfa_enabled TINYINT(1) DEFAULT 0");
}

// Auto-align control codes if old codes are found
$check_old = $conn->query("SELECT 1 FROM controls WHERE control_code = 'RMIT-1.1' LIMIT 1");
if ($check_old && $check_old->num_rows > 0) {
    $code_mappings = [
        'RMIT-1.1' => 'RMIT-8.1',
        'TRM-2.1' => 'TRM-3.1',
        'NC-1.2' => 'NCII-Sec22',
        'RMIT-4.3' => 'RMIT-10.53',
        'TRM-3.5' => 'TRM-9.1',
        'NC-2.7' => 'NCII-COP-3.5',
        'RMIT-2.3' => 'RMIT-10.12',
        'TRM-2.8' => 'TRM-12.3',
        'NC-1.5' => 'NCII-COP-1.5',
        'RMIT-5.1' => 'RMIT-App5.1',
        'TRM-3.8' => 'TRM-11.1',
        'RMIT-7.2' => 'RMIT-11.12',
        'TRM-4.8' => 'TRM-12.2',
        'NC-3.1' => 'NCII-Sec32',
        'RMIT-8.5' => 'RMIT-App8.1',
        'TRM-5.3' => 'TRM-10.1',
        'NC-4.2' => 'NCII-COP-4.2'
    ];
    foreach ($code_mappings as $old => $new) {
        $stmt_align1 = $conn->prepare("UPDATE controls SET control_code = ? WHERE control_code = ?");
        $stmt_align1->bind_param("ss", $new, $old);
        $stmt_align1->execute();
        $stmt_align1->close();

        $stmt_align2 = $conn->prepare("UPDATE documents SET control_code = ? WHERE control_code = ?");
        $stmt_align2->bind_param("ss", $new, $old);
        $stmt_align2->execute();
        $stmt_align2->close();
    }
}

// Auto-initialize controls table alteration to add description column and seed data
$col_check_desc = $conn->query("SHOW COLUMNS FROM controls LIKE 'description'");
if ($col_check_desc && $col_check_desc->num_rows === 0) {
    $conn->query("ALTER TABLE controls ADD COLUMN description TEXT DEFAULT NULL");
    
    $descriptions = [
        // Governance & Risk Management (Concept 1)
        'TPA-1.1' => 'Establish a formal cybersecurity governance framework and risk management committee to oversee technology risks, define risk appetites, and monitor third-party vendor risks.',
        'RMIT-8.1' => 'Financial institutions must establish a comprehensive technology risk management framework (TRMF) and cyber resilience framework (CRF) approved and overseen directly by the Board of Directors.',
        'TRM-3.1' => 'Senior management must implement a technology risk management strategy, appoint key leadership roles (e.g., CISO), and establish a clear governance structure for technology operations.',
        'NCII-Sec22' => 'National Critical Information Infrastructure (NCII) owners must implement cybersecurity measures and standards specified in the Codes of Practice to ensure system resilience and national safety.',

        // Access Control & Identity Management (Concept 2)
        'TPA-2.3' => 'Define and enforce access control policies based on the principle of least privilege, requiring multi-factor authentication (MFA) for administrative and remote network access.',
        'RMIT-10.53' => 'Implement robust user identity and access management policies, restricting user access to critical systems on a strict "need-to-have" basis, with regular access reviews.',
        'TRM-9.1' => 'Enforce multi-factor authentication (MFA), secure privilege access management (PAM), and log all administrative actions to prevent unauthorized data access.',
        'NCII-COP-3.5' => 'Deploy strict access control procedures for critical infrastructure systems, managing user directories, authentication protocols, and administrative privileges.',

        // Training & Cybersecurity Awareness (Concept 3)
        'TPA-2.1' => 'Conduct regular security awareness training sessions for all employees and contractors to educate them on phishing, social engineering, and password hygiene.',
        'RMIT-10.12' => 'Implement mandatory annual cybersecurity training and assessment programs for all personnel, including board members, regarding technology risk awareness.',
        'TRM-12.3' => 'Ensure all staff with access to critical systems receive training on technology risks, data confidentiality, and threat detection mechanisms.',
        'NCII-COP-1.5' => 'Establish cybersecurity education programs for personnel handling NCII assets to mitigate human-related security vulnerabilities.',

        // Network & Communications Security (Concept 4)
        'TPA-3.4' => 'Segment the corporate network into distinct security zones (e.g., DMZ, internal database, user subnets) to prevent lateral movement of attackers in case of breach.',
        'RMIT-App5.1' => 'Segregate networks into multiple zones according to threat profiles, protecting boundaries with redundant firewalls and intrusion prevention systems (IPS).',
        'TRM-11.1' => 'Implement secure network architecture by isolating sensitive data environments, encrypting data in transit, and monitoring network traffic at boundaries.',

        // Incident Response & Exercises (Concept 5)
        'RMIT-11.12' => 'Conduct annual cyber incident simulation exercises (tabletop and technical drills) to test the responsiveness and readiness of the incident handling team.',
        'TRM-12.2' => 'Perform regular tabletop exercises and incident response drills simulating real-world cyber attack scenarios to validate escalation and recovery plans.',
        'NCII-Sec32' => 'Enforce immediate reporting and response procedures for cybersecurity incidents affecting NCII systems, coordinating with the National Cyber Coordination and Command Centre.',

        // Key & Cryptography Management (Concept 6)
        'RMIT-App8.1' => 'Establish secure policies for the entire lifecycle of cryptographic keys, including generation, storage, distribution, rotation, and destruction, utilizing hardware security modules (HSMs).',
        'TRM-10.1' => 'Deploy strong encryption and manage cryptographic keys securely across their lifecycle to protect data confidentiality and integrity both at rest and in transit.',
        'NCII-COP-4.2' => 'Implement approved cryptographic standards and key management protocols to safeguard communications and sensitive critical infrastructure data.'
    ];
    
    $stmt_desc = $conn->prepare("UPDATE controls SET description = ? WHERE control_code = ?");
    if ($stmt_desc) {
        foreach ($descriptions as $code => $desc) {
            $stmt_desc->bind_param("ss", $desc, $code);
            $stmt_desc->execute();
        }
        $stmt_desc->close();
    }
}
?>
