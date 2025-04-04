<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

$conn = getDBConnection();
$photo_id = isset($_POST['photo_id']) ? (int)$_POST['photo_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if ($photo_id && in_array($status, ['approved', 'rejected'])) {
    $stmt = $conn->prepare("INSERT INTO photo_moderation (photo_id, status) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE status = ?, moderated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param("iss", $photo_id, $status, $status);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Foto moderada']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao moderar']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
}

$conn->close();
?>