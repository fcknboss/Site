<?php
require_once 'config.php';

$conn = getDBConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'search';
$suggestions = [];

if (strlen($query) >= 2) {
    if ($type === 'keyword') {
        $stmt = $conn->prepare("SELECT DISTINCT keyword FROM keywords WHERE keyword LIKE ? LIMIT 5");
    } else {
        $stmt = $conn->prepare("SELECT name FROM escorts WHERE name LIKE ? OR description LIKE ? OR services LIKE ? OR tags LIKE ? LIMIT 5");
        $stmt->bind_param("ssss", $param, $param, $param, $param);
    }
    $param = "%$query%";
    $stmt->bind_param("s", $param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $type === 'keyword' ? $row['keyword'] : $row['name'];
    }
}

header('Content-Type: application/json');
echo json_encode($suggestions);
$conn->close();
?>