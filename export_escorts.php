<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$result = $conn->query("SELECT e.id, e.name, e.age, e.description, e.services, e.rates, e.availability, e.type, e.is_online, e.physical_traits, e.phone, e.height, e.weight, e.languages, e.views, e.latitude, e.longitude, u.username 
                        FROM escorts e 
                        JOIN users u ON e.user_id = u.id");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="escorts_export.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Nome', 'Idade', 'Descrição', 'Serviços', 'Tarifas', 'Disponibilidade', 'Tipo', 'Online', 'Características Físicas', 'Telefone', 'Altura', 'Peso', 'Idiomas', 'Visualizações', 'Latitude', 'Longitude', 'Usuário']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [$row['id'], $row['name'], $row['age'], $row['description'], $row['services'], $row['rates'], $row['availability'], $row['type'], $row['is_online'], $row['physical_traits'], $row['phone'], $row['height'], $row['weight'], $row['languages'], $row['views'], $row['latitude'], $row['longitude'], $row['username']]);
}

fclose($output);
$conn->close();
?>