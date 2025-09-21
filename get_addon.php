<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de addon no proporcionado']);
    exit;
}

$addonId = $_GET['id'];

try {
    // Obtener información del addon
    $stmt = $pdo->prepare("SELECT * FROM addons WHERE id = ?");
    $stmt->execute([$addonId]);
    $addon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$addon) {
        echo json_encode(['success' => false, 'message' => 'Addon no encontrado']);
        exit;
    }
    
    // Obtener información del usuario creador
    $stmt = $pdo->prepare("SELECT id, username, profile_pic FROM usuarios WHERE id = ?");
    $stmt->execute([$addon['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'addon' => $addon,
        'user' => $user
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>