<?php
header('Content-Type: application/json');
include 'config.php';

$result = $conn->query("SELECT * FROM acompanhantes");
$acompanhantes = [];

while ($row = $result->fetch_assoc()) {
    $acompanhantes[] = $row;
}

echo json_encode($acompanhantes);
$conn->close();
?>