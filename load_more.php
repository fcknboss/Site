<?php
require_once 'config.php';

$conn = getDBConnection();

$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$order = in_array($_GET['order'] ?? '', ['views', 'name', 'distance']) ? $_GET['order'] : 'views';
$age_min = isset($_GET['age_min']) ? (int)$_GET['age_min'] : 0;
$views_min = isset($_GET['views_min']) ? (int)$_GET['views_min'] : 0;
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : 0;

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
if ($category > 0) {
    $where[] = "ec.category_id = ?";
    $params[] = $category;
    $types .= 'i';
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
$base_where_clause = $where ? "WHERE " . implode(' AND ', $where) : '';

$order_clause = $order === 'distance' && $lat && $lon 
    ? "ORDER BY (6371 * acos(cos(radians(?)) * cos(radians(e.latitude)) * cos(radians(e.longitude) - radians(?)) + sin(radians(?)) * sin(radians(e.latitude)))) ASC"
    : "ORDER BY e.$order DESC, e.id DESC";
if ($order === 'distance' && $lat && $lon) {
    $params = array_merge([$lat, $lon, $lat], $params);
    $types = 'ddd' . $types;
}

$query = "SELECT e.id, e.name, e.profile_photo, e.description, e.type, e.is_online, e.views, e.tags, e.latitude, e.longitude, 
                 (SELECT GROUP_CONCAT(photo_path) FROM photos p WHERE p.escort_id = e.id LIMIT 2) as additional_photos 
          FROM escorts e 
          LEFT JOIN escort_categories ec ON e.id = ec.escort_id 
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

$profiles = [];
while ($row = $result->fetch_assoc()) {
    $distance = $lat && $lon ? round(6371 * acos(cos(deg2rad($lat)) * cos(deg2rad($row['latitude'])) * cos(deg2rad($row['longitude']) - deg2rad($lon)) + sin(deg2rad($lat)) * sin(deg2rad($row['latitude']))), 2) : null;
    $profiles[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'profile_photo' => $row['profile_photo'],
        'description' => $row['description'],
        'type' => $row['type'],
        'is_online' => $row['is_online'],
        'views' => $row['views'],
        'tags' => $row['tags'],
        'additional_photos' => $row['additional_photos'],
        'distance' => $distance
    ];
}

header('Content-Type: application/json');
echo json_encode($profiles);
$conn->close();
?>