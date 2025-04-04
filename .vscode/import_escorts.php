<?php
require_once 'config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (($handle = fopen($file, 'r')) !== false) {
        fgetcsv($handle); // Pula o cabeçalho
        while (($data = fgetcsv($handle)) !== false) {
            // Assume formato: user_id, name, age, location, type, username
            if (count($data) >= 5) {
                $user_id = (int)$data[0];
                $name = $data[1];
                $age = (int)$data[2];
                $location = $data[3];
                $type = in_array($data[4], ['acompanhante', 'criadora']) ? $data[4] : 'acompanhante';

                $stmt = $conn->prepare("INSERT INTO escorts (user_id, name, age, location, type) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isiss", $user_id, $name, $age, $location, $type);
                $stmt->execute();
            }
        }
        fclose($handle);
        echo json_encode(['status' => 'success', 'message' => 'Perfis importados com sucesso']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao abrir o arquivo CSV']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado']);
}

$conn->close();
?>