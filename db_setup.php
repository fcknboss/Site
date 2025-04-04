<?php
// db_setup.php
require_once 'config.php';

// Função para executar consultas com erro detalhado
function executeQuery($conn, $sql, $description = '') {
    if (!$conn->query($sql)) {
        logError("Erro ao executar $description: " . $conn->error);
        die("Erro ao executar $description. Veja o log para detalhes.");
    }
}

// Função para criar backup SQL
function createBackup($conn, $dbname, $backup_dir = 'backups') {
    $backup_dir = __DIR__ . "/$backup_dir";
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true) or die("Erro ao criar diretório de backup.");
    }
    
    $backup_file = "$backup_dir/{$dbname}_" . date('Ymd_His') . ".sql";
    $pass = defined('DB_PASS') && DB_PASS ? "-p" . escapeshellarg(DB_PASS) : '';
    $command = sprintf("mysqldump -h %s -u %s %s %s > %s 2>&1",
        escapeshellarg(DB_HOST), escapeshellarg(DB_USER), $pass, escapeshellarg($dbname), escapeshellarg($backup_file)
    );
    
    // Verifica se mysqldump está disponível
    exec('mysqldump --version', $output, $mysqldump_check);
    if ($mysqldump_check !== 0) {
        logError("mysqldump não encontrado no sistema.");
        echo "Backup não pode ser criado: mysqldump não disponível.<br>";
        return false;
    }
    
    exec($command, $output, $return_var);
    if ($return_var === 0) {
        echo "Backup criado com sucesso em $backup_file!<br>";
        return $backup_file;
    } else {
        logError("Erro ao criar backup: " . implode("\n", $output));
        echo "Erro ao criar backup. Veja o log para detalhes.<br>";
        return false;
    }
}

// Função para criar tabelas
function createTables($conn) {
    executeQuery($conn, "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        role ENUM('client', 'escort', 'admin') DEFAULT 'client'
    )", "tabela users");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS escorts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        age INT CHECK (age >= 18),
        location VARCHAR(100),
        description TEXT,
        services TEXT,
        rates VARCHAR(100),
        availability VARCHAR(255),
        profile_photo VARCHAR(100),
        type ENUM('acompanhante', 'criadora') DEFAULT 'acompanhante',
        is_online TINYINT(1) DEFAULT 0,
        physical_traits VARCHAR(255),
        phone VARCHAR(20),
        height DECIMAL(3,2) CHECK (height >= 0),
        weight INT CHECK (weight >= 0),
        languages VARCHAR(100),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )", "tabela escorts");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        escort_id INT NOT NULL,
        photo_path VARCHAR(100) NOT NULL,
        FOREIGN KEY (escort_id) REFERENCES escorts(id) ON DELETE CASCADE
    )", "tabela photos");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS scraps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        escort_id INT NOT NULL,
        client_id INT NOT NULL,
        message TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (escort_id) REFERENCES escorts(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
    )", "tabela scraps");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        escort_id INT NOT NULL,
        client_id INT NOT NULL,
        rating INT CHECK (rating BETWEEN 1 AND 5),
        comment TEXT,
        date DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_approved TINYINT(1) DEFAULT 0,
        FOREIGN KEY (escort_id) REFERENCES escorts(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
    )", "tabela reviews");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )", "tabela posts");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (post_id, user_id)
    )", "tabela likes");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )", "tabela comments");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        escort_id INT NOT NULL,
        sender_id INT NOT NULL,
        content TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (escort_id) REFERENCES escorts(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )", "tabela messages");

    executeQuery($conn, "CREATE TABLE IF NOT EXISTS schema_version (
        id INT AUTO_INCREMENT PRIMARY KEY,
        version INT NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        description VARCHAR(255)
    )", "tabela schema_version");
}

// Função para inserir dados iniciais
function insertInitialData($conn) {
    $users = [
        ['username' => 'ana_escort', 'password' => password_hash('123', PASSWORD_DEFAULT), 'email' => 'ana@example.com', 'role' => 'escort'],
        ['username' => 'joao_client', 'password' => password_hash('123', PASSWORD_DEFAULT), 'email' => 'joao@example.com', 'role' => 'client'],
        ['username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'email' => 'admin@example.com', 'role' => 'admin'],
        ['username' => 'luna_content', 'password' => password_hash('123', PASSWORD_DEFAULT), 'email' => 'luna@example.com', 'role' => 'escort']
    ];
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->bind_param("ssss", $user['username'], $user['password'], $user['email'], $user['role']);
        $stmt->execute() or logError("Erro ao inserir usuário: " . $conn->error);
    }

    $escorts = [
        [1, 'Ana', 25, 'São Paulo', 'Acompanhante simpática e divertida', 'Companhia, eventos', 'R$200/h', 'Seg-Sex, 18h-23h', 'uploads/ana.jpg', 'acompanhante', 1, 'loira, alta, olhos verdes', '(11) 98226-4226', 1.70, 60, 'Português, Inglês'],
        [4, 'Luna', 28, 'Rio de Janeiro', 'Criadora de conteúdo e companhia', 'Fotos, vídeos, eventos', 'R$300/h', 'Ter-Qui, 14h-20h', 'uploads/luna.jpg', 'criadora', 0, 'morena, curvilínea, olhos castanhos', '(11) 93379-6106', 1.65, 58, 'Português, Espanhol']
    ];
    $stmt = $conn->prepare("INSERT INTO escorts (user_id, name, age, location, description, services, rates, availability, profile_photo, type, is_online, physical_traits, phone, height, weight, languages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($escorts as $escort) {
        $stmt->bind_param("isisssssssisssis", $escort[0], $escort[1], $escort[2], $escort[3], $escort[4], $escort[5], $escort[6], $escort[7], $escort[8], $escort[9], $escort[10], $escort[11], $escort[12], $escort[13], $escort[14], $escort[15]);
        $stmt->execute() or logError("Erro ao inserir acompanhante: " . $conn->error);
    }

    $photos = [
        [1, 'uploads/ana1.jpg'], [1, 'uploads/ana2.jpg'],
        [2, 'uploads/luna1.jpg'], [2, 'uploads/luna2.jpg']
    ];
    $stmt = $conn->prepare("INSERT INTO photos (escort_id, photo_path) VALUES (?, ?)");
    foreach ($photos as $photo) {
        $stmt->bind_param("is", $photo[0], $photo[1]);
        $stmt->execute() or logError("Erro ao inserir foto: " . $conn->error);
    }

    $reviews = [[1, 2, 5, 'Excelente companhia, super recomendo!', 1]];
    $stmt = $conn->prepare("INSERT INTO reviews (escort_id, client_id, rating, comment, is_approved) VALUES (?, ?, ?, ?, ?)");
    foreach ($reviews as $review) {
        $stmt->bind_param("iiisi", $review[0], $review[1], $review[2], $review[3], $review[4]);
        $stmt->execute() or logError("Erro ao inserir revisão: " . $conn->error);
    }

    $posts = [
        [1, 'Disponível hoje à noite em SP!'],
        [2, 'Recomendo a Ana, ótima companhia!']
    ];
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
    foreach ($posts as $post) {
        $stmt->bind_param("is", $post[0], $post[1]);
        $stmt->execute() or logError("Erro ao inserir post: " . $conn->error);
    }

    $likes = [[1, 2], [2, 1]];
    $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
    foreach ($likes as $like) {
        $stmt->bind_param("ii", $like[0], $like[1]);
        $stmt->execute() or logError("Erro ao inserir curtida: " . $conn->error);
    }

    $comments = [
        [1, 2, 'Muito legal!'],
        [2, 1, 'Obrigada pela recomendação!']
    ];
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    foreach ($comments as $comment) {
        $stmt->bind_param("iis", $comment[0], $comment[1], $comment[2]);
        $stmt->execute() or logError("Erro ao inserir comentário: " . $conn->error);
    }
}

// Conexão
$conn = getDBConnection();

// Verifica e aplica a versão do esquema
$schema_version = 1; // Versão atual
$result = $conn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
if ($result->num_rows > 0) {
    $conn->select_db(DB_NAME);
    $result = $conn->query("SHOW TABLES LIKE 'schema_version'");
    if ($result && $result->num_rows > 0) {
        $version_result = $conn->query("SELECT MAX(version) as current_version FROM schema_version");
        $current_version = $version_result->fetch_assoc()['current_version'];
        if ($current_version >= $schema_version) {
            echo "Banco de dados já configurado com a versão $current_version.<br>";
            $conn->close();
            exit;
        }
    }
} else {
    // Cria o banco se não existir
    executeQuery($conn, "CREATE DATABASE " . DB_NAME, "create database");
    $conn->select_db(DB_NAME);
}

// Cria as tabelas e insere dados
createTables($conn);
insertInitialData($conn);

// Define a versão inicial do esquema
executeQuery($conn, "INSERT INTO schema_version (version, description) VALUES ($schema_version, 'Esquema inicial com users, escorts, photos, scraps, reviews, posts, likes, comments, messages')", "inserção de versão inicial");

// Executa o backup
createBackup($conn, DB_NAME);

echo "Banco de dados configurado com sucesso!";
$conn->close();
?>