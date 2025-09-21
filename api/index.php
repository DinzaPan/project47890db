<?php
// Incluir configuración
require_once 'config.php';

// Router básico integrado en index.php
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remover query string
$path = strtok($path, '?');

// Si es la raíz, mostrar la página principal
if ($path === '/' || $path === '/index.php') {
    // [TODO: Aquí va el contenido original de tu index.php]
    
    // Verificar si hay una búsqueda
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Obtener addons con información de reseñas
    $query = "
        SELECT a.*, u.username, u.profile_pic, u.is_verified,
               AVG(r.rating) as avg_rating, 
               COUNT(r.id) as review_count 
        FROM addons a 
        JOIN usuarios u ON a.user_id = u.id 
        LEFT JOIN reviews r ON a.id = r.addon_id 
    ";

    // [Resto del código de tu index.php original...]
    
} elseif ($path === '/login') {
    require 'login.php';
} elseif ($path === '/register') {
    require 'register.php';
} elseif ($path === '/profile') {
    require 'profile.php';
} elseif ($path === '/addon') {
    require 'addon.php';
} elseif ($path === '/add_addon') {
    require 'add_addon.php';
} elseif ($path === '/search') {
    require 'search.php';
} elseif ($path === '/logout') {
    require 'logout.php';
} elseif ($path === '/settings') {
    require 'settings.php';
} elseif (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|webp)$/', $path)) {
    // Servir archivos estáticos
    if (file_exists(__DIR__ . $path)) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon'
        ];
        
        if (isset($mime_types[$extension])) {
            header('Content-Type: ' . $mime_types[$extension]);
            readfile(__DIR__ . $path);
            exit;
        }
    }
    http_response_code(404);
    echo 'Archivo no encontrado';
} else {
    http_response_code(404);
    echo 'Página no encontrada';
}
