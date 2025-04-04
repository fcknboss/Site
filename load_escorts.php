<?php
require_once 'session.php';
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido']);
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = isset($_GET['items_per_page']) ? (int)$_GET['items_per_page'] : 10;
$offset = ($page - 1) * $items_per_page;

$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '';
$filter_online = isset($_GET['filter_online']) ? (int)$_GET['filter_online'] : -1;
$filter_search = isset($_GET['filter_search']) ? trim($_GET['filter_search']) : '';
$filter_views_min = isset($_GET['filter_views_min']) ? (int)$_GET['filter_views_min'] : 0;
$filter_tag = isset($_GET['filter_tag']) ? trim($_GET['filter_tag']) : '';

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

$conn = getDBConnection();
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
        $stmt->bind_param($types . 'ii', ...array_merge($params, [$items_per_page, $offset]));
    } else {
        $stmt->bind_param('ii', $items_per_page, $offset);
    }
    $stmt->execute();
    $escorts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['status' => 'success', 'escorts' => $escorts]);
} catch (mysqli_sql_exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
$conn->close();
exit;
?>