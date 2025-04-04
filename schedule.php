<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['client', 'escort'])) {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();
$user_id = (int)$_SESSION['user_id'];
$escort_id = isset($_GET['escort_id']) ? (int)$_GET['escort_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'client') {
    $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
    $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
    if ($start_time && $end_time) {
        $stmt = $conn->prepare("INSERT INTO schedules (escort_id, client_id, start_time, end_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $escort_id, $user_id, $start_time, $end_time);
        $stmt->execute();
    }
}

$schedules = $conn->query("SELECT s.id, s.start_time, s.end_time, s.status, u.username as client 
                           FROM schedules s 
                           LEFT JOIN users u ON s.client_id = u.id 
                           WHERE s.escort_id = $escort_id 
                           ORDER BY s.start_time DESC 
                           LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="main-content">
            <h2>Agendamento</h2>
            <?php if ($_SESSION['role'] === 'client'): ?>
                <form method="POST" class="admin-form">
                    <div class="form-group">
                        <label for="start_time">Início:</label>
                        <input type="datetime-local" id="start_time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">Fim:</label>
                        <input type="datetime-local" id="end_time" name="end_time" required>
                    </div>
                    <button type="submit" class="load-more">Agendar</button>
                </form>
            <?php endif; ?>
            <h3>Agendamentos</h3>
            <?php if (empty($schedules)): ?>
                <p>Sem agendamentos.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Cliente</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo $schedule['start_time']; ?></td>
                                <td><?php echo $schedule['end_time']; ?></td>
                                <td><?php echo htmlspecialchars($schedule['client'] ?? 'Anônimo'); ?></td>
                                <td><?php echo $schedule['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <button class="back-to-top" onclick="scrollToTop()">↑</button>

    <script>
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const backToTop = document.querySelector('.back-to-top');
            window.addEventListener('scroll', () => {
                backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>