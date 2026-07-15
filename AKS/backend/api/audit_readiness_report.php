<?php
require('../fpdf/fpdf.php');
include('../config/database.php');

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    die("Error: Invalid or missing assessment ID.");
}

$stmt = $conn->prepare("SELECT * FROM assessments WHERE assessment_id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

$percentage = $assessment['compliance_percentage'] ?? 0;

/* Risk Engine */
if ($percentage >= 85) {
    $status = "AUDIT READY";
    $risk = "LOW RISK";
    $color = [0,140,0];
    $insight = "Strong compliance posture. System is ready for external audit review.";
} elseif ($percentage >= 60) {
    $status = "PARTIALLY READY";
    $risk = "MEDIUM RISK";
    $color = [255,140,0];
    $insight = "Moderate gaps exist. Recommended to close missing controls before audit.";
} else {
    $status = "NOT READY";
    $risk = "HIGH RISK";
    $color = [200,0,0];
    $insight = "Critical compliance gaps detected. Immediate remediation required.";
}

$pdf = new FPDF();
$pdf->AddPage();

/* HEADER */
$pdf->SetFillColor(20,20,20);
$pdf->Rect(0,0,210,25,'F');
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',16);
$pdf->SetY(8);
$pdf->Cell(0,8,'ENTERPRISE AUDIT READINESS REPORT',0,1,'C');
$pdf->Ln(10);
$pdf->SetTextColor(0,0,0);

/* META */
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,"Assessment ID: $assessment_id",0,1,'R');
$pdf->Cell(0,6,"Generated: ".date('d M Y H:i'),0,1,'R');
$pdf->Ln(5);

/* SCORE BLOCK */
$pdf->SetFont('Arial','B',14);
$pdf->Cell(190,12,"COMPLIANCE SCORE: $percentage%",1,1,'C');

$pdf->Ln(3);

/* STATUS */
$pdf->SetTextColor($color[0],$color[1],$color[2]);
$pdf->SetFont('Arial','B',14);
$pdf->Cell(190,12,$status." | ".$risk,1,1,'C');
$pdf->SetTextColor(0,0,0);

$pdf->Ln(5);

/* INSIGHT */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,'EXECUTIVE INSIGHT',0,1);

$pdf->SetFont('Arial','',11);
$pdf->MultiCell(0,6,$insight);

$pdf->Output();
?>