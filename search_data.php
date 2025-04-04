<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$result = $conn->query("SELECT query, COUNT(*) as count 
                        FROM search_log 
                        WHERE admin_id = " . (int)$_SESSION['user_id'] . " 
                        GROUP BY query 
                        ORDER BY count DESC 
                        LIMIT 10");

$search_counts = [];
while ($row = $result->fetch_assoc()) {
    $search_counts[$row['query']] = (int)$row['count'];
}

header('Content-Type: application/json');
echo json_encode($search_counts);
$conn->close();
?>