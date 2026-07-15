<?php
include '../config/database.php';

$assessment_id = (int)$_GET['assessment_id'];

$stmt = $conn->prepare("
    SELECT 
        c.control_code,
        c.control_name,
        c.description,
        ac.status,
        (SELECT sc.control_code 
         FROM controls sc 
         JOIN control_mappings scm ON sc.control_id = scm.control_id
         JOIN control_mappings tcm ON scm.master_control_id = tcm.master_control_id
         WHERE tcm.control_id = c.control_id 
           AND sc.framework_id = a.current_framework_id 
         LIMIT 1) AS source_control_code,
        (SELECT sc.control_name 
         FROM controls sc 
         JOIN control_mappings scm ON sc.control_id = scm.control_id
         JOIN control_mappings tcm ON scm.master_control_id = tcm.master_control_id
         WHERE tcm.control_id = c.control_id 
           AND sc.framework_id = a.current_framework_id 
         LIMIT 1) AS source_control_name
    FROM assessment_controls ac
    JOIN controls c ON ac.control_id = c.control_id
    JOIN assessments a ON ac.assessment_id = a.assessment_id
    WHERE ac.assessment_id = ?
");

$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>