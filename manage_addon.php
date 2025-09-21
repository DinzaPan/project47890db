<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_addon') {
        // Validar campos
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $tag = trim($_POST['tag'] ?? '');
        $downloadLink = trim($_POST['downloadLink'] ?? '');
        
        if (empty($title) || empty($description) || empty($version) || empty($downloadLink)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos obligatorios']);
            exit;
        }
        
        // Validar enlace de descarga
        if (!preg_match('/https?:\/\/(www\.)?(mediafire\.com|mega\.io|mega\.nz)/i', $downloadLink)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, usa un enlace de MediaFire o MEGA']);
            exit;
        }
        
        // Procesar imagen de portada
        if (!isset($_FILES['coverImage']) || $_FILES['coverImage']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Por favor, selecciona una imagen de portada']);
            exit;
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['coverImage']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagen no válido. Solo se permiten JPEG, PNG y GIF.']);
            exit;
        }
        
        $uploadDir = './uploads/';
        $fileName = uniqid() . '_' . basename($_FILES['coverImage']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['coverImage']['tmp_name'], $targetPath)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO addons (user_id, title, description, version, tag, download_link, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $description,
                    $version,
                    $tag ?: null,
                    $downloadLink,
                    $fileName
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Addon publicado con éxito']);
            } catch (PDOException $e) {
                // Eliminar la imagen subida si hay un error en la base de datos
                @unlink($targetPath);
                echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir la imagen de portada']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>