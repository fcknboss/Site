<?php
require_once 'config.php';

$conn = getDBConnection();
$result = $conn->query("SELECT tags FROM escorts WHERE tags IS NOT NULL");

$tag_counts = [];
while ($row = $result->fetch_assoc()) {
    $tags = array_map('trim', explode(',', strtolower($row['tags'])));
    foreach ($tags as $tag) {
        if (!empty($tag)) {
            $tag_counts[$tag] = ($tag_counts[$tag] ?? 0) + 1;
        }
    }
}

arsort($tag_counts);
$top_tags = array_slice($tag_counts, 0, 10, true);

header('Content-Type: application/json');
echo json_encode($top_tags);
$conn->close();
?>