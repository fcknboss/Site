<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $age = (int)$_POST['age'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $physical_traits = $_POST['physical_traits'];
    $services = $_POST['services'];
    $rates = $_POST['rates'];
    $availability = $_POST['availability'];
    $attendance = $_POST['attendance'];
    $payment = $_POST['payment'];
    $type = $_POST['type'];

    $username = strtolower(str_replace(' ', '_', $name)) . rand(100, 999);
    $password = password_hash('default123', PASSWORD_DEFAULT);
    $email = $username . '@eskort.com';
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'escort')");
    $stmt->bind_param("sss", $username, $password, $email);
    $stmt->execute();
    $user_id = $conn->insert_id;

    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $profile_photo_name = basename($_FILES["profile_photo"]["name"]);
    $profile_photo_path = $target_dir . $profile_photo_name;
    move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $profile_photo_path);

    $stmt = $conn->prepare("INSERT INTO escorts (user_id, name, age, location, description, physical_traits, services, rates, availability, profile_photo, type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isissssssss", $user_id, $name, $age, $location, $description, $physical_traits, $services, $rates, $availability, $profile_photo_path, $type);
    $stmt->execute();
    $escort_id = $conn->insert_id;

    if (!empty($_FILES['additional_photos']['name'][0])) {
        $photo_count = count($_FILES['additional_photos']['name']);
        for ($i = 0; $i < min($photo_count, 5); $i++) {
            $photo_name = basename($_FILES['additional_photos']['name'][$i]);
            $photo_path = $target_dir . $photo_name;
            if (move_uploaded_file($_FILES['additional_photos']['tmp_name'][$i], $photo_path)) {
                $stmt = $conn->prepare("INSERT INTO photos (escort_id, photo_path) VALUES (?, ?)");
                $stmt->bind_param("is", $escort_id, $photo_path);
                $stmt->execute();
            }
        }
    }

    $admin_id = $_SESSION['user_id'];
    $post_content = "Nova acompanhante cadastrada: $name - $location, $rates";
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
    $stmt->bind_param("is", $admin_id, $post_content);
    $stmt->execute();

    header("Location: index.php");
    exit;
} else {
    echo "Método inválido.";
}

$conn->close();
?>