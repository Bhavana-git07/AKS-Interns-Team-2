-- schema.sql
-- Complete MySQL database schema for XAMPP / phpMyAdmin.
-- Creates the database and all 11 tables, and seeds standard controls & mappings.

CREATE DATABASE IF NOT EXISTS aks;
USE aks;

-- 1. Admins
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1b. Login Attempts (Account Lockout)
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Companies
CREATE TABLE IF NOT EXISTS companies (
    company_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    registration_number VARCHAR(100) NOT NULL DEFAULT '',
    industry VARCHAR(100),
    address VARCHAR(255),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Users
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) DEFAULT 'user',
    first_login BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin', 'user') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Documents
CREATE TABLE IF NOT EXISTS documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    framework VARCHAR(100) DEFAULT NULL,
    control_code VARCHAR(100) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Pending Review',
    extracted_text LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Assessments
CREATE TABLE IF NOT EXISTS assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    current_framework_id INT NOT NULL,
    target_framework_id INT NOT NULL,
    compliance_percentage DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Controls
CREATE TABLE IF NOT EXISTS controls (
    control_id INT AUTO_INCREMENT PRIMARY KEY,
    control_code VARCHAR(50) NOT NULL,
    control_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    framework_id INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Control Mappings
CREATE TABLE IF NOT EXISTS control_mappings (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    control_id INT NOT NULL,
    master_control_id INT NOT NULL,
    FOREIGN KEY (control_id) REFERENCES controls(control_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Assessment Controls
CREATE TABLE IF NOT EXISTS assessment_controls (
    assessment_control_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    control_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    FOREIGN KEY (assessment_id) REFERENCES assessments(assessment_id) ON DELETE CASCADE,
    FOREIGN KEY (control_id) REFERENCES controls(control_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Audits
CREATE TABLE IF NOT EXISTS audits (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    document_id INT NOT NULL,
    progress DECIMAL(5,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'In Progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Audit Checklist
CREATE TABLE IF NOT EXISTS audit_checklist (
    checklist_id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    checklist_item VARCHAR(255) NOT NULL,
    is_completed TINYINT DEFAULT 0,
    FOREIGN KEY (audit_id) REFERENCES audits(audit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Risks (Risk Register)
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


-- ============================================
-- SEED DATA
-- ============================================

-- 1. Default Admin Account (Email: admin@complianceaudit.com, Password: admin123)
-- Bcrypt hash generated for admin123
INSERT INTO admins (admin_id, name, email, password) VALUES
(1, 'Admin User', 'bhavanavraja@gmail.com', '$2y$10$W194B73Gj6q/bW3g9j.G5.gX.R4K7/0g.lU2yVzK1.xT6oT/1aYde')
ON DUPLICATE KEY UPDATE admin_id=admin_id;

-- 2. Seed Controls for Master Control Taxonomy
-- Framework 1 = PayNet TPA
-- Framework 2 = BNM RMiT
-- Framework 3 = MAS TRM
-- Framework 4 = NACSA NC-II

-- Governance & Risk Management (Master Concept 1)
INSERT INTO controls (control_id, control_code, control_name, description, framework_id) VALUES
(1, 'TPA-1.1', 'Governance and Risk Management', 'Establish a formal cybersecurity governance framework and risk management committee to oversee technology risks, define risk appetites, and monitor third-party vendor risks.', 1),
(2, 'RMIT-8.1', 'Governance Framework', 'Financial institutions must establish a comprehensive technology risk management framework (TRMF) and cyber resilience framework (CRF) approved and overseen directly by the Board of Directors.', 2),
(3, 'TRM-3.1', 'Security Governance', 'Senior management must implement a technology risk management strategy, appoint key leadership roles (e.g., CISO), and establish a clear governance structure for technology operations.', 3),
(4, 'NCII-Sec22', 'Cyber Security Governance', 'National Critical Information Infrastructure (NCII) owners must implement cybersecurity measures and standards specified in the Codes of Practice to ensure system resilience and national safety.', 4);

-- Access Control & Identity Management (Master Concept 2)
INSERT INTO controls (control_id, control_code, control_name, description, framework_id) VALUES
(5, 'TPA-2.3', 'Access Control Policy', 'Define and enforce access control policies based on the principle of least privilege, requiring multi-factor authentication (MFA) for administrative and remote network access.', 1),
(6, 'RMIT-10.53', 'Access Control & Identity', 'Implement robust user identity and access management policies, restricting user access to critical systems on a strict "need-to-have" basis, with regular access reviews.', 2),
(7, 'TRM-9.1', 'Access Control', 'Enforce multi-factor authentication (MFA), secure privilege access management (PAM), and log all administrative actions to prevent unauthorized data access.', 3),
(8, 'NCII-COP-3.5', 'Access Control Management', 'Deploy strict access control procedures for critical infrastructure systems, managing user directories, authentication protocols, and administrative privileges.', 4);

-- Training & Cybersecurity Awareness (Master Concept 3)
INSERT INTO controls (control_id, control_code, control_name, description, framework_id) VALUES
(9, 'TPA-2.1', 'User Education & Awareness', 'Conduct regular security awareness training sessions for all employees and contractors to educate them on phishing, social engineering, and password hygiene.', 1),
(10, 'RMIT-10.12', 'Cyber Awareness Training', 'Implement mandatory annual cybersecurity training and assessment programs for all personnel, including board members, regarding technology risk awareness.', 2),
(11, 'TRM-12.3', 'Security Training', 'Ensure all staff with access to critical systems receive training on technology risks, data confidentiality, and threat detection mechanisms.', 3),
(12, 'NCII-COP-1.5', 'Cyber Security Awareness', 'Establish cybersecurity education programs for personnel handling NCII assets to mitigate human-related security vulnerabilities.', 4);

-- Network & Communications Security (Master Concept 4)
INSERT INTO controls (control_id, control_code, control_name, description, framework_id) VALUES
(13, 'TPA-3.4', 'Network Segmentation', 'Segment the corporate network into distinct security zones (e.g., DMZ, internal database, user subnets) to prevent lateral movement of attackers in case of breach.', 1),
(14, 'RMIT-App5.1', 'Network Security', 'Segregate networks into multiple zones according to threat profiles, protecting boundaries with redundant firewalls and intrusion prevention systems (IPS).', 2),
(15, 'TRM-11.1', 'Network Isolation', 'Implement secure network architecture by isolating sensitive data environments, encrypting data in transit, and monitoring network traffic at boundaries.', 3);

-- Incident Response & Exercises (Master Concept 5)
INSERT INTO controls (control_id, control_code, control_name, description, framework_id) VALUES
(16, 'RMIT-11.12', 'Incident Simulations', 'Conduct annual cyber incident simulation exercises (tabletop and technical drills) to test the responsiveness and readiness of the incident handling team.', 2),
(17, 'TRM-12.2', 'Incident Exercises', 'Perform regular tabletop exercises and incident response drills simulating real-world cyber attack scenarios to validate escalation and recovery plans.', 3),
(18, 'NCII-Sec32', 'Cyber Incident Response', 'Enforce immediate reporting and response procedures for cybersecurity incidents affecting NCII systems, coordinating with the National Cyber Coordination and Command Centre.', 4);

-- Key & Cryptography Management (Master Concept 6)
INSERT INTO controls (control_id, control_code, control_name, description, framework_id) VALUES
(19, 'RMIT-App8.1', 'Key Management', 'Establish secure policies for the entire lifecycle of cryptographic keys, including generation, storage, distribution, rotation, and destruction, utilizing hardware security modules (HSMs).', 2),
(20, 'TRM-10.1', 'Cryptographic Key Management', 'Deploy strong encryption and manage cryptographic keys securely across their lifecycle to protect data confidentiality and integrity both at rest and in transit.', 3),
(21, 'NCII-COP-4.2', 'Cryptography Management', 'Implement approved cryptographic standards and key management protocols to safeguard communications and sensitive critical infrastructure data.', 4);

-- 3. Seed Control Mappings (to Master Concepts)
-- Master Control Concept 1 (Governance)
INSERT INTO control_mappings (control_id, master_control_id) VALUES
(1, 1), (2, 1), (3, 1), (4, 1);

-- Master Control Concept 2 (Access Control)
INSERT INTO control_mappings (control_id, master_control_id) VALUES
(5, 2), (6, 2), (7, 2), (8, 2);

-- Master Control Concept 3 (Training)
INSERT INTO control_mappings (control_id, master_control_id) VALUES
(9, 3), (10, 3), (11, 3), (12, 3);

-- Master Control Concept 4 (Network Security)
INSERT INTO control_mappings (control_id, master_control_id) VALUES
(13, 4), (14, 4), (15, 4);

-- Master Control Concept 5 (Incident Response)
INSERT INTO control_mappings (control_id, master_control_id) VALUES
(16, 5), (17, 5), (18, 5);

-- Master Control Concept 6 (Cryptography)
INSERT INTO control_mappings (control_id, master_control_id) VALUES
(19, 6), (20, 6), (21, 6);
