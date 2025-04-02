<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM escorts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$escort = $stmt->get_result()->fetch_assoc();
if (!$escort) {
    die("Acompanhante não encontrada.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $age = (int)$_POST['age'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $physical_traits = $_POST['physical_traits'];
    $services = $_POST['services'];
    $rates = $_POST['rates'];
    $availability = $_POST['availability'];
    $attendance = $_POST['attendance'];
    $payment = $_POST['payment'];
    $type = $_POST['type'];

    $profile_photo_path = $escort['profile_photo'];
    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "uploads/";
        $profile_photo_name = basename($_FILES["profile_photo"]["name"]);
        $profile_photo_path = $target_dir . $profile_photo_name;
        move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $profile_photo_path);
    }

    $stmt = $conn->prepare("UPDATE escorts SET name = ?, age = ?, location = ?, description = ?, physical_traits = ?, services = ?, rates = ?, availability = ?, profile_photo = ?, type = ? WHERE id = ?");
    $stmt->bind_param("sissssssssi", $name, $age, $location, $description, $physical_traits, $services, $rates, $availability, $profile_photo_path, $type, $id);
    $stmt->execute();

    if (!empty($_FILES['additional_photos']['name'][0])) {
        $stmt = $conn->prepare("DELETE FROM photos WHERE escort_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $photo_count = count($_FILES['additional_photos']['name']);
        for ($i = 0; $i < min($photo_count, 5); $i++) {
            $photo_name = basename($_FILES['additional_photos']['name'][$i]);
            $photo_path = $target_dir . $photo_name;
            if (move_uploaded_file($_FILES['additional_photos']['tmp_name'][$i], $photo_path)) {
                $stmt = $conn->prepare("INSERT INTO photos (escort_id, photo_path) VALUES (?, ?)");
                $stmt->bind_param("is", $id, $photo_path);
                $stmt->execute();
            }
        }
    }

    header("Location: profile.php?id=$id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar - <?php echo $escort['name']; ?> - Eskort</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-center">
            <input type="text" placeholder="Pesquisar acompanhantes...">
            <button>Pesquisar</button>
        </div>
        <div class="top-right">
            <a href="index.php">Home</a>
            <a href="admin.php">Admin</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="admin.php">Cadastrar Acompanhante</a></li>
                <li><a href="#">Gerenciar Perfis</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>Editar Acompanhante: <?php echo $escort['name']; ?></h1>
            <form action="edit_escort.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data" class="admin-form" id="admin-form">
                <label>Nome:</label>
                <input type="text" name="name" value="<?php echo $escort['name']; ?>" required>
                <label>Idade:</label>
                <input type="number" name="age" value="<?php echo $escort['age']; ?>" required>
                <label>Localização:</label>
                <input type="text" name="location" value="<?php echo $escort['location']; ?>" required>
                <label>Descrição:</label>
                <textarea name="description" required><?php echo $escort['description']; ?></textarea>
                <label>Características Físicas (separadas por vírgula):</label>
                <input type="text" name="physical_traits" value="<?php echo $escort['physical_traits']; ?>" placeholder="Ex: loira, alta, olhos verdes">
                <label>Serviços (O que ofereço):</label>
                <input type="text" name="services" value="<?php echo $escort['services']; ?>" required>
                <label>Disponibilidade:</label>
                <input type="text" name="availability" value="<?php echo $escort['availability']; ?>" required>
                <label>Atendimento:</label>
                <input type="text" name="attendance" value="Com Local, Hotéis e Motéis" required>
                <label>Pagamento:</label>
                <input type="text" name="payment" value="Dinheiro, Cartão" required>
                <label>Tipo:</label>
                <select name="type" required>
                    <option value="acompanhante" <?php if ($escort['type'] === 'acompanhante') echo 'selected'; ?>>Acompanhante</option>
                    <option value="criadora" <?php if ($escort['type'] === 'criadora') echo 'selected'; ?>>Criadora de Conteúdo</option>
                </select>
                <label>Foto de Perfil (deixe em branco para manter atual):</label>
                <input type="file" name="profile_photo" id="profile-photo" accept="image/*" onchange="previewProfilePhoto(event)">
                <div id="profile-preview" class="photo-preview"><img src="<?php echo $escort['profile_photo']; ?>" style="max-width: 200px; max-height: 250px;"></div>
                <label>Fotos Adicionais (máximo 5, substitui as atuais):</label>
                <input type="file" name="additional_photos[]" id="additional-photos" accept="image/*" multiple onchange="previewAdditionalPhotos(event)">
                <div id="additional-preview" class="photo-preview">
                    <?php
                    $stmt = $conn->prepare("SELECT photo_path FROM photos WHERE escort_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $photos = $stmt->get_result();
                    while ($photo = $photos->fetch_assoc()) {
                        echo "<img src='" . $photo['photo_path'] . "' style='max-width: 150px; max-height: 200px; margin: 5px;'>";
                    }
                    ?>
                </div>
                <button type="submit">Salvar Alterações</button>
            </form>
        </div>
        <div class="right-sidebar">
            <h3>Dicas</h3>
            <p>Atualize os campos conforme necessário.</p>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>
<?php $conn->close(); ?>