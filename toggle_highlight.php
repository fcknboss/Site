<?php
require_once 'session.php';
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido']);
    exit;
}

$escort_id = isset($_POST['escort_id']) ? (int)$_POST['escort_id'] : 0;
if ($escort_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare("UPDATE photos SET is_highlighted = CASE WHEN is_highlighted = 1 THEN 0 ELSE 1 END WHERE escort_id = ? LIMIT 1");
$stmt->bind_param("i", $escort_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Destaque alterado com sucesso']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao alterar destaque']);
}
$stmt->close();
$conn->close();
?>