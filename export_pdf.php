<?php
require_once 'session.php';
require_once 'config.php';
require 'vendor/autoload.php'; // Dompdf
use Dompdf\Dompdf;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Erro de segurança: Token CSRF inválido.");
}

$conn = getDBConnection();
$category_id = isset($_POST['export_category']) ? (int)$_POST['export_category'] : 0;

$where = $category_id > 0 ? "JOIN escort_categories ec ON e.id = ec.escort_id WHERE ec.category_id = ?" : "";
$query = "SELECT e.name, e.type, e.views FROM escorts e $where";
$stmt = $conn->prepare($query);
if ($category_id > 0) {
    $stmt->bind_param("i", $category_id);
}
$stmt->execute();
$result = $stmt->get_result();

$html = "<h1>Relatório de Perfis</h1><table border='1'><tr><th>Nome</th><th>Tipo</th><th>Visualizações</th></tr>";
while ($row = $result->fetch_assoc()) {
    $html .= "<tr><td>" . htmlspecialchars($row['name']) . "</td><td>" . $row['type'] . "</td><td>" . $row['views'] . "</td></tr>";
}
$html .= "</table>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("escorts_report_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);

$conn->close();
exit;
?>