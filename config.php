<?php
// config.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Recomendo usar uma senha em produção
define('DB_NAME', 'eskort');

// Função para conectar ao banco
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        logError("Erro de conexão: " . $conn->connect_error);
        die("Erro de conexão. Veja o log para detalhes.");
    }
    // Seleciona o banco de dados ou cria se não existir
    if (!$conn->select_db(DB_NAME)) {
        $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME) or die("Erro ao criar o banco: " . $conn->error);
        $conn->select_db(DB_NAME) or die("Erro ao selecionar o banco após criação: " . $conn->error);
    }
    return $conn;
}

// Função para logging
function logError($message) {
    $log_file = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}
?>