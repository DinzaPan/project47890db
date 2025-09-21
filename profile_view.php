<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_GET['id'];

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Verificar si el usuario actual sigue a este usuario
$isFollowing = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user_id'], $userId]);
    $isFollowing = $stmt->fetchColumn();
}

// Obtener los addons del usuario con su puntuación promedio
$stmt = $pdo->prepare("SELECT a.*, COALESCE(AVG(r.rating), 0) as avg_rating 
                      FROM addons a 
                      LEFT JOIN reviews r ON a.id = r.addon_id 
                      WHERE a.user_id = ? 
                      GROUP BY a.id 
                      ORDER BY a.created_at DESC");
$stmt->execute([$userId]);
$addons = $stmt->fetchAll();

// Obtener estadísticas del usuario
$stmt = $pdo->prepare("SELECT COUNT(*) as addon_count FROM addons WHERE user_id = ?");
$stmt->execute([$userId]);
$addonCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM user_follows WHERE following_id = ?");
$stmt->execute([$userId]);
$followerCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as following_count FROM user_follows WHERE follower_id = ?");
$stmt->execute([$userId]);
$followingCount = $stmt->fetchColumn();

// Procesar seguir/dejar de seguir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && isset($_POST['follow'])) {
    if ($isFollowing) {
        // Dejar de seguir
        $stmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$_SESSION['user_id'], $userId]);
        $isFollowing = false;
    } else {
        // Seguir
        $stmt = $pdo->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $userId]);
        $isFollowing = true;
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: profile_view.php?id=$userId");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - MCPixel</title>
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
            --verified-color: #94a3b8;
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
            overflow-x: hidden;
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
            max-width: 100%;
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
            max-width: 100%;
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
            max-width: 70rem;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
            padding: 0.5rem 1rem;
            background-color: var(--dark-card);
            color: var(--dark-text);
            border-radius: var(--rounded);
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid var(--dark-border);
            font-size: 0.9rem;
        }

        body[data-theme="light"] .back-btn {
            background-color: var(--light-card);
            color: var(--light-text);
            border-color: var(--light-border);
        }

        .back-btn:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: var(--primary-color);
        }

        .back-btn svg {
            width: 1rem;
            height: 1rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .profile-avatar {
            width: 8rem;
            height: 8rem;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            box-shadow: var(--shadow);
        }

        .profile-info {
            flex: 1;
            min-width: 200px;
        }

        .profile-name {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body[data-theme="light"] .profile-name {
            color: var(--light-text);
        }

        .profile-bio {
            color: var(--dark-text);
            margin-bottom: 1rem;
            word-wrap: break-word;
        }

        body[data-theme="light"] .profile-bio {
            color: var(--light-text);
        }

        .verified-icon {
            color: var(--verified-color);
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
        }

        .profile-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.25rem;
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

        .follow-btn {
            padding: 0.5rem 1.25rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .follow-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .following {
            background-color: var(--dark-border);
        }

        body[data-theme="light"] .following {
            background-color: var(--light-border);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 2rem 0 1rem;
            color: var(--dark-text);
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 3rem;
            height: 0.25rem;
            background: linear-gradient(90deg, #3B82F6, #10B981);
            border-radius: 0.25rem;
        }

        body[data-theme="light"] .section-title {
            color: var(--light-text);
        }

        .addons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .addon-card {
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .addon-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .addon-cover {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-bottom: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-cover {
            border-color: var(--light-border);
        }

        .addon-content {
            padding: 1rem;
        }

        .addon-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        body[data-theme="light"] .addon-title {
            color: var(--light-text);
        }

        .addon-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        body[data-theme="light"] .addon-meta {
            color: #64748b;
        }

        .addon-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .addon-rating .star {
            color: var(--secondary-color);
        }

        .addon-description {
            font-size: 0.875rem;
            color: var(--dark-text);
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-wrap: break-word;
        }

        body[data-theme="light"] .addon-description {
            color: var(--light-text);
        }

        .view-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--rounded);
            text-decoration: none;
            font-size: 0.8rem;
            transition: var(--transition);
            text-align: center;
            width: 100%;
        }

        .view-btn:hover {
            background-color: var(--primary-hover);
        }

        .no-addons {
            text-align: center;
            padding: 3rem;
            color: var(--dark-text);
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
        }

        body[data-theme="light"] .no-addons {
            color: var(--light-text);
            background-color: var(--light-card);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem 1rem;
            }
            
            .navbar {
                padding: 0.75rem 1rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .profile-info {
                text-align: center;
            }
            
            .profile-stats {
                justify-content: center;
            }
            
            .profile-name {
                justify-content: center;
            }
            
            .addons-grid {
                grid-template-columns: 1fr;
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
            <?php if (isLoggedIn()): ?>
                <?php $currentUser = getUserInfo($pdo, $_SESSION['user_id']); ?>
                <img src="./uploads/<?php echo htmlspecialchars($currentUser['profile_pic']); ?>" alt="Foto de perfil" class="profile-pic" id="profilePic">
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="profile.php">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Perfil
                    </a>
                    <a href="settings.php">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
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
                    <a href="./sc/index2.php">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Términos y Condiciones
                    </a>
                    <a href="logout.php">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Cerrar sesión
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" style="color: white; text-decoration: none; font-weight: 600; background-color: var(--primary-color); padding: 0.5rem 1.25rem; border-radius: 2rem; transition: var(--transition); display: flex; align-items: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Iniciar sesión
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container">
        <!-- Botón para volver al inicio -->
        <a href="index.php" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver al inicio
        </a>

        <div class="profile-header">
            <img src="./uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Avatar" class="profile-avatar">
            <div class="profile-info">
                <h1 class="profile-name">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if ($user['is_verified']): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-icon">
                            <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </h1>
                <?php if (!empty($user['bio'])): ?>
                    <p class="profile-bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                <?php endif; ?>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $addonCount; ?></div>
                        <div class="stat-label">Addons</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $followerCount; ?></div>
                        <div class="stat-label">Seguidores</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $followingCount; ?></div>
                        <div class="stat-label">Siguiendo</div>
                    </div>
                </div>
                
                <?php if (isLoggedIn() && $_SESSION['user_id'] != $userId): ?>
                    <form method="POST">
                        <button type="submit" name="follow" class="follow-btn <?php echo $isFollowing ? 'following' : ''; ?>">
                            <?php echo $isFollowing ? 'Siguiendo' : 'Seguir'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <h2 class="section-title">Addons publicados</h2>
        
        <?php if (count($addons) > 0): ?>
            <div class="addons-grid">
                <?php foreach ($addons as $addon): ?>
                    <div class="addon-card">
                        <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada del addon" class="addon-cover">
                        <div class="addon-content">
                            <h3 class="addon-title" title="<?php echo htmlspecialchars($addon['title']); ?>">
                                <?php echo htmlspecialchars($addon['title']); ?>
                            </h3>
                            <div class="addon-meta">
                                <span><?php echo date('d/m/Y', strtotime($addon['created_at'])); ?></span>
                                <span>•</span>
                                <span>MC <?php echo htmlspecialchars($addon['version']); ?></span>
                                <span>•</span>
                                <div class="addon-rating">
                                    <?php
                                    $rating = round($addon['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '<span class="star">★</span>' : '<span class="star">☆</span>';
                                    }
                                    ?>
                                    <span>(<?php echo number_format($addon['avg_rating'], 1); ?>)</span>
                                </div>
                            </div>
                            <p class="addon-description"><?php echo htmlspecialchars($addon['description']); ?></p>
                            <a href="addon.php?id=<?php echo $addon['id']; ?>" class="view-btn">Ver detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-addons">
                <p>Este usuario aún no ha publicado ningún addon.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Menú desplegable del perfil
        document.getElementById('profilePic')?.addEventListener('click', function() {
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
    </script>
</body>
</html>