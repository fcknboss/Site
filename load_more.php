<?php
require_once 'config.php';

$conn = getDBConnection();

$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
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

$profiles = [];
while ($row = $result->fetch_assoc()) {
    $profiles[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'profile_photo' => $row['profile_photo'],
        'description' => $row['description'],
        'type' => $row['type'],
        'is_online' => $row['is_online'],
        'views' => $row['views'],
        'additional_photos' => $row['additional_photos']
    ];
}

header('Content-Type: application/json');
echo json_encode($profiles);
$conn->close();
?>