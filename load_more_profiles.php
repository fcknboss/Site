<?php
require_once 'config.php';

$conn = getDBConnection();

$section = $_GET['section'] ?? 'all';
$limit = (int)($_GET['limit'] ?? 12);
$offset = (int)($_GET['offset'] ?? 0);
$location = $_GET['location'] ?? '';

$where = [];
$params = [];
$types = '';
if (!empty($location)) {
    $where[] = "location LIKE ?";
    $params[] = '%' . $location . '%';
    $types .= 's';
}

if ($section === 'new') {
    $where[] = "id > (SELECT MAX(id) - 10 FROM escorts)";
    $order = "ORDER BY id DESC";
} elseif ($section === 'vip') {
    $where[] = "type = 'acompanhante'";
    $order = "ORDER BY name ASC";
} else {
    $order = "ORDER BY name ASC";
}

$where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';
$query = "SELECT id, name, profile_photo, phone, physical_traits, type, is_online 
          FROM escorts 
          $where_clause 
          $order 
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$profiles = [];
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
while ($row = $result->fetch_assoc()) {
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
        $promo = $promo_phrases[$row['type']];
    }
    $profiles[] = [
        'id' => $row['id'],
        'name' => htmlspecialchars($row['name']),
        'profile_photo' => $row['profile_photo'],
        'phone' => $row['phone'],
        'promo' => $promo,
        'is_online' => $row['is_online']
    ];
}

$total_query = "SELECT COUNT(*) as total FROM escorts $where_clause";
$stmt_total = $conn->prepare($total_query);
if ($types) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total = $stmt_total->get_result()->fetch_assoc()['total'];
$hasMore = ($offset + $limit) < $total;

echo json_encode(['status' => 'success', 'profiles' => $profiles, 'hasMore' => $hasMore]);
$conn->close();
?>