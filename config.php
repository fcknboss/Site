<?php
// config.php

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eskort');

// Conexão ao banco de dados
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        logError("Falha na conexão com o banco: " . $conn->connect_error);
        die("Erro interno. Veja o log para detalhes.");
    }
    $conn->set_charset("utf8");
    return $conn;
}

// Configurações gerais
date_default_timezone_set('America/Sao_Paulo');
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');

// Sanitização de entradas
function sanitize($input) {
    global $conn;
    return htmlspecialchars(mysqli_real_escape_string($conn, trim($input ?? '')), ENT_QUOTES, 'UTF-8');
}

// Função de log de erro
function logError($message, $file = 'eskort_errors.log') {
    $log_dir = 'C:\xampp\htdocs\eskort\Site\logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    $log_file = "$log_dir/$file";
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] " . $message . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . " | User: " . ($_SESSION['username'] ?? 'N/A') . "\n";
    file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}

// Função de log de registros do banco
function logDBAction($action, $table_name, $record_id = null, $details = null) {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO db_log (action, table_name, record_id, user_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $action, $table_name, $record_id, $user_id, $details);
    $stmt->execute() or logError("Erro ao registrar ação no db_log: " . $conn->error);
    $stmt->close();
    $conn->close();
}

// Variável global para acumular logs de tarefas
$task_logs = [];

// Função de log de tarefas
function logTask($action, $details, $file = 'task_log.log') {
    global $task_logs;
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] Tarefa: $action | Detalhes: $details | Executado por: Grok (ID 999)";
    $task_logs[] = $entry; // Acumula logs em vez de escrever imediatamente
}

// Função para registrar todos os logs acumulados ao final
function saveTaskLogs($file = 'task_log.log') {
    global $task_logs;
    if (!empty($task_logs)) {
        $log_dir = 'C:\xampp\htdocs\eskort\Site\logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $log_file = "$log_dir/$file";
        $full_entry = implode("\n", $task_logs) . "\n";
        file_put_contents($log_file, $full_entry, FILE_APPEND | LOCK_EX);
        $task_logs = []; // Limpa após salvar
    }
}

// Configuração de erros do PHP
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\eskort\Site\logs\php_error.log');

// Handler de erros customizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("Erro PHP [$errno]: $errstr em $errfile:$errline");
    return true;
});

// Handler de exceções
set_exception_handler(function($exception) {
    logError("Exceção: " . $exception->getMessage() . " em " . $exception->getFile() . ":" . $exception->getLine());
    die("Erro interno. Veja o log para detalhes.");
});

// Registro das tarefas será salvo ao final da execução deste arquivo
register_shutdown_function('saveTaskLogs');
?>