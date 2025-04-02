<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'client'; // Padrão para novos usuários

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $error = "Usuário ou e-mail já existe.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $password, $email, $role);
        $stmt->execute();
        header("Location: login.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Eskort</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Registro</h1>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post">
            <label>Usuário:</label>
            <input type="text" name="username" required>
            <label>E-mail:</label>
            <input type="email" name="email" required>
            <label>Senha:</label>
            <input type="password" name="password" required>
            <button type="submit">Registrar</button>
            <p>Já tem conta? <a href="login.php">Faça login</a></p>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>