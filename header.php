<?php
require_once 'session.php';
require_once 'config.php';

// Define o nome do usuário ou "Visitante" se não logado
$user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Visitante';
$role = $_SESSION['role'] ?? null;
?>

<header class="top-bar">
    <div class="top-left">
        <h2>Eskort</h2>
    </div>
    <div class="top-center">
        <form action="index.php" method="GET" class="search-form">
            <input type="text" id="search-input" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Pesquisar acompanhantes..." aria-label="Pesquisar">
            <button type="submit" class="btn" aria-label="Buscar">🔍</button>
        </form>
    </div>
    <nav class="top-right">
        <span><?php echo $user; ?></span>
        <a href="index.php">Home</a>
        <?php if ($role === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <a href="logout.php">Sair</a>
    </nav>
</header>