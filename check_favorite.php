<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isset($_GET['addon_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de addon no proporcionado']);
    exit;
}

$addonId = $_GET['addon_id'];
$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id FROM favoritos WHERE user_id = ? AND addon_id = ?");
    $stmt->execute([$userId, $addonId]);
    
    echo json_encode([
        'success' => true,
        'isFavorite' => $stmt->fetch() !== false
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?><?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isset($_GET['addon_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de addon no proporcionado']);
    exit;
}

$addonId = $_GET['addon_id'];
$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id FROM favoritos WHERE user_id = ? AND addon_id = ?");
    $stmt->execute([$userId, $addonId]);
    
    echo json_encode([
        'success' => true,
        'isFavorite' => $stmt->fetch() !== false
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>