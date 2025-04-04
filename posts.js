// posts.js

function submitPost() {
    const content = $('#post-content').value.trim();
    if (!content) return;

    showConfirmAction('Confirmar Post', 'Tem certeza que deseja publicar este post?', () => {
        showLoader('feed');
        handleFetch('add_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `content=${encodeURIComponent(content)}`
        }).then(data => {
            hideLoader('feed');
            if (data.status === 'success') {
                const feed = $('#feed');
                const post = document.createElement('div');
                post.className = 'post';
                post.id = `post-${data.post.id}`;
                post.innerHTML = `
                    <div class="another-post-header">
                        <img src="${data.post.profile_photo || 'uploads/default.jpg'}" alt="Foto" loading="lazy">
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
                $('#post-content').value = '';
            } else {
                alert(data.message);
            }
        });
    });
}

function likePost(postId) {
    handleFetch('like_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}`
    }).then(data => {
        if (data.status === 'success') {
            const button = $(`#post-${postId} .post-actions button[data-likes]`);
            button.textContent = `Curtir (${data.likes})`;
            button.disabled = true;
        } else {
            alert(data.message);
        }
    });
}

function showComment(postId) {
    const content = prompt('Digite seu comentário:');
    if (!content) return;

    handleFetch('comment_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}&content=${encodeURIComponent(content)}`
    }).then(data => {
        if (data.status === 'success') {
            const commentsDiv = $(`#comments-${postId}`);
            const p = document.createElement('p');
            p.innerHTML = `<strong>${data.comment.username}:</strong> ${data.comment.content}`;
            commentsDiv.appendChild(p);
        } else {
            alert(data.message);
        }
    });
}