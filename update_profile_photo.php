<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $id = (int)$_POST['id'];
    $target_dir = "uploads/";
    $profile_photo_name = basename($_FILES["profile_photo"]["name"]);
    $profile_photo_path = $target_dir . $profile_photo_name;

    if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $profile_photo_path)) {
        $stmt = $conn->prepare("UPDATE escorts SET profile_photo = ? WHERE id = ?");
        $stmt->bind_param("si", $profile_photo_path, $id);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Foto atualizada com sucesso']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao fazer upload da foto']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Requisição inválida']);
}

$conn->close();
?>