<?php
require_once 'session.php';
require_once 'config.php';

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo CSV válido enviado']);
    exit;
}

$conn = getDBConnection();
$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, 'r');

if ($handle === false) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao abrir o arquivo CSV']);
    exit;
}

$headers = fgetcsv($handle, 1000, ',');
$header_map = array_flip($headers);

$inserted = 0;
$updated = 0;
$errors = [];

while (($data = fgetcsv($handle, 1000, ',')) !== false) {
    $row = array_combine($headers, $data);
    if (!isset($row['name']) || !isset($row['age']) || !isset($row['user_id'])) {
        $errors[] = "Linha inválida: faltam campos obrigatórios (name, age, user_id)";
        continue;
    }

    $name = trim($row['name']);
    $age = (int)$row['age'];
    $user_id = (int)$row['user_id'];

    if (empty($name) || $age < 18 || $user_id <= 0) {
        $errors[] = "Linha inválida para '$name': nome vazio, idade < 18 ou user_id inválido";
        continue;
    }

    $description = isset($row['description']) ? trim($row['description']) : '';
    $services = isset($row['services']) ? trim($row['services']) : '';
    $rates = isset($row['rates']) ? trim($row['rates']) : '';
    $availability = isset($row['availability']) ? trim($row['availability']) : '';
    $profile_photo = isset($row['profile_photo']) && !empty(trim($row['profile_photo'])) ? trim($row['profile_photo']) : 'uploads/default.jpg';
    $type = isset($row['type']) && in_array(trim($row['type']), ['acompanhante', 'criadora']) ? trim($row['type']) : 'acompanhante';
    $is_online = isset($row['is_online']) ? (int)$row['is_online'] : 0;
    $physical_traits = isset($row['physical_traits']) ? trim($row['physical_traits']) : '';
    $phone = isset($row['phone']) && preg_match('/^\+?\d{10,15}$/', trim($row['phone'])) ? trim($row['phone']) : '';
    $height = isset($row['height']) ? (float)$row['height'] : 0;
    $weight = isset($row['weight']) ? (int)$row['weight'] : 0;
    $languages = isset($row['languages']) ? trim($row['languages']) : '';
    $tags = isset($row['tags']) ? trim($row['tags']) : '';

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('escort', 'admin')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $errors[] = "Usuário ID $user_id não encontrado ou inválido para '$name'";
        continue;
    }

    $stmt = $conn->prepare("INSERT INTO escorts (user_id, name, age, description, services, rates, availability, profile_photo, type, is_online, physical_traits, phone, height, weight, languages, tags) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            age = VALUES(age), description = VALUES(description), services = VALUES(services), rates = VALUES(rates), 
                            availability = VALUES(availability), profile_photo = VALUES(profile_photo), type = VALUES(type), 
                            is_online = VALUES(is_online), physical_traits = VALUES(physical_traits), phone = VALUES(phone), 
                            height = VALUES(height), weight = VALUES(weight), languages = VALUES(languages), tags = VALUES(tags)");
    $stmt->bind_param("isissssssisssidss", $user_id, $name, $age, $description, $services, $rates, $availability, $profile_photo, $type, $is_online, $physical_traits, $phone, $height, $weight, $languages, $tags);

    if ($stmt->execute()) {
        if ($stmt->affected_rows == 1) $inserted++;
        elseif ($stmt->affected_rows == 2) $updated++;

        $escort_id = $stmt->insert_id ?: $conn->query("SELECT id FROM escorts WHERE name = '$name' AND user_id = $user_id LIMIT 1")->fetch_assoc()['id'];
        $action = $stmt->affected_rows == 1 ? 'create' : 'update';
        $stmt_log = $conn->prepare("INSERT INTO edit_log (admin_id, escort_id, action) VALUES (?, ?, ?)");
        $stmt_log->bind_param("iis", $_SESSION['user_id'], $escort_id, $action);
        $stmt_log->execute();
    } else {
        $errors[] = "Erro ao importar '$name': " . $stmt->error;
    }
}

fclose($handle);

if (empty($errors)) {
    echo json_encode(['status' => 'success', 'inserted' => $inserted, 'updated' => $updated, 'message' => 'Importação concluída com sucesso']);
} else {
    echo json_encode(['status' => 'error', 'message' => implode('; ', $errors)]);
}

$conn->close();
exit;
?>