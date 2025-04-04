<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'escort') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();
$user_id = (int)$_SESSION['user_id'];

$escort = $conn->query("SELECT * FROM escorts WHERE user_id = $user_id")->fetch_assoc();
if (!$escort) {
    die("Perfil não encontrado.");
}

$photos = $conn->query("SELECT id, photo_path, is_highlighted, caption FROM photos WHERE escort_id = " . $escort['id'])->fetch_all(MYSQLI_ASSOC);
$messages = $conn->query("SELECT m.id, m.message, m.sent_at, m.is_read, u.username as sender 
                          FROM messages m 
                          JOIN users u ON m.sender_id = u.id 
                          WHERE m.receiver_id = $user_id 
                          ORDER BY m.sent_at DESC 
                          LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$conn->query("UPDATE messages SET is_read = 1 WHERE receiver_id = $user_id AND is_read = 0");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort</h2>
        </div>
        <div class="top-right">
            <a href="index.php">Home</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <h2>Bem-vindo, <?php echo htmlspecialchars($escort['name']); ?>!</h2>

            <div class="profile-header-card">
                <div class="profile-photo-container">
                    <picture>
                        <source srcset="<?php echo htmlspecialchars(str_replace('.jpg', '.webp', $escort['profile_photo'])); ?>" type="image/webp">
                        <img src="<?php echo htmlspecialchars($escort['profile_photo']); ?>" alt="<?php echo htmlspecialchars($escort['name']); ?>">
                    </picture>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($escort['name']); ?></h1>
                    <p class="profile-meta">
                        <?php echo $escort['type'] === 'acompanhante' ? 'Acompanhante' : 'Pornstar'; ?> • 
                        <?php echo $escort['views']; ?> Visualizações
                    </p>
                    <p><strong>Disponibilidade:</strong> <?php echo htmlspecialchars($escort['availability']); ?></p>
                    <p><strong>Tarifas:</strong> <?php echo htmlspecialchars($escort['rates']); ?></p>
                </div>
            </div>

            <div class="profile-section-card">
                <h3>Sobre</h3>
                <p><?php echo htmlspecialchars($escort['description']); ?></p>
                <p><strong>Tags:</strong> <?php echo htmlspecialchars($escort['tags']); ?></p>
            </div>

            <div class="profile-section-card">
                <h3>Fotos</h3>
                <div class="gallery-feed">
                    <?php foreach ($photos as $photo): ?>
                        <div class="gallery-item">
                            <picture>
                                <source srcset="<?php echo htmlspecialchars(str_replace('.jpg', '.webp', $photo['photo_path'])); ?>" type="image/webp">
                                <img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="<?php echo htmlspecialchars($photo['caption'] ?? 'Foto'); ?>" style="<?php echo $photo['is_highlighted'] ? 'border: 2px solid #E95B95;' : ''; ?>">
                            </picture>
                            <p><?php echo htmlspecialchars($photo['caption'] ?? 'Sem legenda'); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="profile-section-card">
                <h3>Mensagens Recentes</h3>
                <?php if (empty($messages)): ?>
                    <p>Sem mensagens recentes.</p>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="feed-post">
                            <div class="feed-post-header">
                                <h4><?php echo htmlspecialchars($msg['sender']); ?></h4>
                                <small><?php echo $msg['sent_at']; ?></small>
                            </div>
                            <p><?php echo htmlspecialchars($msg['message']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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