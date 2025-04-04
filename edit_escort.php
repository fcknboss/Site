<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = getDBConnection();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$escort = null;
$error = '';
$suggested_tags = [];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT e.*, u.username FROM escorts e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $escort = $stmt->get_result()->fetch_assoc();
    if (!$escort) {
        die("Acompanhante não encontrada.");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $data = [
        'user_id' => $user_id,
        'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING),
        'age' => (int)$_POST['age'],
        'description' => filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING),
        'services' => filter_input(INPUT_POST, 'services', FILTER_SANITIZE_STRING),
        'rates' => filter_input(INPUT_POST, 'rates', FILTER_SANITIZE_STRING),
        'availability' => filter_input(INPUT_POST, 'availability', FILTER_SANITIZE_STRING),
        'profile_photo' => $escort ? $escort['profile_photo'] : 'uploads/default.jpg',
        'type' => in_array($_POST['type'], ['acompanhante', 'criadora']) ? $_POST['type'] : 'acompanhante',
        'is_online' => isset($_POST['is_online']) ? 1 : 0,
        'physical_traits' => filter_input(INPUT_POST, 'physical_traits', FILTER_SANITIZE_STRING),
        'phone' => filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING),
        'height' => (float)$_POST['height'] ?? 0,
        'weight' => (int)$_POST['weight'] ?? 0,
        'languages' => filter_input(INPUT_POST, 'languages', FILTER_SANITIZE_STRING),
        'views' => (int)$_POST['views'] ?? 0,
        'latitude' => (float)$_POST['latitude'] ?? 0,
        'longitude' => (float)$_POST['longitude'] ?? 0,
        'tags' => filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_STRING)
    ];

    if (empty($data['name'])) {
        $error = "Nome é obrigatório.";
    } elseif ($data['age'] < 18) {
        $error = "Idade mínima é 18 anos.";
    } elseif (!empty($data['phone']) && !preg_match('/^\+?\d{10,15}$/', $data['phone'])) {
        $error = "Formato de telefone inválido. Use: +5511998765432";
    } elseif ($data['height'] < 0 || $data['weight'] < 0) {
        $error = "Altura e peso não podem ser negativos.";
    } else {
        if (!empty($_FILES['profile_photo']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $profile_photo_name = time() . '_' . basename($_FILES["profile_photo"]["name"]);
            $target_file = $target_dir . $profile_photo_name;
            $image_info = getimagesize($_FILES["profile_photo"]["tmp_name"]);
            if ($image_info && $_FILES["profile_photo"]["size"] <= 5 * 1024 * 1024) { // Max 5MB
                if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                    $data['profile_photo'] = $target_file;
                } else {
                    $error = "Erro ao fazer upload da foto de perfil.";
                }
            } else {
                $error = "Foto de perfil inválida ou muito grande (máx. 5MB).";
            }
        }

        if (!empty($_FILES['additional_photos']['name'][0]) && !$error) {
            $photo_ids = [];
            foreach ($_FILES['additional_photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['additional_photos']['size'][$key] > 0) {
                    $photo_name = time() . '_' . basename($_FILES["additional_photos"]["name"][$key]);
                    $photo_file = $target_dir . $photo_name;
                    $image_info = getimagesize($tmp_name);
                    if ($image_info && $_FILES["additional_photos"]["size"][$key] <= 5 * 1024 * 1024) {
                        if (move_uploaded_file($tmp_name, $photo_file)) {
                            $stmt = $conn->prepare("INSERT INTO photos (escort_id, photo_path) VALUES (?, ?)");
                            $stmt->bind_param("is", $escort_id ?? $id, $photo_file);
                            $stmt->execute();
                            $photo_ids[] = $conn->insert_id;
                        } else {
                            $error = "Erro ao fazer upload da foto adicional: " . $_FILES["additional_photos"]["name"][$key];
                            break;
                        }
                    } else {
                        $error = "Foto adicional inválida ou muito grande: " . $_FILES["additional_photos"]["name"][$key];
                        break;
                    }
                }
            }
        }

        if (!$error) {
            $text = implode(' ', [$data['description'], $data['services'], $data['physical_traits'], $data['tags']]);
            $suggested_tags = array_unique(array_filter(array_map('trim', explode(',', strtolower($text))), function($tag) {
                return in_array($tag, ['loira', 'morena', 'eventos', 'vídeos', 'companhia']);
            }));

            if ($id > 0) {
                $query = "UPDATE escorts SET user_id = ?, name = ?, age = ?, description = ?, services = ?, rates = ?, availability = ?, profile_photo = ?, type = ?, is_online = ?, physical_traits = ?, phone = ?, height = ?, weight = ?, languages = ?, views = ?, latitude = ?, longitude = ?, tags = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("isissssssisssididdsi", $data['user_id'], $data['name'], $data['age'], $data['description'], $data['services'], $data['rates'], $data['availability'], $data['profile_photo'], $data['type'], $data['is_online'], $data['physical_traits'], $data['phone'], $data['height'], $data['weight'], $data['languages'], $data['views'], $data['latitude'], $data['longitude'], $data['tags'], $id);
                $action = 'update';
                $escort_id = $id;
            } else {
                $query = "INSERT INTO escorts (user_id, name, age, description, services, rates, availability, profile_photo, type, is_online, physical_traits, phone, height, weight, languages, views, latitude, longitude, tags) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("isissssssisssididds", $data['user_id'], $data['name'], $data['age'], $data['description'], $data['services'], $data['rates'], $data['availability'], $data['profile_photo'], $data['type'], $data['is_online'], $data['physical_traits'], $data['phone'], $data['height'], $data['weight'], $data['languages'], $data['views'], $data['latitude'], $data['longitude'], $data['tags']);
                $action = 'create';
            }

            if ($stmt->execute()) {
                $escort_id = $id > 0 ? $id : $stmt->insert_id;
                $admin_id = $_SESSION['user_id'];
                $stmt_log = $conn->prepare("INSERT INTO edit_log (admin_id, escort_id, action) VALUES (?, ?, ?)");
                $stmt_log->bind_param("iis", $admin_id, $escort_id, $action);
                $stmt_log->execute();

                // Notificação via WebSocket (simulada)
                $ws_message = json_encode(['type' => 'new_profile', 'name' => $data['name']]);
                // Aqui você enviaria $ws_message para o servidor WebSocket, se configurado

                header("Location: admin.php#escorts");
                exit;
            } else {
                $error = "Erro ao salvar: " . $conn->error;
            }
        }
    }
}

$users = $conn->query("SELECT id, username FROM users WHERE role IN ('escort', 'admin') ORDER BY username")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id > 0 ? "Editar" : "Adicionar"; ?> Perfil - Eskort</title>
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
            <h2><?php echo $id > 0 ? "Editar" : "Adicionar"; ?> Perfil</h2>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form id="escort-form" action="edit_escort.php<?php echo $id > 0 ? "?id=$id" : ""; ?>" method="post" enctype="multipart/form-data" class="admin-form">
                <div class="form-group">
                    <label for="user_id">Usuário:</label>
                    <select id="user_id" name="user_id" required>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $escort && $escort['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="name">Nome:</label>
                    <input type="text" id="name" name="name" value="<?php echo $escort ? htmlspecialchars($escort['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="age">Idade:</label>
                    <input type="number" id="age" name="age" value="<?php echo $escort ? $escort['age'] : ''; ?>" required min="18">
                </div>
                <div class="form-group">
                    <label for="description">Descrição:</label>
                    <textarea id="description" name="description" onkeyup="suggestTags()"><?php echo $escort ? htmlspecialchars($escort['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="services">Serviços:</label>
                    <textarea id="services" name="services" onkeyup="suggestTags()"><?php echo $escort ? htmlspecialchars($escort['services']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="rates">Tarifas:</label>
                    <input type="text" id="rates" name="rates" value="<?php echo $escort ? htmlspecialchars($escort['rates']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="availability">Disponibilidade:</label>
                    <input type="text" id="availability" name="availability" value="<?php echo $escort ? htmlspecialchars($escort['availability']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="profile_photo">Foto de Perfil:</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" onchange="previewPhoto(this, 'profile-preview')">
                    <img id="profile-preview" src="<?php echo $escort ? htmlspecialchars($escort['profile_photo']) : 'uploads/default.jpg'; ?>" alt="Prévia" style="max-width: 200px; margin-top: 10px;">
                </div>
                <div class="form-group">
                    <label for="additional_photos">Fotos Adicionais:</label>
                    <input type="file" id="additional_photos" name="additional_photos[]" accept="image/*" multiple onchange="previewPhotos(this)">
                    <div id="additional-previews" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;"></div>
                </div>
                <div class="form-group">
                    <label for="type">Tipo:</label>
                    <select id="type" name="type" required>
                        <option value="acompanhante" <?php echo $escort && $escort['type'] === 'acompanhante' ? 'selected' : ''; ?>>Acompanhante</option>
                        <option value="criadora" <?php echo $escort && $escort['type'] === 'criadora' ? 'selected' : ''; ?>>Pornstar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="is_online">Online:</label>
                    <input type="checkbox" id="is_online" name="is_online" <?php echo $escort && $escort['is_online'] ? 'checked' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="physical_traits">Características Físicas:</label>
                    <input type="text" id="physical_traits" name="physical_traits" value="<?php echo $escort ? htmlspecialchars($escort['physical_traits']) : ''; ?>" onkeyup="suggestTags()">
                </div>
                <div class="form-group">
                    <label for="phone">Telefone:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo $escort ? htmlspecialchars($escort['phone']) : ''; ?>" placeholder="+5511998765432">
                </div>
                <div class="form-group">
                    <label for="height">Altura (m):</label>
                    <input type="number" step="0.01" id="height" name="height" value="<?php echo $escort ? $escort['height'] : ''; ?>" placeholder="Ex: 1.70">
                </div>
                <div class="form-group">
                    <label for="weight">Peso (kg):</label>
                    <input type="number" id="weight" name="weight" value="<?php echo $escort ? $escort['weight'] : ''; ?>" placeholder="Ex: 60">
                </div>
                <div class="form-group">
                    <label for="languages">Idiomas:</label>
                    <input type="text" id="languages" name="languages" value="<?php echo $escort ? htmlspecialchars($escort['languages']) : ''; ?>" placeholder="Ex: Português, Inglês">
                </div>
                <div class="form-group">
                    <label for="views">Visualizações:</label>
                    <input type="number" id="views" name="views" value="<?php echo $escort ? $escort['views'] : 0; ?>" min="0">
                </div>
                <div class="form-group">
                    <label for="latitude">Latitude:</label>
                    <input type="number" step="0.00000001" id="latitude" name="latitude" value="<?php echo $escort ? $escort['latitude'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="longitude">Longitude:</label>
                    <input type="number" step="0.00000001" id="longitude" name="longitude" value="<?php echo $escort ? $escort['longitude'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="tags">Tags:</label>
                    <input type="text" id="tags" name="tags" value="<?php echo $escort ? htmlspecialchars($escort['tags']) : implode(', ', $suggested_tags); ?>" placeholder="Ex: loira, eventos">
                    <p id="suggested-tags">Sugestões: <?php echo implode(', ', $suggested_tags); ?></p>
                </div>
                <button type="submit" class="load-more">Salvar</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('escort-form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value;
            const age = document.getElementById('age').value;
            const phone = document.getElementById('phone').value;
            const height = document.getElementById('height').value;
            const weight = document.getElementById('weight').value;

            if (!name) {
                e.preventDefault();
                alert('Nome é obrigatório.');
                return;
            }
            if (age < 18) {
                e.preventDefault();
                alert('A idade mínima é 18 anos.');
                return;
            }
            if (phone && !/^\+?\d{10,15}$/.test(phone)) {
                e.preventDefault();
                alert('Formato de telefone inválido. Use: +5511998765432');
                return;
            }
            if (height < 0 || weight < 0) {
                e.preventDefault();
                alert('Altura e peso não podem ser negativos.');
                return;
            }
        });

        function previewPhoto(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewPhotos(input) {
            const previews = document.getElementById('additional-previews');
            previews.innerHTML = '';
            Array.from(input.files).forEach(file => {
                if (file.size <= 5 * 1024 * 1024) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.maxWidth = '100px';
                        previews.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                } else {
                    alert('Foto ' + file.name + ' é muito grande (máx. 5MB).');
                }
            });
        }

        function suggestTags() {
            const description = document.getElementById('description').value.toLowerCase();
            const services = document.getElementById('services').value.toLowerCase();
            const traits = document.getElementById('physical_traits').value.toLowerCase();
            const text = `${description} ${services} ${traits}`;
            const tags = ['loira', 'morena', 'eventos', 'vídeos', 'companhia'];
            const suggested = tags.filter(tag => text.includes(tag));
            document.getElementById('suggested-tags').textContent = 'Sugestões: ' + (suggested.length ? suggested.join(', ') : 'Nenhuma sugestão');
            if (!document.getElementById('tags').value) {
                document.getElementById('tags').value = suggested.join(', ');
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>