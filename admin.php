<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();

$items_per_page = 10;
$page_escorts = isset($_GET['page_escorts']) ? (int)$_GET['page_escorts'] : 1;
$offset_escorts = ($page_escorts - 1) * $items_per_page;

$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '';
$filter_online = isset($_GET['filter_online']) ? (int)$_GET['filter_online'] : -1;
$filter_search = isset($_GET['filter_search']) ? trim($_GET['filter_search']) : '';
$filter_views_min = isset($_GET['filter_views_min']) ? (int)$_GET['filter_views_min'] : 0;
$export_category = isset($_POST['export_category']) ? (int)$_POST['export_category'] : 0;

$where = [];
$params = [];
$types = '';
if (!empty($filter_type)) {
    $where[] = "e.type = ?";
    $params[] = $filter_type;
    $types .= 's';
}
if ($filter_online !== -1) {
    $where[] = "e.is_online = ?";
    $params[] = $filter_online;
    $types .= 'i';
}
if (!empty($filter_search)) {
    $where[] = "e.name LIKE ?";
    $params[] = '%' . $filter_search . '%';
    $types .= 's';
}
if ($filter_views_min > 0) {
    $where[] = "e.views >= ?";
    $params[] = $filter_views_min;
    $types .= 'i';
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$total_escorts = $conn->query("SELECT COUNT(*) as total FROM escorts e $where_clause" . ($types ? " WITH (" . implode(',', $params) . ")" : ""))->fetch_assoc()['total'];
$total_pages_escorts = ceil($total_escorts / $items_per_page);

function getEscorts($conn, $offset, $limit, $where_clause, $types, $params) {
    $query = "SELECT e.id, e.name, e.type, e.is_online, e.views, e.latitude, e.longitude, u.username 
              FROM escorts e 
              JOIN users u ON e.user_id = u.id 
              $where_clause 
              ORDER BY e.views DESC 
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if ($types) {
        $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$escorts = getEscorts($conn, $offset_escorts, $items_per_page, $where_clause, $types, $params);

$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM escorts WHERE type = 'acompanhante') as acompanhantes,
    (SELECT COUNT(*) FROM escorts WHERE type = 'criadora') as pornstars,
    (SELECT SUM(views) FROM escorts) as total_views,
    (SELECT COUNT(*) FROM favorites WHERE admin_id = " . (int)$_SESSION['user_id'] . ") as favorites")->fetch_assoc();

if (!empty($filter_search)) {
    $stmt = $conn->prepare("INSERT INTO search_log (admin_id, query) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['user_id'], $filter_search);
    $stmt->execute();
}

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Moderação em lote
if (isset($_POST['moderate_photos'])) {
    $photo_ids = isset($_POST['photo_ids']) ? array_map('intval', $_POST['photo_ids']) : [];
    $action = $_POST['action'];
    if (!empty($photo_ids) && in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
        $stmt = $conn->prepare("INSERT INTO photo_moderation (photo_id, status) VALUES (?, '$status') 
                                ON DUPLICATE KEY UPDATE status = '$status', moderated_at = CURRENT_TIMESTAMP");
        foreach ($photo_ids as $photo_id) {
            $stmt->bind_param("i", $photo_id);
            $stmt->execute();
        }
        $message = "Fotos moderadas com sucesso!";
    } else {
        $error = "Selecione pelo menos uma foto e uma ação válida.";
    }
}

$photos = $conn->query("SELECT p.id, p.photo_path, e.name as escort_name, pm.status 
                        FROM photos p 
                        JOIN escorts e ON p.escort_id = e.id 
                        LEFT JOIN photo_moderation pm ON p.id = pm.photo_id 
                        ORDER BY p.id DESC 
                        LIMIT 20")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-table th, .admin-table td { padding: 10px; text-align: left; }
        .filter-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .favorite-btn { background: #E95B95; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .favorite-btn:hover { background: #F6ECB2; color: #333; }
        .highlight-btn { background: #28A745; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .highlight-btn:hover { background: #F6ECB2; color: #333; }
        .stats { margin-bottom: 20px; }
        #views-chart, #tags-chart, #search-chart { max-width: 600px; margin: 20px 0; }
        .export-form { margin: 10px 0; display: flex; gap: 10px; }
        .photo-moderation img { max-width: 100px; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-right">
            <a href="index.php">Home</a>
            <a href="report.php">Relatórios</a>
            <a href="manage_categories.php">Categorias</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <h3>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>

            <section class="stats">
                <h2>Estatísticas</h2>
                <p>Acompanhantes: <?php echo $stats['acompanhantes']; ?></p>
                <p>Pornstars: <?php echo $stats['pornstars']; ?></p>
                <p>Total de Visualizações: <?php echo $stats['total_views']; ?></p>
                <p>Favoritos: <?php echo $stats['favorites']; ?></p>
                <canvas id="views-chart"></canvas>
            </section>

            <section id="escorts">
                <h2>Gerenciar Perfis</h2>
                <form class="export-form" action="export_escorts.php" method="POST">
                    <label><input type="checkbox" name="fields[]" value="name" checked> Nome</label>
                    <label><input type="checkbox" name="fields[]" value="type"> Tipo</label>
                    <label><input type="checkbox" name="fields[]" value="views"> Views</label>
                    <button type="submit" class="export-btn">Exportar CSV</button>
                </form>
                <form class="export-form" action="export_pdf.php" method="POST">
                    <select name="export_category">
                        <option value="0">Todas as Categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $export_category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="export-btn">Exportar PDF</button>
                </form>
                <input type="file" id="import-csv" accept=".csv" onchange="importCSV(this)" style="margin: 10px 0;">
                <a href="edit_escort.php" class="add-btn">Adicionar Perfil</a>
                <form class="filter-form" method="GET">
                    <input type="text" name="filter_search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Buscar por nome">
                    <select name="filter_type">
                        <option value="">Todos os Tipos</option>
                        <option value="acompanhante" <?php echo $filter_type === 'acompanhante' ? 'selected' : ''; ?>>Acompanhante</option>
                        <option value="criadora" <?php echo $filter_type === 'criadora' ? 'selected' : ''; ?>>Pornstar</option>
                    </select>
                    <select name="filter_online">
                        <option value="-1">Todos Status</option>
                        <option value="1" <?php echo $filter_online === 1 ? 'selected' : ''; ?>>Online</option>
                        <option value="0" <?php echo $filter_online === 0 ? 'selected' : ''; ?>>Offline</option>
                    </select>
                    <input type="number" name="filter_views_min" value="<?php echo $filter_views_min; ?>" placeholder="Views mínimas">
                    <button type="submit" class="search-btn">Filtrar</button>
                </form>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Online</th>
                            <th>Views</th>
                            <th>Lat/Long</th>
                            <th>Usuário</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($escorts as $escort): ?>
                            <tr>
                                <td><?php echo $escort['id']; ?></td>
                                <td><?php echo htmlspecialchars($escort['name']); ?></td>
                                <td><?php echo $escort['type']; ?></td>
                                <td><?php echo $escort['is_online'] ? 'Sim' : 'Não'; ?></td>
                                <td><?php echo $escort['views']; ?></td>
                                <td><?php echo $escort['latitude'] . ', ' . $escort['longitude']; ?></td>
                                <td><?php echo htmlspecialchars($escort['username']); ?></td>
                                <td>
                                    <a href="edit_escort.php?id=<?php echo $escort['id']; ?>" class="edit-btn">Editar</a>
                                    <button onclick="toggleFavorite(<?php echo $escort['id']; ?>)" class="favorite-btn">Favoritar</button>
                                    <button onclick="toggleHighlight(<?php echo $escort['id']; ?>)" class="highlight-btn">Destacar</button>
                                    <button onclick="showDeletePopup(<?php echo $escort['id']; ?>)" class="delete-btn">Excluir</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages_escorts; $i++): ?>
                        <a href="?page_escorts=<?php echo $i; ?>&filter_type=<?php echo urlencode($filter_type); ?>&filter_online=<?php echo $filter_online; ?>&filter_search=<?php echo urlencode($filter_search); ?>&filter_views_min=<?php echo $filter_views_min; ?>" class="<?php echo $i === $page_escorts ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </section>

            <section id="photo-moderation">
                <h2>Moderação de Fotos</h2>
                <form method="POST">
                    <table class="admin-table photo-moderation">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" onclick="toggleSelectAll()"></th>
                                <th>ID</th>
                                <th>Foto</th>
                                <th>Perfil</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($photos as $photo): ?>
                                <tr>
                                    <td><input type="checkbox" name="photo_ids[]" value="<?php echo $photo['id']; ?>"></td>
                                    <td><?php echo $photo['id']; ?></td>
                                    <td><img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="Foto"></td>
                                    <td><?php echo htmlspecialchars($photo['escort_name']); ?></td>
                                    <td><?php echo $photo['status'] ?? 'Pendente'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <select name="action">
                        <option value="approve">Aprovar</option>
                        <option value="reject">Rejeitar</option>
                    </select>
                    <button type="submit" name="moderate_photos" class="load-more">Aplicar</button>
                </form>
            </section>

            <!-- Outras seções (edit_log, view_log, search_log, tags-report) permanecem iguais -->
        </div>
    </div>

    <div id="delete-popup" class="confirm-popup">
        <div class="confirm-content">
            <h3>Confirmar Exclusão</h3>
            <p>Tem certeza que deseja excluir este perfil?</p>
            <div class="confirm-buttons">
                <button id="delete-yes">Sim</button>
                <button onclick="closeDeletePopup()">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        function showDeletePopup(id) {
            const popup = document.getElementById('delete-popup');
            popup.classList.add('active');
            document.getElementById('delete-yes').onclick = () => {
                fetch('delete_escort.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') location.reload();
                    else alert(data.message);
                    closeDeletePopup();
                });
            };
        }

        function closeDeletePopup() {
            document.getElementById('delete-popup').classList.remove('active');
        }

        function toggleFavorite(id) {
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `escort_id=${id}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') alert(data.message);
                    else alert(data.message);
                });
        }

        function toggleHighlight(id) {
            alert('Funcionalidade de destaque manual será implementada no futuro.');
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('input[name="photo_ids[]"]');
            const selectAll = document.getElementById('select-all');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        // Outros scripts (exportCSV, importCSV, gráficos) permanecem iguais
    </script>
</body>
</html>
<?php $conn->close(); ?>