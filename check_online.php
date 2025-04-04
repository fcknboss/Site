<?php
include 'config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $conn->prepare("SELECT is_online FROM escorts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo json_encode(['is_online' => (bool)$result['is_online']]);
$conn->close();
?>