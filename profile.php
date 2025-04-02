<?php
include 'config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM escorts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$escort = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $escort['name']; ?> - Eskort</title>
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
            <a href="index.php">Home</a>
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
            </ul>
        </div>
        <div class="main-content">
            <div class="profile-header">
                <img src="<?php echo $escort['profile_photo']; ?>" alt="<?php echo $escort['name']; ?>">
                <h1><?php echo $escort['name']; ?>, <?php echo $escort['age']; ?></h1>
                <p><?php echo $escort['location']; ?></p>
            </div>
            <div class="profile-details">
                <p><strong>Descrição:</strong> <?php echo $escort['description']; ?></p>
                <p><strong>Serviços:</strong> <?php echo $escort['services']; ?></p>
                <p><strong>Taxas:</strong> <?php echo $escort['rates']; ?></p>
                <p><strong>Disponibilidade:</strong> <?php echo $escort['availability']; ?></p>
                <button>Reservar</button>
            </div>
            <div class="scrapbook">
                <h3>Scrapbook</h3>
                <?php
                $stmt = $conn->prepare("SELECT s.message, u.username FROM scraps s JOIN users u ON s.client_id = u.id WHERE s.escort_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $scraps = $stmt->get_result();
                while ($scrap = $scraps->fetch_assoc()) {
                    echo "<p><strong>" . $scrap['username'] . ":</strong> " . $scrap['message'] . "</p>";
                }
                ?>
            </div>
            <div class="reviews">
                <h3>Avaliações</h3>
                <?php
                $stmt = $conn->prepare("SELECT r.rating, r.comment, u.username FROM reviews r JOIN users u ON r.client_id = u.id WHERE r.escort_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $reviews = $stmt->get_result();
                while ($review = $reviews->fetch_assoc()) {
                    echo "<p><strong>" . $review['username'] . ":</strong> " . $review['rating'] . "/5 - " . $review['comment'] . "</p>";
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
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>