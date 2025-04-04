<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$fields = isset($_POST['fields']) ? $_POST['fields'] : ['name', 'type', 'views'];
$field_map = [
    'name' => 'e.name',
    'type' => 'e.type',
    'views' => 'e.views'
];
$selected_fields = array_intersect_key($field_map, array_flip($fields));
$query_fields = implode(', ', $selected_fields);

$result = $conn->query("SELECT $query_fields FROM escorts e JOIN users u ON e.user_id = u.id");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="escorts_export.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array_keys($selected_fields));

while ($row = $result->fetch_assoc()) {
    fputcsv($output, array_values($row));
}

fclose($output);
$conn->close();
?>