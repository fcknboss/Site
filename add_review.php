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

    $stmt = $conn->prepare("SELECT id FROM reviews WHERE escort_id = ? AND client_id = ?");
    $stmt->bind_param("ii", $escort_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['status' => 'error', 'message' => 'Você já avaliou esta acompanhante.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO reviews (escort_id, client_id, rating, comment, is_approved) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("iiis", $escort_id, $user_id, $rating, $comment);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Avaliação enviada para aprovação']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>