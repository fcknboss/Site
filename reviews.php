<?php
header('Content-Type: application/json');
include 'config.php';
$result = $conn->query("SELECT r.rating, r.comment, u.username FROM reviews r JOIN users u ON r.client_id = u.id");
$reviews = [];
while ($row = $result->fetch_assoc()) $reviews[] = $row;
echo json_encode($reviews);
$conn->close();
?>