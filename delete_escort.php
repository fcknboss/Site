<?php
require_once 'session.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Requisição inválida']);
    exit;
}

$conn = getDBConnection();
$id = (int)$_POST['id'];
$stmt = $conn->prepare("DELETE FROM escorts WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logDBAction("DELETE", "escorts", $id, "Perfil excluído");
    echo json_encode(['status' => 'success', 'message' => 'Perfil excluído com sucesso']);
} else {
    logError("Erro ao excluir perfil ID $id: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir perfil']);
}

$stmt->close();
$conn->close();
?>