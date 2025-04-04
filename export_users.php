<?php
require_once 'config.php';

$conn = getDBConnection();
$result = $conn->query("SELECT id, username, email, role FROM users");

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="users.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Username', 'Email', 'Role']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [$row['id'], $row['username'], $row['email'], $row['role']]);
}

fclose($output);
$conn->close();
exit;
?>