<?php
require_once 'config.php';

$conn = getDBConnection();

$items_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = [];
$params = [];
$types = '';
if (!empty($search)) {
    $where[] = "(e.name LIKE ? OR e.description LIKE ? OR e.services LIKE ? OR e.tags LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ssss';
}
$base_where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$total_query = "SELECT COUNT(*) as total 
                FROM escorts e 
                $base_where_clause";
$stmt_total = $conn->prepare($total_query);
if ($types) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_profiles = $stmt_total->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_profiles / $items_per_page);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskort - Banco de Dados de Pornstars e Acompanhantes</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .suggestions {
            position: absolute;
            background: #fff;
            border: 1px solid #B4D1EC;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            width: 250px;
            z-index: 1000;
            display: none;
        }
        .suggestions div {
            padding: 8px;
            cursor: pointer;
        }
        .suggestions div:hover {
            background: #F6ECB2;
        }
        #loading {
            text-align: center;
            padding: 20px;
            display: none;
            color: #E95B95;
        }
        .top-bar {
            justify-content: center;
        }
        .top-center {
            flex-grow: 1;
            max-width: 600px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="top-center">
            <h2 style="color: #E95B95; margin: 0;">Eskort</h2>
            <div style="position: relative; flex-grow: 1;">
                <input type="text" id="search-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Busque pornstars e acompanhantes..." onkeyup="suggest(this.value)">
                <div id="suggestions" class="suggestions"></div>
            </div>
            <button onclick="filterProfiles()" class="search-btn">🔍</button>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="profiles-feed">
                <div class="profiles-container">
                    <h3>Resultados (<?php echo $total_profiles; ?> encontrados)</h3>
                    <div class="feed-grid" id="profiles-grid">
                        <?php
                        $query = "SELECT e.id, e.name, e.profile_photo, e.description, e.type, e.is_online, e.views, 
                                         (SELECT GROUP_CONCAT(photo_path) FROM photos p WHERE p.escort_id = e.id LIMIT 2) as additional_photos 
                                  FROM escorts e 
                                  $base_where_clause 
                                  GROUP BY e.id 
                                  ORDER BY e.views DESC, e.id DESC 
                                  LIMIT ? OFFSET ?";
                        $stmt = $conn->prepare($query);
                        if ($types) {
                            $stmt->bind_param($types . 'ii', ...array_merge($params, [$items_per_page, $offset]));
                        } else {
                            $stmt->bind_param('ii', $items_per_page, $offset);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $photo = $row['profile_photo'] ?: 'uploads/default.jpg';
                                $photo_webp = str_replace('.jpg', '.webp', $photo);
                                $additional_photos = $row['additional_photos'] ? explode(',', $row['additional_photos']) : [];
                                echo "<div class='feed-card' data-id='{$row['id']}' onmouseover='showPreview(event, this, \"".htmlspecialchars($row['description'])."\")' onmouseout='hidePreview()'>";
                                echo "<a href='profile.php?id=" . $row['id'] . "'>";
                                echo "<div class='photo-container'>";
                                echo "<picture>";
                                echo "<source srcset='" . htmlspecialchars($photo_webp) . "' type='image/webp'>";
                                echo "<img data-src='" . htmlspecialchars($photo) . "' alt='" . htmlspecialchars($row['name']) . "' class='lazy-load'>";
                                echo "</picture>";
                                foreach ($additional_photos as $add_photo) {
                                    $add_photo_webp = str_replace('.jpg', '.webp', $add_photo);
                                    echo "<picture>";
                                    echo "<source srcset='" . htmlspecialchars($add_photo_webp) . "' type='image/webp'>";
                                    echo "<img data-src='" . htmlspecialchars($add_photo) . "' alt='Foto adicional' class='lazy-load small'>";
                                    echo "</picture>";
                                }
                                echo "</div>";
                                echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                                echo "</a>";
                                echo "<p>" . ($row['type'] === 'acompanhante' ? 'Acompanhante' : 'Pornstar') . " • " . $row['views'] . " views</p>";
                                echo "<span class='online-status " . ($row['is_online'] ? 'online' : 'offline') . "'>" . ($row['is_online'] ? 'Online' : 'Offline') . "</span>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p>Nenhum perfil encontrado.</p>";
                        }
                        ?>
                    </div>
                    <div id="loading">Carregando mais...</div>
                </div>
            </div>
        </div>
    </div>

    <div id="profile-preview" class="profile-preview"></div>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script>
        let page = <?php echo $page; ?>;
        let loading = false;
        let totalPages = <?php echo $total_pages; ?>;

        function filterProfiles() {
            const search = document.getElementById('search-input').value;
            window.location.href = `?search=${encodeURIComponent(search)}`;
        }

        function suggest(query) {
            if (query.length < 2) {
                document.getElementById('suggestions').style.display = 'none';
                return;
            }
            fetch(`suggest.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById('suggestions');
                    suggestions.innerHTML = '';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.textContent = item;
                        div.onclick = () => {
                            document.getElementById('search-input').value = item;
                            suggestions.style.display = 'none';
                            filterProfiles();
                        };
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = data.length ? 'block' : 'none';
                });
        }

        function loadMore() {
            if (loading || page >= totalPages) return;
            loading = true;
            document.getElementById('loading').style.display = 'block';

            const search = document.getElementById('search-input').value;
            const url = `load_more.php?page=${page + 1}&search=${encodeURIComponent(search)}`;

            const cached = localStorage.getItem(url);
            if (cached) {
                appendProfiles(JSON.parse(cached));
                page++;
                loading = false;
                document.getElementById('loading').style.display = 'none';
                return;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    appendProfiles(data);
                    localStorage.setItem(url, JSON.stringify(data));
                    page++;
                    loading = false;
                    document.getElementById('loading').style.display = 'none';
                });
        }

        function appendProfiles(profiles) {
            const grid = document.getElementById('profiles-grid');
            profiles.forEach(profile => {
                const card = document.createElement('div');
                card.className = 'feed-card';
                card.dataset.id = profile.id;
                card.onmouseover = () => showPreview(event, card, profile.description);
                card.onmouseout = hidePreview;
                const photo = profile.profile_photo || 'uploads/default.jpg';
                const photoWebp = photo.replace('.jpg', '.webp');
                const additionalPhotos = profile.additional_photos ? profile.additional_photos.split(',') : [];
                card.innerHTML = `
                    <a href="profile.php?id=${profile.id}">
                        <div class="photo-container">
                            <picture>
                                <source srcset="${photoWebp}" type="image/webp">
                                <img data-src="${photo}" alt="${profile.name}" class="lazy-load">
                            </picture>
                            ${additionalPhotos.map(photo => `
                                <picture>
                                    <source srcset="${photo.replace('.jpg', '.webp')}" type="image/webp">
                                    <img data-src="${photo}" alt="Foto adicional" class="lazy-load small">
                                </picture>
                            `).join('')}
                        </div>
                        <h4>${profile.name}</h4>
                    </a>
                    <p>${profile.type === 'acompanhante' ? 'Acompanhante' : 'Pornstar'} • ${profile.views} views</p>
                    <span class="online-status ${profile.is_online ? 'online' : 'offline'}">${profile.is_online ? 'Online' : 'Offline'}</span>
                `;
                grid.appendChild(card);
            });
            lazyLoadImages();
        }

        function showPreview(event, card, description) {
            const preview = document.getElementById('profile-preview');
            const rect = card.getBoundingClientRect();
            preview.style.left = `${rect.left + window.scrollX + rect.width / 2}px`;
            preview.style.top = `${rect.top + window.scrollY - 10}px`;
            preview.innerHTML = `
                <div class="preview-content">
                    <h4>${card.querySelector('h4').textContent}</h4>
                    <p>${description || 'Sem descrição'}</p>
                </div>
            `;
            preview.style.display = 'block';
        }

        function hidePreview() {
            document.getElementById('profile-preview').style.display = 'none';
        }

        function lazyLoadImages() {
            const images = document.querySelectorAll('.lazy-load');
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-load');
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '0px 0px 100px 0px' });
            images.forEach(img => observer.observe(img));
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const backToTop = document.querySelector('.back-to-top');
            window.addEventListener('scroll', () => {
                backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
                if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 200) {
                    loadMore();
                }
            });
            lazyLoadImages();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>