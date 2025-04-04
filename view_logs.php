<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();

$items_per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$escort_id = isset($_GET['escort_id']) ? (int)$_GET['escort_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where = [];
$params = [];
$types = '';
if ($escort_id > 0) {
    $where[] = "vl.escort_id = ?";
    $params[] = $escort_id;
    $types .= 'i';
}
if ($start_date) {
    $where[] = "vl.timestamp >= ?";
    $params[] = "$start_date 00:00:00";
    $types .= 's';
}
if ($end_date) {
    $where[] = "vl.timestamp <= ?";
    $params[] = "$end_date 23:59:59";
    $types .= 's';
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$total_query = "SELECT COUNT(*) as total 
                FROM view_log vl 
                JOIN escorts e ON vl.escort_id = e.id 
                $where_clause";
$stmt_total = $conn->prepare($total_query);
if ($types) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_logs = $stmt_total->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $items_per_page);

$query = "SELECT vl.id, e.name, vl.timestamp 
          FROM view_log vl 
          JOIN escorts e ON vl.escort_id = e.id 
          $where_clause 
          ORDER BY vl.timestamp DESC 
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$items_per_page, $offset]));
} else {
    $stmt->bind_param('ii', $items_per_page, $offset);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$escorts = $conn->query("SELECT id, name FROM escorts ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="view_logs_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Perfil', 'Data']);
    foreach ($logs as $log) {
        fputcsv($output, [$log['id'], $log['name'], $log['timestamp']]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Visualização - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-right">
            <a href="admin.php">Voltar ao Painel</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <h2>Logs de Visualização</h2>
            <form method="GET" class="filter-form">
                <select name="escort_id">
                    <option value="0">Todos os Perfis</option>
                    <?php foreach ($escorts as $escort): ?>
                        <option value="<?php echo $escort['id']; ?>" <?php echo $escort_id == $escort['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($escort['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Data Inicial: <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"></label>
                <label>Data Final: <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"></label>
                <button type="submit" class="search-btn">Filtrar</button>
                <button type="submit" name="export_csv" value="1" class="export-btn">Exportar CSV</button>
            </form>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Perfil</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo htmlspecialchars($log['name']); ?></td>
                            <td><?php echo $log['timestamp']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&escort_id=<?php echo $escort_id; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>