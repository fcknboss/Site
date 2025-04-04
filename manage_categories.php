<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                $message = "Categoria '$name' adicionada com sucesso!";
            } else {
                $error = "Erro ao adicionar categoria: " . $conn->error;
            }
        } else {
            $error = "Nome da categoria é obrigatório.";
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        if (!empty($name)) {
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
            if ($stmt->execute()) {
                $message = "Categoria '$name' atualizada com sucesso!";
            } else {
                $error = "Erro ao atualizar categoria: " . $conn->error;
            }
        } else {
            $error = "Nome da categoria é obrigatório.";
        }
    } elseif (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Categoria excluída com sucesso!";
        } else {
            $error = "Erro ao excluir categoria: " . $conn->error;
        }
    }
}

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-right">
            <a href="admin.php">Voltar ao Painel</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <h2>Gerenciar Categorias</h2>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($message): ?>
                <p class="success"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <form method="POST" class="admin-form">
                <div class="form-group">
                    <label for="name">Nova Categoria:</label>
                    <input type="text" id="name" name="name" required>
                    <button type="submit" name="add" class="load-more">Adicionar</button>
                </div>
            </form>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" required>
                                    <button type="submit" name="edit" class="edit-btn">Salvar</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" name="delete" class="delete-btn" onclick="return confirm('Tem certeza que deseja excluir esta categoria?');">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>