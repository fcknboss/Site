function submitPost() {
    const content = document.getElementById('post-content').value;
    if (content) {
        const feed = document.getElementById('feed');
        const post = document.createElement('div');
        post.className = 'post';
        const timestamp = new Date().toLocaleString();
        post.innerHTML = `
            <div class="post-header">
                <img src="uploads/default.jpg" alt="Foto">
                <div>
                    <h4>Você</h4>
                    <small>${timestamp}</small>
                </div>
            </div>
            <p>${content}</p>
            <div class="post-actions">
                <button onclick="likePost(this)" data-likes="0">Curtir (0)</button>
                <button onclick="showComment(this)">Comentar</button>
            </div>
            <div class="comments"></div>
        `;
        feed.insertBefore(post, feed.firstChild);
        document.getElementById('post-content').value = '';
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