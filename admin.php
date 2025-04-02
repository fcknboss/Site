<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT id, role, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['last_activity'] = time();
                header("Location: index.php");
                exit;
            }
        }
        $error = "Usuário ou senha inválidos.";
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
            <a href="logout.php">Sair</a>
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
            <form action="add_escort.php" method="post" enctype="multipart/form-data" class="admin-form" id="admin-form">
                <label>Nome:</label>
                <input type="text" name="name" required>
                <label>Idade:</label>
                <input type="number" name="age" required>
                <label>Localização:</label>
                <input type="text" name="location" required>
                <label>Descrição:</label>
                <textarea name="description" required></textarea>
                <label>Características Físicas (separadas por vírgula):</label>
                <input type="text" name="physical_traits" placeholder="Ex: loira, alta, olhos verdes">
                <label>Serviços (O que ofereço):</label>
                <input type="text" name="services" required>
                <label>Disponibilidade:</label>
                <input type="text" name="availability" required>
                <label>Atendimento:</label>
                <input type="text" name="attendance" value="Com Local, Hotéis e Motéis" required>
                <label>Pagamento:</label>
                <input type="text" name="payment" value="Dinheiro, Cartão" required>
                <label>Tipo:</label>
                <select name="type" required>
                    <option value="acompanhante">Acompanhante</option>
                    <option value="criadora">Criadora de Conteúdo</option>
                </select>
                <label>Foto de Perfil:</label>
                <input type="file" name="profile_photo" id="profile-photo" accept="image/*" required onchange="previewProfilePhoto(event)">
                <div id="profile-preview" class="photo-preview"></div>
                <label>Fotos Adicionais (máximo 5):</label>
                <input type="file" name="additional_photos[]" id="additional-photos" accept="image/*" multiple onchange="previewAdditionalPhotos(event)">
                <div id="additional-preview" class="photo-preview"></div>
                <button type="submit">Cadastrar</button>
            </form>

            <h1>Aprovar Avaliações</h1>
            <div class="reviews-approval">
                <?php
                $stmt = $conn->prepare("SELECT r.id, r.rating, r.comment, u.username, e.name 
                                        FROM reviews r 
                                        JOIN users u ON r.client_id = u.id 
                                        JOIN escorts e ON r.escort_id = e.id 
                                        WHERE r.is_approved = 0");
                $stmt->execute();
                $reviews = $stmt->get_result();
                while ($review = $reviews->fetch_assoc()) {
                    echo "<div class='review-pending'>";
                    echo "<p><strong>" . $review['username'] . " sobre " . $review['name'] . ":</strong> " . $review['rating'] . "/5</p>";
                    echo "<p>" . $review['comment'] . "</p>";
                    echo "<button onclick='approveReview(" . $review['id'] . ")'>Aprovar</button>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
        <div class="right-sidebar">
            <h3>Dicas</h3>
            <p>Preencha todos os campos e adicione até 5 fotos adicionais.</p>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>