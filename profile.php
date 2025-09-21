<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserInfo($pdo, $_SESSION['user_id']);

// Verificar si estamos viendo estadísticas de un addon específico
$viewStats = isset($_GET['stats']) && is_numeric($_GET['stats']);
$currentAddonId = $viewStats ? $_GET['stats'] : null;

// Procesar eliminación de addon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_addon'])) {
    $addonId = filter_input(INPUT_POST, 'addon_id', FILTER_VALIDATE_INT);
    
    if ($addonId) {
        // Verificar que el addon pertenece al usuario
        $stmt = $pdo->prepare("SELECT user_id, cover_image FROM addons WHERE id = ?");
        $stmt->execute([$addonId]);
        $addon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($addon && $addon['user_id'] == $_SESSION['user_id']) {
            try {
                // Eliminar imagen de portada
                if (!empty($addon['cover_image']) && file_exists(UPLOAD_DIR . $addon['cover_image'])) {
                    @unlink(UPLOAD_DIR . $addon['cover_image']);
                }
                
                // Eliminar de favoritos
                $pdo->prepare("DELETE FROM favoritos WHERE addon_id = ?")->execute([$addonId]);
                
                // Eliminar reseñas
                $pdo->prepare("DELETE FROM reviews WHERE addon_id = ?")->execute([$addonId]);
                
                // Eliminar estadísticas
                $pdo->prepare("DELETE FROM addon_stats WHERE addon_id = ?")->execute([$addonId]);
                
                // Eliminar addon
                $pdo->prepare("DELETE FROM addons WHERE id = ?")->execute([$addonId]);
                
                $_SESSION['success_message'] = 'Addon eliminado correctamente';
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                error_log("Error deleting addon: " . $e->getMessage());
                $_SESSION['error_message'] = 'Error al eliminar el addon';
            }
        } else {
            $_SESSION['error_message'] = 'No tienes permiso para eliminar este addon';
        }
    }
}

// Obtener addons del usuario
$stmt = $pdo->prepare("SELECT * FROM addons WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$userAddons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener número de seguidores
$stmt = $pdo->prepare("SELECT COUNT(*) as followers FROM user_follows WHERE following_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$followers = $stmt->fetch(PDO::FETCH_ASSOC)['followers'];

// Obtener estadísticas de addons si estamos viendo un addon específico
$addonStats = null;
$addonReviews = [];
if ($viewStats && $currentAddonId) {
    // Verificar que el addon pertenece al usuario
    $stmt = $pdo->prepare("SELECT id, title FROM addons WHERE id = ? AND user_id = ?");
    $stmt->execute([$currentAddonId, $_SESSION['user_id']]);
    $currentAddon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentAddon) {
        // Obtener estadísticas de visitas
        $stmt = $pdo->prepare("SELECT COUNT(*) as views FROM addon_stats WHERE addon_id = ?");
        $stmt->execute([$currentAddonId]);
        $addonStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener reseñas del addon
        $stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_pic FROM reviews r JOIN usuarios u ON r.user_id = u.id WHERE r.addon_id = ? ORDER BY r.created_at DESC");
        $stmt->execute([$currentAddonId]);
        $addonReviews = $stmt->fetchAll();
    } else {
        // Si el addon no pertenece al usuario, redirigir
        header('Location: profile.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($user['username']); ?> | MCPixel</title>
    <style>
        :root {
            --primary-color: #3B82F6;
            --primary-hover: #2563EB;
            --secondary-color: #F59E0B;
            --accent-color: #10B981;
            --dark-bg: #0F172A;
            --dark-card: #1E293B;
            --dark-text: #F8FAFC;
            --dark-border: #334155;
            --light-bg: #F1F5F9;
            --light-card: #FFFFFF;
            --light-text: #1E293B;
            --light-border: #E2E8F0;
            --error-color: #EF4444;
            --success-color: #10B981;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --rounded: 0.75rem;
            --rounded-lg: 1rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--dark-bg);
            color: var(--dark-text);
            line-height: 1.6;
            min-height: 100vh;
            transition: var(--transition);
        }

        body[data-theme="light"] {
            background-color: var(--light-bg);
            color: var(--light-text);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--dark-card);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background-color: rgba(30, 41, 59, 0.8);
        }

        body[data-theme="light"] .navbar {
            background-color: rgba(255, 255, 255, 0.8);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            height: 2.5rem;
            width: auto;
            border-radius: 50%;
        }

        .site-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(90deg, #3B82F6, #10B981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        body[data-theme="light"] .site-title {
            background: linear-gradient(90deg, #3B82F6, #10B981);
        }

        .user-menu {
            position: relative;
        }

        .profile-pic {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid var(--primary-color);
            object-fit: cover;
            transition: var(--transition);
        }

        .profile-pic:hover {
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 3.5rem;
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            box-shadow: var(--shadow-lg);
            min-width: 12rem;
            z-index: 1001;
            overflow: hidden;
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .dropdown-menu {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--dark-text);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        body[data-theme="light"] .dropdown-menu a {
            color: var(--light-text);
        }

        .dropdown-menu a:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        body[data-theme="light"] .dropdown-menu a:hover {
            background-color: #f3f4f6;
        }

        .dropdown-menu a svg {
            width: 1rem;
            height: 1rem;
            color: var(--primary-color);
        }

        .show {
            display: block;
        }

        .container {
            padding: 0 2rem 2rem;
            max-width: 90rem;
            margin: 0 auto;
        }

        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
            text-align: center;
            padding: 2rem 0;
        }

        .profile-pic-large {
            width: 8rem;
            height: 8rem;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .profile-pic-large:hover {
            transform: scale(1.05);
        }

        .profile-username-container {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 100%;
        }

        .profile-username {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 12ch;
        }

        body[data-theme="light"] .profile-username {
            color: var(--light-text);
        }

        .verified-icon {
            color: var(--accent-color);
            width: 1.5rem;
            height: 1.5rem;
            flex-shrink: 0;
            margin-left: 0.25rem;
        }

        .profile-bio {
            max-width: 40rem;
            margin: 1rem auto 0;
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
            padding: 0 1rem;
        }

        body[data-theme="light"] .profile-bio {
            color: #64748b;
        }

        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .stat-item {
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--rounded);
        }

        .stat-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        body[data-theme="light"] .stat-label {
            color: #64748b;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark-text);
            position: relative;
            padding-bottom: 0.75rem;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 4rem;
            height: 0.25rem;
            background: linear-gradient(90deg, #3B82F6, #10B981);
            border-radius: 0.25rem;
        }

        body[data-theme="light"] .section-title {
            color: var(--light-text);
        }

        .addons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .addon-card {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            position: relative;
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .addon-card:hover {
            transform: translateY(-0.5rem);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .addon-cover {
            width: 100%;
            height: 12rem;
            object-fit: cover;
            border-bottom: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-cover {
            border-bottom-color: var(--light-border);
        }

        .addon-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .addon-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.75rem 0;
            color: var(--dark-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        body[data-theme="light"] .addon-title {
            color: var(--light-text);
        }

        .addon-description {
            font-size: 0.9375rem;
            color: #94a3b8;
            margin: 0 0 1rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
            line-height: 1.5;
        }

        body[data-theme="light"] .addon-description {
            color: #64748b;
        }

        .addon-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-meta {
            color: #64748b;
            border-top-color: var(--light-border);
        }

        .addon-version {
            background-color: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        body[data-theme="light"] .addon-version {
            background-color: var(--primary-color);
        }

        .no-addons {
            text-align: center;
            padding: 3rem;
            color: var(--dark-text);
            grid-column: 1 / -1;
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
        }

        body[data-theme="light"] .no-addons {
            color: var(--light-text);
            background-color: var(--light-card);
        }

        /* Acciones de tarjeta */
        .card-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: var(--transition);
        }

        .addon-card:hover .card-actions {
            opacity: 1;
        }

        .action-btn {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(15, 23, 42, 0.8);
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .edit-btn:hover {
            background-color: var(--secondary-color);
        }

        .delete-btn:hover {
            background-color: var(--error-color);
        }

        .stats-btn:hover {
            background-color: var(--primary-color);
        }

        .confirm-delete {
            position: absolute;
            top: 3rem;
            right: 0.5rem;
            background-color: var(--dark-card);
            padding: 0.75rem;
            border-radius: var(--rounded);
            box-shadow: var(--shadow-lg);
            z-index: 10;
            display: none;
            width: 12rem;
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .confirm-delete {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .confirm-text {
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--dark-text);
        }

        body[data-theme="light"] .confirm-text {
            color: var(--light-text);
        }

        .confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .confirm-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
            border: none;
        }

        .confirm-yes {
            background-color: var(--error-color);
            color: white;
        }

        .confirm-no {
            background-color: var(--dark-border);
            color: var(--dark-text);
        }

        body[data-theme="light"] .confirm-no {
            background-color: var(--light-border);
            color: var(--light-text);
        }

        /* Mensajes */
        .success-message {
            background-color: var(--success-color);
            color: white;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
        }

        .error-message {
            background-color: var(--error-color);
            color: white;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
        }

        /* Panel de estadísticas */
        .stats-panel {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .stats-panel {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .stats-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-text);
        }

        body[data-theme="light"] .stats-title {
            color: var(--light-text);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: var(--dark-bg);
            border-radius: var(--rounded);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .stat-card {
            background-color: var(--light-bg);
            border-color: var(--light-border);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-card-label {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        body[data-theme="light"] .stat-card-label {
            color: #64748b;
        }

        /* Lista de reseñas */
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .review-card {
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .review-card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .reviewer-pic {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .reviewer-name {
            font-weight: 600;
            color: var(--dark-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60%;
        }

        body[data-theme="light"] .reviewer-name {
            color: var(--light-text);
        }

        .review-rating {
            display: flex;
            gap: 0.25rem;
            margin-left: auto;
        }

        .review-rating .star {
            color: var(--secondary-color);
        }

        .review-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .review-content {
            color: var(--dark-text);
            line-height: 1.6;
        }

        body[data-theme="light"] .review-content {
            color: var(--light-text);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem 1rem;
            }
            
            .addons-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar {
                padding: 0.75rem 1rem;
            }
            
            .dropdown-menu {
                top: 3rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .profile-username {
                max-width: 8ch;
            }

            .reviewer-name {
                max-width: 50%;
            }
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar">
        <div class="logo-container">
            <img src="./img/logo.png" alt="MCPixel Logo" class="logo">
            <h1 class="site-title">MCPixel</h1>
        </div>
        
        <div class="user-menu">
            <img src="./uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Foto de perfil" class="profile-pic" id="profilePic">
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="index.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Inicio
                </a>
                <a href="profile.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Perfil
                </a>
                <a href="settings.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c-.94 1.543.826 3.31 2.37 2.37a1.724 1.724 0 002.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Ajustes
                </a>
                <a href="?view=favorites">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    Favoritos
                </a>
                <a href="logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Cerrar sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container">
        <!-- Mensajes de éxito/error -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($viewStats && $currentAddonId): ?>
            <!-- Panel de estadísticas de un addon específico -->
            <div class="stats-panel">
                <div class="stats-header">
                    <h2 class="stats-title">Estadísticas: <?php echo htmlspecialchars($currentAddon['title']); ?></h2>
                    <a href="profile.php" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Volver al perfil
                    </a>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $addonStats['views'] ?? 0; ?></div>
                        <div class="stat-card-label">Visitas totales</div>
                    </div>
                </div>
                
                <h3 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    Reseñas de este addon
                </h3>
                
                <?php if (count($addonReviews) > 0): ?>
                    <div class="reviews-list">
                        <?php foreach ($addonReviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <img src="./uploads/<?php echo htmlspecialchars($review['profile_pic']); ?>" alt="Reseñador" class="reviewer-pic">
                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['username']); ?></span>
                                    <div class="review-rating">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $review['rating'] ? '<span class="star">★</span>' : '<span class="star">☆</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="review-date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></div>
                                <p class="review-content"><?php echo htmlspecialchars($review['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--dark-text);">Este addon no tiene reseñas aún.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Perfil normal -->
            <div class="profile-header">
                <img src="./uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Foto de perfil" class="profile-pic-large">
                <div class="profile-username-container">
                    <h2 class="profile-username" title="<?php echo htmlspecialchars($user['username']); ?>">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </h2>
                    <?php if ($user['is_verified']): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-icon">
                            <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($user['bio'])): ?>
                    <p class="profile-bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                <?php endif; ?>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($userAddons); ?></div>
                        <div class="stat-label">Addons</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $followers; ?></div>
                        <div class="stat-label">Seguidores</div>
                    </div>
                </div>
            </div>
            
            <h2 class="section-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                Mis Addons Publicados
            </h2>
            
            <?php if (count($userAddons) > 0): ?>
                <div class="addons-grid">
                    <?php foreach ($userAddons as $addon): ?>
                        <div class="addon-card" data-id="<?php echo $addon['id']; ?>">
                            <!-- Acciones de tarjeta (iconos) -->
                            <div class="card-actions">
                                <a href="edit_addon.php?id=<?php echo $addon['id']; ?>" class="action-btn edit-btn" title="Editar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </a>
                                <a href="profile.php?stats=<?php echo $addon['id']; ?>" class="action-btn stats-btn" title="Estadísticas">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="20" x2="18" y2="10"></line>
                                        <line x1="12" y1="20" x2="12" y2="4"></line>
                                        <line x1="6" y1="20" x2="6" y2="14"></line>
                                    </svg>
                                </a>
                                <button class="action-btn delete-btn" title="Eliminar" onclick="showDeleteConfirm(<?php echo $addon['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Confirmación de eliminación -->
                            <div class="confirm-delete" id="confirmDelete<?php echo $addon['id']; ?>">
                                <p class="confirm-text">¿Eliminar este addon permanentemente?</p>
                                <div class="confirm-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="addon_id" value="<?php echo $addon['id']; ?>">
                                        <button type="submit" name="delete_addon" class="confirm-btn confirm-yes">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Sí
                                        </button>
                                    </form>
                                    <button class="confirm-btn confirm-no" onclick="hideDeleteConfirm(<?php echo $addon['id']; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                        No
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Contenido de la tarjeta -->
                            <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada del addon" class="addon-cover">
                            <div class="addon-info">
                                <h3 class="addon-title"><?php echo htmlspecialchars($addon['title']); ?></h3>
                                <p class="addon-description"><?php echo htmlspecialchars($addon['description']); ?></p>
                                <div class="addon-meta">
                                    <span>Versión: <?php echo htmlspecialchars($addon['version']); ?></span>
                                    <span><?php echo date('d/m/Y', strtotime($addon['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-addons">
                    <p>No hay addons publicados. <a href="upload.php" style="color: var(--primary-color);">¡Sube tu primer addon!</a></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Menú desplegable del perfil
        document.getElementById('profilePic').addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.toggle('show');
        });

        // Cerrar el menú desplegable al hacer clic fuera de él
        window.addEventListener('click', function(event) {
            if (!event.target.matches('#profilePic')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        });

        // Mostrar confirmación de eliminación
        function showDeleteConfirm(addonId) {
            // Ocultar todos los confirm primero
            document.querySelectorAll('.confirm-delete').forEach(el => {
                el.style.display = 'none';
            });
            
            // Mostrar solo el confirm del addon seleccionado
            const confirmEl = document.getElementById('confirmDelete' + addonId);
            if (confirmEl) {
                confirmEl.style.display = 'block';
                
                // Cerrar al hacer clic fuera
                setTimeout(() => {
                    const clickOutsideHandler = (e) => {
                        if (!confirmEl.contains(e.target) && !e.target.closest('.delete-btn')) {
                            confirmEl.style.display = 'none';
                            document.removeEventListener('click', clickOutsideHandler);
                        }
                    };
                    document.addEventListener('click', clickOutsideHandler);
                }, 10);
            }
        }
        
        // Ocultar confirmación de eliminación
        function hideDeleteConfirm(addonId) {
            const confirmEl = document.getElementById('confirmDelete' + addonId);
            if (confirmEl) {
                confirmEl.style.display = 'none';
            }
        }
    </script>
</body>
</html>