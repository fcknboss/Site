<?php 
include 'check_session.php';
include 'config.php';
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Sair</a>
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
            <div class="profiles-section">
                <h3>Acompanhantes</h3>
                <div class="filters">
                    <label>Localização:</label>
                    <input type="text" id="filter-location" placeholder="Digite a cidade...">
                    <label>Tipo:</label>
                    <select id="filter-type">
                        <option value="">Todos</option>
                        <option value="acompanhante">Acompanhante</option>
                        <option value="criadora">Criadora de Conteúdo</option>
                    </select>
                    <button onclick="filterProfiles()">Filtrar</button>
                </div>
                <div class="profiles-grid" id="profiles-grid">
                    <?php
                    $result = $conn->query("SELECT id, name, profile_photo FROM escorts");
                    while ($row = $result->fetch_assoc()) {
                        $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                        echo "<a href='profile.php?id=" . $row['id'] . "' class='profile-card'>";
                        echo "<img src='$photo' alt='" . $row['name'] . "'>";
                        echo "<p>" . $row['name'] . "</p>";
                        echo "</a>";
                    }
                    ?>
                </div>
            </div>

            <div class="post-form">
                <textarea id="post-content" placeholder="No que você está pensando?"></textarea>
                <button onclick="submitPost()">Publicar</button>
            </div>

            <div class="feed" id="feed">
                <?php
                $result = $conn->query("SELECT p.id, p.content, p.timestamp, u.username, e.profile_photo 
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN escorts e ON u.id = e.user_id 
                    ORDER BY p.timestamp DESC");
                while ($row = $result->fetch_assoc()) {
                    $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                    $post_id = $row['id'];
                    $likes = $conn->query("SELECT COUNT(*) as likes FROM likes WHERE post_id = $post_id")->fetch_assoc()['likes'];
                    $comments = $conn->query("SELECT c.content, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = $post_id ORDER BY c.timestamp");
                    echo "<div class='post' id='post-$post_id'>";
                    echo "<div class='another-post-header'>";
                    echo "<img src='$photo' alt='Foto'>";
                    echo "<div>";
                    echo "<h4>" . $row['username'] . "</h4>";
                    echo "<small>" . $row['timestamp'] . "</small>";
                    echo "</div>";
                    echo "</div>";
                    echo "<p>" . $row['content'] . "</p>";
                    echo "<div class='post-actions'>";
                    echo "<button onclick='likePost($post_id)' data-likes='$likes'>Curtir ($likes)</button>";
                    echo "<button onclick='showComment($post_id)'>Comentar</button>";
                    echo "</div>";
                    echo "<div class='comments' id='comments-$post_id'>";
                    while ($comment = $comments->fetch_assoc()) {
                        echo "<p><strong>" . $comment['username'] . ":</strong> " . $comment['content'] . "</p>";
                    }
                    echo "</div>";
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
            <div class="carousel">
                <?php
                $result = $conn->query("SELECT id, name, profile_photo FROM escorts LIMIT 3");
                while ($row = $result->fetch_assoc()) {
                    $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                    echo "<div class='carousel-item'>";
                    echo "<a href='profile.php?id=" . $row['id'] . "'>";
                    echo "<img src='$photo' alt='" . $row['name'] . "'>";
                    echo "<p>" . $row['name'] . "</p>";
                    echo "</a>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>