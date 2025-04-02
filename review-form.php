<?php
// review-form.php
$user_review = $user_review ?? null;
$id = $id ?? 0;
$user_id = $user_id ?? null;
?>
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