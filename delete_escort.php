<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

$conn = getDBConnection();
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$stmt = $conn->prepare("DELETE FROM escorts WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Perfil excluído']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir']);
}

$conn->close();
?>