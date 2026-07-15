<?php
// backend/update_db_controls.php
require_once 'config/database.php';

// Map of old codes to new codes
$mappings = [
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

$conn->begin_transaction();

try {
    $updatedControls = 0;
    $updatedDocs = 0;

    foreach ($mappings as $old => $new) {
        // 1. Update controls table
        $stmt1 = $conn->prepare("UPDATE controls SET control_code = ? WHERE control_code = ?");
        $stmt1->bind_param("ss", $new, $old);
        $stmt1->execute();
        $updatedControls += $stmt1->affected_rows;
        $stmt1->close();

        // 2. Update documents table
        $stmt2 = $conn->prepare("UPDATE documents SET control_code = ? WHERE control_code = ?");
        $stmt2->bind_param("ss", $new, $old);
        $stmt2->execute();
        $updatedDocs += $stmt2->affected_rows;
        $stmt2->close();
    }

    $conn->commit();
    echo "SUCCESS: DB controls migration completed.\n";
    echo "Updated controls: $updatedControls rows.\n";
    echo "Updated documents: $updatedDocs rows.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
}
?>
