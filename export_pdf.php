<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // Instale com "composer require dompdf/dompdf"

use Dompdf\Dompdf;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Acesso negado');
}

$conn = getDBConnection();
$category = isset($_POST['export_category']) ? (int)$_POST['export_category'] : 0;
$where = $category > 0 ? "JOIN escort_categories ec ON e.id = ec.escort_id WHERE ec.category_id = ?" : "";
$params = $category > 0 ? [$category] : [];
$types = $category > 0 ? "i" : "";

$query = "SELECT e.name, e.type, e.views 
          FROM escorts e 
          $where";
$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$html = '<h1>Relatório de Perfis - Eskort</h1><table border="1"><tr><th>Nome</th><th>Tipo</th><th>Views</th></tr>';
while ($row = $result->fetch_assoc()) {
    $html .= "<tr><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['type']) . "</td><td>" . $row['views'] . "</td></tr>";
}
$html .= '</table>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("escorts_report.pdf", ["Attachment" => true]);

$conn->close();
?>