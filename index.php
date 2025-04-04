<?php
require_once 'config.php';

$conn = getDBConnection();

$items_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$availability = isset($_GET['availability']) ? trim($_GET['availability']) : '';
$language = isset($_GET['language']) ? trim($_GET['language']) : '';
$rate_min = isset($_GET['rate_min']) ? (float)$_GET['rate_min'] : 0;
$rate_max = isset($_GET['rate_max']) ? (float)$_GET['rate_max'] : 0;
$physical_traits = isset($_GET['physical_traits']) ? trim($_GET['physical_traits']) : '';
$order = in_array($_GET['order'] ?? '', ['views', 'name', 'distance']) ? $_GET['order'] : 'views';
$age_min = isset($_GET['age_min']) ? (int)$_GET['age_min'] : 0;
$views_min = isset($_GET['views_min']) ? (int)$_GET['views_min'] : 0;
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : 0;
$radius = isset($_GET['radius']) ? (float)$_GET['radius'] : 50;

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
if (!empty($keyword)) {
    $where[] = "k.keyword = ?";
    $params[] = $keyword;
    $types .= 's';
}
if ($category > 0) {
    $where[] = "ec.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}
if (!empty($availability)) {
    $where[] = "e.availability LIKE ?";
    $params[] = '%' . $availability . '%';
    $types .= 's';
}
if (!empty($language)) {
    $where[] = "e.languages LIKE ?";
    $params[] = '%' . $language . '%';
    $types .= 's';
}
if ($rate_min > 0) {
    $where[] = "CAST(REGEXP_REPLACE(e.rates, '[^0-9.]', '') AS DECIMAL) >= ?";
    $params[] = $rate_min;
    $types .= 'd';
}
if ($rate_max > 0) {
    $where[] = "CAST(REGEXP_REPLACE(e.rates, '[^0-9.]', '') AS DECIMAL) <= ?";
    $params[] = $rate_max;
    $types .= 'd';
}
if (!empty($physical_traits)) {
    $where[] = "e.physical_traits LIKE ?";
    $params[] = '%' . $physical_traits . '%';
    $types .= 's';
}
if ($age_min > 0) {
    $where[] = "e.age >= ?";
    $params[] = $age_min;
    $types .= 'i';
}
if ($views_min > 0) {
    $where[] = "e.views >= ?";
    $params[] = $views_min;
    $types .= 'i';
}
if ($lat && $lon && $radius) {
    $where[] = "(6371 * acos(cos(radians(?)) * cos(radians(e.latitude)) * cos(radians(e.longitude) - radians(?)) + sin(radians(?)) * sin(radians(e.latitude)))) <= ?";
    $params[] = $lat;
    $params[] = $lon;
    $params[] = $lat;
    $params[] = $radius;
    $types .= 'dddd';
}
$base_where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$total_query = "SELECT COUNT(DISTINCT e.id) as total 
                FROM escorts e 
                LEFT JOIN escort_categories ec ON e.id = ec.escort_id 
                LEFT JOIN keywords k ON e.id = k.escort_id 
                $base_where_clause";
$stmt_total = $conn->prepare($total_query);
if ($types) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_profiles = $stmt_total->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_profiles / $items_per_page);

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskort - Banco de Dados de Pornstars e Acompanhantes</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="main-content">
            <div class="profiles-feed">
                <div class="profiles-container">
                    <div class="highlights-section">
                        <h3>Destaques</h3>
                        <div class="carousel">
                            <button class="carousel-prev" onclick="carouselPrev()">◄</button>
                            <div class="carousel-inner">
                                <?php
                                // Query ajustada para não usar is_public até que o banco seja atualizado
                                $highlight_query = "SELECT e.id, e.name, e.profile_photo 
                                                    FROM escorts e 
                                                    JOIN favorites f ON e.id = f.escort_id 
                                                    GROUP BY e.id 
                                                    ORDER BY COUNT(f.id) DESC 
                                                    LIMIT 3";
                                $highlight_result = $conn->query($highlight_query);
                                while ($highlight = $highlight_result->fetch_assoc()) {
                                    $photo = $highlight['profile_photo'] ?: 'uploads/default.jpg';
                                    $photo_webp = str_replace('.jpg', '.webp', $photo);
                                    echo "<div class='carousel-item'>";
                                    echo "<a href='public_profile.php?id=" . $highlight['id'] . "'>";
                                    echo "<picture>";
                                    echo "<source srcset='" . htmlspecialchars($photo_webp) . "' type='image/webp'>";
                                    echo "<img data-src='" . htmlspecialchars($photo) . "' alt='" . htmlspecialchars($highlight['name']) . "' class='lazy-load'>";
                                    echo "</picture>";
                                    echo "<p>" . htmlspecialchars($highlight['name']) . " (Favoritado)</p>";
                                    echo "</a>";
                                    echo "</div>";
                                }
                                ?>
                            </div>
                            <button class="carousel-next" onclick="carouselNext()">►</button>
                        </div>
                    </div>
                    <h3>Resultados (<?php echo $total_profiles; ?> encontrados)</h3>
                    <div class="feed-grid" id="profiles-grid">
                        <?php
                        $order_clause = $order === 'distance' && $lat && $lon 
                            ? "ORDER BY (6371 * acos(cos(radians(?)) * cos(radians(e.latitude)) * cos(radians(e.longitude) - radians(?)) + sin(radians(?)) * sin(radians(e.latitude)))) ASC"
                            : "ORDER BY e.$order DESC, e.id DESC";
                        if ($order === 'distance' && $lat && $lon) {
                            $params = array_merge([$lat, $lon, $lat], $params);
                            $types = 'ddd' . $types;
                        }

                        $query = "SELECT e.id, e.name, e.profile_photo, e.description, e.type, e.is_online, e.views, e.tags, e.latitude, e.longitude, 
                                         (SELECT GROUP_CONCAT(photo_path) FROM photos p WHERE p.escort_id = e.id LIMIT 2) as additional_photos,
                                         (SELECT COUNT(*) FROM favorites f WHERE f.escort_id = e.id) as favorite_count 
                                  FROM escorts e 
                                  LEFT JOIN escort_categories ec ON e.id = ec.escort_id 
                                  LEFT JOIN keywords k ON e.id = k.escort_id 
                                  $base_where_clause 
                                  GROUP BY e.id 
                                  $order_clause 
                                  LIMIT ? OFFSET ?";
                        $stmt = $conn->prepare($query);
                        if ($types) {
                            $stmt->bind_param($types . 'ii', ...array_merge($params, [$items_per_page, $offset]));
                        } else {
                            $stmt->bind_param('ii', $items_per_page, $offset);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $tags_searched = !empty($search) ? array_map('trim', explode(',', strtolower($search))) : [];
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $photo = $row['profile_photo'] ?: 'uploads/default.jpg';
                                $photo_webp = str_replace('.jpg', '.webp', $photo);
                                $additional_photos = $row['additional_photos'] ? explode(',', $row['additional_photos']) : [];
                                $distance = $lat && $lon ? round(6371 * acos(cos(deg2rad($lat)) * cos(deg2rad($row['latitude'])) * cos(deg2rad($row['longitude']) - deg2rad($lon)) + sin(deg2rad($lat)) * sin(deg2rad($row['latitude']))), 2) : null;
                                echo "<div class='feed-card' data-id='{$row['id']}' onmouseover='showPreview(event, this, \"".htmlspecialchars($row['description'])."\", \"".htmlspecialchars($row['tags'])."\", \"$distance\")' onmouseout='hidePreview()'>";
                                echo "<a href='public_profile.php?id=" . $row['id'] . "'>";
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
                                echo "<p>" . ($row['type'] === 'acompanhante' ? 'Acompanhante' : 'Pornstar') . " • " . $row['views'] . " views" . ($distance ? " • $distance km" : "") . ($row['favorite_count'] > 0 ? " • {$row['favorite_count']} favoritos" : "") . "</p>";
                                echo "<span class='online-status " . ($row['is_online'] ? 'online' : 'offline') . "'>" . ($row['is_online'] ? 'Online' : 'Offline') . "</span>";
                                echo "</div>";

                                if (!empty($tags_searched) && !empty($row['tags'])) {
                                    $profile_tags = array_map('trim', explode(',', strtolower($row['tags'])));
                                    $similarity = count(array_intersect($tags_searched, $profile_tags));
                                    if ($similarity > 0) {
                                        $similar[$row['id']] = $similarity;
                                    }
                                }
                            }
                        } else {
                            echo "<p>Nenhum perfil encontrado.</p>";
                        }

                        if (!empty($similar)) {
                            arsort($similar);
                            echo "<div class='similar-profiles'><h3>Perfis Similares</h3><div class='feed-grid'>";
                            $similar_ids = array_keys(array_slice($similar, 0, 3, true));
                            $similar_query = "SELECT e.id, e.name, e.profile_photo, e.type, e.is_online, e.views 
                                              FROM escorts e 
                                              WHERE e.id IN (" . implode(',', $similar_ids) . ") 
                                              ORDER BY FIELD(e.id, " . implode(',', $similar_ids) . ")";
                            $similar_result = $conn->query($similar_query);
                            while ($similar_row = $similar_result->fetch_assoc()) {
                                $photo = $similar_row['profile_photo'] ?: 'uploads/default.jpg';
                                $photo_webp = str_replace('.jpg', '.webp', $photo);
                                echo "<div class='feed-card' data-id='{$similar_row['id']}'>";
                                echo "<a href='public_profile.php?id=" . $similar_row['id'] . "'>";
                                echo "<div class='photo-container'>";
                                echo "<picture>";
                                echo "<source srcset='" . htmlspecialchars($photo_webp) . "' type='image/webp'>";
                                echo "<img data-src='" . htmlspecialchars($photo) . "' alt='" . htmlspecialchars($similar_row['name']) . "' class='lazy-load'>";
                                echo "</picture>";
                                echo "</div>";
                                echo "<h4>" . htmlspecialchars($similar_row['name']) . "</h4>";
                                echo "</a>";
                                echo "<p>" . ($similar_row['type'] === 'acompanhante' ? 'Acompanhante' : 'Pornstar') . " • " . $similar_row['views'] . " views</p>";
                                echo "<span class='online-status " . ($similar_row['is_online'] ? 'online' : 'offline') . "'>" . ($similar_row['is_online'] ? 'Online' : 'Offline') . "</span>";
                                echo "</div>";
                            }
                            echo "</div></div>";
                        }
                        ?>
                    </div>
                    <div id="loading">Carregando mais...</div>
                </div>
            </div>
        </div>
    </div>

    <div id="profile-preview" class="profile-preview"></div>
    <div id="notification" style="display: none;"></div>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script>
        let page = <?php echo $page; ?>;
        let loading = false;
        let totalPages = <?php echo $total_pages; ?>;
        let lat = <?php echo $lat; ?>;
        let lon = <?php echo $lon; ?>;
        const ws = new WebSocket('ws://localhost:8080');

        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            if (data.type === 'new_profile') {
                const notification = document.getElementById('notification');
                notification.textContent = 'Novo perfil adicionado: ' + data.name;
                notification.style.display = 'block';
                setTimeout(() => notification.style.display = 'none', 3000);
            }
        };

        function filterProfiles() {
            const search = document.getElementById('search-input').value;
            const keyword = document.getElementById('keyword-input').value;
            const category = document.getElementById('category-filter').value;
            const availability = document.getElementById('availability-filter').value;
            const language = document.getElementById('language-filter').value;
            const rateMin = document.getElementById('rate-min-filter').value;
            const rateMax = document.getElementById('rate-max-filter').value;
            const physicalTraits = document.getElementById('physical-traits-filter').value;
            const order = document.getElementById('order-filter').value;
            const ageMin = document.getElementById('age-min-filter').value;
            const viewsMin = document.getElementById('views-min-filter').value;
            const radius = document.getElementById('radius-filter').value;
            window.location.href = `?search=${encodeURIComponent(search)}&keyword=${encodeURIComponent(keyword)}&category=${category}&availability=${encodeURIComponent(availability)}&language=${encodeURIComponent(language)}&rate_min=${rateMin}&rate_max=${rateMax}&physical_traits=${encodeURIComponent(physicalTraits)}&order=${order}&age_min=${ageMin}&views_min=${viewsMin}&lat=${lat}&lon=${lon}&radius=${radius}`;
        }

        function suggest(query, type) {
            if (query.length < 2) {
                document.getElementById(type + '-suggestions').style.display = 'none';
                return;
            }
            fetch(`suggest.php?q=${encodeURIComponent(query)}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById(type + '-suggestions');
                    suggestions.innerHTML = '';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.textContent = item;
                        div.onclick = () => {
                            document.getElementById(type + '-input').value = item;
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
            const keyword = document.getElementById('keyword-input').value;
            const category = document.getElementById('category-filter').value;
            const availability = document.getElementById('availability-filter').value;
            const language = document.getElementById('language-filter').value;
            const rateMin = document.getElementById('rate-min-filter').value;
            const rateMax = document.getElementById('rate-max-filter').value;
            const physicalTraits = document.getElementById('physical-traits-filter').value;
            const order = document.getElementById('order-filter').value;
            const ageMin = document.getElementById('age-min-filter').value;
            const viewsMin = document.getElementById('views-min-filter').value;
            const radius = document.getElementById('radius-filter').value;
            const url = `load_more.php?page=${page + 1}&search=${encodeURIComponent(search)}&keyword=${encodeURIComponent(keyword)}&category=${category}&availability=${encodeURIComponent(availability)}&language=${encodeURIComponent(language)}&rate_min=${rateMin}&rate_max=${rateMax}&physical_traits=${encodeURIComponent(physicalTraits)}&order=${order}&age_min=${ageMin}&views_min=${viewsMin}&lat=${lat}&lon=${lon}&radius=${radius}`;

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
                card.onmouseover = () => showPreview(event, card, profile.description, profile.tags, profile.distance);
                card.onmouseout = hidePreview;
                const photo = profile.profile_photo || 'uploads/default.jpg';
                const photoWebp = photo.replace('.jpg', '.webp');
                const additionalPhotos = profile.additional_photos ? profile.additional_photos.split(',') : [];
                card.innerHTML = `
                    <a href="public_profile.php?id=${profile.id}">
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
                    <p>${profile.type === 'acompanhante' ? 'Acompanhante' : 'Pornstar'} • ${profile.views} views${profile.distance ? ' • ' + profile.distance + ' km' : ''}${profile.favorite_count > 0 ? ' • ' + profile.favorite_count + ' favoritos' : ''}</p>
                    <span class="online-status ${profile.is_online ? 'online' : 'offline'}">${profile.is_online ? 'Online' : 'Offline'}</span>
                `;
                grid.appendChild(card);
            });
            lazyLoadImages();
        }

        function showPreview(event, card, description, tags, distance) {
            const preview = document.getElementById('profile-preview');
            const rect = card.getBoundingClientRect();
            preview.style.left = `${rect.left + window.scrollX + rect.width / 2}px`;
            preview.style.top = `${rect.top + window.scrollY - 10}px`;
            preview.innerHTML = `
                <div class="preview-content">
                    <h4>${card.querySelector('h4').textContent}</h4>
                    <p>${description || 'Sem descrição'}</p>
                    <p><strong>Tags:</strong> ${tags || 'Sem tags'}</p>
                    ${distance ? '<p><strong>Distância:</strong> ' + distance + ' km</p>' : ''}
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

        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    lat = position.coords.latitude;
                    lon = position.coords.longitude;
                    document.getElementById('order-filter').value = 'distance';
                    filterProfiles();
                }, () => alert('Não foi possível obter sua localização.'));
            } else {
                alert('Geolocalização não suportada pelo navegador.');
            }
        }

        function carouselPrev() {
            const items = document.querySelectorAll('.carousel-item');
            let current = Array.from(items).findIndex(item => item.classList.contains('active'));
            items[current].classList.remove('active');
            current = (current - 1 + items.length) % items.length;
            items[current].classList.add('active');
        }

        function carouselNext() {
            const items = document.querySelectorAll('.carousel-item');
            let current = Array.from(items).findIndex(item => item.classList.contains('active'));
            items[current].classList.remove('active');
            current = (current + 1) % items.length;
            items[current].classList.add('active');
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
            document.querySelector('.carousel-item').classList.add('active');
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>