<?php
require_once 'config.php';

$conn = getDBConnection();
$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);

$backup_file = "$backup_dir/eskort_" . date('Ymd_His') . ".sql";
$pass = defined('DB_PASS') && DB_PASS ? "-p" . escapeshellarg(DB_PASS) : '';
$command = sprintf("mysqldump -h %s -u %s %s eskort > %s 2>&1",
    escapeshellarg(DB_HOST), escapeshellarg(DB_USER), $pass, escapeshellarg($backup_file));

exec($command, $output, $return_var);
if ($return_var === 0) {
    $to = "admin@example.com"; // Substitua pelo seu email
    $subject = "Backup Eskort - " . date('Y-m-d H:i:s');
    $message = "Backup criado com sucesso: $backup_file";
    $headers = "From: noreply@eskort.com";
    mail($to, $subject, $message, $headers);
    echo "Backup criado em $backup_file e email enviado.";
} else {
    echo "Erro ao criar backup: " . implode("\n", $output);
}

$conn->close();
?>