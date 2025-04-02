<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não logado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $content = $_POST['content'];

    $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $content);
    $stmt->execute();
    $post_id = $conn->insert_id;

    $stmt = $conn->prepare("SELECT p.id, p.content, p.timestamp, u.username, e.profile_photo 
                            FROM posts p 
                            JOIN users u ON p.user_id = u.id 
                            LEFT JOIN escorts e ON u.id = e.user_id 
                            WHERE p.id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();

    echo json_encode(['status' => 'success', 'post' => $post]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
}

$conn->close();
?>