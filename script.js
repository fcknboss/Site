// script.js

// Funções de Utilidade
function $(selector) {
    return document.querySelector(selector);
}

function $$(selector) {
    return document.querySelectorAll(selector);
}

function handleFetch(url, options) {
    return fetch(url, options)
        .then(response => response.json())
        .catch(error => {
            console.error(`Erro na requisição a ${url}:`, error);
            return { status: 'error', message: 'Erro de conexão com o servidor' };
        });
}

function showLoader(targetId) {
    const loader = $(`#${targetId}-loader`) || document.createElement('div');
    if (!loader.id) {
        loader.id = `${targetId}-loader`;
        loader.className = 'loader';
        $(`#${targetId}`).prepend(loader);
    }
    loader.style.display = 'block';
}

function hideLoader(targetId) {
    const loader = $(`#${targetId}-loader`);
    if (loader) loader.style.display = 'none';
}

function showConfirmAction(title, message, callback) {
    $('#confirm-title').textContent = title;
    $('#confirm-message').textContent = message;
    $('#confirm-action').classList.add('active');
    $('#confirm-yes').onclick = () => {
        callback();
        closeConfirmAction();
    };
}

function closeConfirmAction() {
    $('#confirm-action').classList.remove('active');
}

// Perfil
function previewProfilePhoto(input) {
    if (!input.files || !input.files[0]) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const img = $('#profile-photo');
        img.src = e.target.result;

        showConfirmAction('Confirmar Foto', 'Deseja salvar esta nova foto de perfil?', () => {
            showLoader('profile-photo-container');
            const formData = new FormData();
            formData.append('profile_photo', input.files[0]);
            formData.append('id', window.profileId);

            handleFetch('update_profile_photo.php', { method: 'POST', body: formData })
                .then(data => {
                    hideLoader('profile-photo-container');
                    if (data.status !== 'success') {
                        alert(`Erro ao atualizar a foto: ${data.message}`);
                        img.src = img.dataset.originalSrc;
                    }
                });
        });
    };
    reader.readAsDataURL(input.files[0]);
}

// Contato
function sendMessage(event, id) {
    event.preventDefault();
    const content = $('#contact-message').value.trim();
    if (!content || content.length < 5) {
        $('#contact-response').innerHTML = '<p class="error">A mensagem deve ter pelo menos 5 caracteres.</p>';
        return;
    }

    showConfirmAction('Confirmar Mensagem', 'Tem certeza que deseja enviar esta mensagem?', () => {
        showLoader('contact-response');
        const body = `escort_id=${id}&content=${encodeURIComponent(content)}`;
        handleFetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        }).then(data => {
            hideLoader('contact-response');
            const responseDiv = $('#contact-response');
            responseDiv.innerHTML = `<p class="${data.status === 'success' ? 'success' : 'error'}">${data.message}</p>`;
            if (data.status === 'success') $('#contact-message').value = '';
        });
    });
}

// Exclusão
function deleteEscort(id) {
    $('#confirm-delete').classList.add('active');
    window.confirmDeleteId = id;
}

function confirmDelete() {
    const id = window.confirmDeleteId;
    showLoader('confirm-delete');
    handleFetch('delete_escort.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(data => {
        hideLoader('confirm-delete');
        if (data.status === 'success') {
            window.location.href = 'index.php';
        } else {
            alert(data.message);
        }
        closeConfirm();
    });
}

function closeConfirm() {
    $('#confirm-delete').classList.remove('active');
}

// Carrossel
function startCarousel() {
    const items = $$('.carousel-item');
    if (!items.length) return;
    let currentIndex = 0;

    function showNext() {
        items[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % items.length;
        items[currentIndex].classList.add('active');
    }

    items[currentIndex].classList.add('active');
    setInterval(showNext, 3000);
}

// Lightbox
function openLightbox(src) {
    $('#lightbox-img').src = src;
    $('#lightbox').classList.add('active');
}

function closeLightbox() {
    $('#lightbox').classList.remove('active');
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    startCarousel();
    $('.profile-info button')?.addEventListener('click', () => alert('Reserva solicitada! Entre em contato para confirmar.'));
});