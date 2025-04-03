<?php 
include 'check_session.php';
require_once 'config.php';

// Conexão com o banco
$conn = getDBConnection();

// Configuração de paginação e ordenação
$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Filtros
$location = isset($_GET['location']) ? $_GET['location'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$trait = isset($_GET['trait']) ? $_GET['trait'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

$where = [];
$params = [];
$types = '';
if (!empty($location)) {
    $where[] = "location LIKE ?";
    $params[] = '%' . $location . '%';
    $types .= 's';
}
if (!empty($type)) {
    $where[] = "type = ?";
    $params[] = $type;
    $types .= 's';
}
if (!empty($trait)) {
    $where[] = "physical_traits LIKE ?";
    $params[] = '%' . $trait . '%';
    $types .= 's';
}
if ($category === 'new') {
    $where[] = "id > (SELECT MAX(id) - 10 FROM escorts)";
} elseif ($category === 'vip') {
    $where[] = "type = 'acompanhante'";
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';
$total_query = "SELECT COUNT(*) as total FROM escorts $where_clause";
$stmt_total = $conn->prepare($total_query);
if ($types) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_profiles = $stmt_total->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_profiles / $items_per_page);

// Frases promocionais
$promo_phrases = [
    'loira' => 'Loirinha deliciosa!',
    'morena' => 'Morena sensual e provocante!',
    'alta' => 'Elegante e imponente!',
    'curvilínea' => 'Corpo escultural!',
    'olhos verdes' => 'Olhar irresistível!',
    'olhos castanhos' => 'Doce e envolvente!',
    'acompanhante' => 'Companhia inesquecível!',
    'criadora' => 'Criadora de momentos únicos!'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskort - Rede de Acompanhantes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort</h2>
        </div>
        <div class="top-center">
            <input type="text" id="search-input" placeholder="Pesquisar acompanhantes...">
            <button onclick="filterProfiles()">Pesquisar</button>
        </div>
        <div class="top-right">
            <a href="profile.php">Perfil</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#">Perfis</a></li>
                <li><a href="#">Categorias</a></li>
                <li><a href="#">Blog</a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="categories-section">
                <h3>Categorias</h3>
                <div class="categories">
                    <a href="?category=all" class="<?php echo $category === 'all' ? 'active' : ''; ?>">Todos</a>
                    <a href="?category=new" class="<?php echo $category === 'new' ? 'active' : ''; ?>">Novidades</a>
                    <a href="?category=vip" class="<?php echo $category === 'vip' ? 'active' : ''; ?>">VIP</a>
                </div>
            </div>

            <div class="profiles-section">
                <h3>Acompanhantes</h3>
                <div class="filters">
                    <label>Localização:</label>
                    <input type="text" id="filter-location" placeholder="Digite a cidade..." value="<?php echo htmlspecialchars($location); ?>">
                    <label>Tipo:</label>
                    <select id="filter-type">
                        <option value="">Todos</option>
                        <option value="acompanhante" <?php echo $type === 'acompanhante' ? 'selected' : ''; ?>>Acompanhante</option>
                        <option value="criadora" <?php echo $type === 'criadora' ? 'selected' : ''; ?>>Criadora de Conteúdo</option>
                    </select>
                    <label>Característica:</label>
                    <select id="filter-trait">
                        <option value="">Todas</option>
                        <option value="loira" <?php echo $trait === 'loira' ? 'selected' : ''; ?>>Loira</option>
                        <option value="morena" <?php echo $trait === 'morena' ? 'selected' : ''; ?>>Morena</option>
                        <option value="alta" <?php echo $trait === 'alta' ? 'selected' : ''; ?>>Alta</option>
                        <option value="curvilínea" <?php echo $trait === 'curvilínea' ? 'selected' : ''; ?>>Curvilínea</option>
                    </select>
                    <label>Ordenar por:</label>
                    <select id="sort-profiles" onchange="filterProfiles()">
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nome</option>
                        <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>Data de Cadastro</option>
                    </select>
                    <button onclick="filterProfiles()">Filtrar</button>
                </div>
                <div class="profiles-grid" id="profiles-grid">
                    <?php
                    $query = "SELECT id, name, profile_photo, phone, physical_traits, type 
                              FROM escorts 
                              $where_clause 
                              ORDER BY $sort $order 
                              LIMIT ? OFFSET ?";
                    $stmt = $conn->prepare($query);
                    if ($types) {
                        $stmt->bind_param($types . 'ii', ...array_merge($params, [$items_per_page, $offset]));
                    } else {
                        $stmt->bind_param('ii', $items_per_page, $offset);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                        $phone_clean = preg_replace('/[^0-9]/', '', $row['phone'] ?? '');
                        $whatsapp_link = $phone_clean ? "https://wa.me/{$phone_clean}" : '#';
                        $promo = 'Companhia inesquecível!';
                        if ($row['physical_traits']) {
                            $traits = array_map('trim', explode(',', $row['physical_traits']));
                            foreach ($traits as $trait) {
                                if (isset($promo_phrases[$trait])) {
                                    $promo = $promo_phrases[$trait];
                                    break;
                                }
                            }
                        } elseif (isset($promo_phrases[$row['type']])) {
                            $promo = $promo_phrases;$row['type'];
                        }
                        echo "<a href='profile.php?id=" . $row['id'] . "' class='profile-card'>";
                        echo "<img data-src='$photo' alt='" . htmlspecialchars($row['name']) . "' class='lazy-load'>";
                        echo "<h4>" . htmlspecialchars($row['name']) . "</h4>";
                        echo "<p class='promo'>" . $promo . "</p>";
                        if ($phone_clean) {
                            echo "<a href='$whatsapp_link' target='_blank' class='whatsapp-btn'>WhatsApp</a>";
                        }
                        echo "</a>";
                    }
                    ?>
                </div>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&location=<?php echo urlencode($location); ?>&type=<?php echo urlencode($type); ?>&trait=<?php echo urlencode($trait); ?>&category=<?php echo urlencode($category); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="post-form">
                <textarea id="post-content" placeholder="No que você está pensando?"></textarea>
                <button onclick="submitPost()">Publicar</button>
            </div>

            <div class="feed" id="feed">
                <?php
                $result = $conn->query("SELECT p.id, p.content, p.timestamp, u.username, e.profile_photo 
                    FROM posts p 
                    JOIN users u ON p.user_id = u.id 
                    LEFT JOIN escorts e ON u.id = e.user_id 
                    ORDER BY p.timestamp DESC LIMIT 10");
                $latest_post_id = 0;
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                        $post_id = $row['id'];
                        if ($post_id > $latest_post_id) $latest_post_id = $post_id;
                        $likes = $conn->query("SELECT COUNT(*) as likes FROM likes WHERE post_id = $post_id")->fetch_assoc()['likes'];
                        $comments = $conn->query("SELECT c.content, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = $post_id ORDER BY c.timestamp");
                        echo "<div class='post' id='post-$post_id'>";
                        echo "<div class='another-post-header'>";
                        echo "<img data-src='$photo' alt='Foto' class='lazy-load'>";
                        echo "<div>";
                        echo "<h4>" . htmlspecialchars($row['username']) . "</h4>";
                        echo "<small>" . $row['timestamp'] . "</small>";
                        echo "</div>";
                        echo "</div>";
                        echo "<p>" . htmlspecialchars($row['content']) . "</p>";
                        echo "<div class='post-actions'>";
                        echo "<button onclick='likePost($post_id)' data-likes='$likes'>Curtir ($likes)</button>";
                        echo "<button onclick='showComment($post_id)'>Comentar</button>";
                        echo "</div>";
                        echo "<div class='comments' id='comments-$post_id'>";
                        while ($comment = $comments->fetch_assoc()) {
                            echo "<p><strong>" . htmlspecialchars($comment['username']) . ":</strong> " . htmlspecialchars($comment['content']) . "</p>";
                        }
                        echo "</div>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Erro ao carregar feed: " . $conn->error . "</p>";
                }
                ?>
                <div id="post-notification" class="notification" style="display: none;">Novo post adicionado!</div>
            </div>
        </div>
        <div class="right-sidebar">
            <h3>Filtros Rápidos</h3>
            <ul>
                <li><a href="?location=São Paulo">São Paulo</a></li>
                <li><a href="?location=Rio de Janeiro">Rio de Janeiro</a></li>
            </ul>
            <h3>Acompanhantes em Destaque</h3>
            <div class="carousel" id="carousel">
                <?php
                $result = $conn->query("SELECT e.id, e.name, e.profile_photo 
                                        FROM escorts e 
                                        LEFT JOIN reviews r ON e.id = r.escort_id 
                                        GROUP BY e.id, e.name, e.profile_photo 
                                        ORDER BY COUNT(r.id) DESC, AVG(r.rating) DESC 
                                        LIMIT 3");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $photo = $row['profile_photo'] ? $row['profile_photo'] : 'uploads/default.jpg';
                        echo "<div class='carousel-item'>";
                        echo "<a href='profile.php?id=" . $row['id'] . "'>";
                        echo "<img data-src='$photo' alt='" . htmlspecialchars($row['name']) . "' class='lazy-load'>";
                        echo "<p>" . htmlspecialchars($row['name']) . "</p>";
                        echo "</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Erro ao carregar destaques: " . $conn->error . "</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function filterProfiles() {
            const location = document.getElementById('filter-location').value;
            const type = document.getElementById('filter-type').value;
            const trait = document.getElementById('filter-trait').value;
            const sort = document.getElementById('sort-profiles').value;
            const order = '<?php echo $order === 'ASC' ? 'asc' : 'desc'; ?>';
            const category = '<?php echo urlencode($category); ?>';
            const url = `?location=${encodeURIComponent(location)}&type=${encodeURIComponent(type)}&trait=${encodeURIComponent(trait)}&sort=${sort}&order=${order}&category=${category}`;
            window.location.href = url;
        }

        function startCarousel() {
            const items = document.querySelectorAll('.carousel-item');
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

        function lazyLoadImages() {
            const images = document.querySelectorAll('.lazy-load');
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-load');
                        observer.unobserve(img);
                    }
                });
            });
            images.forEach(img => observer.observe(img));
        }

        function checkNewPosts() {
            fetch('get_latest_post.php')
                .then(response => response.json())
                .then(data => {
                    if (data.latest_post_id > <?php echo $latest_post_id; ?>) {
                        const notification = document.getElementById('post-notification');
                        notification.style.display = 'block';
                        setTimeout(() => notification.style.display = 'none', 3000);
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            startCarousel();
            lazyLoadImages();
            setInterval(checkNewPosts, 60000); // Verifica novos posts a cada 60 segundos
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>