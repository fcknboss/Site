<?php
require_once 'config.php';

$conn = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.*, u.username FROM escorts e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$escort = $stmt->get_result()->fetch_assoc();
if (!$escort) {
    http_response_code(404);
    die("Perfil não encontrado.");
}

$conn->query("UPDATE escorts SET views = views + 1 WHERE id = $id");
$conn->query("INSERT INTO view_log (escort_id) VALUES ($id)");

$stmt = $conn->prepare("SELECT p.photo_path, pm.status 
                        FROM photos p 
                        LEFT JOIN photo_moderation pm ON p.id = pm.photo_id 
                        WHERE p.escort_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT comment FROM admin_comments WHERE escort_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($escort['name']); ?> - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .gallery-feed { display: flex; overflow-x: hidden; position: relative; }
        .gallery-item { flex: 0 0 200px; margin-right: 10px; }
        .gallery-item img { width: 100%; height: auto; border-radius: 5px; }
        .carousel-prev, .carousel-next { position: absolute; top: 50%; transform: translateY(-50%); }
        .carousel-prev { left: 10px; }
        .carousel-next { right: 10px; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2><a href="index.php" style="color: #E95B95; text-decoration: none;">Eskort</a></h2>
        </div>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="top-right">
                <a href="admin.php">Admin</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="profile-feed">
                <div class="profile-header-card">
                    <div class="profile-photo-container">
                        <picture>
                            <source srcset="<?php echo htmlspecialchars(str_replace('.jpg', '.webp', $escort['profile_photo'])); ?>" type="image/webp">
                            <img src="<?php echo htmlspecialchars($escort['profile_photo']); ?>" alt="<?php echo htmlspecialchars($escort['name']); ?>" class="lazy-load">
                        </picture>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($escort['name']); ?></h1>
                        <p class="profile-meta">
                            <?php echo $escort['type'] === 'acompanhante' ? 'Acompanhante' : 'Pornstar'; ?> • 
                            <?php echo $escort['views']; ?> Visualizações
                        </p>
                    </div>
                </div>

                <div class="profile-section-card">
                    <h3>Galeria</h3>
                    <div class="gallery-feed" id="gallery-feed">
                        <button class="carousel-prev" onclick="galleryPrev()">◄</button>
                        <?php foreach ($photos as $photo): ?>
                            <div class="gallery-item">
                                <picture>
                                    <source srcset="<?php echo htmlspecialchars(str_replace('.jpg', '.webp', $photo['photo_path'])); ?>" type="image/webp">
                                    <img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="Foto de <?php echo htmlspecialchars($escort['name']); ?>" class="lazy-load">
                                </picture>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <button onclick="moderatePhoto(<?php echo $photo['id']; ?>, 'approved')" class="approve-btn">Aprovar</button>
                                    <button onclick="moderatePhoto(<?php echo $photo['id']; ?>, 'rejected')" class="delete-btn">Rejeitar</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <button class="carousel-next" onclick="galleryNext()">►</button>
                    </div>
                </div>

                <div class="profile-section-card">
                    <h3>Sobre</h3>
                    <p><?php echo htmlspecialchars($escort['description']); ?></p>
                    <p><strong>Idade:</strong> <?php echo $escort['age']; ?> anos</p>
                    <p><strong>Altura:</strong> <?php echo $escort['height']; ?>m</p>
                    <p><strong>Peso:</strong> <?php echo $escort['weight']; ?>kg</p>
                    <p><strong>Características Físicas:</strong> <?php echo htmlspecialchars($escort['physical_traits']); ?></p>
                    <p><strong>Idiomas:</strong> <?php echo htmlspecialchars($escort['languages']); ?></p>
                    <p><strong>Tags:</strong> <?php echo htmlspecialchars($escort['tags']); ?></p>
                </div>

                <div class="profile-section-card">
                    <h3>Serviços</h3>
                    <p><?php echo htmlspecialchars($escort['services']); ?></p>
                    <p><strong>Tarifas:</strong> <?php echo htmlspecialchars($escort['rates']); ?></p>
                    <p><strong>Disponibilidade:</strong> <?php echo htmlspecialchars($escort['availability']); ?></p>
                </div>

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <div class="profile-section-card">
                        <h3>Comentário do Admin</h3>
                        <p><?php echo htmlspecialchars($comment['comment'] ?? 'Nenhum comentário'); ?></p>
                        <form method="POST" action="add_comment.php">
                            <input type="hidden" name="escort_id" value="<?php echo $id; ?>">
                            <textarea name="comment" placeholder="Adicionar comentário..." rows="3"></textarea>
                            <button type="submit" class="load-more">Salvar Comentário</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script>
        let galleryIndex = 0;
        const galleryItems = document.querySelectorAll('.gallery-item');
        const itemsPerView = 3;

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function moderatePhoto(photoId, status) {
            fetch('moderate_photo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `photo_id=${photoId}&status=${status}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') location.reload();
                    else alert(data.message);
                });
        }

        function galleryPrev() {
            if (galleryIndex > 0) {
                galleryIndex--;
                updateGallery();
            }
        }

        function galleryNext() {
            if (galleryIndex < galleryItems.length - itemsPerView) {
                galleryIndex++;
                updateGallery();
            }
        }

        function updateGallery() {
            const feed = document.getElementById('gallery-feed');
            const offset = -galleryIndex * (200 + 10); // 200px width + 10px margin
            feed.style.transform = `translateX(${offset}px)`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const backToTop = document.querySelector('.back-to-top');
            window.addEventListener('scroll', () => {
                backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
            });

            const images = document.querySelectorAll('.lazy-load');
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-load');
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '0px 0px 100px 0px' });
            images.forEach(img => observer.observe(img));

            updateGallery();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>