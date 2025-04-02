document.addEventListener('DOMContentLoaded', () => {
    // Enviar scrap
    document.querySelector('.post-form button')?.addEventListener('click', () => {
        const texto = document.querySelector('.post-form textarea').value;
        if (texto) {
            // Aqui seria uma chamada AJAX para scraps.php (simplificado)
            const feed = document.getElementById('feed');
            const post = document.createElement('div');
            post.innerHTML = `<p>${texto}</p>`;
            feed.insertBefore(post, feed.firstChild);
            document.querySelector('.post-form textarea').value = '';
        }
    });

    // Botão de reserva (simulado)
    document.querySelector('.profile-details button')?.addEventListener('click', () => {
        alert('Reserva solicitada! Entre em contato para confirmar.');
    });
});