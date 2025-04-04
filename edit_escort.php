<?php
require_once 'session.php';
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();
$id = (int)($_GET['id'] ?? 0);
$escort = $id > 0 ? $conn->query("SELECT e.*, u.username, u.email FROM escorts e JOIN users u ON e.user_id = u.id WHERE e.id = $id")->fetch_assoc() : null;
$error = '';
$target_dir = 'uploads/';
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'user_id' => (int)$_POST['user_id'],
        'name' => sanitize($_POST['name']),
        'age' => (int)$_POST['age'],
        'description' => sanitize($_POST['description'] ?? ''),
        'services' => sanitize($_POST['services'] ?? ''),
        'rates' => sanitize($_POST['rates'] ?? ''),
        'availability' => sanitize($_POST['availability'] ?? ''),
        'type' => in_array($_POST['type'], ['acompanhante', 'criadora']) ? $_POST['type'] : 'acompanhante',
        'is_online' => isset($_POST['is_online']) ? 1 : 0,
        'physical_traits' => sanitize($_POST['physical_traits'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'height' => (float)$_POST['height'] ?? 0,
        'weight' => (int)$_POST['weight'] ?? 0,
        'languages' => sanitize($_POST['languages'] ?? ''),
        'views' => (int)$_POST['views'] ?? 0,
        'latitude' => (float)$_POST['latitude'] ?? 0,
        'longitude' => (float)$_POST['longitude'] ?? 0,
        'tags' => sanitize($_POST['tags'] ?? ''),
        'profile_photo' => $escort ? $escort['profile_photo'] : 'uploads/default.jpg',
        'video_url' => $escort ? $escort['video_url'] : '',
        'video_thumbnail' => $escort ? $escort['video_thumbnail'] : ''
    ];

    if (empty($data['name'])) $error = "Nome é obrigatório.";
    elseif ($data['age'] < 18) $error = "Idade mínima é 18 anos.";
    elseif ($data['phone'] && !preg_match('/^\+?\d{10,15}$/', $data['phone'])) $error = "Telefone inválido (ex.: +5511998765432).";
    elseif ($data['height'] < 0 || $data['weight'] < 0) $error = "Altura e peso não podem ser negativos.";
    else {
        if (!empty($_FILES['profile_photo']['name'])) {
            $photo_name = time() . '_' . basename($_FILES['profile_photo']['name']);
            $photo_file = $target_dir . $photo_name;
            if (getimagesize($_FILES['profile_photo']['tmp_name']) && $_FILES['profile_photo']['size'] <= 5 * 1024 * 1024) {
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $photo_file)) {
                    $data['profile_photo'] = $photo_file;
                } else {
                    $error = "Erro ao fazer upload da foto.";
                    logError("Falha ao mover foto de perfil: " . $_FILES['profile_photo']['name']);
                }
            } else {
                $error = "Foto inválida ou muito grande (máx. 5MB).";
                logError("Foto de perfil inválida: " . $_FILES['profile_photo']['name']);
            }
        }

        if (!$error && !empty($_FILES['video']['name'])) {
            $video_name = time() . '_' . basename($_FILES['video']['name']);
            $video_file = $target_dir . $video_name;
            $video_type = mime_content_type($_FILES['video']['tmp_name']);
            if (in_array($video_type, ['video/mp4', 'video/webm']) && $_FILES['video']['size'] <= 50 * 1024 * 1024) {
                if (move_uploaded_file($_FILES['video']['tmp_name'], $video_file)) {
                    $data['video_url'] = $video_file;
                    $thumbnail_file = $target_dir . time() . '_thumb.jpg';
                    exec("ffmpeg -i $video_file -ss 00:00:01 -frames:v 1 $thumbnail_file 2>&1", $output, $return);
                    if ($return === 0 && file_exists($thumbnail_file)) {
                        $data['video_thumbnail'] = $thumbnail_file;
                    } else {
                        logError("Falha ao gerar thumbnail do vídeo: " . implode("\n", $output));
                    }
                } else {
                    $error = "Erro ao fazer upload do vídeo.";
                    logError("Falha ao mover vídeo: " . $_FILES['video']['name']);
                }
            } else {
                $error = "Vídeo inválido ou muito grande (máx. 50MB, MP4/WebM).";
                logError("Vídeo inválido: " . $_FILES['video']['name']);
            }
        }

        if (!$error) {
            $query = $id > 0 
                ? "UPDATE escorts SET user_id=?, name=?, age=?, description=?, services=?, rates=?, availability=?, type=?, is_online=?, physical_traits=?, phone=?, height=?, weight=?, languages=?, views=?, latitude=?, longitude=?, tags=?, profile_photo=?, video_url=?, video_thumbnail=? WHERE id=?"
                : "INSERT INTO escorts (user_id, name, age, description, services, rates, availability, type, is_online, physical_traits, phone, height, weight, languages, views, latitude, longitude, tags, profile_photo, video_url, video_thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isisssssisssididdssss" . ($id > 0 ? 'i' : ''), 
                $data['user_id'], $data['name'], $data['age'], $data['description'], $data['services'], $data['rates'], $data['availability'], 
                $data['type'], $data['is_online'], $data['physical_traits'], $data['phone'], $data['height'], $data['weight'], $data['languages'], 
                $data['views'], $data['latitude'], $data['longitude'], $data['tags'], $data['profile_photo'], $data['video_url'], $data['video_thumbnail'], 
                $id > 0 ? $id : null);
            $action = $id > 0 ? 'UPDATE' : 'INSERT';

            if ($stmt->execute()) {
                $escort_id = $id > 0 ? $id : $stmt->insert_id;
                $details = json_encode(['name' => $data['name'], 'type' => $data['type']]);
                logDBAction($action, 'escorts', $escort_id, $details);

                $to = $escort ? $escort['email'] : $conn->query("SELECT email FROM users WHERE id = {$data['user_id']}")->fetch_assoc()['email'];
                $subject = $id > 0 ? "Perfil Atualizado - Eskort" : "Novo Perfil Criado - Eskort";
                $message = "Olá,\n\nSeu perfil foi " . ($id > 0 ? "atualizado" : "criado") . ".\nNome: {$data['name']}\nTipo: {$data['type']}\nDisponibilidade: {$data['availability']}\n\nAcesse o painel para mais detalhes.\n\nEquipe Eskort";
                mail($to, $subject, $message, "From: no-reply@eskort.com") or logError("Falha ao enviar e-mail para $to");

                $notification = json_encode(['type' => 'edit_escort', 'message' => "Perfil $action: {$data['name']} por " . $_SESSION['username']]);
                if (@file_get_contents("http://localhost:8080?msg=" . urlencode($notification)) === false) {
                    logError("Falha ao enviar notificação WebSocket: " . error_get_last()['message']);
                }
                header("Location: admin.php#escorts");
                exit;
            } else {
                logError("Erro ao salvar perfil: " . $conn->error);
                $error = "Erro ao salvar. Veja o log.";
            }
        }
    }
}

$users = $conn->query("SELECT id, username FROM users WHERE role IN ('escort', 'admin') ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$photos = $id > 0 ? $conn->query("SELECT id, photo_path, is_highlighted FROM photos WHERE escort_id = $id")->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id > 0 ? 'Editar' : 'Adicionar'; ?> Perfil - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        .form-group img, .form-group video { max-width: 200px; margin-top: 10px; }
        .error { color: #DC3545; font-weight: 600; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="main-content">
            <h2><?php echo $id > 0 ? 'Editar' : 'Adicionar'; ?> Perfil</h2>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form action="edit_escort.php<?php echo $id ? "?id=$id" : ''; ?>" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Usuário: <select name="user_id" required>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $escort && $escort['user_id'] == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                </div>
                <div class="form-group">
                    <label>Nome: <input type="text" name="name" value="<?php echo $escort ? htmlspecialchars($escort['name']) : ''; ?>" required></label>
                </div>
                <div class="form-group">
                    <label>Idade: <input type="number" name="age" value="<?php echo $escort ? $escort['age'] : ''; ?>" min="18" required></label>
                </div>
                <div class="form-group">
                    <label>Descrição: <textarea name="description"><?php echo $escort ? htmlspecialchars($escort['description']) : ''; ?></textarea></label>
                </div>
                <div class="form-group">
                    <label>Serviços: <textarea name="services"><?php echo $escort ? htmlspecialchars($escort['services']) : ''; ?></textarea></label>
                </div>
                <div class="form-group">
                    <label>Tarifas: <input type="text" name="rates" value="<?php echo $escort ? htmlspecialchars($escort['rates']) : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Disponibilidade: <input type="text" name="availability" value="<?php echo $escort ? htmlspecialchars($escort['availability']) : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Foto de Perfil: <input type="file" name="profile_photo" accept="image/*" onchange="preview(this, 'profile-preview')"></label>
                    <img id="profile-preview" src="<?php echo $escort ? htmlspecialchars($escort['profile_photo']) : 'uploads/default.jpg'; ?>" alt="Prévia">
                </div>
                <div class="form-group">
                    <label>Vídeo: <input type="file" name="video" accept="video/mp4,video/webm" onchange="preview(this, 'video-preview')"></label>
                    <?php if ($escort && $escort['video_url']): ?>
                        <video id="video-preview" controls poster="<?php echo htmlspecialchars($escort['video_thumbnail'] ?? ''); ?>">
                            <source src="<?php echo htmlspecialchars($escort['video_url']); ?>" type="video/mp4">
                        </video>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Tipo: <select name="type">
                        <option value="acompanhante" <?php echo $escort && $escort['type'] === 'acompanhante' ? 'selected' : ''; ?>>Acompanhante</option>
                        <option value="criadora" <?php echo $escort && $escort['type'] === 'criadora' ? 'selected' : ''; ?>>Pornstar</option>
                    </select></label>
                </div>
                <div class="form-group">
                    <label>Online: <input type="checkbox" name="is_online" <?php echo $escort && $escort['is_online'] ? 'checked' : ''; ?>></label>
                </div>
                <div class="form-group">
                    <label>Características Físicas: <input type="text" name="physical_traits" value="<?php echo $escort ? htmlspecialchars($escort['physical_traits']) : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Telefone: <input type="text" name="phone" value="<?php echo $escort ? htmlspecialchars($escort['phone']) : ''; ?>" placeholder="+5511998765432"></label>
                </div>
                <div class="form-group">
                    <label>Altura (m): <input type="number" step="0.01" name="height" value="<?php echo $escort ? $escort['height'] : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Peso (kg): <input type="number" name="weight" value="<?php echo $escort ? $escort['weight'] : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Idiomas: <input type="text" name="languages" value="<?php echo $escort ? htmlspecialchars($escort['languages']) : ''; ?>" placeholder="Ex: Português, Inglês"></label>
                </div>
                <div class="form-group">
                    <label>Visualizações: <input type="number" name="views" value="<?php echo $escort ? $escort['views'] : 0; ?>" min="0"></label>
                </div>
                <div class="form-group">
                    <label>Latitude: <input type="number" step="0.00000001" name="latitude" value="<?php echo $escort ? $escort['latitude'] : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Longitude: <input type="number" step="0.00000001" name="longitude" value="<?php echo $escort ? $escort['longitude'] : ''; ?>"></label>
                </div>
                <div class="form-group">
                    <label>Tags: <input type="text" name="tags" value="<?php echo $escort ? htmlspecialchars($escort['tags']) : ''; ?>" placeholder="Ex: loira, eventos"></label>
                </div>
                <button type="submit" class="btn">Salvar</button>
            </form>
            <?php if ($photos): ?>
                <h3>Fotos Existentes</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                    <?php foreach ($photos as $p): ?>
                        <div>
                            <img src="<?php echo htmlspecialchars($p['photo_path']); ?>" alt="Foto" style="max-width: 100px;">
                            <p><?php echo $p['is_highlighted'] ? 'Destaque' : ''; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function preview(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const el = document.getElementById(previewId) || (input.name === 'video' ? document.createElement('video') : document.createElement('img'));
                    if (input.name === 'video') {
                        el.controls = true;
                        el.innerHTML = `<source src="${e.target.result}" type="${input.files[0].type}">`;
                    } else {
                        el.src = e.target.result;
                    }
                    if (!document.getElementById(previewId)) input.parentNode.appendChild(el);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        const socket = new WebSocket('ws://localhost:8080');
        socket.onopen = () => console.log('WebSocket OK');
        socket.onmessage = e => Toastify({ text: JSON.parse(e.data).message, duration: 3000, style: { background: '#28A745' } }).showToast();
    </script>
</body>
</html>
<?php $conn->close(); ?>