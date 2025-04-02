function sendMessage(event, id) {
    event.preventDefault();
    const content = document.getElementById('contact-message').value;
    if (content) {
        fetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `escort_id=${id}&content=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            const responseDiv = document.getElementById('contact-response');
            responseDiv.innerHTML = `<p class="${data.status === 'success' ? 'success' : 'error'}">${data.message}</p>`;
            if (data.status === 'success') {
                document.getElementById('contact-message').value = '';
            }
        });
    }
}

// ... (outras funções como deleteEscort, confirmDelete, closeConfirm, etc.) ...
function validateAndSendMessage(event, id) {
    event.preventDefault();
    const message = document.getElementById('contact-message').value.trim();
    if (message.length < 5) {
        document.getElementById('contact-response').innerHTML = '<p style="color: red;">A mensagem deve ter pelo menos 5 caracteres.</p>';
        return false;
    }
    sendMessage(event, id);
    return false;
}

// Funções existentes mantidas
function sendMessage(event, id) {
    event.preventDefault();
    const content = document.getElementById('contact-message').value;
    if (content) {
        fetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `escort_id=${id}&content=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            const responseDiv = document.getElementById('contact-response');
            if (data.status === 'success') {
                responseDiv.innerHTML = '<p style="color: green;">' + data.message + '</p>';
                document.getElementById('contact-message').value = '';
            } else {
                responseDiv.innerHTML = '<p style="color: red;">' + data.message + '</p>';
            }
        });
    }
}

// ... (outras funções como deleteEscort, confirmDelete, closeConfirm, etc.) ...
function deleteEscort(id) {
    document.getElementById('confirm-delete').classList.add('active');
}

function confirmDelete(id) {
    fetch('delete_escort.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'index.php';
        } else {
            alert(data.message);
        }
        closeConfirm();
    });
}

function closeConfirm() {
    document.getElementById('confirm-delete').classList.remove('active');
}

// ... (outras funções mantidas: setRating, submitReview, editReview, etc.) ...

// ... (outras funções mantidas: setRating, submitReview, editReview, etc.) ...
function approveReview(reviewId) {
    fetch('approve_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `review_id=${reviewId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const reviewDiv = document.querySelector(`.review-pending button[onclick='approveReview(${reviewId})']`).parentElement;
            reviewDiv.remove();
        } else {
            alert(data.message);
        }
    });
}

// ... (outras funções mantidas: setRating, submitReview, editReview, etc.) ...

// ... (outras funções mantidas) ...
function setRating(value) {
    const stars = document.querySelectorAll('.star');
    stars.forEach(star => {
        star.classList.remove('active');
        if (parseInt(star.getAttribute('data-value')) <= value) {
            star.classList.add('active');
        }
    });
    document.getElementById('rating-value').value = value;
}

document.querySelectorAll('.star').forEach(star => {
    star.addEventListener('click', () => setRating(parseInt(star.getAttribute('data-value'))));
});

function submitReview(escortId, reviewId = null) {
    const rating = document.getElementById('rating-value').value;
    const comment = document.getElementById('review-comment').value;
    if (rating > 0 && comment) {
        const url = reviewId ? 'update_review.php' : 'add_review.php';
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `escort_id=${escortId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const reviewsList = document.getElementById('reviews-list');
                if (reviewId) {
                    const reviewDiv = reviewsList.querySelector(`.review[data-id="${data.review_id}"]`);
                    reviewDiv.innerHTML = `
                        <p><strong>${data.review.username}:</strong> ${data.review.rating}/5</p>
                        <p>${data.review.comment}</p>
                    `;
                } else {
                    const review = document.createElement('div');
                    review.className = 'review';
                    review.dataset.id = data.review_id;
                    review.innerHTML = `
                        <p><strong>${data.review.username}:</strong> ${data.review.rating}/5</p>
                        <p>${data.review.comment}</p>
                    `;
                    reviewsList.insertBefore(review, reviewsList.firstChild);
                }
                document.getElementById('review-comment').value = '';
                setRating(0);
                document.querySelector('.review-form h3').textContent = 'Editar sua Avaliação';
                document.querySelector('.review-form button').textContent = 'Atualizar Avaliação';
            } else {
                alert(data.message);
            }
        });
    } else {
        alert('Por favor, selecione uma nota e escreva um comentário.');
    }
}

// ... REVIEW ...
function setRating(value) {
    const stars = document.querySelectorAll('.star');
    stars.forEach(star => {
        star.classList.remove('active');
        if (parseInt(star.getAttribute('data-value')) <= value) {
            star.classList.add('active');
        }
    });
    document.getElementById('rating-value').value = value;
}

document.querySelectorAll('.star').forEach(star => {
    star.addEventListener('click', () => setRating(parseInt(star.getAttribute('data-value'))));
});

function submitReview(escortId) {
    const rating = document.getElementById('rating-value').value;
    const comment = document.getElementById('review-comment').value;
    if (rating > 0 && comment) {
        fetch('add_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `escort_id=${escortId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const reviewsList = document.getElementById('reviews-list');
                const review = document.createElement('div');
                review.className = 'review';
                review.innerHTML = `
                    <p><strong>${data.review.username}:</strong> ${data.review.rating}/5</p>
                    <p>${data.review.comment}</p>
                `;
                reviewsList.insertBefore(review, reviewsList.firstChild);
                document.getElementById('review-comment').value = '';
                setRating(0);
            } else {
                alert(data.message);
            }
        });
    } else {
        alert('Por favor, selecione uma nota e escreva um comentário.');
    }
}

// ... (outras funções mantidas) ...
function startCarousel() {
    const items = document.querySelectorAll('.carousel-item');
    let currentIndex = 0;

    function showNext() {
        items[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % items.length;
        items[currentIndex].classList.add('active');
    }

    items[currentIndex].classList.add('active');
    setInterval(showNext, 3000); // Troca a cada 3 segundos
}

document.addEventListener('DOMContentLoaded', () => {
    startCarousel();
    // ... (outras funções mantidas) ...
});
function openLightbox(src) {
    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    img.src = src;
    lightbox.classList.add('active');
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    lightbox.classList.remove('active');
}

// ... (outras funções mantidas: filterProfiles, submitPost, likePost, showComment, sendMessage) ...

// ... (funções existentes: filterProfiles, submitPost, likePost, showComment, sendMessage) ...
function submitPost() {
    const content = document.getElementById('post-content').value;
    if (content) {
        fetch('add_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `content=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const feed = document.getElementById('feed');
                const post = document.createElement('div');
                post.className = 'post';
                post.id = `post-${data.post.id}`;
                post.innerHTML = `
                    <div class="another-post-header">
                        <img src="${data.post.profile_photo || 'uploads/default.jpg'}" alt="Foto">
                        <div>
                            <h4>${data.post.username}</h4>
                            <small>${data.post.timestamp}</small>
                        </div>
                    </div>
                    <p>${data.post.content}</p>
                    <div class="post-actions">
                        <button onclick="likePost(${data.post.id})" data-likes="0">Curtir (0)</button>
                        <button onclick="showComment(${data.post.id})">Comentar</button>
                    </div>
                    <div class="comments" id="comments-${data.post.id}"></div>
                `;
                feed.insertBefore(post, feed.firstChild);
                document.getElementById('post-content').value = '';
            } else {
                alert(data.message);
            }
        });
    }
}
function likePost(postId) {
    if (typeof postId !== 'number') {
        postId = parseInt(postId.parentElement.parentElement.id.split('-')[1]);
    }
    fetch('like_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const button = document.querySelector(`#post-${postId} .post-actions button[data-likes]`);
            button.textContent = `Curtir (${data.likes})`;
            button.disabled = true;
        } else {
            alert(data.message);
        }
    });
}

function showComment(postId) {
    if (typeof postId !== 'number') {
        postId = parseInt(postId.parentElement.parentElement.id.split('-')[1]);
    }
    const content = prompt('Digite seu comentário:');
    if (content) {
        fetch('comment_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `post_id=${postId}&content=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const commentsDiv = document.getElementById(`comments-${postId}`);
                const p = document.createElement('p');
                p.innerHTML = `<strong>${data.comment.username}:</strong> ${data.comment.content}`;
                commentsDiv.appendChild(p);
            } else {
                alert(data.message);
            }
        });
    }
}

function sendMessage(event, escortId) {
    event.preventDefault();
    const content = document.getElementById('contact-message').value;
    if (content) {
        fetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `escort_id=${escortId}&content=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            const responseDiv = document.getElementById('contact-response');
            if (data.status === 'success') {
                responseDiv.innerHTML = '<p style="color: green;">' + data.message + '</p>';
                document.getElementById('contact-message').value = '';
            } else {
                responseDiv.innerHTML = '<p style="color: red;">' + data.message + '</p>';
            }
        });
    }
}


document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.profile-details button')?.addEventListener('click', () => {
        alert('Reserva solicitada! Entre em contato para confirmar.');
    });

});
