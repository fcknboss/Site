<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'eskort';

// Conectar ao MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error);

// Criar banco de dados
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

// Criar tabelas
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(255),
    email VARCHAR(100),
    role ENUM('client', 'escort', 'admin') DEFAULT 'client'
)");

$conn->query("CREATE TABLE IF NOT EXISTS escorts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100),
    age INT,
    location VARCHAR(100),
    description TEXT,
    services TEXT,
    rates VARCHAR(100),
    availability VARCHAR(255),
    profile_photo VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS scraps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escort_id INT,
    client_id INT,
    message TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escort_id) REFERENCES escorts(id),
    FOREIGN KEY (client_id) REFERENCES users(id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escort_id INT,
    client_id INT,
    rating INT,
    comment TEXT,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escort_id) REFERENCES escorts(id),
    FOREIGN KEY (client_id) REFERENCES users(id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    content TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Inserir dados de exemplo
$conn->query("INSERT INTO users (username, password, email, role) 
    VALUES ('ana_escort', '123', 'ana@example.com', 'escort'), 
           ('joao_client', '123', 'joao@example.com', 'client'),
           ('admin', 'admin123', 'admin@example.com', 'admin')");

$conn->query("INSERT INTO escorts (user_id, name, age, location, description, services, rates, availability, profile_photo) 
    VALUES (1, 'Ana', 25, 'São Paulo', 'Acompanhante simpática e divertida', 'Companhia, eventos', 'R$200/h', 'Seg-Sex, 18h-23h', 'uploads/ana.jpg')");

$conn->query("INSERT INTO scraps (escort_id, client_id, message) 
    VALUES (1, 2, 'Oi Ana, adorei seu perfil!')");

$conn->query("INSERT INTO reviews (escort_id, client_id, rating, comment) 
    VALUES (1, 2, 5, 'Excelente companhia, super recomendo!')");

$conn->query("INSERT INTO posts (user_id, content) 
    VALUES (1, 'Disponível hoje à noite em SP!'), 
           (2, 'Recomendo a Ana, ótima companhia!')");

echo "Banco de dados configurado com sucesso!";
$conn->close();
?>