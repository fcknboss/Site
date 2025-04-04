<?php
require_once 'session.php';
require_once 'config.php';

logTask("UPDATE", "Corrigir erro 'Unknown column e.online' para e.is_online em index.php");

$conn = getDBConnection();
$search = sanitize($_GET['search'] ?? '');
$where = $search ? "WHERE name LIKE '%$search%'" : '';
$escorts = $conn->query("SELECT id, name, type, is_online, profile_photo FROM escorts $where LIMIT 20")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskort - Home</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="main-content">
            <h2>Perfis</h2>
            <?php foreach ($escorts as $e): ?>
                <div>
                    <img src="<?php echo htmlspecialchars($e['profile_photo'] ?? 'uploads/default.jpg'); ?>" alt="Foto" style="max-width: 100px;">
                    <p><?php echo htmlspecialchars($e['name'] ?? 'Sem nome'); ?> - <?php echo htmlspecialchars($e['type'] ?? 'N/A'); ?> (<?php echo $e['is_online'] ? 'Online' : 'Offline'; ?>)</p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>