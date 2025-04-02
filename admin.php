<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT role, id FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['role'] = $row['role'];
            $_SESSION['user_id'] = $row['id'];
        }
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo '<form method="post">
            <input type="text" name="username" placeholder="Usuário" required>
            <input type="password" name="password" placeholder="Senha" required>
            <button type="submit">Login</button>
        </form>';
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Eskort</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-center">
            <input type="text" placeholder="Pesquisar acompanhantes...">
            <button>Pesquisar</button>
        </div>
        <div class="top-right">
            <a href="index.php">Home</a>
            <a href="admin.php">Admin</a>
            <a href="#">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="admin.php">Cadastrar Acompanhante</a></li>
                <li><a href="#">Gerenciar Perfis</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>Cadastrar Novo Acompanhante</h1>
            <form action="add_escort.php" method="post" enctype="multipart/form-data" class="admin-form">
                <label>Nome:</label>
                <input type="text" name="name" required>
                <label>Idade:</label>
                <input type="number" name="age" required>
                <label>Localização:</label>
                <input type="text" name="location" required>
                <label>Descrição:</label>
                <textarea name="description" required></textarea>
                <label>Serviços:</label>
                <input type="text" name="services" required>
                <label>Taxas:</label>
                <input type="text" name="rates" required>
                <label>Disponibilidade:</label>
                <input type="text" name="availability" required>
                <label>Tipo:</label>
                <select name="type" required>
                    <option value="acompanhante">Acompanhante</option>
                    <option value="criadora">Criadora de Conteúdo</option>
                </select>
                <label>Foto de Perfil:</label>
                <input type="file" name="profile_photo" accept="image/*" required>
                <label>Fotos Adicionais (máximo 5):</label>
                <input type="file" name="additional_photos[]" accept="image/*" multiple>
                <button type="submit">Cadastrar</button>
            </form>
        </div>
        <div class="right-sidebar">
            <h3>Dicas</h3>
            <p>Preencha todos os campos e adicione até 5 fotos adicionais.</p>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>