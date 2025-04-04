<?php
session_start();
require_once 'config.php';

logTask("UPDATE", "Substituir FILTER_SANITIZE_STRING por sanitize() em login.php");

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'];
    // ... resto do código permanece igual ...

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    if (!$stmt) {
        logError("Erro ao preparar a consulta: " . $conn->error);
        die("Erro ao preparar a consulta. Veja o log para detalhes.");
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        logDBAction("LOGIN", "users", $user['id'], "Login bem-sucedido");
        if ($user['role'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Usuário ou senha inválidos.";
        logDBAction("LOGIN_ATTEMPT", "users", null, "Tentativa de login falha para username: '$username'");
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <p class="error" role="alert"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post" action="login.php" role="form" aria-labelledby="login-title">
            <h2 id="login-title" style="display: none;">Formulário de Login</h2> <!-- Título oculto para acessibilidade -->
            <div class="form-group">
                <label for="username" id="username-label">Usuário:</label>
                <input type="text" id="username" name="username" required aria-required="true" aria-labelledby="username-label">
            </div>
            <div class="form-group">
                <label for="password" id="password-label">Senha:</label>
                <input type="password" id="password" name="password" required aria-required="true" aria-labelledby="password-label">
            </div>
            <button type="submit" aria-label="Entrar no sistema">Entrar</button>
        </form>
        <p>Não tem conta? <a href="register.php" aria-label="Ir para página de cadastro">Cadastre-se</a></p>
    </div>
</body>
</html>
<?php $conn->close(); ?>