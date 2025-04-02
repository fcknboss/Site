<?php
include 'config.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = (int)$_POST['review_id'];

    $stmt = $conn->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Avaliação não encontrada']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>