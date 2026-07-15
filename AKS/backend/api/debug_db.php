<?php
// api/debug_db.php
require_once '../config/database.php';

echo "<html><head><title>Database Status Check</title><style>body{font-family:sans-serif;background:#121212;color:#e0e0e0;padding:20px;}table{border-collapse:collapse;width:100%;margin-bottom:20px;background:#1e1e1e;}th,td{border:1px solid #333;padding:10px;text-align:left;}th{background:#2a2a2a;}</style></head><body>";
echo "<h2>Database Debug Status</h2>";

if ($conn->connect_error) {
    die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
}
echo "<p style='color:green;'>Connected to database successfully.</p>";

// Query companies
echo "<h3>Companies Table Content:</h3>";
$res = $conn->query("SELECT * FROM companies");
if ($res) {
    if ($res->num_rows === 0) {
        echo "<p style='color:yellow;'>No companies found in the database table.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Reg No</th><th>Industry</th><th>Status</th><th>Created At</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            echo "<td>".htmlspecialchars($row['company_id'])."</td>";
            echo "<td>".htmlspecialchars($row['company_name'])."</td>";
            echo "<td>".htmlspecialchars($row['registration_number'])."</td>";
            echo "<td>".htmlspecialchars($row['industry'])."</td>";
            echo "<td>".htmlspecialchars($row['status'] ?? 'active')."</td>";
            echo "<td>".htmlspecialchars($row['created_at'])."</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color:red;'>Error querying companies: " . htmlspecialchars($conn->error) . "</p>";
}

// Query users
echo "<h3>Users Table Content:</h3>";
$res = $conn->query("SELECT * FROM users");
if ($res) {
    if ($res->num_rows === 0) {
        echo "<p style='color:yellow;'>No users found in the database table.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Company ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>First Login</th><th>Created At</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            echo "<td>".htmlspecialchars($row['user_id'])."</td>";
            echo "<td>".htmlspecialchars($row['company_id'])."</td>";
            echo "<td>".htmlspecialchars($row['full_name'])."</td>";
            echo "<td>".htmlspecialchars($row['email'])."</td>";
            echo "<td>".htmlspecialchars($row['role'])."</td>";
            echo "<td>".htmlspecialchars($row['first_login'])."</td>";
            echo "<td>".htmlspecialchars($row['created_at'])."</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color:red;'>Error querying users: " . htmlspecialchars($conn->error) . "</p>";
}
echo "</body></html>";
?>
