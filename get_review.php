<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não logado']);
    exit;
}

$escort_id = (int)$_GET['escort_id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT rating, comment FROM reviews WHERE escort_id = ? AND client_id = ?");
$stmt->bind_param("ii", $escort_id, $user_id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();

if ($review) {
    echo json_encode(['status' => 'success', 'review' => $review]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Avaliação não encontrada']);
}

$conn->close();
?>