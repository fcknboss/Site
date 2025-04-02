<?php
include 'check_session.php';
include 'config.php';

// Obtém e valida o ID do perfil
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Busca dados do acompanhante
$stmt = $conn->prepare("SELECT e.*, u.username, u.email FROM escorts e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$escort = $stmt->get_result()->fetch_assoc();
if (!$escort) {
    http_response_code(404);
    die("Acompanhante não encontrada.");
}

// Calcula média de avaliações aprovadas com cache simples
$cache_file = "cache/reviews_$id.json";
$cache_duration = 300; // 5 minutos
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration)) {
    $rating_data = json_decode(file_get_contents($cache_file), true);
} else {
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE escort_id = ? AND is_approved = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $rating_data = $stmt->get_result()->fetch_assoc();
    file_put_contents($cache_file, json_encode($rating_data));
}
$avg_rating = $rating_data['avg_rating'] ? number_format($rating_data['avg_rating'], 1) : 'N/A';
$review_count = $rating_data['review_count'];

// Verifica se o usuário atual tem uma avaliação
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, rating, comment FROM reviews WHERE escort_id = ? AND client_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$user_review = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($escort['name']); ?> - Eskort</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="profile-container">
        <section class="profile-header">
            <div class="profile-photo-container">
                <img src="<?php echo htmlspecialchars($escort['profile_photo']); ?>" alt="<?php echo htmlspecialchars($escort['name']); ?>" loading="lazy">
            </div>
            <div class="profile-info">
                <h1>
                    <?php echo htmlspecialchars($escort['name']); ?>
                    <span class="verified">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z" fill="#E95B95"/>
                        </svg>
                    </span>
                    <?php if ($escort['type'] === 'acompanhante'): ?>
                        <span class="acompanhante">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7C10.9 7 10 7.9 10 9H8C8 6.79 9.79 5 12 5C14.21 5 16 6.79 16 9C16 9.88 15.64 10.68 15.07 11.25Z" fill="#28A745"/>
                            </svg>
                        </span>
                    <?php elseif ($escort['type'] === 'criadora'): ?>
                        <span class="criadora">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#FFD700"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="location"><?php echo htmlspecialchars($escort['location']); ?> | <?php echo $escort['age']; ?> anos</p>
                <p class="rates"><?php echo htmlspecialchars($escort['rates']); ?></p>
                <p><strong>Avaliação Média:</strong> <?php echo $avg_rating; ?>/5 (<?php echo $review_count; ?> avaliações)</p>
                <div class="social-links">
                    <a href="https://instagram.com/<?php echo htmlspecialchars(strtolower(str_replace(' ', '', $escort['username']))); ?>" target="_blank" rel="noopener">Instagram</a>
                    <a href="https://twitter.com/<?php echo htmlspecialchars(strtolower(str_replace(' ', '', $escort['username']))); ?>" target="_blank" rel="noopener">Twitter</a>
                </div>
                <button type="button">Reservar</button>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="profile-actions">
                        <a href="edit_escort.php?id=<?php echo $id; ?>" class="edit-btn">
                            Editar
                            <span class="tooltip">Editar Perfil</span>
                        </a>
                        <button onclick="deleteEscort(<?php echo $id; ?>)" class="delete-btn">
                            Excluir
                            <span class="tooltip">Excluir Perfil</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="profile-section">
            <h2>Galeria</h2>
            <div class="photo-gallery">
                <?php
                $stmt = $conn->prepare("SELECT photo_path FROM photos WHERE escort_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $photos = $stmt->get_result();
                while ($photo = $photos->fetch_assoc()):
                ?>
                    <img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="Foto adicional" onclick="openLightbox(this.src)" loading="lazy">
                <?php endwhile; ?>
            </div>
        </section>

        <section class="profile-section">
            <h2>Sobre</h2>
            <p><?php echo htmlspecialchars($escort['description']); ?></p>
            <?php if ($escort['physical_traits']): ?>
                <div class="tags">
                    <?php
                    $traits = array_map('trim', explode(',', $escort['physical_traits']));
                    foreach ($traits as $trait):
                    ?>
                        <span class="tag"><?php echo htmlspecialchars($trait); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="profile-section">
            <h2>Serviços</h2>
            <p><strong>O que ofereço:</strong> <?php echo htmlspecialchars($escort['services']); ?></p>
            <p><strong>Disponibilidade:</strong> <?php echo htmlspecialchars($escort['availability']); ?></p>
            <p><strong>Atendimento:</strong> Com Local, Hotéis e Motéis</p>
            <p><strong>Pagamento:</strong> Dinheiro, Cartão</p>
        </section>

        <section class="profile-section">
            <h2>Contato</h2>
            <form id="contact-form" onsubmit="return validateAndSendMessage(event, <?php echo $id; ?>);">
                <textarea id="contact-message" placeholder="Envie uma mensagem..." required aria-label="Mensagem"></textarea>
                <button type="submit">Enviar</button>
            </form>
            <div id="contact-response"></div>
        </section>

        <section class="profile-section">
            <h2>Avaliações</h2>
            <div class="review-form">
                <h3><?php echo $user_review ? 'Edite sua Avaliação' : 'Deixe sua Avaliação'; ?></h3>
                <div class="star-rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-value="<?php echo $i; ?>" <?php if ($user_review && $i <= $user_review['rating']) echo 'class="active"'; ?>>★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden" id="rating-value" value="<?php echo $user_review ? $user_review['rating'] : 0; ?>">
                <textarea id="review-comment" placeholder="Escreva seu comentário..." required><?php echo $user_review ? htmlspecialchars($user_review['comment']) : ''; ?></textarea>
                <button onclick="submitReview(<?php echo $id; ?>, <?php echo $user_review ? 'true' : 'false'; ?>)">
                    <?php echo $user_review ? 'Atualizar Avaliação' : 'Enviar Avaliação'; ?>
                </button>
            </div>
            <div id="reviews-list">
                <?php
                $stmt = $conn->prepare("SELECT r.rating, r.comment, u.username, r.client_id 
                                        FROM reviews r 
                                        JOIN users u ON r.client_id = u.id 
                                        WHERE r.escort_id = ? AND r.is_approved = 1");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $reviews = $stmt->get_result();
                while ($review = $reviews->fetch_assoc()):
                ?>
                    <div class="review" id="review-<?php echo $review['client_id']; ?>">
                        <p><strong><?php echo htmlspecialchars($review['username']); ?>:</strong> <?php echo $review['rating']; ?>/5</p>
                        <p><?php echo htmlspecialchars($review['comment']); ?></p>
                        <?php if ($review['client_id'] == $user_id): ?>
                            <button onclick="editReview(<?php echo $id; ?>)">Editar</button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
    </main>

    <div id="lightbox" class="lightbox">
        <span class="close-lightbox" onclick="closeLightbox()">×</span>
        <img id="lightbox-img" src="" alt="Imagem ampliada">
    </div>

    <div id="confirm-delete" class="confirm-popup">
        <div class="confirm-content">
            <h3>Confirmar Exclusão</h3>
            <p>Tem certeza que deseja excluir o perfil de <?php echo htmlspecialchars($escort['name']); ?>? Esta ação não pode ser desfeita.</p>
            <div class="confirm-buttons">
                <button onclick="confirmDelete(<?php echo $id; ?>)">Sim, Excluir</button>
                <button onclick="closeConfirm()">Cancelar</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>