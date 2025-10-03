<?php
// Configuración de sesión - DEBE ir ANTES de session_start()
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); // 30 días
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 días para la cookie de sesión
ini_set('session.cookie_httponly', 1); // Las cookies solo accesibles por HTTP
ini_set('session.cookie_secure', 1); // Solo enviar cookies sobre HTTPS
ini_set('session.use_strict_mode', 1); // Modo estricto de sesiones
ini_set('session.sid_length', 128); // Longitud del ID de sesión
ini_set('session.sid_bits_per_character', 6); // Bits por carácter

// Detectar si estamos en Vercel
define('IS_VERCEL', getenv('VERCEL') === '1' || isset($_SERVER['VERCEL']));

// SOLUCIÓN: Permitir acceso directo a archivos PHP específicos en Vercel
if (IS_VERCEL && php_sapi_name() !== 'cli') {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $allowed_direct_scripts = ['login.php', 'register.php', 'logout.php', 'add_addon.php', 'profile.php', 'settings.php', 'addon.php', 'search.php'];
    
    if (in_array($current_script, $allowed_direct_scripts)) {
        // Si es un script permitido, iniciar sesión y continuar normalmente
        session_start();
        // No incluir config.php nuevamente para evitar duplicación
        return;
    }
}

session_start();

// Configuración para Vercel - usar SQLite en /tmp
if (IS_VERCEL) {
    // En Vercel, usamos /tmp para escritura
    $dbPath = '/tmp/database.sqlite';
    
    // Configurar headers para CORS
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    // Manejar preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
} else {
    // Configuración local
    $dbPath = __DIR__ . '/database.sqlite';
}

// Crear directorio si no existe (solo local)
if (!IS_VERCEL && !file_exists(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0755, true);
}

// Conexión a SQLite
try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Crear tablas si no existen
    createTablesIfNotExist($pdo);
} catch (PDOException $e) {
    error_log("Error de conexión SQLite: " . $e->getMessage());
    // No mostrar detalles del error en producción
    if (IS_VERCEL) {
        die("Error de conexión a la base de datos");
    } else {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Configuración de Supabase
define('SUPABASE_URL', 'https://dsercozkjptjmgjbfxdz.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRzZXJjb3pranB0am1namJmeGR6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTgzMjY3NTgsImV4cCI6MjA3MzkwMjc1OH0.Ee2qiJecAfqfZrrJ8DH2u6f5HNyyUyPe3hMQLWuqiJY');
define('SUPABASE_BUCKET', 'mcpixel-storage');

// Configuración de uploads
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR_PROFILES', 'profiles'); // Directorio para imágenes de perfil
define('UPLOAD_DIR_ADDONS', 'addons'); // Directorio para imágenes de addons

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Función para manejar el "recuerdame"
function rememberMe($pdo, $user_id) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (60 * 60 * 24 * 30); // 30 días
    
    // Guardar token en la base de datos
    $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = ?, token_expiry = ? WHERE id = ?");
    $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $user_id]);
    
    // Establecer cookie
    setcookie('remember_token', $token, $expiry, '/', '', false, true);
}

// Verificar cookie "recuerdame" al inicio
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $pdo->prepare("SELECT id, is_banned FROM usuarios WHERE remember_token = ? AND token_expiry > datetime('now')");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    
    if ($user && !$user['is_banned']) {
        $_SESSION['user_id'] = $user['id'];
        // Actualizar la cookie para extender su duración
        rememberMe($pdo, $user['id']);
    }
}

// Función para obtener información del usuario
function getUserInfo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Verificar si el usuario es administrador o está verificado
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT is_admin, is_banned, is_verified FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    $_SESSION['is_admin'] = $user['is_admin'] ?? false;
    $_SESSION['is_banned'] = $user['is_banned'] ?? false;
    $_SESSION['is_verified'] = $user['is_verified'] ?? false;
    
    // Si el usuario está baneado, cerrar sesión
    if ($_SESSION['is_banned']) {
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        header('Location: login.php?banned=1');
        exit;
    }
}

// Función para subir archivos a Supabase
function uploadFile($file, $path = '') {
    $errors = [];
    
    // Verificar errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error al subir el archivo. Código: " . $file['error'];
        return [false, $errors];
    }
    
    // Verificar tamaño
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "El archivo es demasiado grande (Máx. " . (MAX_FILE_SIZE / 1024 / 1024) . "MB)";
    }
    
    // Verificar tipo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) {
        $errors[] = "Tipo de archivo no permitido. Solo se aceptan JPEG, PNG, GIF o WebP.";
    }
    
    if (!empty($errors)) {
        return [false, $errors];
    }
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $extension;
    $fullPath = $path ? trim($path, '/') . '/' . $filename : $filename;
    
    // Subir a Supabase usando cURL
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $fullPath);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file['tmp_name']));
    
    $headers = array();
    $headers[] = 'Authorization: Bearer ' . SUPABASE_KEY;
    $headers[] = 'Content-Type: ' . $mime;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $errors[] = "Error al subir a Supabase: " . curl_error($ch);
        curl_close($ch);
        return [false, $errors];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return [true, $filename];
    } else {
        $errors[] = "Error al subir a Supabase. Código HTTP: " . $httpCode;
        return [false, $errors];
    }
}

// Función para eliminar archivos de Supabase
function deleteFile($filename, $path = '') {
    $fullPath = $path ? trim($path, '/') . '/' . $filename : $filename;
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $fullPath);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    
    $headers = array();
    $headers[] = 'Authorization: Bearer ' . SUPABASE_KEY;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

// Obtener URL pública de un archivo en Supabase
function getFileUrl($filename, $path = '') {
    $fullPath = $path ? trim($path, '/') . '/' . $filename : $filename;
    return SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $fullPath;
}

// Establecer tema
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
} else {
    $theme = 'dark';
}

// Funciones administrativas
function deleteUser($pdo, $userId) {
    try {
        $pdo->beginTransaction();
        
        // Obtener addons del usuario
        $stmt = $pdo->prepare("SELECT cover_image FROM addons WHERE user_id = ?");
        $stmt->execute([$userId]);
        $addons = $stmt->fetchAll();
        
        // Eliminar imágenes de addons de Supabase
        foreach ($addons as $addon) {
            if ($addon['cover_image'] !== 'default.png') {
                deleteFile($addon['cover_image'], UPLOAD_DIR_ADDONS);
            }
        }
        
        // Eliminar favoritos
        $pdo->prepare("DELETE FROM favoritos WHERE user_id = ?")->execute([$userId]);
        
        // Eliminar addons
        $pdo->prepare("DELETE FROM addons WHERE user_id = ?")->execute([$userId]);
        
        // Eliminar imagen de perfil si no es la predeterminada
        $stmt = $pdo->prepare("SELECT profile_pic FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && $user['profile_pic'] !== 'default.png') {
            deleteFile($user['profile_pic'], UPLOAD_DIR_PROFILES);
        }
        
        // Eliminar usuario
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al eliminar usuario: " . $e->getMessage());
        return false;
    }
}

function banUser($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE usuarios SET is_banned = 1 WHERE id = ?");
    return $stmt->execute([$userId]);
}

function unbanUser($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE usuarios SET is_banned = 0 WHERE id = ?");
    return $stmt->execute([$userId]);
}

function verifyUser($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE usuarios SET is_verified = 1 WHERE id = ?");
    return $stmt->execute([$userId]);
}

function unverifyUser($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE usuarios SET is_verified = 0 WHERE id = ?");
    return $stmt->execute([$userId]);
}

function deleteAddon($pdo, $addonId) {
    try {
        $pdo->beginTransaction();
        
        // Obtener información del addon
        $stmt = $pdo->prepare("SELECT cover_image FROM addons WHERE id = ?");
        $stmt->execute([$addonId]);
        $addon = $stmt->fetch();
        
        if ($addon) {
            // Eliminar imagen de portada de Supabase
            if ($addon['cover_image'] !== 'default.png') {
                deleteFile($addon['cover_image'], UPLOAD_DIR_ADDONS);
            }
            
            // Eliminar de favoritos
            $pdo->prepare("DELETE FROM favoritos WHERE addon_id = ?")->execute([$addonId]);
            
            // Eliminar addon
            $pdo->prepare("DELETE FROM addons WHERE id = ?")->execute([$addonId]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al eliminar addon: " . $e->getMessage());
        return false;
    }
}

// Protección básica contra CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para generar URL seguras
function generateSecureUrl($path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host . '/' . ltrim($path, '/');
}

// Función para búsqueda global
function globalSearch($pdo, $query, $limit = 5) {
    $results = [];
    
    // Buscar usuarios
    $stmt = $pdo->prepare("SELECT id, username, profile_pic, is_verified FROM usuarios 
                          WHERE username LIKE ? OR email LIKE ?
                          LIMIT ?");
    $stmt->execute(["%$query%", "%$query%", $limit]);
    $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar addons
    $stmt = $pdo->prepare("SELECT a.id, a.title, a.description, a.cover_image, u.username 
                          FROM addons a 
                          JOIN usuarios u ON a.user_id = u.id 
                          WHERE a.title LIKE ? OR a.description LIKE ? OR u.username LIKE ?
                          LIMIT ?");
    $stmt->execute(["%$query%", "%$query%", "%$query%", $limit]);
    $results['addons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar reseñas
    $stmt = $pdo->prepare("SELECT r.id, r.comment, r.rating, u.username, a.title as addon_title 
                          FROM reviews r 
                          JOIN usuarios u ON r.user_id = u.id 
                          JOIN addons a ON r.addon_id = a.id 
                          WHERE r.comment LIKE ? OR u.username LIKE ? OR a.title LIKE ?
                          LIMIT ?");
    $stmt->execute(["%$query%", "%$query%", "%$query%", $limit]);
    $results['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $results;
}

// Función para crear tablas SQLite si no existen
function createTablesIfNotExist($pdo) {
    // Tabla usuarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            profile_pic VARCHAR(255) DEFAULT 'default.png',
            bio VARCHAR(500) DEFAULT NULL,
            theme VARCHAR(10) DEFAULT 'dark' CHECK (theme IN ('dark', 'light')),
            last_profile_update DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_admin BOOLEAN DEFAULT 0,
            is_banned BOOLEAN DEFAULT 0,
            is_verified BOOLEAN DEFAULT 0,
            remember_token VARCHAR(64) DEFAULT NULL,
            token_expiry DATETIME DEFAULT NULL
        )
    ");
    
    // Tabla addons
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS addons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            version VARCHAR(20) NOT NULL,
            tag VARCHAR(50) DEFAULT NULL,
            download_link VARCHAR(255) NOT NULL,
            cover_image VARCHAR(255) NOT NULL DEFAULT 'default.png',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            verified BOOLEAN DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
        )
    ");
    
    // Tabla addon_stats
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS addon_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            addon_id INTEGER NOT NULL,
            user_ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (addon_id) REFERENCES addons (id) ON DELETE CASCADE
        )
    ");
    
    // Tabla favoritos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS favoritos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            addon_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, addon_id),
            FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            FOREIGN KEY (addon_id) REFERENCES addons (id) ON DELETE CASCADE
        )
    ");
    
    // Tabla reviews
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            addon_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(addon_id, user_id),
            FOREIGN KEY (addon_id) REFERENCES addons (id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
        )
    ");
    
    // Tabla review_replies
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS review_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            review_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            reply TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
        )
    ");
    
    // Tabla user_follows
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_follows (
            follower_id INTEGER NOT NULL,
            following_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, following_id),
            FOREIGN KEY (follower_id) REFERENCES usuarios (id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES usuarios (id) ON DELETE CASCADE
        )
    ");
    
    // Crear índices para mejorar el rendimiento
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_username ON usuarios(username)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_addons_user_id ON addons(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_addons_title ON addons(title)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_favoritos_user_id ON favoritos(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_favoritos_addon_id ON favoritos(addon_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reviews_addon_id ON reviews(addon_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reviews_user_id ON reviews(user_id)");
    
    // Insertar usuario admin por defecto si no existe
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE is_admin = 1");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (username, email, password, is_admin, is_verified) VALUES (?, ?, ?, 1, 1)")
            ->execute(['admin', 'admin@mcpixel.net', $hashedPassword]);
    }
}
?>
