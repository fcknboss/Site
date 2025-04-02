<?php
header('Content-Type: application/json');
include 'config.php';
$result = $conn->query("SELECT s.message, u.username FROM scraps s JOIN users u ON s.client_id = u.id");
$scraps = [];
while ($row = $result->fetch_assoc()) $scraps[] = $row;
echo json_encode($scraps);
$conn->close();
?>