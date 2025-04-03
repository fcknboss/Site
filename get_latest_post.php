<?php
require_once 'config.php';

$conn = getDBConnection();
$result = $conn->query("SELECT MAX(id) as latest_post_id FROM posts");
$data = $result->fetch_assoc();
echo json_encode($data);
$conn->close();
?>