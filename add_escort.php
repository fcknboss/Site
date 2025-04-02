<?php
session_start();
include 'config.php';

// Verificar se é admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $age = (int)$_POST['age'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $services = $_POST['services'];
    $rates = $_POST['rates'];
    $availability = $_POST['availability'];

    // Criar usuário básico para o acompanhante
    $ username = strtolower(str_replace(' ', '_', $name)) . rand(100, 999);
    $password = 'default123'; // Em produção, use hash seguro
    $email = $username . '@eskort.com';
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'escort')");
    $stmt->bind_param("sss", $username, $password, $email);
    $stmt->execute();
    $user_id = $conn->insert_id;

    // Upload da foto
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $photo_name = basename($_FILES["profile_photo"]["name"]);
    $target_file = $target_dir . $photo_name;
    move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file);

    // Inserir acompanhante
    $stmt = $conn->prepare("INSERT INTO escorts (user_id, name, age, location, description, services, rates, availability, profile_photo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isissssss", $user_id, $name, $age, $location, $description, $services, $rates, $availability, $target_file);
    $stmt->execute();

    echo "Acompanhante cadastrado com sucesso! <a href='admin.php'>Voltar</a>";
} else {
    echo "Método inválido.";
}

$conn->close();
?>