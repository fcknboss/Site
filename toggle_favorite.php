<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

$conn = getDBConnection();
$admin_id = $_SESSION['user_id'];
$escort_id = isset($_POST['escort_id']) ? (int)$_POST['escort_id'] : 0;

$check = $conn->prepare("SELECT id FROM favorites WHERE admin_id = ? AND escort_id = ?");
$check->bind_param("ii", $admin_id, $escort_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $delete = $conn->prepare("DELETE FROM favorites WHERE admin_id = ? AND escort_id = ?");
    $delete->bind_param("ii", $admin_id, $escort_id);
    $delete->execute();
    $message = 'Removido dos favoritos';
} else {
    $insert = $conn->prepare("INSERT INTO favorites (admin_id, escort_id) VALUES (?, ?)");
    $insert->bind_param("ii", $admin_id, $escort_id);
    $insert->execute();
    $message = 'Adicionado aos favoritos';
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => $message]);
$conn->close();
?>