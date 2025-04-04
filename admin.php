<?php
require_once 'session.php'; // Centraliza a sessão
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();

// Função para verificar a integridade do banco de dados
function checkDatabaseIntegrity($conn) {
    $required_tables = [
        'escorts' => ['id', 'user_id', 'name', 'age', 'type', 'is_online', 'views', 'latitude', 'longitude', 'tags'],
        'users' => ['id', 'username', 'role'],
        'favorites' => ['id', 'admin_id', 'escort_id', 'is_public'],
        'messages' => ['id', 'receiver_id', 'is_read'],
        'schedules' => ['id', 'status'],
        'photos' => ['id', 'escort_id', 'photo_path'],
        'photo_moderation' => ['photo_id', 'status'],
        'search_log' => ['id', 'admin_id', 'query'],
        'categories' => ['id', 'name']
    ];

    $errors = [];

    foreach ($required_tables as $table => $columns) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            $errors[] = "Tabela '$table' não existe.";
            continue;
        }

        $result = $conn->query("SHOW COLUMNS FROM $table");
        $existing_columns = [];
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }

        foreach ($columns as $column) {
            if (!in_array($column, $existing_columns)) {
                $errors[] = "Coluna '$column' não existe na tabela '$table'.";
            }
        }
    }

    return empty($errors) ? true : $errors;
}

// Verifica o banco
$db_check = checkDatabaseIntegrity($conn);
if ($db_check !== true) {
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><title>Erro no Banco de Dados</title>";
    echo "<link rel='stylesheet' href='style.css?v=" . time() . "'>";
    echo "</head><body><div class='container'><div class='main-content error-box'><h1>Erro no Banco de Dados</h1><p>O banco de dados está incompleto. Corrija os seguintes problemas:</p>";
    echo "<ul>";
    foreach ($db_check as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul><p>Por favor, crie as tabelas e colunas necessárias no phpMyAdmin e tente novamente.</p></div></div></body></html>";
    exit;
}

// Configuração da página
$items_per_page = 10;
$page_escorts = isset($_GET['page_escorts']) ? max(1, (int)$_GET['page_escorts']) : 1;
$offset_escorts = ($page_escorts - 1) * $items_per_page;

$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '';
$filter_online = isset($_GET['filter_online']) ? (int)$_GET['filter_online'] : -1;
$filter_search = isset($_GET['filter_search']) ? trim($_GET['filter_search']) : '';
$filter_views_min = isset($_GET['filter_views_min']) ? (int)$_GET['filter_views_min']) : 0;
$filter_tag = isset($_GET['filter_tag']) ? trim($_GET['filter_tag']) : '';
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
if (!empty($filter_tag)) {
    $where[] = "e.tags LIKE ?";
    $params[] = '%' . $filter_tag . '%';
    $types .= 's';
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$total_query = "SELECT COUNT(*) as total FROM escorts e $where_clause";
try {
    $stmt_total = $conn->prepare($total_query);
    if ($types) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_escorts = $stmt_total->get_result()->fetch_assoc()['total'];
} catch (mysqli_sql_exception $e) {
    die("Erro ao contar escorts: " . $e->getMessage());
}
$total_pages_escorts = ceil($total_escorts / $items_per_page);

function getEscorts($conn, $offset, $limit, $where_clause, $types, $params) {
    $query = "SELECT e.id, e.name, e.type, e.is_online, e.views, e.latitude, e.longitude, u.username, 
                     (SELECT COUNT(*) FROM favorites f WHERE f.escort_id = e.id AND f.is_public = 1) as public_favorites 
              FROM escorts e 
              JOIN users u ON e.user_id = u.id 
              $where_clause 
              ORDER BY e.views DESC 
              LIMIT ? OFFSET ?";
    try {
        $stmt = $conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
        } else {
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        die("Erro ao buscar escorts: " . $e->getMessage());
    }
}

$escorts = getEscorts($conn, $offset_escorts, $items_per_page, $where_clause, $types, $params);

$stats_query = "SELECT 
    COUNT(CASE WHEN e.type = 'acompanhante' THEN 1 END) as acompanhantes,
    COUNT(CASE WHEN e.type = 'criadora' THEN 1 END) as pornstars,
    SUM(e.views) as total_views,
    (SELECT COUNT(*) FROM favorites WHERE admin_id = ?) as favorites,
    (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0) as unread_messages,
    (SELECT COUNT(*) FROM schedules WHERE status = 'pending') as pending_schedules
FROM escorts e";
try {
    $stmt_stats = $conn->prepare($stats_query);
    $stmt_stats->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (mysqli_sql_exception $e) {
    die("Erro ao buscar estatísticas: " . $e->getMessage());
}

if (!empty($filter_search)) {
    try {
        $stmt = $conn->prepare("INSERT INTO search_log (admin_id, query) VALUES (?, ?)");
        $stmt->bind_param("is", $_SESSION['user_id'], $filter_search);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        error_log("Erro ao logar busca: " . $e->getMessage());
    }
}

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$photo_moderation_exists = $conn->query("SHOW TABLES LIKE 'photo_moderation'")->num_rows > 0;
$photos = [];
if ($photo_moderation_exists) {
    try {
        $photos = $conn->query("SELECT p.id, p.photo_path, e.name as escort_name, pm.status 
                                FROM photos p 
                                JOIN escorts e ON p.escort_id = e.id 
                                LEFT JOIN photo_moderation pm ON p.id = pm.photo_id 
                                WHERE pm.status IS NULL OR pm.status = 'pending' 
                                ORDER BY p.id DESC 
                                LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        error_log("Erro ao buscar fotos para moderação: " . $e->getMessage());
    }
}

if (isset($_POST['moderate_photos']) && $photo_moderation_exists) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erro de segurança: Token CSRF inválido.");
    }
    $photo_ids = isset($_POST['photo_ids']) ? $_POST['photo_ids'] : [];
    $action = in_array($_POST['action'], ['approve', 'reject']) ? $_POST['action'] : 'pending';
    if (!empty($photo_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
            $stmt = $conn->prepare("INSERT INTO photo_moderation (photo_id, status) 
                                    VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE status = VALUES(status), moderated_at = NOW()");
            foreach ($photo_ids as $photo_id) {
                $stmt->bind_param("is", $photo_id, $action);
                $stmt->execute();
            }
            // Enviar notificação via WebSocket
            $notification = json_encode(['type' => 'photo_moderation', 'message' => "Fotos moderadas: " . count($photo_ids) . " marcadas como '$action' por " . $_SESSION['username']]);
            file_get_contents("http://localhost:8080?msg=" . urlencode($notification));
            header("Location: admin.php#photo-moderation");
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log("Erro ao moderar fotos: " . $e->getMessage());
        }
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
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="main-content">
            <h3>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>

            <section class="dashboard-widgets">
                <div class="widget" onclick="location.href='#escorts';">
                    <h4>Acompanhantes</h4>
                    <p><?php echo $stats['acompanhantes']; ?></p>
                    <canvas id="acompanhantes-chart"></canvas>
                </div>
                <div class="widget" onclick="location.href='#escorts';">
                    <h4>Pornstars</h4>
                    <p><?php echo $stats['pornstars']; ?></p>
                    <canvas id="pornstars-chart"></canvas>
                </div>
                <div class="widget" onclick="location.href='report.php';">
                    <h4>Total de Visualizações</h4>
                    <p><?php echo $stats['total_views']; ?></p>
                    <canvas id="views-chart"></canvas>
                </div>
                <div class="widget" onclick="location.href='favorites.php';">
                    <h4>Favoritos</h4>
                    <p><?php echo $stats['favorites']; ?></p>
                    <canvas id="favorites-chart"></canvas>
                </div>
                <div class="widget" onclick="location.href='schedule.php';">
                    <h4>Agendamentos Pendentes</h4>
                    <p><?php echo $stats['pending_schedules']; ?></p>
                    <canvas id="schedules-chart"></canvas>
                </div>
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
                        <button type="submit" class="btn" aria-label="Exportar perfis como CSV">Exportar CSV</button>
                    </form>
                    <form class="export-form" action="export_pdf.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <select name="export_category" aria-label="Selecionar categoria para exportação">
                            <option value="0">Todas as Categorias</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $export_category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn" aria-label="Exportar perfis como PDF">Exportar PDF</button>
                    </form>
                    <input type="file" id="import-csv" accept=".csv" onchange="importCSV(this)" aria-label="Importar CSV de perfis">
                    <a href="edit_escort.php" class="btn" aria-label="Adicionar novo perfil">Adicionar Perfil</a>
                </div>
                <div id="import-result"></div>
                <form class="filter-form" method="GET">
                    <input type="text" name="filter_search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Buscar por nome" aria-label="Buscar por nome">
                    <select name="filter_type" aria-label="Filtrar por tipo">
                        <option value="">Todos os Tipos</option>
                        <option value="acompanhante" <?php echo $filter_type === 'acompanhante' ? 'selected' : ''; ?>>Acompanhante</option>
                        <option value="criadora" <?php echo $filter_type === 'criadora' ? 'selected' : ''; ?>>Pornstar</option>
                    </select>
                    <select name="filter_online" aria-label="Filtrar por status online">
                        <option value="-1">Todos Status</option>
                        <option value="1" <?php echo $filter_online === 1 ? 'selected' : ''; ?>>Online</option>
                        <option value="0" <?php echo $filter_online === 0 ? 'selected' : ''; ?>>Offline</option>
                    </select>
                    <input type="number" name="filter_views_min" value="<?php echo $filter_views_min; ?>" placeholder="Views mínimas" aria-label="Filtrar por visualizações mínimas">
                    <input type="text" name="filter_tag" value="<?php echo htmlspecialchars($filter_tag); ?>" placeholder="Tag (ex: loira)" aria-label="Filtrar por tag">
                    <button type="submit" class="btn" aria-label="Aplicar filtros">Filtrar</button>
                </form>
                <div id="escorts-table-container">
                    <table class="admin-table" role="grid">
                        <thead>
                            <tr>
                                <th aria-sort="none" onclick="sortTable(0)">ID</th>
                                <th aria-sort="none" onclick="sortTable(1)">Nome</th>
                                <th aria-sort="none" onclick="sortTable(2)">Tipo</th>
                                <th aria-sort="none" onclick="sortTable(3)">Online</th>
                                <th aria-sort="none" onclick="sortTable(4)">Views</th>
                                <th aria-sort="none" onclick="sortTable(5)">Lat/Long</th>
                                <th aria-sort="none" onclick="sortTable(6)">Usuário</th>
                                <th aria-sort="none" onclick="sortTable(7)">Favoritos Públicos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="escorts-tbody">
                            <?php foreach ($escorts as $escort): ?>
                                <tr>
                                    <td><?php echo $escort['id']; ?></td>
                                    <td><?php echo htmlspecialchars($escort['name']); ?></td>
                                    <td><?php echo $escort['type']; ?></td>
                                    <td><?php echo $escort['is_online'] ? 'Sim' : 'Não'; ?></td>
                                    <td><?php echo $escort['views']; ?></td>
                                    <td><?php echo $escort['latitude'] . ', ' . $escort['longitude']; ?></td>
                                    <td><?php echo htmlspecialchars($escort['username']); ?></td>
                                    <td><?php echo $escort['public_favorites']; ?></td>
                                    <td>
                                        <a href="edit_escort.php?id=<?php echo $escort['id']; ?>" class="btn" aria-label="Editar perfil <?php echo htmlspecialchars($escort['name']); ?>">Editar</a>
                                        <button onclick="toggleFavorite(<?php echo $escort['id']; ?>, this)" class="btn" aria-label="Favoritar perfil <?php echo htmlspecialchars($escort['name']); ?>">Favoritar<span class="action-feedback" id="favorite-feedback-<?php echo $escort['id']; ?>"></span></button>
                                        <button onclick="toggleHighlight(<?php echo $escort['id']; ?>, this)" class="btn" aria-label="Destacar perfil <?php echo htmlspecialchars($escort['name']); ?>">Destacar<span class="action-feedback" id="highlight-feedback-<?php echo $escort['id']; ?>"></span></button>
                                        <button onclick="showDeletePopup(<?php echo $escort['id']; ?>)" class="btn" aria-label="Excluir perfil <?php echo htmlspecialchars($escort['name']); ?>">Excluir</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages_escorts; $i++): ?>
                        <a href="?page_escorts=<?php echo $i; ?>&filter_type=<?php echo urlencode($filter_type); ?>&filter_online=<?php echo $filter_online; ?>&filter_search=<?php echo urlencode($filter_search); ?>&filter_views_min=<?php echo $filter_views_min; ?>&filter_tag=<?php echo urlencode($filter_tag); ?>" class="<?php echo $i === $page_escorts ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </section>

            <section id="photo-moderation">
                <h2>Moderação de Fotos</h2>
                <?php if (!$photo_moderation_exists): ?>
                    <p class="error">A tabela de moderação de fotos não está disponível. Crie a tabela 'photo_moderation' no banco de dados.</p>
                <?php elseif (empty($photos)): ?>
                    <p>Nenhuma foto pendente para moderação.</p>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <table class="admin-table" role="grid">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all" onclick="toggleSelectAll()" aria-label="Selecionar todas as fotos"></th>
                                    <th>ID</th>
                                    <th>Foto</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($photos as $photo): ?>
                                    <tr>
                                        <td><input type="checkbox" name="photo_ids[]" value="<?php echo $photo['id']; ?>" aria-label="Selecionar foto ID <?php echo $photo['id']; ?>"></td>
                                        <td><?php echo $photo['id']; ?></td>
                                        <td><img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="Foto de <?php echo htmlspecialchars($photo['escort_name']); ?>"></td>
                                        <td><?php echo htmlspecialchars($photo['escort_name']); ?></td>
                                        <td><?php echo $photo['status'] ?? 'Pendente'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <select name="action" aria-label="Ação de moderação">
                            <option value="approve">Aprovar</option>
                            <option value="reject">Rejeitar</option>
                        </select>
                        <button type="submit" name="moderate_photos" class="btn" aria-label="Aplicar ação de moderação">Aplicar</button>
                    </form>
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
                <button id="delete-cancel" class="btn" onclick="closeDeletePopup()">Cancelar</button>
            </div>
            <p id="delete-feedback">Excluindo...</p>
        </div>
    </div>

    <script>
        let sortDirection = {};
        let isDeleting = false;
        let page = <?php echo $page_escorts; ?>;
        const itemsPerPage = <?php echo $items_per_page; ?>;

        // WebSocket para notificações em tempo real
        const socket = new WebSocket('ws://localhost:8080');
        socket.onopen = () => console.log('Conectado ao WebSocket');
        socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            Toastify({
                text: data.message,
                duration: 5000,
                style: { background: data.type === 'photo_moderation' ? '#28A745' : '#1877F2' }
            }).showToast();
        };
        socket.onerror = (error) => console.error('Erro no WebSocket:', error);
        socket.onclose = () => console.log('Desconectado do WebSocket');

        function showDeletePopup(id) {
            if (isDeleting) return;
            const popup = document.getElementById('delete-popup');
            const yesBtn = document.getElementById('delete-yes');
            const cancelBtn = document.getElementById('delete-cancel');
            const feedback = document.getElementById('delete-feedback');

            popup.classList.add('active');
            yesBtn.disabled = false;
            cancelBtn.disabled = false;
            feedback.style.display = 'none';

            yesBtn.onclick = () => {
                if (isDeleting) return;
                isDeleting = true;
                yesBtn.disabled = true;
                cancelBtn.disabled = true;
                feedback.style.display = 'block';

                fetch('delete_escort.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        feedback.textContent = 'Perfil excluído com sucesso!';
                        feedback.style.color = '#28A745';
                        Toastify({
                            text: "Perfil excluído com sucesso!",
                            duration: 3000,
                            style: { background: "#28A745" }
                        }).showToast();
                        socket.send(JSON.stringify({ type: 'delete', message: `Perfil ID ${id} excluído por <?php echo $_SESSION['username']; ?>` }));
                        setTimeout(() => {
                            closeDeletePopup();
                            location.reload();
                        }, 1000);
                    } else {
                        feedback.textContent = 'Erro: ' + data.message;
                        feedback.style.color = '#DC3545';
                        Toastify({
                            text: "Erro: " + data.message,
                            duration: 3000,
                            style: { background: "#DC3545" }
                        }).showToast();
                        yesBtn.disabled = false;
                        cancelBtn.disabled = false;
                        isDeleting = false;
                    }
                })
                .catch(error => {
                    feedback.textContent = 'Erro ao excluir: ' + error.message;
                    feedback.style.color = '#DC3545';
                    Toastify({
                        text: "Erro ao excluir: " + error.message,
                        duration: 3000,
                        style: { background: "#DC3545" }
                    }).showToast();
                    yesBtn.disabled = false;
                    cancelBtn.disabled = false;
                    isDeleting = false;
                });
            };
        }

        function closeDeletePopup() {
            if (isDeleting) return;
            const popup = document.getElementById('delete-popup');
            popup.classList.remove('active');
            isDeleting = false;
        }

        function toggleFavorite(id, button) {
            const feedback = document.getElementById(`favorite-feedback-${id}`);
            feedback.textContent = 'Processando...';
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `escort_id=${id}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    feedback.textContent = '✓';
                    setTimeout(() => feedback.textContent = '', 2000);
                    Toastify({
                        text: data.message,
                        duration: 3000,
                        style: { background: "#28A745" }
                    }).showToast();
                    socket.send(JSON.stringify({ type: 'favorite', message: `${data.message} para ID ${id} por <?php echo $_SESSION['username']; ?>` }));
                    setTimeout(() => location.reload(), 1000);
                } else {
                    feedback.textContent = 'Erro: ' + data.message;
                    feedback.style.color = '#DC3545';
                    Toastify({
                        text: "Erro: " + data.message,
                        duration: 3000,
                        style: { background: "#DC3545" }
                    }).showToast();
                }
            })
            .catch(error => {
                feedback.textContent = 'Erro: ' + error.message;
                feedback.style.color = '#DC3545';
                Toastify({
                    text: "Erro: " + error.message,
                    duration: 3000,
                    style: { background: "#DC3545" }
                }).showToast();
            });
        }

        function toggleHighlight(id, button) {
            const feedback = document.getElementById(`highlight-feedback-${id}`);
            feedback.textContent = 'Processando...';
            fetch('toggle_highlight.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `escort_id=${id}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    feedback.textContent = '✓';
                    setTimeout(() => feedback.textContent = '', 2000);
                    Toastify({
                        text: "Destaque alterado com sucesso!",
                        duration: 3000,
                        style: { background: "#28A745" }
                    }).showToast();
                    socket.send(JSON.stringify({ type: 'highlight', message: `Destaque alterado para ID ${id} por <?php echo $_SESSION['username']; ?>` }));
                    setTimeout(() => location.reload(), 1000);
                } else {
                    feedback.textContent = 'Erro: ' + data.message;
                    feedback.style.color = '#DC3545';
                    Toastify({
                        text: "Erro: " + data.message,
                        duration: 3000,
                        style: { background: "#DC3545" }
                    }).showToast();
                }
            })
            .catch(error => {
                feedback.textContent = 'Erro: ' + error.message;
                feedback.style.color = '#DC3545';
                Toastify({
                    text: "Erro: " + error.message,
                    duration: 3000,
                    style: { background: "#DC3545" }
                }).showToast();
            });
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('input[name="photo_ids[]"]');
            const selectAll = document.getElementById('select-all');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        function sortTable(columnIndex) {
            const table = document.querySelector('.admin-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const th = table.querySelectorAll('th')[columnIndex];
            const direction = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            sortDirection[columnIndex] = direction;

            rows.sort((a, b) => {
                const aValue = a.cells[columnIndex].textContent.trim();
                const bValue = b.cells[columnIndex].textContent.trim();
                if (!isNaN(aValue) && !isNaN(bValue)) {
                    return direction === 'asc' ? aValue - bValue : bValue - aValue;
                }
                return direction === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            });

            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));

            table.querySelectorAll('th').forEach(th => th.setAttribute('aria-sort', 'none'));
            th.setAttribute('aria-sort', direction);
        }

        function importCSV(input) {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            const resultDiv = document.getElementById('import-result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'loading';
            resultDiv.innerHTML = '<div class="spinner"></div> Importando...';

            fetch('import_escorts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.style.display = 'block';
                if (data.status === 'success') {
                    resultDiv.className = 'success';
                    resultDiv.textContent = `Importação concluída! ${data.inserted} perfis inseridos, ${data.updated} atualizados.`;
                    Toastify({
                        text: "Importação concluída com sucesso!",
                        duration: 3000,
                        style: { background: "#28A745" }
                    }).showToast();
                    socket.send(JSON.stringify({ type: 'import', message: `Importação concluída: ${data.inserted} inseridos, ${data.updated} atualizados por <?php echo $_SESSION['username']; ?>` }));
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.className = 'error';
                    resultDiv.textContent = `Erro na importação: ${data.message}`;
                    Toastify({
                        text: "Erro na importação: " + data.message,
                        duration: 3000,
                        style: { background: "#DC3545" }
                    }).showToast();
                }
            })
            .catch(error => {
                resultDiv.style.display = 'block';
                resultDiv.className = 'error';
                resultDiv.textContent = 'Erro ao processar o arquivo CSV: ' + error.message;
                Toastify({
                    text: "Erro ao processar o arquivo CSV: " + error.message,
                    duration: 3000,
                    style: { background: "#DC3545" }
                }).showToast();
            });
        }

        function loadMoreEscorts() {
            page++;
            fetch(`load_escorts.php?page=${page}&items_per_page=${itemsPerPage}&filter_type=<?php echo urlencode($filter_type); ?>&filter_online=<?php echo $filter_online; ?>&filter_search=<?php echo urlencode($filter_search); ?>&filter_views_min=<?php echo $filter_views_min; ?>&filter_tag=<?php echo urlencode($filter_tag); ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const tbody = document.getElementById('escorts-tbody');
                        data.escorts.forEach(escort => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${escort.id}</td>
                                <td>${escort.name}</td>
                                <td>${escort.type}</td>
                                <td>${escort.is_online ? 'Sim' : 'Não'}</td>
                                <td>${escort.views}</td>
                                <td>${escort.latitude}, ${escort.longitude}</td>
                                <td>${escort.username}</td>
                                <td>${escort.public_favorites}</td>
                                <td>
                                    <a href="edit_escort.php?id=${escort.id}" class="btn" aria-label="Editar perfil ${escort.name}">Editar</a>
                                    <button onclick="toggleFavorite(${escort.id}, this)" class="btn" aria-label="Favoritar perfil ${escort.name}">Favoritar<span class="action-feedback" id="favorite-feedback-${escort.id}"></span></button>
                                    <button onclick="toggleHighlight(${escort.id}, this)" class="btn" aria-label="Destacar perfil ${escort.name}">Destacar<span class="action-feedback" id="highlight-feedback-${escort.id}"></span></button>
                                    <button onclick="showDeletePopup(${escort.id})" class="btn" aria-label="Excluir perfil ${escort.name}">Excluir</button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                        if (data.escorts.length < itemsPerPage) {
                            document.getElementById('load-more-btn')?.remove();
                        }
                    } else {
                        Toastify({
                            text: "Erro ao carregar mais perfis: " + data.message,
                            duration: 3000,
                            style: { background: "#DC3545" }
                        }).showToast();
                    }
                })
                .catch(error => {
                    Toastify({
                        text: "Erro ao carregar mais perfis: " + error.message,
                        duration: 3000,
                        style: { background: "#DC3545" }
                    }).showToast();
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const acompanhantesChart = new Chart(document.getElementById('acompanhantes-chart'), {
                type: 'doughnut',
                data: { labels: ['Acompanhantes'], datasets: [{ data: [<?php echo $stats['acompanhantes']; ?>], backgroundColor: ['#1877F2'] }] },
                options: { plugins: { legend: { display: false } }, cutout: '70%' }
            });
            const pornstarsChart = new Chart(document.getElementById('pornstars-chart'), {
                type: 'doughnut',
                data: { labels: ['Pornstars'], datasets: [{ data: [<?php echo $stats['pornstars']; ?>], backgroundColor: ['#1877F2'] }] },
                options: { plugins: { legend: { display: false } }, cutout: '70%' }
            });
            const viewsChart = new Chart(document.getElementById('views-chart'), {
                type: 'doughnut',
                data: { labels: ['Visualizações'], datasets: [{ data: [<?php echo $stats['total_views']; ?>], backgroundColor: ['#1877F2'] }] },
                options: { plugins: { legend: { display: false } }, cutout: '70%' }
            });
            const favoritesChart = new Chart(document.getElementById('favorites-chart'), {
                type: 'doughnut',
                data: { labels: ['Favoritos'], datasets: [{ data: [<?php echo $stats['favorites']; ?>], backgroundColor: ['#1877F2'] }] },
                options: { plugins: { legend: { display: false } }, cutout: '70%' }
            });
            const schedulesChart = new Chart(document.getElementById('schedules-chart'), {
                type: 'doughnut',
                data: { labels: ['Agendamentos'], datasets: [{ data: [<?php echo $stats['pending_schedules']; ?>], backgroundColor: ['#1877F2'] }] },
                options: { plugins: { legend: { display: false } }, cutout: '70%' }
            });

            // Lazy loading observer
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMoreEscorts();
                }
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