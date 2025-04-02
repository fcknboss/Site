<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("SELECT user_id FROM escorts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user_id = $stmt->get_result()->fetch_assoc()['user_id'];

    $stmt = $conn->prepare("DELETE FROM photos WHERE escort_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM reviews WHERE escort_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM messages WHERE escort_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM escorts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>