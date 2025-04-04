<?php
require_once 'session.php';
require_once 'config.php';

logTask("UPDATE", "Corrigir erro 'Unknown column e.online' para e.is_online em admin.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();

// Verifica integridade do banco
function checkDB($conn) {
    $tables = ['escorts', 'users', 'favorites', 'messages', 'schedules', 'photos', 'photo_moderation', 'search_log', 'categories', 'db_log'];
    foreach ($tables as $table) {
        if ($conn->query("SHOW TABLES LIKE '$table'")->num_rows == 0) {
            logError("Tabela '$table' não existe no banco de dados.");
            die("<div class='container'><div class='main-content error-box'><h1>Erro no Banco</h1><p>Tabela '$table' não existe. Configure no phpMyAdmin.</p></div></div>");
        }
    }
}
checkDB($conn);

// Configuração de paginação e filtros
$items_per_page = 10;
$page = max(1, (int)($_GET['page_escorts'] ?? 1));
$offset = ($page - 1) * $items_per_page;
$filters = [
    'type' => sanitize($_GET['filter_type'] ?? ''),
    'online' => (int)($_GET['filter_online'] ?? -1),
    'search' => sanitize($_GET['filter_search'] ?? ''),
    'views_min' => (int)($_GET['filter_views_min'] ?? 0),
    'tag' => sanitize($_GET['filter_tag'] ?? '')
];
$where = [];
$params = [];
$types = '';
foreach ($filters as $key => $value) {
    if ($key === 'online' && $value !== -1) {
        $where[] = "e.is_online = ?"; // Corrigido de 'e.online' para 'e.is_online'
        $params[] = $value;
        $types .= 'i';
    } elseif ($key === 'search' && $value) {
        $where[] = "e.name LIKE ?";
        $params[] = "%$value%";
        $types .= 's';
    } elseif ($value) {
        $where[] = "e." . ($key === 'views_min' ? 'views' : $key) . " " . ($key === 'views_min' ? '>=' : '=') . " ?";
        $params[] = $value;
        $types .= $key === 'views_min' ? 'i' : 's';
    }
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';
// ... resto do código permanece igual ...

// Total de escorts
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM escorts e $where_clause");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute() or logError("Erro ao contar escorts: " . $conn->error);
$total_escorts = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_escorts / $items_per_page);

// Lista de escorts
$stmt = $conn->prepare("SELECT e.id, e.name, e.type, e.is_online, e.views, e.latitude, e.longitude, u.username, 
                              COUNT(f.id) as public_favorites 
                       FROM escorts e 
                       JOIN users u ON e.user_id = u.id 
                       LEFT JOIN favorites f ON f.escort_id = e.id AND f.is_public = 1 
                       $where_clause 
                       GROUP BY e.id 
                       ORDER BY e.views DESC 
                       LIMIT ? OFFSET ?");
if ($types) $stmt->bind_param($types . 'ii', ...array_merge($params, [$items_per_page, $offset]));
else $stmt->bind_param('ii', $items_per_page, $offset);
$stmt->execute() or logError("Erro ao buscar escorts: " . $conn->error);
$escorts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Estatísticas com cache
$cache_file = 'cache/stats_' . $_SESSION['user_id'] . '.json';
$cache_time = 300;
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $stats = json_decode(file_get_contents($cache_file), true);
} else {
    $stmt = $conn->prepare("SELECT COUNT(CASE WHEN e.type = 'acompanhante' THEN 1 END) as acompanhantes,
                                  COUNT(CASE WHEN e.type = 'criadora' THEN 1 END) as pornstars,
                                  SUM(e.views) as total_views,
                                  (SELECT COUNT(*) FROM favorites WHERE admin_id = ?) as favorites,
                                  (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0) as unread_messages,
                                  (SELECT COUNT(*) FROM schedules WHERE status = 'pending') as pending_schedules
                           FROM escorts e");
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute() or logError("Erro ao buscar estatísticas: " . $conn->error);
    $stats = $stmt->get_result()->fetch_assoc();
    file_put_contents($cache_file, json_encode($stats));
}

// Log de busca
if ($filters['search']) {
    $stmt = $conn->prepare("INSERT INTO search_log (admin_id, query) VALUES (?, ?)");
    $stmt->bind_param("is", $_SESSION['user_id'], $filters['search']);
    $stmt->execute() or logError("Erro ao logar busca: " . $conn->error);
}

// Categorias e moderação de fotos
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$photos = $conn->query("SHOW TABLES LIKE 'photo_moderation'")->num_rows > 0
    ? $conn->query("SELECT p.id, p.photo_path, e.name as escort_name, pm.status 
                    FROM photos p JOIN escorts e ON p.escort_id = e.id 
                    LEFT JOIN photo_moderation pm ON p.id = pm.photo_id 
                    WHERE pm.status IS NULL OR pm.status = 'pending' 
                    ORDER BY p.id DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC)
    : [];

if (isset($_POST['moderate_photos']) && $photos) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logError("Token CSRF inválido na moderação de fotos.");
        die("Erro: Token CSRF inválido.");
    }
    $photo_ids = $_POST['photo_ids'] ?? [];
    $action = in_array($_POST['action'], ['approve', 'reject']) ? $_POST['action'] : 'pending';
    if ($photo_ids) {
        $stmt = $conn->prepare("INSERT INTO photo_moderation (photo_id, status) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE status = ?, moderated_at = NOW()");
        foreach ($photo_ids as $photo_id) {
            $stmt->bind_param("iss", $photo_id, $action, $action);
            $stmt->execute() or logError("Erro ao moderar foto ID $photo_id: " . $conn->error);
            logDBAction("UPDATE", "photo_moderation", $photo_id, "Status alterado para '$action'");
        }
        $notification = json_encode(['type' => 'photo_moderation', 'message' => "Fotos moderadas: " . count($photo_ids) . " como '$action' por " . $_SESSION['username']]);
        if (@file_get_contents("http://localhost:8080?msg=" . urlencode($notification)) === false) {
            logError("Falha ao enviar notificação WebSocket: " . error_get_last()['message']);
        }
        if (file_exists($cache_file)) unlink($cache_file);
        header("Location: admin.php#photo-moderation");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        .loading { display: flex; align-items: center; justify-content: center; padding: 10px; background: #f9f9f9; border-radius: 6px; }
        .spinner { width: 20px; height: 20px; border: 3px solid rgba(24,119,242,0.3); border-top-color: #1877F2; border-radius: 50%; animation: spin 1s infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="main-content">
            <h3>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>

            <section class="dashboard-widgets">
                <?php foreach (['acompanhantes' => 'Acompanhantes', 'pornstars' => 'Pornstars', 'total_views' => 'Visualizações', 'favorites' => 'Favoritos', 'pending_schedules' => 'Agendamentos'] as $key => $label): ?>
                    <div class="widget" onclick="location.href='<?php echo $key === 'pending_schedules' ? 'schedule.php' : ($key === 'favorites' ? 'favorites.php' : '#escorts'); ?>';">
                        <h4><?php echo $label; ?></h4>
                        <p><?php echo $stats[$key]; ?></p>
                        <canvas id="<?php echo $key; ?>-chart"></canvas>
                    </div>
                <?php endforeach; ?>
            </section>

            <section id="escorts">
                <h2>Gerenciar Perfis</h2>
                <div class="action-bar">
                    <form class="export-form" action="export_escorts.php" method="POST">
                        <?php $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <label><input type="checkbox" name="fields[]" value="name" checked> Nome</label>
                        <label><input type="checkbox" name="fields[]" value="type"> Tipo</label>
                        <label><input type="checkbox" name="fields[]" value="views"> Views</label>
                        <button type="submit" class="btn">Exportar CSV</button>
                    </form>
                    <form class="export-form" action="export_pdf.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <select name="export_category">
                            <option value="0">Todas</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $export_category == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Exportar PDF</button>
                    </form>
                    <input type="file" id="import-csv" accept=".csv" onchange="importCSV(this)">
                    <a href="edit_escort.php" class="btn">Adicionar Perfil</a>
                </div>
                <div id="import-result"></div>
                <form class="filter-form" method="GET">
                    <input type="text" name="filter_search" value="<?php echo htmlspecialchars($filters['search']); ?>" placeholder="Buscar por nome">
                    <select name="filter_type">
                        <option value="">Todos</option>
                        <option value="acompanhante" <?php echo $filters['type'] === 'acompanhante' ? 'selected' : ''; ?>>Acompanhante</option>
                        <option value="criadora" <?php echo $filters['type'] === 'criadora' ? 'selected' : ''; ?>>Pornstar</option>
                    </select>
                    <select name="filter_online">
                        <option value="-1">Todos</option>
                        <option value="1" <?php echo $filters['online'] === 1 ? 'selected' : ''; ?>>Online</option>
                        <option value="0" <?php echo $filters['online'] === 0 ? 'selected' : ''; ?>>Offline</option>
                    </select>
                    <input type="number" name="filter_views_min" value="<?php echo $filters['views_min']; ?>" placeholder="Views mín">
                    <input type="text" name="filter_tag" value="<?php echo htmlspecialchars($filters['tag']); ?>" placeholder="Tag">
                    <button type="submit" class="btn">Filtrar</button>
                    <button type="button" class="btn" onclick="resetFilters()">Limpar</button>
                </form>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">ID</th>
                            <th onclick="sortTable(1)">Nome</th>
                            <th onclick="sortTable(2)">Tipo</th>
                            <th onclick="sortTable(3)">Online</th>
                            <th onclick="sortTable(4)">Views</th>
                            <th onclick="sortTable(5)">Lat/Long</th>
                            <th onclick="sortTable(6)">Usuário</th>
                            <th onclick="sortTable(7)">Favoritos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="escorts-tbody">
                        <?php foreach ($escorts as $e): ?>
                            <tr>
                                <td><?php echo $e['id']; ?></td>
                                <td><?php echo htmlspecialchars($e['name']); ?></td>
                                <td><?php echo $e['type']; ?></td>
                                <td><?php echo $e['is_online'] ? 'Sim' : 'Não'; ?></td>
                                <td><?php echo $e['views']; ?></td>
                                <td><?php echo $e['latitude'] . ', ' . $e['longitude']; ?></td>
                                <td><?php echo htmlspecialchars($e['username']); ?></td>
                                <td><?php echo $e['public_favorites']; ?></td>
                                <td>
                                    <a href="edit_escort.php?id=<?php echo $e['id']; ?>" class="btn">Editar</a>
                                    <button onclick="toggleAction('favorite', <?php echo $e['id']; ?>, this)" class="btn">Favoritar<span id="favorite-feedback-<?php echo $e['id']; ?>"></span></button>
                                    <button onclick="toggleAction('highlight', <?php echo $e['id']; ?>, this)" class="btn">Destacar<span id="highlight-feedback-<?php echo $e['id']; ?>"></span></button>
                                    <button onclick="showDeletePopup(<?php echo $e['id']; ?>)" class="btn">Excluir</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page_escorts=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </section>

            <section id="photo-moderation">
                <h2>Moderação de Fotos</h2>
                <?php if ($photos): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <table class="admin-table">
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
                                <?php foreach ($photos as $p): ?>
                                    <tr>
                                        <td><input type="checkbox" name="photo_ids[]" value="<?php echo $p['id']; ?>"></td>
                                        <td><?php echo $p['id']; ?></td>
                                        <td><img src="<?php echo htmlspecialchars($p['photo_path']); ?>" alt="Foto" style="max-width: 100px;"></td>
                                        <td><?php echo htmlspecialchars($p['escort_name']); ?></td>
                                        <td><?php echo $p['status'] ?? 'Pendente'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <select name="action">
                            <option value="approve">Aprovar</option>
                            <option value="reject">Rejeitar</option>
                        </select>
                        <button type="submit" name="moderate_photos" class="btn">Aplicar</button>
                    </form>
                <?php else: ?>
                    <p><?php echo $photo_moderation_exists ? 'Nenhuma foto pendente.' : 'Tabela photo_moderation não existe.'; ?></p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <div id="delete-popup" class="confirm-popup">
        <div class="confirm-content">
            <h3>Confirmar Exclusão</h3>
            <p>Tem certeza que deseja excluir este perfil?</p>
            <div class="confirm-buttons">
                <button id="delete-yes" class="btn">Sim</button>
                <button id="delete-cancel" class="btn" onclick="closeDeletePopup()">Não</button>
            </div>
            <p id="delete-feedback" style="display: none;"></p>
        </div>
    </div>

    <script>
        let sortDir = {}, isDeleting = false, page = <?php echo $page; ?>, itemsPerPage = <?php echo $items_per_page; ?>;
        const socket = new WebSocket('ws://localhost:8080');
        socket.onopen = () => console.log('WebSocket OK');
        socket.onmessage = e => Toastify({ text: JSON.parse(e.data).message, duration: 3000, style: { background: '#28A745' } }).showToast();

        function showDeletePopup(id) {
            if (isDeleting) return;
            const popup = document.getElementById('delete-popup'), yes = document.getElementById('delete-yes'), 
                  cancel = document.getElementById('delete-cancel'), feedback = document.getElementById('delete-feedback');
            popup.classList.add('active');
            yes.disabled = cancel.disabled = false;
            feedback.style.display = 'none';

            yes.onclick = () => {
                if (isDeleting) return;
                isDeleting = true;
                yes.disabled = cancel.disabled = true;
                feedback.style.display = 'block';
                fetch('delete_escort.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
                })
                .then(r => r.json())
                .then(d => {
                    feedback.textContent = d.status === 'success' ? 'Excluído!' : 'Erro: ' + d.message;
                    feedback.style.color = d.status === 'success' ? '#28A745' : '#DC3545';
                    Toastify({ text: d.status === 'success' ? 'Excluído!' : 'Erro: ' + d.message, duration: 3000, style: { background: d.status === 'success' ? '#28A745' : '#DC3545' } }).showToast();
                    if (d.status === 'success') {
                        socket.send(JSON.stringify({ type: 'delete', message: `Perfil ID ${id} excluído por <?php echo $_SESSION['username']; ?>` }));
                        setTimeout(() => { closeDeletePopup(); location.reload(); }, 1000);
                    } else {
                        yes.disabled = cancel.disabled = false;
                        isDeleting = false;
                    }
                });
            };
        }

        function closeDeletePopup() {
            if (isDeleting) return;
            document.getElementById('delete-popup').classList.remove('active');
        }

        function toggleAction(action, id, btn) {
            const feedback = document.getElementById(`${action}-feedback-${id}`);
            feedback.textContent = '⌛';
            fetch(`toggle_${action}.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `escort_id=${id}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
            })
            .then(r => r.json())
            .then(d => {
                feedback.textContent = d.status === 'success' ? '✓' : '✗';
                feedback.style.color = d.status === 'success' ? '#28A745' : '#DC3545';
                Toastify({ text: d.message, duration: 3000, style: { background: d.status === 'success' ? '#28A745' : '#DC3545' } }).showToast();
                if (d.status === 'success') {
                    socket.send(JSON.stringify({ type: action, message: `${d.message} para ID ${id} por <?php echo $_SESSION['username']; ?>` }));
                    setTimeout(() => location.reload(), 1000);
                }
            });
        }

        function toggleSelectAll() {
            document.querySelectorAll('input[name="photo_ids[]"]').forEach(cb => cb.checked = document.getElementById('select-all').checked);
        }

        function sortTable(col) {
            const tbody = document.getElementById('escorts-tbody'), rows = Array.from(tbody.querySelectorAll('tr')), 
                  th = document.querySelectorAll('.admin-table th')[col];
            sortDir[col] = sortDir[col] === 'asc' ? 'desc' : 'asc';
            rows.sort((a, b) => {
                const aVal = a.cells[col].textContent.trim(), bVal = b.cells[col].textContent.trim();
                return sortDir[col] === 'asc' ? aVal.localeCompare(bVal, undefined, { numeric: true }) : bVal.localeCompare(aVal, undefined, { numeric: true });
            });
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
            document.querySelectorAll('.admin-table th').forEach(th => th.setAttribute('aria-sort', 'none'));
            th.setAttribute('aria-sort', sortDir[col]);
        }

        function importCSV(input) {
            if (!input.files[0]) return;
            const formData = new FormData();
            formData.append('csv_file', input.files[0]);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            const result = document.getElementById('import-result');
            result.style.display = 'block';
            result.className = 'loading';
            result.innerHTML = '<div class="spinner"></div> Importando...';
            fetch('import_escorts.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    result.className = d.status === 'success' ? 'success' : 'error';
                    result.textContent = d.status === 'success' ? `Concluído! ${d.inserted} inseridos, ${d.updated} atualizados.` : `Erro: ${d.message}`;
                    Toastify({ text: d.status === 'success' ? "Concluído!" : "Erro: " + d.message, duration: 3000, style: { background: d.status === 'success' ? '#28A745' : '#DC3545' } }).showToast();
                    if (d.status === 'success') {
                        socket.send(JSON.stringify({ type: 'import', message: `Importação: ${d.inserted} inseridos, ${d.updated} atualizados por <?php echo $_SESSION['username']; ?>` }));
                        setTimeout(() => location.reload(), 2000);
                    }
                });
        }

        function loadMoreEscorts() {
            page++;
            const url = `load_escorts.php?page=${page}&items_per_page=${itemsPerPage}&<?php echo http_build_query($filters); ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`;
            fetch(url)
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        const tbody = document.getElementById('escorts-tbody');
                        d.escorts.forEach(e => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${e.id}</td>
                                <td>${e.name}</td>
                                <td>${e.type}</td>
                                <td>${e.is_online ? 'Sim' : 'Não'}</td>
                                <td>${e.views}</td>
                                <td>${e.latitude}, ${e.longitude}</td>
                                <td>${e.username}</td>
                                <td>${e.public_favorites}</td>
                                <td>
                                    <a href="edit_escort.php?id=${e.id}" class="btn">Editar</a>
                                    <button onclick="toggleAction('favorite', ${e.id}, this)" class="btn">Favoritar<span id="favorite-feedback-${e.id}"></span></button>
                                    <button onclick="toggleAction('highlight', ${e.id}, this)" class="btn">Destacar<span id="highlight-feedback-${e.id}"></span></button>
                                    <button onclick="showDeletePopup(${e.id})" class="btn">Excluir</button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                        if (d.escorts.length < itemsPerPage) document.getElementById('load-more-btn')?.remove();
                    } else {
                        Toastify({ text: "Erro ao carregar: " + d.message, duration: 3000, style: { background: "#DC3545" } }).showToast();
                    }
                });
        }

        function resetFilters() {
            document.querySelector('.filter-form').reset();
            window.location.href = 'admin.php?page_escorts=<?php echo $page; ?>';
        }

        document.addEventListener('DOMContentLoaded', () => {
            ['acompanhantes', 'pornstars', 'total_views', 'favorites', 'pending_schedules'].forEach(key => {
                new Chart(document.getElementById(`${key}-chart`), {
                    type: 'doughnut',
                    data: { labels: [key.replace('_', ' ').toUpperCase()], datasets: [{ data: [<?php echo json_encode($stats); ?>[key]], backgroundColor: ['#1877F2'] }] },
                    options: { plugins: { legend: { display: false } }, cutout: '70%' }
                });
            });

            const observer = new IntersectionObserver(entries => {
                if (entries[0].isIntersecting) loadMoreEscorts();
            }, { rootMargin: '100px' });
            const loadMoreBtn = document.createElement('button');
            loadMoreBtn.id = 'load-more-btn';
            loadMoreBtn.className = 'btn';
            loadMoreBtn.textContent = 'Carregar Mais';
            loadMoreBtn.onclick = loadMoreEscorts;
            document.querySelector('#escorts').appendChild(loadMoreBtn);
            observer.observe(loadMoreBtn);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>