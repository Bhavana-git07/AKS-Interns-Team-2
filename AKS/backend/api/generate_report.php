<?php
ob_start();
require('../fpdf/fpdf.php');
include('../config/database.php');

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    die("Error: Invalid or missing assessment ID.");
}

/* ================= DATA ================= */

$stmt_assess = $conn->prepare("
    SELECT a.*, c.company_name 
    FROM assessments a 
    JOIN companies c ON a.company_id = c.company_id 
    WHERE a.assessment_id = ?
");
$stmt_assess->bind_param("i", $assessment_id);
$stmt_assess->execute();
$assessment = $stmt_assess->get_result()->fetch_assoc();

$fwNameMap = [
    1 => 'PayNet TPA',
    2 => 'BNM RMiT',
    3 => 'MAS TRM',
    4 => 'NACSA NC-II'
];

if ($assessment) {
    $assessment['framework_name'] = $fwNameMap[$assessment['target_framework_id']] ?? 'Unknown Framework';
}

$percentage = $assessment['compliance_percentage'] ?? 0;

$stmt_matched = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Matched'");
$stmt_matched->bind_param("i", $assessment_id);
$stmt_matched->execute();
$matched = $stmt_matched->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_missing = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Missing'");
$stmt_missing->bind_param("i", $assessment_id);
$stmt_missing->execute();
$missing = $stmt_missing->get_result()->fetch_assoc()['total'] ?? 0;

/* ================= RISK ENGINE ================= */

if ($percentage >= 85) {
    $risk = "LOW RISK";
    $riskColor = [0, 140, 0];
} elseif ($percentage >= 60) {
    $risk = "MEDIUM RISK";
    $riskColor = [247, 178, 79];
} else {
    $risk = "HIGH RISK";
    $riskColor = [247, 95, 79];
}

/* ================= PDF CLASS ================= */

class PDF extends FPDF {
    public $report_title = 'ENTERPRISE COMPLIANCE AUDIT REPORT';

    function Header() {

        // Dark top bar
        $this->SetFillColor(20, 20, 20);
        $this->Rect(0, 0, 210, 22, 'F');

        $this->SetFont('Arial','B',14);
        $this->SetTextColor(255,255,255);
        $this->SetY(6);
        $this->Cell(0,6,$this->report_title,0,1,'C');

        $this->SetFont('Arial','',9);
        $this->Cell(0,6,'Automated Risk & Control Assessment System',0,1,'C');

        $this->Ln(8);
        $this->SetTextColor(0,0,0);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(120,120,120);
        $this->Cell(0,10,'Confidential | Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

/* ================= INIT ================= */

$report_type = $_GET['report_type'] ?? 'readiness';

$pdf = new PDF();
if ($report_type === 'gap') {
    $pdf->report_title = 'DETAILED OVERLAP & GAP ANALYSIS REPORT';
} else {
    $pdf->report_title = 'ENTERPRISE AUDIT READINESS REPORT';
}
$pdf->AliasNbPages();
$pdf->AddPage();

/* ================= META ================= */

$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'Report Generated: '.date('d M Y - H:i:s'),0,1,'R');

$pdf->Ln(3);

/* ================= EXECUTIVE SUMMARY ================= */

$pdf->SetFont('Arial','B',14);
if ($report_type === 'gap') {
    $pdf->Cell(0,8,'MAPPING SCOPE & METHODOLOGY',0,1);
} else {
    $pdf->Cell(0,8,'EXECUTIVE SUMMARY',0,1);
}
$pdf->Ln(1);

$pdf->SetFont('Arial','',10);
if ($report_type === 'gap') {
    $summary = "This report presents a detailed clause-by-clause mapping and gap analysis for " . $assessment['company_name'] . " against the target " . $assessment['framework_name'] . " framework. Matched controls indicate areas where your existing baseline controls map to target requirements, while Gaps indicate outstanding compliance omissions requiring technical remediation.";
} else {
    $summary = "This report presents the consolidated compliance assessment findings for " . $assessment['company_name'] . " regarding the " . $assessment['framework_name'] . " compliance framework. A structured gap assessment was performed to map operational cybersecurity controls against defined regulatory guidelines. Out of the active requirements mapped to this assessment, " . $matched . " controls are successfully verified (Matched), while " . $missing . " controls remain unverified (Missing) or non-compliant.";
}

$pdf->MultiCell(0,5,utf8_decode($summary));
$pdf->Ln(4);

/* ================= KPI DASHBOARD ================= */

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(245,245,245);

if ($report_type === 'gap') {
    $pdf->Cell(63,14,"OVERLAP PERCENTAGE: ".$percentage.'%',1,0,'C',true);
    $pdf->Cell(63,14,"DIRECT OVERLAPS: ".$matched,1,0,'C',true);
    $pdf->Cell(64,14,"UNMAPPED GAPS: ".$missing,1,1,'C',true);
} else {
    $pdf->Cell(63,14,"COMPLIANCE: ".$percentage.'%',1,0,'C',true);
    $pdf->Cell(63,14,"MATCHED: ".$matched,1,0,'C',true);
    $pdf->Cell(64,14,"GAPS / MISSING: ".$missing,1,1,'C',true);
}

$pdf->Ln(5);

if ($report_type !== 'gap') {
    /* ================= RISK SUMMARY SECTION ================= */

    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,8,'RISK PROFILE SUMMARY',0,1);
    $pdf->Ln(1);

    $pdf->SetFont('Arial','B',11);
    $pdf->SetTextColor($riskColor[0], $riskColor[1], $riskColor[2]);
    $pdf->Cell(190,8,'POSTURE EVALUATION: ' . $risk,1,1,'C');
    $pdf->SetTextColor(0,0,0);
    $pdf->Ln(2);

    $pdf->SetFont('Arial','',10);
    if ($percentage >= 85) {
        $risk_desc = "The overall framework posture is evaluated as LOW RISK. The organization has successfully verified the vast majority of critical compliance parameters. Keep monitoring controls to prevent decay.";
    } elseif ($percentage >= 60) {
        $risk_desc = "The overall framework posture is evaluated as MEDIUM RISK. Significant gaps exist in target frameworks, representing moderate cybersecurity exposure. Mitigation strategies must be mapped and reviewed.";
    } else {
        $risk_desc = "The overall framework posture is evaluated as HIGH RISK. Critical core controls are unverified or missing. The organization faces significant audit failure exposures and vulnerability vectors. Immediate remediation is required.";
    }
    $pdf->MultiCell(0,5,$risk_desc);
    $pdf->Ln(5);

    /* ================= VECTOR CHART SECTION ================= */

    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,6,'Audit Control Distribution Chart:',0,1);
    $pdf->Ln(2);

    $total_cnt = max(1, $matched + $missing);
    $matched_pct = ($matched / $total_cnt) * 100;
    $missing_pct = ($missing / $total_cnt) * 100;

    $bar_y = $pdf->GetY();

    // Draw Matched bar
    $pdf->SetFont('Arial','',10);
    $pdf->SetXY(10, $bar_y);
    $pdf->Cell(35, 6, "Matched ({$matched})", 0, 0);
    $pdf->SetFillColor(61, 214, 172); // Mint Green
    $pdf->Rect(45, $bar_y + 1, 140 * ($matched_pct / 100), 4, 'F');

    // Draw Missing bar
    $pdf->Ln(6);
    $bar_y2 = $pdf->GetY();
    $pdf->SetXY(10, $bar_y2);
    $pdf->Cell(35, 6, "Missing ({$missing})", 0, 0);
    $pdf->SetFillColor(247, 95, 79); // Red
    $pdf->Rect(45, $bar_y2 + 1, 140 * ($missing_pct / 100), 4, 'F');

    $pdf->Ln(10);

    /* ================= AUDIT RECOMMENDATIONS ================= */

    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,8,'AUDIT RECOMMENDATIONS',0,1);
    $pdf->Ln(1);

    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,5,'- Remediate missing controls listed in the Gap Matrix below.',0,1);
    $pdf->Cell(0,5,'- Map relevant security logs/policy documents in the Evidence Vault to support unverified controls.',0,1);
    if ($percentage < 85) {
        $pdf->Cell(0,5,'- Enforce immediate risk register reviews for high-exposure items.',0,1);
    }
    if ($percentage < 60) {
        $pdf->Cell(0,5,'- Conduct incident response tabletop simulations to offset lack of active controls.',0,1);
    }
    $pdf->Cell(0,5,'- Perform quarterly automated vulnerability scans to review configuration health.',0,1);

    $pdf->Ln(8);
}

/* ================= GAP ANALYSIS ================= */

if ($report_type === 'gap') {
    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,8,'DETAILED OVERLAP & REMEDIATION MATRIX',0,1);
    $pdf->Ln(2);

    /* TABLE HEADER */
    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(230,230,230);
    $pdf->Cell(85,8,'Target Control Requirement',1,0,'C',true);
    $pdf->Cell(30,8,'Target Code',1,0,'C',true);
    $pdf->Cell(40,8,'Mapped Source',1,0,'C',true);
    $pdf->Cell(35,8,'Status',1,1,'C',true);

    /* TABLE DATA */
    $pdf->SetFont('Arial','',9);

    $stmt_controls = $conn->prepare("
        SELECT 
            c.control_code,
            c.control_name,
            ac.status,
            (SELECT sc.control_code 
             FROM controls sc 
             JOIN control_mappings scm ON sc.control_id = scm.control_id
             JOIN control_mappings tcm ON scm.master_control_id = tcm.master_control_id
             WHERE tcm.control_id = c.control_id 
               AND sc.framework_id = a.current_framework_id 
             LIMIT 1) AS source_control_code
        FROM assessment_controls ac
        JOIN controls c ON ac.control_id = c.control_id
        JOIN assessments a ON ac.assessment_id = a.assessment_id
        WHERE ac.assessment_id = ?
    ");
    $stmt_controls->bind_param("i", $assessment_id);
    $stmt_controls->execute();
    $controls = $stmt_controls->get_result();

    while ($row = $controls->fetch_assoc()) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
        }

        $nameText = utf8_decode($row['control_name']);
        if (strlen($nameText) > 42) {
            $nameText = substr($nameText, 0, 39) . '...';
        }

        $pdf->Cell(85,7,$nameText,1);
        $pdf->Cell(30,7,utf8_decode($row['control_code']),1,0,'C');
        
        $srcCode = $row['source_control_code'] ? utf8_decode($row['source_control_code']) : 'None (Gap)';
        $pdf->Cell(40,7,$srcCode,1,0,'C');

        if ($row['status'] == 'Matched') {
            $pdf->SetTextColor(0,140,0);
        } else {
            $pdf->SetTextColor(200,0,0);
        }
        $pdf->Cell(35,7,$row['status'],1,1,'C');
        $pdf->SetTextColor(0,0,0);
    }
} else {
    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,8,'AUDIT READINESS CONTROL SUMMARY',0,1);
    $pdf->Ln(2);

    /* TABLE HEADER */
    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(230,230,230);
    $pdf->Cell(110,8,'Control Requirement',1,0,'C',true);
    $pdf->Cell(40,8,'Code',1,0,'C',true);
    $pdf->Cell(40,8,'Verification Status',1,1,'C',true);

    /* TABLE DATA */
    $pdf->SetFont('Arial','',9);

    $stmt_controls = $conn->prepare("
        SELECT c.control_name, c.control_code, ac.status
        FROM assessment_controls ac
        JOIN controls c ON ac.control_id = c.control_id
        WHERE ac.assessment_id = ?
    ");
    $stmt_controls->bind_param("i", $assessment_id);
    $stmt_controls->execute();
    $controls = $stmt_controls->get_result();

    while ($row = $controls->fetch_assoc()) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
        }

        $nameText = utf8_decode($row['control_name']);
        if (strlen($nameText) > 55) {
            $nameText = substr($nameText, 0, 52) . '...';
        }

        $pdf->Cell(110,7,$nameText,1);
        $pdf->Cell(40,7,utf8_decode($row['control_code']),1,0,'C');

        if ($row['status'] == 'Matched') {
            $pdf->SetTextColor(0,140,0);
        } else {
            $pdf->SetTextColor(200,0,0);
        }
        $pdf->Cell(40,7,$row['status'],1,1,'C');
        $pdf->SetTextColor(0,0,0);
    }
}

/* ================= FOOT NOTE SECTION ================= */

$pdf->Ln(5);
$pdf->SetFont('Arial','I',9);
$pdf->SetTextColor(100,100,100);

$pdf->MultiCell(0,5,
"Note: This report is system-generated and based on automated control mapping logic. "
."It should be validated during manual audit review.");

ob_end_clean();
$pdf->Output();
?>