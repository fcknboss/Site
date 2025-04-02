<?php 
include 'config.php';
session_start(); // Para simular usuário logado
$_SESSION['user_id'] = 2; // Simulação de login como joao_client (em produção, use autenticação real)
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskort - Rede de Acompanhantes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Topo estilo Facebook com cores do Orkut -->
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort</h2>
        </div>
        <div class="top-center">
            <input type="text" placeholder="Pesquisar acompanhantes...">
            <button>Pesquisar</button>
        </div>
        <div class="top-right">
            <a href="profile.php">Perfil</a>
            <a href="admin.php">Admin</a>
            <a href="#">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#">Perfis</a></li>
                <li><a href="#">Categorias</a></li>
                <li><a href="#">Blog</a></li>
            </ul>
        </div>
        <div class="main-content">
            <!-- Campo de postagem estilo Facebook -->
            <div class="post-form">
                <textarea id="post-content" placeholder="No que você está pensando?"></textarea>
                <button onclick="submitPost()">Publicar</button>
            </div>
            <!-- Mural estilo Facebook -->
            <div class="feed" id="feed">
                <?php
                $result = $conn->query("SELECT p.id, p.content, p.timestamp, u.username, e.profile_photo 
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN escorts e ON u.id = e.user_id 
                    ORDER BY p.timestamp DESC");
                while ($row = $result->fetch_assoc()) {
                    $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                    echo "<div class='post'>";
                    echo "<div class='post-header'>";
                    echo "<img src='$photo' alt='Foto'>";
                    echo "<div>";
                    echo "<h4>" . $row['username'] . "</h4>";
                    echo "<small>" . $row['timestamp'] . "</small>";
                    echo "</div>";
                    echo "</div>";
                    echo "<p>" . $row['content'] . "</p>";
                    echo "<div class='post-actions'>";
                    echo "<button onclick='likePost(" . $row['id'] . ")'>Curtir</button>";
                    echo "<button onclick='showComment(" . $row['id'] . ")'>Comentar</button>";
                    echo "</div>";
                    echo "<div class='comments' id='comments-" . $row['id'] . "'></div>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
        <div class="right-sidebar">
            <h3>Filtros</h3>
            <ul>
                <li><a href="#">São Paulo</a></li>
                <li><a href="#">Rio de Janeiro</a></li>
            </ul>
            <h3>Acompanhantes em Destaque</h3>
            <?php
            $result = $conn->query("SELECT name, rates FROM escorts LIMIT 2");
            while ($row = $result->fetch_assoc()) {
                echo "<p>" . $row['name'] . " - " . $row['rates'] . "</p>";
            }
            ?>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>