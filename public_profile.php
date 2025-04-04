<?php
require_once 'config.php';

$conn = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.name, e.profile_photo, e.description, e.type, e.views, e.tags, e.video_url, e.latitude, e.longitude 
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
$conn->query("INSERT INTO view_log (escort_id) VALUES ($id)"); // Corrigido

$stmt = $conn->prepare("SELECT photo_path, is_highlighted FROM photos WHERE escort_id = ? ORDER BY is_highlighted DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT rating, comment, created_at FROM reviews WHERE escort_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($escort['name']); ?> - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .gallery-feed { position: relative; overflow: hidden; }
        .gallery-inner { display: flex; transition: transform 0.3s ease; }
        .gallery-item { flex: 0 0 100px; margin-right: 10px; }
        .gallery-item img { width: 100%; height: auto; border-radius: 5px; }
        #map { height: 200px; width: 100%; border-radius: 8px; }
    </style>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY"></script>
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2><a href="index.php" style="color: #E95B95; text-decoration: none;">Eskort</a></h2>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
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
                </div>
            </div>

            <div class="profile-section-card">
                <h3>Sobre</h3>
                <p><?php echo htmlspecialchars($escort['description']); ?></p>
                <p><strong>Tags:</strong> <?php echo htmlspecialchars($escort['tags']); ?></p>
            </div>

            <div class="profile-section-card">
                <h3>Fotos</h3>
                <div class="gallery-feed" id="gallery-feed">
                    <button class="carousel-prev" onclick="galleryPrev()">◄</button>
                    <div class="gallery-inner" id="gallery-inner">
                        <?php foreach ($photos as $photo): ?>
                            <div class="gallery-item">
                                <picture>
                                    <source srcset="<?php echo htmlspecialchars(str_replace('.jpg', '.webp', $photo['photo_path'])); ?>" type="image/webp">
                                    <img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="Foto de <?php echo htmlspecialchars($escort['name']); ?>" style="<?php echo $photo['is_highlighted'] ? 'border: 2px solid #E95B95;' : ''; ?>">
                                </picture>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-next" onclick="galleryNext()">►</button>
                </div>
            </div>

            <?php if ($escort['video_url']): ?>
                <div class="profile-section-card">
                    <h3>Vídeo</h3>
                    <video controls>
                        <source src="<?php echo htmlspecialchars($escort['video_url']); ?>" type="video/mp4">
                        Seu navegador não suporta vídeos.
                    </video>
                </div>
            <?php endif; ?>

            <?php if ($escort['latitude'] && $escort['longitude']): ?>
                <div class="profile-section-card">
                    <h3>Localização</h3>
                    <div id="map"></div>
                </div>
            <?php endif; ?>

            <div class="profile-section-card">
                <h3>Avaliações</h3>
                <?php if (empty($reviews)): ?>
                    <p>Sem avaliações ainda.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="feed-post">
                            <p><strong>Nota:</strong> <?php echo $review['rating']; ?>/5</p>
                            <p><?php echo htmlspecialchars($review['comment']); ?></p>
                            <small><?php echo $review['created_at']; ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script>
        let galleryIndex = 0;
        const galleryItems = document.querySelectorAll('.gallery-item');
        const itemsPerView = Math.floor(document.querySelector('.gallery-feed').offsetWidth / 110);

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
            const galleryInner = document.getElementById('gallery-inner');
            const offset = -galleryIndex * 110;
            galleryInner.style.transform = `translateX(${offset}px)`;
        }

        function initMap() {
            const location = { lat: <?php echo $escort['latitude']; ?>, lng: <?php echo $escort['longitude']; ?> };
            const map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: location
            });
            new google.maps.Marker({
                position: location,
                map: map
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const backToTop = document.querySelector('.back-to-top');
            window.addEventListener('scroll', () => {
                backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
            });
            updateGallery();
            <?php if ($escort['latitude'] && $escort['longitude']): ?>
                initMap();
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>