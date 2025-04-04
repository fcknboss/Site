<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$result = $conn->query("SELECT e.name, SUM(vl.id) as views 
                        FROM escorts e 
                        LEFT JOIN view_log vl ON e.id = vl.escort_id 
                        WHERE vl.timestamp BETWEEN '$start_date' AND '$end_date 23:59:59' 
                        GROUP BY e.id, e.name 
                        ORDER BY views DESC 
                        LIMIT 10");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = ['name' => $row['name'], 'views' => (int)$row['views']];
}

header('Content-Type: application/json');
echo json_encode($data);
$conn->close();
?>