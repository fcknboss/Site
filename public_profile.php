<?php
require_once 'config.php';

$conn = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.name, e.profile_photo, e.description, e.type, e.views, e.tags 
                        FROM escorts e 
                        WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$escort = $stmt->get_result()->fetch_assoc();
if (!$escort) {
    http_response_code(404);
    die("Perfil não encontrado.");
}

$conn->query("UPDATE escorts SET views = views + 1 WHERE id = $id");
$conn->query("INSERT INTO view_log (escort_id) VALUES ($id)");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($escort['name']); ?> - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .profile-container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
        .profile-container img { max-width: 200px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2><a href="index.php" style="color: #E95B95; text-decoration: none;">Eskort</a></h2>
        </div>
    </div>

    <div class="profile-container">
        <h1><?php echo htmlspecialchars($escort['name']); ?></h1>
        <picture>
            <source srcset="<?php echo htmlspecialchars(str_replace('.jpg', '.webp', $escort['profile_photo'])); ?>" type="image/webp">
            <img src="<?php echo htmlspecialchars($escort['profile_photo']); ?>" alt="<?php echo htmlspecialchars($escort['name']); ?>">
        </picture>
        <p><strong>Tipo:</strong> <?php echo $escort['type'] === 'acompanhante' ? 'Acompanhante' : 'Pornstar'; ?></p>
        <p><strong>Visualizações:</strong> <?php echo $escort['views']; ?></p>
        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($escort['description']); ?></p>
        <p><strong>Tags:</strong> <?php echo htmlspecialchars($escort['tags']); ?></p>
    </div>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script>
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const backToTop = document.querySelector('.back-to-top');
            window.addEventListener('scroll', () => {
                backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>