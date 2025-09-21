<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['addon_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$addonId = $data['addon_id'];
$userId = $_SESSION['user_id'];
$action = $data['action'];

try {
    if ($action === 'add') {
        // Verificar si el addon existe
        $stmt = $pdo->prepare("SELECT id FROM addons WHERE id = ?");
        $stmt->execute([$addonId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Addon no encontrado']);
            exit;
        }
        
        // Añadir a favoritos
        $stmt = $pdo->prepare("INSERT INTO favoritos (user_id, addon_id) VALUES (?, ?)");
        $stmt->execute([$userId, $addonId]);
    } elseif ($action === 'remove') {
        // Eliminar de favoritos
        $stmt = $pdo->prepare("DELETE FROM favoritos WHERE user_id = ? AND addon_id = ?");
        $stmt->execute([$userId, $addonId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        exit;
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>