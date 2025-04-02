<?php 
include 'check_session.php';
include 'config.php';
?>
<?php 
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="pt-BR">
<!-- Restante do código igual ao anterior -->


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskort - Rede de Acompanhantes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort</h2>
        </div>
        <div class="top-center">
            <input type="text" placeholder="Pesquisar acompanhantes...">
            <button>Pesquisar</button>
        </div>
        <div class="top-right">
            <a href="profile.php">Perfil</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <!-- Restante do código igual ao anterior -->
    <!-- ... (mantém profiles-section, post-form, feed, right-sidebar) ... -->
</body>
</html>
<?php $conn->close(); ?>