<?php
// header.php
session_start();

// Garante que $_SESSION['role'] esteja definido, default para vazio se não estiver
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Visitante';
?>

<header class="top-bar">
    <div class="top-left">
        <h2>
            <a href="index.php" style="color: #E95B95; text-decoration: none;">
                Eskort <?php echo $role === 'admin' ? '(Admin)' : ($role === 'escort' ? '(Escort)' : ''); ?>
            </a>
        </h2>
    </div>
    <div class="top-center">
        <form action="index.php" method="GET" class="search-form">
            <label for="search-input" class="sr-only">Pesquisar acompanhantes</label>
            <input 
                type="text" 
                id="search-input" 
                name="search" 
                placeholder="Pesquisar acompanhantes..." 
                aria-label="Pesquisar acompanhantes no Eskort" 
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >
            <button type="submit" class="btn" aria-label="Executar pesquisa">🔍 Pesquisar</button>
        </form>
    </div>
    <nav class="top-right">
        <a href="index.php">Home</a>
        <?php if ($role === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php elseif ($role === 'escort'): ?>
            <a href="escort_dashboard.php">Dashboard</a>
        <?php endif; ?>
        <?php if ($role): ?>
            <span>Bem-vindo, <?php echo $username; ?></span>
            <a href="logout.php">Sair</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<style>
/* Estilos locais para ajustes finos, complementando style.css */
.search-form {
    display: flex;
    gap: 0.625rem;
    align-items: center;
    width: 100%;
}

.search-form input[type="text"] {
    flex-grow: 1;
    min-width: 0; /* Evita overflow em telas pequenas */
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}

/* Ajustes para responsividade já estão no style.css, mas reforço aqui */
@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
    }
    .search-form button {
        width: 100%;
    }
}
</style>