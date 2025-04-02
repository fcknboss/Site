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
    type ENUM('acompanhante', 'criadora') DEFAULT 'acompanhante',
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escort_id INT,
    photo_path VARCHAR(100),
    FOREIGN KEY (escort_id) REFERENCES escorts(id)
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

$conn->query("CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    user_id INT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE (post_id, user_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    user_id INT,
    content TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Inserir dados de exemplo
$conn->query("INSERT INTO users (username, password, email, role) 
    VALUES ('ana_escort', '123', 'ana@example.com', 'escort'), 
           ('joao_client', '123', 'joao@example.com', 'client'),
           ('admin', 'admin123', 'admin@example.com', 'admin'),
           ('luna_content', '123', 'luna@example.com', 'escort')");

$conn->query("INSERT INTO escorts (user_id, name, age, location, description, services, rates, availability, profile_photo, type) 
    VALUES (1, 'Ana', 25, 'São Paulo', 'Acompanhante simpática e divertida', 'Companhia, eventos', 'R$200/h', 'Seg-Sex, 18h-23h', 'uploads/ana.jpg', 'acompanhante'),
           (4, 'Luna', 28, 'Rio de Janeiro', 'Criadora de conteúdo e companhia', 'Fotos, vídeos, eventos', 'R$300/h', 'Ter-Qui, 14h-20h', 'uploads/luna.jpg', 'criadora')");

$conn->query("INSERT INTO photos (escort_id, photo_path) 
    VALUES (1, 'uploads/ana1.jpg'), (1, 'uploads/ana2.jpg'), 
           (2, 'uploads/luna1.jpg'), (2, 'uploads/luna2.jpg')");

$conn->query("INSERT INTO scraps (escort_id, client_id, message) 
    VALUES (1, 2, 'Oi Ana, adorei seu perfil!')");

$conn->query("INSERT INTO reviews (escort_id, client_id, rating, comment) 
    VALUES (1, 2, 5, 'Excelente companhia, super recomendo!')");

$conn->query("INSERT INTO posts (user_id, content) 
    VALUES (1, 'Disponível hoje à noite em SP!'), 
           (2, 'Recomendo a Ana, ótima companhia!')");

$conn->query("INSERT INTO likes (post_id, user_id) 
    VALUES (1, 2), (2, 1)");

$conn->query("INSERT INTO comments (post_id, user_id, content) 
    VALUES (1, 2, 'Muito legal!'), (2, 1, 'Obrigada pela recomendação!')");

echo "Banco de dados configurado com sucesso!";
$conn->close();
?>