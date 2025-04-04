<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$result = $conn->query("SELECT e.name, COUNT(f.id) as favorite_count 
                        FROM escorts e 
                        LEFT JOIN favorites f ON e.id = f.escort_id 
                        GROUP BY e.id, e.name 
                        ORDER BY favorite_count DESC 
                        LIMIT 10");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'name' => $row['name'],
        'favorite_count' => (int)$row['favorite_count']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
$conn->close();
?>