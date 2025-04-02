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
    $content = $_POST['content'];

    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $post_id, $user_id, $content);
    $stmt->execute();

    $comment_id = $conn->insert_id;
    $stmt = $conn->prepare("SELECT c.content, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $comment = $stmt->get_result()->fetch_assoc();

    echo json_encode(['status' => 'success', 'comment' => $comment]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>