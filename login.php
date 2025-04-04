<?php
session_start();
require_once 'config.php'; // Inclui config.php com logDBAction()

// Conexão com o banco
$conn = getDBConnection();

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    // Prepara a consulta para buscar o usuário
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    if (!$stmt) {
        logError("Erro ao preparar a consulta: " . $conn->error);
        die("Erro ao preparar a consulta. Veja o log para detalhes.");
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verifica se o usuário existe e a senha está correta
    if ($user && password_verify($password, $user['password'])) {
        // Inicia a sessão com os dados do usuário
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Registra o login bem-sucedido no db_log
        logDBAction("LOGIN", "users", $user['id'], "Login bem-sucedido");

        // Redireciona com base no papel do usuário
        if ($user['role'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Usuário ou senha inválidos.";
        // Registra tentativa de login falha no db_log
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
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
        <p>Não tem conta? <a href="register.php">Cadastre-se</a></p>
    </div>
</body>
</html>
<?php $conn->close(); ?>