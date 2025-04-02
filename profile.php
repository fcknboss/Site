<?php 
include 'check_session.php';
include 'config.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
    
<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ... (restante do código igual ao anterior) ...
?>
