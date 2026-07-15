<?php
// backend/add_control_description.php
require_once 'config/database.php';

// Add the column if it does not exist
$col_check = $conn->query("SHOW COLUMNS FROM controls LIKE 'description'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE controls ADD COLUMN description TEXT DEFAULT NULL");
}

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

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("UPDATE controls SET description = ? WHERE control_code = ?");
    $updatedCount = 0;
    foreach ($descriptions as $code => $desc) {
        $stmt->bind_param("ss", $desc, $code);
        $stmt->execute();
        $updatedCount += $stmt->affected_rows;
    }
    $stmt->close();
    $conn->commit();
    echo "SUCCESS: Column added and descriptions populated for $updatedCount controls.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: Failed to add descriptions: " . $e->getMessage() . "\n";
}
?>
