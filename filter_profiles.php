<?php
include 'config.php';

$location = isset($_GET['location']) ? $_GET['location'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

$sql = "SELECT id, name, profile_photo FROM escorts WHERE 1=1";
$params = [];
$types = '';

if ($location) {
    $sql .= " AND location LIKE ?";
    $params[] = "%$location%";
    $types .= 's';
}
if ($type) {
    $sql .= " AND type = ?";
    $params[] = $type;
    $types .= 's';
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$profiles = [];
while ($row = $result->fetch_assoc()) {
    $profiles[] = $row;
}

echo json_encode($profiles);
$conn->close();
?>