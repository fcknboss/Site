<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();
$fields = isset($_POST['fields']) ? $_POST['fields'] : ['name', 'type', 'views'];

$query = "SELECT " . implode(', ', array_map(function($field) { return "e.$field"; }, $fields)) . " FROM escorts e";
$result = $conn->query($query);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="escorts_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, $fields);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, array_values($row));
}

fclose($output);
$conn->close();
exit;
?>