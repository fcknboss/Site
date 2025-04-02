<?php
header('Content-Type: application/json');
include 'config.php';
echo json_encode(['status' => 'Funcionalidade em desenvolvimento']);
$conn->close();
?>