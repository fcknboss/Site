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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .admin-table th, .admin-table td { padding: 10px; text-align: left; }
        .filter-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .favorite-btn { background: #E95B95; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .favorite-btn:hover { background: #F6ECB2; color: #333; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-right">
            <a href="index.php">Home</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <h3>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>

            <section id="escorts">
                <h2>Gerenciar Perfis</h2>
                <button onclick="exportCSV()" class="export-btn">Exportar CSV</button>
                <a href="edit_escort.php" class="add-btn">Adicionar Perfil</a>
                <form class="filter-form" method="GET">
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
                                    <button onclick="showDeletePopup(<?php echo $escort['id']; ?>)" class="delete-btn">Excluir</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages_escorts; $i++): ?>
                        <a href="?page_escorts=<?php echo $i; ?>&filter_type=<?php echo urlencode($filter_type); ?>&filter_online=<?php echo $filter_online; ?>" class="<?php echo $i === $page_escorts ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </section>
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

        function exportCSV() {
            window.location.href = 'export_escorts.php';
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>