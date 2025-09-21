<?php
require_once 'config.php';

// Limpiar la cookie "recuerdame"
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    
    // También limpiar en la base de datos
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = NULL, token_expiry = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
}

session_destroy();
header('Location: index.php');
exit;
?>