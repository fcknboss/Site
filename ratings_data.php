<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$result = $conn->query("SELECT e.name, AVG(r.rating) as avg_rating, COUNT(r.id) as total_reviews 
                        FROM escorts e 
                        LEFT JOIN reviews r ON e.id = r.escort_id 
                        WHERE r.created_at BETWEEN '$start_date' AND '$end_date 23:59:59' 
                        GROUP BY e.id, e.name 
                        HAVING total_reviews > 0 
                        ORDER BY avg_rating DESC 
                        LIMIT 10");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'name' => $row['name'],
        'avg_rating' => round($row['avg_rating'], 2),
        'total_reviews' => (int)$row['total_reviews']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
$conn->close();
?>