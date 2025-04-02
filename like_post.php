<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não logado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)$_POST['post_id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();

    $count = $conn->query("SELECT COUNT(*) as likes FROM likes WHERE post_id = $post_id")->fetch_assoc()['likes'];
    echo json_encode(['status' => 'success', 'likes' => $count]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>