<?php
// session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0); // Oculta erros na página
ini_set('log_errors', 1); // Registra erros no log
ini_set('error_log', 'C:\xampp\php\logs\php_error.log'); // Caminho do log no XAMPP
?>