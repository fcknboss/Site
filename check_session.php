<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$timeout = 30 * 60; // 30 minutos em segundos
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();
?>