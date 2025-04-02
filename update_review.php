<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não logado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $escort_id = (int)$_POST['escort_id'];
    $user_id = $_SESSION['user_id'];
    $rating = (int)$_POST['rating'];
    $comment = $_POST['comment'];

    $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, is_approved = 0 WHERE escort_id = ? AND client_id = ?");
    $stmt->bind_param("isii", $rating, $comment, $escort_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt = $conn->prepare("SELECT r.rating, r.comment, u.username FROM reviews r JOIN users u ON r.client_id = u.id WHERE r.escort_id = ? AND r.client_id = ?");
        $stmt->bind_param("ii", $escort_id, $user_id);
        $stmt->execute();
        $review = $stmt->get_result()->fetch_assoc();
        echo json_encode(['status' => 'success', 'review' => $review]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nenhuma avaliação encontrada para editar']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>