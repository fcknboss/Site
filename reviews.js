// reviews.js

function setRating(value) {
    $$('.star').forEach(star => {
        star.classList.toggle('active', parseInt(star.dataset.value) <= value);
    });
    $('#rating-value').value = value;
}

function submitReview(escortId, isEdit = false) {
    const rating = $('#rating-value').value;
    const comment = $('#review-comment').value.trim();
    if (!rating || rating <= 0 || !comment) {
        alert('Por favor, selecione uma nota e escreva um comentário.');
        return;
    }

    showConfirmAction('Confirmar Avaliação', 'Tem certeza que deseja enviar esta avaliação?', () => {
        const url = isEdit ? 'update_review.php' : 'add_review.php';
        const body = `escort_id=${escortId}&rating=${rating}&comment=${encodeURIComponent(comment)}`;
        showLoader('reviews-list');
        handleFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        }).then(data => {
            hideLoader('reviews-list');
            if (data.status === 'success') {
                const reviewsList = $('#reviews-list');
                const reviewDiv = isEdit ? $(`.review#review-${data.review.client_id}`) : document.createElement('div');
                if (!isEdit) reviewDiv.className = 'review';
                reviewDiv.id = `review-${data.review.client_id}`;
                reviewDiv.innerHTML = `
                    <p><strong>${data.review.username}:</strong> ${data.review.rating}/5</p>
                    <p>${data.review.comment}</p>
                    ${data.review.client_id == window.userId ? `<button onclick="editReview(${escortId})">Editar</button>` : ''}
                `;
                if (!isEdit) reviewsList.insertBefore(reviewDiv, reviewsList.firstChild);
                $('#review-comment').value = '';
                setRating(0);
                $('.review-form h3').textContent = isEdit ? 'Edite sua Avaliação' : 'Deixe sua Avaliação';
                $('.review-form button').setAttribute('onclick', `submitReview(${escortId}, ${isEdit})`);
            } else {
                alert(data.message);
            }
        });
    });
}

function editReview(escortId) {
    handleFetch(`get_review.php?escort_id=${escortId}&user_id=${window.userId}`)
        .then(data => {
            if (data.status === 'success') {
                setRating(data.review.rating);
                $('#review-comment').value = data.review.comment;
                $('.review-form h3').textContent = 'Edite sua Avaliação';
                $('.review-form button').setAttribute('onclick', `submitReview(${escortId}, true)`);
            } else {
                alert(data.message);
            }
        });
}

function approveReview(reviewId) {
    showConfirmAction('Aprovar Avaliação', 'Tem certeza que deseja aprovar esta avaliação?', () => {
        handleFetch('approve_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `review_id=${reviewId}`
        }).then(data => {
            if (data.status === 'success') {
                $(`.review-pending button[onclick="approveReview(${reviewId})"]`).parentElement.remove();
            } else {
                alert(data.message);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    $$('.star').forEach(star => star.addEventListener('click', () => setRating(parseInt(star.dataset.value))));
});