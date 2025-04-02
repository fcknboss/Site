<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT id, role, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['last_activity'] = time(); // Inicia a sessão
            header("Location: index.php");
            exit;
        } else {
            $error = "Usuário ou senha inválidos.";
        }
    } else {
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php elseif (isset($_GET['timeout'])): ?>
            <p style="color: red;">Sessão expirada. Faça login novamente.</p>
        <?php endif; ?>
        <form method="post">
            <label>Usuário:</label>
            <input type="text" name="username" required>
            <label>Senha:</label>
            <input type="password" name="password" required>
            <button type="submit">Entrar</button>
            <p>Não tem conta? <a href="register.php">Registre-se</a></p>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>