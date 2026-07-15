<?php
require('../fpdf/fpdf.php');
include('../config/database.php');

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessment_id <= 0) {
    die("Error: Invalid or missing assessment ID.");
}

$stmt_matched = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Matched'");
$stmt_matched->bind_param("i", $assessment_id);
$stmt_matched->execute();
$matched = $stmt_matched->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_missing = $conn->prepare("SELECT COUNT(*) AS total FROM assessment_controls WHERE assessment_id = ? AND status = 'Missing'");
$stmt_missing->bind_param("i", $assessment_id);
$stmt_missing->execute();
$missing = $stmt_missing->get_result()->fetch_assoc()['total'] ?? 0;

$total = max($matched + $missing, 1);
$coverage = round(($matched / $total) * 100, 2);

/* Insight Engine */
if ($coverage >= 85) {
    $insight = "Excellent coverage across controls.";
    $risk = "LOW RISK";
    $color = [0,140,0];
} elseif ($coverage >= 60) {
    $insight = "Moderate coverage with improvement areas.";
    $risk = "MEDIUM RISK";
    $color = [255,140,0];
} else {
    $insight = "Low coverage indicates major compliance gaps.";
    $risk = "HIGH RISK";
    $color = [200,0,0];
}

$pdf = new FPDF();
$pdf->AddPage();

/* HEADER */
$pdf->SetFillColor(20,20,20);
$pdf->Rect(0,0,210,25,'F');
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',15);
$pdf->SetY(8);
$pdf->Cell(0,8,'COMPLIANCE COVERAGE ANALYTICS REPORT',0,1,'C');
$pdf->Ln(10);
$pdf->SetTextColor(0,0,0);

/* METRICS */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(95,12,"TOTAL CONTROLS: $total",1,0,'C');
$pdf->Cell(95,12,"COVERAGE: $coverage%",1,1,'C');

$pdf->Cell(95,12,"MATCHED: $matched",1,0,'C');
$pdf->Cell(95,12,"MISSING: $missing",1,1,'C');

$pdf->Ln(5);

/* RISK */
$pdf->SetTextColor($color[0],$color[1],$color[2]);
$pdf->SetFont('Arial','B',14);
$pdf->Cell(190,12,"RISK LEVEL: $risk",1,1,'C');
$pdf->SetTextColor(0,0,0);

$pdf->Ln(5);

/* INSIGHT */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,'ANALYSIS SUMMARY',0,1);

$pdf->SetFont('Arial','',11);
$pdf->MultiCell(0,6,$insight);

$pdf->Output();
?>