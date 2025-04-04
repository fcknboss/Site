<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = (int)$_POST['receiver_id'];
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    if ($receiver_id && $message) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $_SESSION['user_id'], $receiver_id, $message);
        $stmt->execute();
    }
}

$users = $conn->query("SELECT id, username FROM users WHERE role IN ('escort', 'admin') AND id != " . (int)$_SESSION['user_id'] . " ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$messages = $conn->query("SELECT m.id, m.message, m.sent_at, m.is_read, u.username as sender 
                          FROM messages m 
                          JOIN users u ON m.sender_id = u.id 
                          WHERE m.receiver_id = " . (int)$_SESSION['user_id'] . " 
                          ORDER BY m.sent_at DESC 
                          LIMIT 50")->fetch_all(MYSQLI_ASSOC);

// Marcar mensagens como lidas
$conn->query("UPDATE messages SET is_read = 1 WHERE receiver_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens - Eskort</title>
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
            <h2>Mensagens</h2>
            <form method="POST" class="post-form">
                <select name="receiver_id" required>
                    <option value="">Enviar para...</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="message" placeholder="Digite sua mensagem..." required></textarea>
                <button type="submit" class="load-more">Enviar</button>
            </form>
            <div class="feed-container">
                <?php foreach ($messages as $msg): ?>
                    <div class="feed-post">
                        <div class="feed-post-header">
                            <h4><?php echo htmlspecialchars($msg['sender']); ?></h4>
                            <small><?php echo $msg['sent_at']; ?></small>
                        </div>
                        <p><?php echo htmlspecialchars($msg['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>