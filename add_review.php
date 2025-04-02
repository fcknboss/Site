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

    $stmt = $conn->prepare("INSERT INTO reviews (escort_id, client_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $escort_id, $user_id, $rating, $comment);
    $stmt->execute();

    $review_id = $conn->insert_id;
    $stmt = $conn->prepare("SELECT r.rating, r.comment, u.username FROM reviews r JOIN users u ON r.client_id = u.id WHERE r.id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();

    echo json_encode(['status' => 'success', 'review' => $review]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>