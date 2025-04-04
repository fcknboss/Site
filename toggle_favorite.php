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
$admin_id = (int)$_SESSION['user_id'];

if ($escort_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

$conn = getDBConnection();
$check = $conn->prepare("SELECT id FROM favorites WHERE admin_id = ? AND escort_id = ?");
$check->bind_param("ii", $admin_id, $escort_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $stmt = $conn->prepare("DELETE FROM favorites WHERE admin_id = ? AND escort_id = ?");
    $action = "removido";
} else {
    $stmt = $conn->prepare("INSERT INTO favorites (admin_id, escort_id, is_public) VALUES (?, ?, 0)");
    $action = "adicionado";
}
$stmt->bind_param("ii", $admin_id, $escort_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => "Favorito $action com sucesso"]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao alterar favorito']);
}
$stmt->close();
$conn->close();
?>