<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$escort_id = isset($_POST['escort_id']) ? (int)$_POST['escort_id'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($escort_id && $comment) {
    $stmt = $conn->prepare("INSERT INTO admin_comments (escort_id, comment) VALUES (?, ?)");
    $stmt->bind_param("is", $escort_id, $comment);
    $stmt->execute();
}

header("Location: profile.php?id=$escort_id");
$conn->close();
?>