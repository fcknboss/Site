<?php
include 'check_session.php';
include 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT e.*, u.username, u.email 
                        FROM escorts e 
                        JOIN users u ON e.user_id = u.id 
                        WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$escort = $stmt->get_result()->fetch_assoc();
if (!$escort) {
    die("Acompanhante não encontrada.");
}

$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE escort_id = ? AND is_approved = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$rating_data = $stmt->get_result()->fetch_assoc();
$avg_rating = $rating_data['avg_rating'] ? number_format($rating_data['avg_rating'], 1) : 'N/A';
$review_count = $rating_data['review_count'];

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
    <title><?php echo $escort['name']; ?> - Eskort</title>
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
            <a href="index.php">Home</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-photo-container">
                <img src="<?php echo $escort['profile_photo']; ?>" alt="<?php echo $escort['name']; ?>">
            </div>
            <div class="profile-info">
                <h1>
                    <?php echo $escort['name']; ?>
                    <span class="verified">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z" fill="#E95B95"/>
                        </svg>
                    </span>
                    <?php if (isset($escort['type']) && $escort['type'] === 'acompanhante'): ?>
                        <span class="acompanhante">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7C10.9 7 10 7.9 10 9H8C8 6.79 9.79 5 12 5C14.21 5 16 6.79 16 9C16 9.88 15.64 10.68 15.07 11.25Z" fill="#28A745"/>
                            </svg>
                        </span>
                    <?php elseif (isset($escort['type']) && $escort['type'] === 'criadora'): ?>
                        <span class="criadora">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#FFD700"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="location"><?php echo $escort['location']; ?> | <?php echo $escort['age']; ?> anos</p>
                <p class="rates"><?php echo $escort['rates']; ?></p>
                <p><strong>Avaliação Média:</strong> <?php echo $avg_rating; ?>/5 (<?php echo $review_count; ?> avaliações)</p>
                <div class="social-links">
                    <a href="https://instagram.com/<?php echo strtolower(str_replace(' ', '', $escort['username'])); ?>" target="_blank">Instagram</a>
                    <a href="https://twitter.com/<?php echo strtolower(str_replace(' ', '', $escort['username'])); ?>" target="_blank">Twitter</a>
                </div>
                <button>Reservar</button>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="profile-actions">
                        <a href="edit_escort.php?id=<?php echo $id; ?>" class="edit-btn">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z" fill="white"/>
                            </svg>
                        </a>
                        <button onclick="deleteEscort(<?php echo $id; ?>)" class="delete-btn">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM19 4H15.5L14.5 3H9.5L8.5 4H5V6H19V4Z" fill="white"/>
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-section">
            <h2>Galeria</h2>
            <div class="photo-gallery">
                <?php
                $stmt = $conn->prepare("SELECT photo_path FROM photos WHERE escort_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $photos = $stmt->get_result();
                while ($photo = $photos->fetch_assoc()) {
                    echo "<img src='" . $photo['photo_path'] . "' alt='Foto adicional' onclick='openLightbox(this.src)'>";
                }
                ?>
            </div>
        </div>

        <div class="profile-section">
            <h2>Sobre</h2>
            <p><?php echo $escort['description']; ?></p>
            <?php if ($escort['physical_traits']): ?>
                <div class="tags">
                    <?php
                    $traits = explode(',', $escort['physical_traits']);
                    foreach ($traits as $trait) {
                        echo "<span class='tag'>" . trim($trait) . "</span>";
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-section">
            <h2>Serviços</h2>
            <p><strong>O que ofereço:</strong> <?php echo $escort['services']; ?></p>
            <p><strong>Disponibilidade:</strong> <?php echo $escort['availability']; ?></p>
            <p><strong>Atendimento:</strong> Com Local, Hotéis e Motéis</p>
            <p><strong>Pagamento:</strong> Dinheiro, Cartão</p>
        </div>

        <div class="profile-section">
            <h2>Contato</h2>
            <form id="contact-form" onsubmit="sendMessage(event, <?php echo $id; ?>)">
                <textarea id="contact-message" placeholder="Envie uma mensagem..." required></textarea>
                <button type="submit">Enviar</button>
            </form>
            <div id="contact-response"></div>
        </div>

        <div class="profile-section">
            <h2>Avaliações</h2>
            <div class="review-form">
                <h3><?php echo $user_review ? 'Edite sua Avaliação' : 'Deixe sua Avaliação'; ?></h3>
                <div class="star-rating">
                    <span class="star" data-value="1">★</span>
                    <span class="star" data-value="2">★</span>
                    <span class="star" data-value="3">★</span>
                    <span class="star" data-value="4">★</span>
                    <span class="star" data-value="5">★</span>
                </div>
                <input type="hidden" id="rating-value" value="<?php echo $user_review ? $user_review['rating'] : 0; ?>">
                <textarea id="review-comment" placeholder="Escreva seu comentário..." required><?php echo $user_review ? $user_review['comment'] : ''; ?></textarea>
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
                while ($review = $reviews->fetch_assoc()) {
                    echo "<div class='review' id='review-" . $review['client_id'] . "'>";
                    echo "<p><strong>" . $review['username'] . ":</strong> " . $review['rating'] . "/5</p>";
                    echo "<p>" . $review['comment'] . "</p>";
                    if ($review['client_id'] == $user_id) {
                        echo "<button onclick='editReview(" . $id . ")'>Editar</button>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <div id="lightbox" class="lightbox">
        <span class="close-lightbox" onclick="closeLightbox()">×</span>
        <img id="lightbox-img" src="">
    </div>
    
<!-- Após o </div> do lightbox -->
<div id="confirm-delete" class="confirm-popup">
    <div class="confirm-content">
        <h3>Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir o perfil de <?php echo $escort['name']; ?>? Esta ação não pode ser desfeita.</p>
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