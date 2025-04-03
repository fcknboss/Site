<?php
require_once 'config.php';

$conn = getDBConnection();
$latest_post_id = $conn->query("SELECT MAX(id) as latest FROM posts")->fetch_assoc()['latest'] ?? 0;

header('Content-Type: application/json');
echo json_encode(['latest_post_id' => $latest_post_id]);
$conn->close();
?>