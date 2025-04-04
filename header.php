<?php
require_once 'session.php'; // Centraliza a sessão
require_once 'config.php';
?>
<header class="top-bar">
    <div class="top-left">
        <h2>Eskort</h2>
    </div>
    <div class="top-center">
        <form action="index.php" method="GET" class="search-form">
            <label for="search-input" class="sr-only">Pesquisar acompanhantes</label>
            <input type="text" id="search-input" name="search" placeholder="Pesquisar acompanhantes..." aria-label="Pesquisar acompanhantes no Eskort" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit" class="btn" aria-label="Executar pesquisa">🔍 Pesquisar</button>
        </form>
    </div>
    <nav class="top-right">
        <a href="index.php">Home</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <a href="logout.php">Sair</a>
    </nav>
</header>