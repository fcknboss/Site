<?php
// header.php
session_start();
?>
<header class="top-bar">
    <div class="top-left">
        <h2>Eskort</h2>
    </div>
    <div class="top-center">
        <input type="text" placeholder="Pesquisar acompanhantes..." aria-label="Pesquisar">
        <button type="button">Pesquisar</button>
    </div>
    <nav class="top-right">
        <a href="index.php">Home</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <a href="logout.php">Sair</a>
    </nav>
</header>