-- seed.sql
-- Run this script to populate your database tables with sample companies, users, assessments, and risks.

USE aks;

-- 1. Insert Sample Companies
INSERT INTO companies (company_id, company_name, registration_number, industry, address, contact_email, contact_phone) VALUES
(1, 'TechCorp Sdn Bhd', '123456-X', 'Technology', 'Level 10, Menara KL, Kuala Lumpur', 'techcorp@compliance.com', '+603-21661122'),
(2, 'FinancePlus Bhd', '789012-Y', 'Finance', 'Suite 5.01, Cyberview Towers, Cyberjaya', 'financeplus@compliance.com', '+603-83112233'),
(3, 'SecureBank Bhd', '345678-Z', 'Banking', 'Menara Bank, Persiaran Perdana, Putrajaya', 'securebank@compliance.com', '+603-88889999'),
(4, 'DataSafe Sdn Bhd', '901234-W', 'Security', 'Building 12, Bayan Lepas Free Industrial Zone, Penang', 'datasafe@compliance.com', '+604-6445566')
ON DUPLICATE KEY UPDATE company_id=company_id;

-- 2. Insert Sample Users
-- Passwords are set to "user123" (hashed)
INSERT INTO users (user_id, company_id, full_name, email, password, role, first_login) VALUES
(1, 1, 'Sarah Chen', 'sarah@techcorp.com', '$2y$10$W194B73Gj6q/bW3g9j.G5.gX.R4K7/0g.lU2yVzK1.xT6oT/1aYde', 'user', FALSE),
(2, 2, 'Alex Lee', 'alex@financeplus.com', '$2y$10$W194B73Gj6q/bW3g9j.G5.gX.R4K7/0g.lU2yVzK1.xT6oT/1aYde', 'user', FALSE),
(3, 3, 'David Lim', 'david@securebank.com', '$2y$10$W194B73Gj6q/bW3g9j.G5.gX.R4K7/0g.lU2yVzK1.xT6oT/1aYde', 'user', TRUE),
(4, 4, 'Emily Tan', 'emily@datasafe.com', '$2y$10$W194B73Gj6q/bW3g9j.G5.gX.R4K7/0g.lU2yVzK1.xT6oT/1aYde', 'user', FALSE)
ON DUPLICATE KEY UPDATE user_id=user_id;

-- 3. Insert Sample Assessments
INSERT INTO assessments (assessment_id, company_id, current_framework_id, target_framework_id, compliance_percentage) VALUES
(1, 1, 1, 2, 75.00), -- TechCorp (PayNet TPA -> BNM RMiT)
(2, 2, 1, 3, 100.00), -- FinancePlus (PayNet TPA -> MAS TRM)
(3, 3, 4, 2, 45.00), -- SecureBank (NACSA NC-II -> BNM RMiT)
(4, 4, 3, 1, 62.00)  -- DataSafe (MAS TRM -> PayNet TPA)
ON DUPLICATE KEY UPDATE assessment_id=assessment_id;

-- 4. Seed Mapped Assessment Controls for TechCorp (Assessment 1)
-- Framework 2 (BNM RMiT) controls:
-- control_id=2 (RMIT-8.1), 6 (RMIT-10.53), 10 (RMIT-10.12), 14 (RMIT-App5.1), 16 (RMIT-11.12), 19 (RMIT-App8.1)
INSERT INTO assessment_controls (assessment_id, control_id, status) VALUES
(1, 2, 'Matched'),
(1, 6, 'Missing'),
(1, 10, 'Matched'),
(1, 14, 'Matched'),
(1, 16, 'Missing'),
(1, 19, 'Missing')
ON DUPLICATE KEY UPDATE assessment_control_id=assessment_control_id;

-- 5. Seed Mapped Assessment Controls for SecureBank (Assessment 3)
INSERT INTO assessment_controls (assessment_id, control_id, status) VALUES
(3, 2, 'Matched'),
(3, 6, 'Missing'),
(3, 10, 'Missing'),
(3, 14, 'Matched'),
(3, 16, 'Missing'),
(3, 19, 'Missing')
ON DUPLICATE KEY UPDATE assessment_control_id=assessment_control_id;

-- 6. Insert Sample Risks into Risk Register
INSERT INTO risks (risk_id, company_id, risk_title, risk_description, likelihood, impact, risk_score, mitigation_strategy, status) VALUES
(1, 1, 'Data Leakage via Ransomware', 'Exposure of sensitive client infosec logs.', 3, 4, 12, 'Deploy automated file integrity checkers and daily offsite backups.', 'Open'),
(2, 2, 'Unauthorized Admin Privilege Escalation', 'Auditors inheriting platform roles.', 2, 5, 10, 'Enforce periodic privilege audits and session regenerations.', 'Open')
ON DUPLICATE KEY UPDATE risk_id=risk_id;
