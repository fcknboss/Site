<?php
session_start();
require_once 'config.php'; // Garantir que config.php seja incluído

// Conexão com o banco
$conn = getDBConnection(); // Inicializa a conexão e seleciona o banco eskort

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password']; // Senha não precisa de sanitização aqui, será verificada com password_verify

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

        // Registra o login bem-sucedido no log do banco
        $details = json_encode(['username' => $username, 'role' => $user['role']]);
        logDBAction("LOGIN_SUCCESS", "users", $user['id'], $details);

        // Redireciona com base no papel do usuário
        if ($user['role'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        // Registra tentativa de login falhada
        $details = json_encode(['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
        logDBAction("LOGIN_FAIL", "users", null, $details);
        $error = "Usuário ou senha inválidos.";
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