<?php
require_once 'config.php';

$conn = getDBConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$suggestions = [];

if (strlen($query) >= 2) {
    $stmt = $conn->prepare("SELECT name FROM escorts WHERE name LIKE ? OR description LIKE ? OR services LIKE ? OR tags LIKE ? LIMIT 5");
    $param = "%$query%";
    $stmt->bind_param("ssss", $param, $param, $param, $param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['name'];
    }
}

header('Content-Type: application/json');
echo json_encode($suggestions);
$conn->close();
?>