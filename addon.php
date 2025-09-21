<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$addonId = $_GET['id'];

// Registrar visita al addon
if (!isset($_SESSION['addon_views'])) {
    $_SESSION['addon_views'] = [];
}

if (!in_array($addonId, $_SESSION['addon_views'])) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO addon_stats (addon_id, user_ip) VALUES (?, ?)");
        $stmt->execute([$addonId, $ip]);
        $_SESSION['addon_views'][] = $addonId;
    } catch (PDOException $e) {
        error_log("Error al registrar visita al addon: " . $e->getMessage());
    }
}

// Obtener información del addon con verificación del usuario
$stmt = $pdo->prepare("SELECT a.*, u.username, u.profile_pic, u.id as user_id, u.is_verified FROM addons a JOIN usuarios u ON a.user_id = u.id WHERE a.id = ?");
$stmt->execute([$addonId]);
$addon = $stmt->fetch();

if (!$addon) {
    header('Location: index.php');
    exit;
}

// Obtener reseñas con verificación del usuario
$stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_pic, u.is_verified FROM reviews r JOIN usuarios u ON r.user_id = u.id WHERE r.addon_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$addonId]);
$reviews = $stmt->fetchAll();

// Obtener respuestas a las reseñas
$reviewIds = array_map(function($review) { return $review['id']; }, $reviews);
$repliesByReview = [];
if (!empty($reviewIds)) {
    $reviewIdsPlaceholder = implode(',', array_fill(0, count($reviewIds), '?'));
    $stmt = $pdo->prepare("SELECT rr.*, u.username, u.profile_pic, u.is_verified FROM review_replies rr JOIN usuarios u ON rr.user_id = u.id WHERE rr.review_id IN ($reviewIdsPlaceholder) ORDER BY rr.created_at ASC");
    $stmt->execute($reviewIds);
    $replies = $stmt->fetchAll();

    foreach ($replies as $reply) {
        $repliesByReview[$reply['review_id']][] = $reply;
    }
}

// Obtener promedio de calificaciones
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE addon_id = ?");
$stmt->execute([$addonId]);
$ratingInfo = $stmt->fetch();

// Obtener número de visitas
$stmt = $pdo->prepare("SELECT COUNT(*) as views FROM addon_stats WHERE addon_id = ?");
$stmt->execute([$addonId]);
$views = $stmt->fetch(PDO::FETCH_ASSOC)['views'];

// Verificar si el usuario actual ya ha dejado una reseña
$userHasReviewed = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE addon_id = ? AND user_id = ?");
    $stmt->execute([$addonId, $_SESSION['user_id']]);
    $userReviewId = $stmt->fetchColumn();
    $userHasReviewed = $userReviewId !== false;
    
    // Verificar si el usuario sigue al creador del addon
    $stmt = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user_id'], $addon['user_id']]);
    $isFollowing = $stmt->fetchColumn();
}

// Procesar formulario de reseña si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    if (isset($_POST['delete_review'])) {
        // Eliminar reseña
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_review'], $_SESSION['user_id']]);
        $userHasReviewed = false;
        
        // Actualizar información de calificación
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE addon_id = ?");
        $stmt->execute([$addonId]);
        $ratingInfo = $stmt->fetch();
        
        // Redirigir para evitar reenvío del formulario
        header("Location: addon.php?id=$addonId");
        exit;
    } elseif (isset($_POST['delete_reply'])) {
        // Eliminar respuesta a reseña
        // Primero verificamos que la respuesta pertenezca al usuario o que el usuario sea el creador del addon
        $stmt = $pdo->prepare("SELECT rr.user_id, r.addon_id FROM review_replies rr JOIN reviews r ON rr.review_id = r.id WHERE rr.id = ?");
        $stmt->execute([$_POST['delete_reply']]);
        $replyInfo = $stmt->fetch();
        
        if ($replyInfo && ($replyInfo['user_id'] == $_SESSION['user_id'] || $addon['user_id'] == $_SESSION['user_id'])) {
            $stmt = $pdo->prepare("DELETE FROM review_replies WHERE id = ?");
            $stmt->execute([$_POST['delete_reply']]);
            
            // Redirigir para evitar reenvío del formulario
            header("Location: addon.php?id=$addonId");
            exit;
        }
    } elseif (isset($_POST['rating']) && isset($_POST['comment']) && !$userHasReviewed) {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        
        if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
            $stmt = $pdo->prepare("INSERT INTO reviews (addon_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$addonId, $_SESSION['user_id'], $rating, $comment]);
            
            // Actualizar información de calificación
            $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE addon_id = ?");
            $stmt->execute([$addonId]);
            $ratingInfo = $stmt->fetch();
            
            $userHasReviewed = true;
            
            // Redirigir para evitar reenvío del formulario
            header("Location: addon.php?id=$addonId");
            exit;
        }
    } elseif (isset($_POST['reply']) && isset($_POST['review_id'])) {
        $reply = trim($_POST['reply']);
        $reviewId = (int)$_POST['review_id'];
        
        if (!empty($reply) && ($_SESSION['user_id'] == $addon['user_id'])) {
            // Solo el creador del addon puede responder
            $stmt = $pdo->prepare("INSERT INTO review_replies (review_id, user_id, reply) VALUES (?, ?, ?)");
            $stmt->execute([$reviewId, $_SESSION['user_id'], $reply]);
            
            // Redirigir para evitar reenvío del formulario
            header("Location: addon.php?id=$addonId");
            exit;
        }
    }
}

// Procesar seguir/dejar de seguir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && isset($_POST['follow'])) {
    if ($isFollowing) {
        // Dejar de seguir
        $stmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$_SESSION['user_id'], $addon['user_id']]);
        $isFollowing = false;
    } else {
        // Seguir
        $stmt = $pdo->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $addon['user_id']]);
        $isFollowing = true;
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: addon.php?id=$addonId");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($addon['title']); ?> - MCPixel</title>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WMKN9TN9H"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-3WMKN9TN9H');
    </script>
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

        .addon-header {
            margin-bottom: 2rem;
        }

        .addon-cover {
            width: 100%;
            max-height: 30rem;
            object-fit: cover;
            border-radius: var(--rounded-lg);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--dark-border);
            max-width: 100%;
        }

        body[data-theme="light"] .addon-cover {
            border-color: var(--light-border);
        }

        .addon-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark-text);
            word-wrap: break-word;
        }

        body[data-theme="light"] .addon-title {
            color: var(--light-text);
        }

        .addon-author {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .author-pic {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            max-width: 100%;
        }

        .author-name {
            font-weight: 600;
            color: var(--dark-text);
            word-break: break-word;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            text-decoration: none;
        }

        body[data-theme="light"] .author-name {
            color: var(--light-text);
        }

        .verified-icon {
            color: #94a3b8;
            font-size: 1.125rem;
        }

        body[data-theme="light"] .verified-icon {
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
            margin-left: 1rem;
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

        .addon-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark-text);
            font-size: 0.9rem;
        }

        body[data-theme="light"] .meta-item {
            color: var(--light-text);
        }

        .meta-item svg {
            width: 1.25rem;
            height: 1.25rem;
            color: var(--primary-color);
        }

        .addon-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .addon-tag {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            word-break: break-word;
        }

        .addon-description {
            margin-bottom: 2rem;
            line-height: 1.6;
            color: var(--dark-text);
            white-space: pre-line;
            word-wrap: break-word;
            max-width: 100%;
        }

        body[data-theme="light"] .addon-description {
            color: var(--light-text);
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--rounded);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            max-width: 100%;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .download-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .download-btn svg {
            width: 1rem;
            height: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 2rem 0 1rem;
            color: var(--dark-text);
            position: relative;
            padding-bottom: 0.5rem;
            word-break: break-word;
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

        .rating-container {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .average-rating {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .rating-count {
            color: var(--dark-text);
            font-size: 0.9rem;
        }

        body[data-theme="light"] .rating-count {
            color: var(--light-text);
        }

        .stars {
            display: flex;
            gap: 0.25rem;
        }

        .star {
            color: var(--secondary-color);
            font-size: 1.5rem;
        }

        .empty-star {
            color: #94a3b8;
            font-size: 1.5rem;
        }

        body[data-theme="light"] .empty-star {
            color: #64748b;
        }

        .review-form {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .review-form {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        body[data-theme="light"] .form-label {
            color: var(--light-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            border: 1px solid var(--dark-border);
            border-radius: var(--rounded);
            background-color: var(--dark-card);
            color: var(--dark-text);
            transition: var(--transition);
            max-width: 100%;
            box-sizing: border-box;
            font-family: inherit;
        }

        body[data-theme="light"] .form-control {
            background-color: var(--light-card);
            color: var(--light-text);
            border-color: var(--light-border);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .submit-btn {
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: var(--rounded);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            background-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
            font-family: inherit;
        }

        .submit-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .review-card {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
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
            flex-wrap: wrap;
        }

        .reviewer-pic {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            max-width: 100%;
        }

        .reviewer-name {
            font-weight: 600;
            color: var(--dark-text);
            word-break: break-word;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            text-decoration: none;
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
            font-size: 1rem;
            color: var(--secondary-color);
        }

        .review-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .review-content {
            color: var(--dark-text);
            line-height: 1.6;
            word-wrap: break-word;
        }

        body[data-theme="light"] .review-content {
            color: var(--light-text);
        }

        .review-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .delete-review-btn {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: var(--transition);
            font-family: inherit;
            padding: 0;
        }

        .delete-review-btn:hover {
            text-decoration: underline;
        }

        .no-reviews {
            text-align: center;
            padding: 3rem;
            color: var(--dark-text);
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
        }

        body[data-theme="light"] .no-reviews {
            color: var(--light-text);
            background-color: var(--light-card);
        }

        .stats-card {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .stats-card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .stats-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stats-label {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        body[data-theme="light"] .stats-label {
            color: #64748b;
        }

        .view-stats-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--rounded);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.9rem;
            box-shadow: var(--shadow);
        }

        .view-stats-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .view-stats-btn svg {
            width: 1rem;
            height: 1rem;
        }

        /* Modal de login */
        .login-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        body[data-theme="light"] .login-modal {
            background-color: rgba(241, 245, 249, 0.8);
        }

        .login-modal-content {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            padding: 2rem;
            max-width: 28rem;
            width: 90%;
            box-shadow: var(--shadow-lg);
            text-align: center;
            animation: modalFadeIn 0.3s ease-out;
            border: 1px solid var(--dark-border);
            position: relative;
        }

        body[data-theme="light"] .login-modal-content {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(1rem);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-modal h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        body[data-theme="light"] .login-modal h3 {
            color: var(--light-text);
        }

        .login-modal p {
            color: #94a3b8;
            margin-bottom: 2rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .modal-btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .modal-btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background-color: transparent;
            color: var(--dark-text);
            border: 2px solid var(--dark-border);
        }

        body[data-theme="light"] .modal-btn-secondary {
            color: var(--light-text);
            border-color: var(--light-border);
        }

        .modal-btn-secondary:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: var(--primary-color);
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--primary-color);
            transform: rotate(90deg);
        }

        .login-icon {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .download-icon {
            color: var(--accent-color);
            font-size: 1.5rem;
        }

        /* Estilos para respuestas a reseñas */
        .review-replies {
            margin-top: 1rem;
            padding-left: 2rem;
            border-left: 2px solid var(--dark-border);
        }

        body[data-theme="light"] .review-replies {
            border-left-color: var(--light-border);
        }

        .reply-card {
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            padding: 1rem;
            margin-top: 0.75rem;
            position: relative;
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .reply-card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .reply-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .reply-author {
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.9rem;
            text-decoration: none;
        }

        body[data-theme="light"] .reply-author {
            color: var(--light-text);
        }

        .reply-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        body[data-theme="light"] .reply-date {
            color: #64748b;
        }

        .reply-content {
            color: var(--dark-text);
            line-height: 1.5;
            font-size: 0.9rem;
            word-wrap: break-word;
        }

        body[data-theme="light"] .reply-content {
            color: var(--light-text);
        }

        .reply-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }

        .delete-reply-btn {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: var(--transition);
            font-family: inherit;
            padding: 0;
        }

        .delete-reply-btn:hover {
            text-decoration: underline;
        }

        .reply-form {
            margin-top: 1rem;
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            padding: 1rem;
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .reply-form {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .reply-input {
            width: 100%;
            padding: 0.75rem;
            border-radius: var(--rounded);
            border: 1px solid var(--dark-border);
            background-color: var(--dark-bg);
            color: var(--dark-text);
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }

        body[data-theme="light"] .reply-input {
            background-color: var(--light-bg);
            color: var(--light-text);
            border-color: var(--light-border);
        }

        .reply-submit-btn {
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--rounded);
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition);
        }

        .reply-submit-btn:hover {
            background-color: var(--primary-hover);
        }

        .author-badge {
            background-color: var(--primary-color);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 2rem;
            margin-left: 0.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem 1rem;
            }
            
            .navbar {
                padding: 0.75rem 1rem;
            }
            
            .addon-title {
                font-size: 1.5rem;
            }
            
            .addon-meta {
                gap: 1rem;
            }
            
            .login-modal-buttons {
                flex-direction: column;
            }
            
            .modal-btn {
                width: 100%;
            }
            
            .review-replies {
                padding-left: 1rem;
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
                <?php $user = getUserInfo($pdo, $_SESSION['user_id']); ?>
                <img src="./uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Foto de perfil" class="profile-pic" id="profilePic">
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

        <div class="addon-header">
            <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada del addon" class="addon-cover">
            <h1 class="addon-title"><?php echo htmlspecialchars($addon['title']); ?></h1>
            
            <div class="addon-author">
                <img src="./uploads/<?php echo htmlspecialchars($addon['profile_pic']); ?>" alt="Autor" class="author-pic">
                <a href="profile_view.php?id=<?php echo $addon['user_id']; ?>" class="author-name">
                    <?php echo htmlspecialchars($addon['username']); ?>
                    <?php if ($addon['is_verified']): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-icon" width="18" height="18">
                            <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </a>
                <?php if (isLoggedIn() && $_SESSION['user_id'] != $addon['user_id']): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="follow" class="follow-btn <?php echo $isFollowing ? 'following' : ''; ?>">
                            <?php echo $isFollowing ? 'Siguiendo' : 'Seguir'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="addon-meta">
                <div class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo date('d/m/Y', strtotime($addon['created_at'])); ?></span>
                </div>
                <div class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>Minecraft <?php echo htmlspecialchars($addon['version']); ?></span>
                </div>
                <div class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <span><?php echo $ratingInfo['review_count'] ?? 0; ?> reseñas</span>
                </div>
                <div class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span><?php echo $views; ?> visitas</span>
                </div>
            </div>
            
            <?php if ($addon['tag']): ?>
                <div class="addon-tags">
                    <?php $tags = explode(',', $addon['tag']); ?>
                    <?php foreach ($tags as $tag): ?>
                        <?php if (trim($tag) !== ''): ?>
                            <span class="addon-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p class="addon-description"><?php echo htmlspecialchars($addon['description']); ?></p>
            
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo htmlspecialchars($addon['download_link']); ?>" class="download-btn" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar
                </a>
            <?php else: ?>
                <button class="download-btn" onclick="showLoginModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar (Requiere inicio de sesión)
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas rápidas -->
        <?php if (isLoggedIn() && $_SESSION['user_id'] == $addon['user_id']): ?>
            <div class="stats-card">
                <div class="stats-value"><?php echo $views; ?></div>
                <div class="stats-label">Visitas totales</div>
                <a href="profile.php?stats=<?php echo $addonId; ?>" class="view-stats-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Ver estadísticas completas
                </a>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Reseñas</h2>
        
        <?php if ($ratingInfo['review_count'] > 0): ?>
            <div class="rating-container">
                <div class="average-rating"><?php echo number_format($ratingInfo['avg_rating'], 1); ?></div>
                <div class="stars">
                    <?php
                    $avgRating = round($ratingInfo['avg_rating']);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $avgRating ? '<span class="star">★</span>' : '<span class="empty-star">☆</span>';
                    }
                    ?>
                </div>
                <div class="rating-count">(<?php echo $ratingInfo['review_count']; ?> reseñas)</div>
            </div>
        <?php else: ?>
            <p class="no-reviews">Este addon aún no tiene reseñas.</p>
        <?php endif; ?>
        
        <?php if (!isLoggedIn()): ?>
            <div class="review-form">
                <p>Debes <a href="login.php" style="color: var(--primary-color);">iniciar sesión</a> para dejar una reseña.</p>
            </div>
        <?php elseif (!$userHasReviewed && $_SESSION['user_id'] != $addon['user_id']): ?>
            <form method="POST" class="review-form">
                <div class="form-group">
                    <label class="form-label">Calificación</label>
                    <div class="stars" id="ratingStars">
                        <span class="star" data-rating="1">☆</span>
                        <span class="star" data-rating="2">☆</span>
                        <span class="star" data-rating="3">☆</span>
                        <span class="star" data-rating="4">☆</span>
                        <span class="star" data-rating="5">☆</span>
                        <input type="hidden" name="rating" id="selectedRating" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="comment" class="form-label">Comentario</label>
                    <textarea id="comment" name="comment" rows="4" class="form-control" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Enviar reseña</button>
            </form>
        <?php endif; ?>
        
        <div class="reviews-list">
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <img src="./uploads/<?php echo htmlspecialchars($review['profile_pic']); ?>" alt="Reseñador" class="reviewer-pic">
                            <a href="profile_view.php?id=<?php echo $review['user_id']; ?>" class="reviewer-name">
                                <?php echo htmlspecialchars($review['username']); ?>
                                <?php if ($review['is_verified']): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-icon" width="18" height="18">
                                        <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                                    </svg>
                                <?php endif; ?>
                            </a>
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
                        
                        <?php if (isLoggedIn() && $_SESSION['user_id'] == $review['user_id']): ?>
                            <div class="review-actions">
                                <form method="POST">
                                    <input type="hidden" name="delete_review" value="<?php echo $review['id']; ?>">
                                    <button type="submit" class="delete-review-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18"></path>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2 2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                        Eliminar reseña
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Respuestas a la reseña -->
                        <?php if (isset($repliesByReview[$review['id']])): ?>
                            <div class="review-replies">
                                <?php foreach ($repliesByReview[$review['id']] as $reply): ?>
                                    <div class="reply-card">
                                        <div class="reply-header">
                                            <img src="./uploads/<?php echo htmlspecialchars($reply['profile_pic']); ?>" alt="Autor de la respuesta" class="reviewer-pic" style="width: 2rem; height: 2rem;">
                                            <div>
                                                <a href="profile_view.php?id=<?php echo $reply['user_id']; ?>" class="reply-author">
                                                    <?php echo htmlspecialchars($reply['username']); ?>
                                                    <?php if ($reply['is_verified']): ?>
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                                                            <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                                                        </svg>
                                                    <?php endif; ?>
                                                    <?php if ($reply['user_id'] == $addon['user_id']): ?>
                                                        <span class="author-badge">Creador</span>
                                                    <?php endif; ?>
                                                </a>
                                                <div class="review-date"><?php echo date('d/m/Y', strtotime($reply['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        <p class="reply-content"><?php echo htmlspecialchars($reply['reply']); ?></p>
                                        
                                        <?php if (isLoggedIn() && ($_SESSION['user_id'] == $reply['user_id'] || $_SESSION['user_id'] == $addon['user_id'])): ?>
                                            <div class="reply-actions">
                                                <form method="POST">
                                                    <input type="hidden" name="delete_reply" value="<?php echo $reply['id']; ?>">
                                                    <button type="submit" class="delete-reply-btn">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M3 6h18"></path>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2 2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                        </svg>
                                                        Eliminar respuesta
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Formulario para responder (solo para el creador del addon) -->
                        <?php if (isLoggedIn() && $_SESSION['user_id'] == $addon['user_id']): ?>
                            <form method="POST" class="reply-form">
                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                <textarea name="reply" class="reply-input" placeholder="Escribe tu respuesta..." required></textarea>
                                <button type="submit" class="reply-submit-btn">Responder</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-reviews">Este addon aún no tiene reseñas.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de login -->
    <div class="login-modal" id="loginModal">
        <div class="login-modal-content">
            <button class="close-modal" onclick="hideLoginModal()">&times;</button>
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="login-icon">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                Acceso Requerido
            </h3>
            <p>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="download-icon">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Para descargar este addon y dejar reseñas, por favor inicia sesión o regístrate en nuestra plataforma.
            </p>
            <div class="login-modal-buttons">
                <a href="login.php" class="modal-btn modal-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Iniciar Sesión
                </a>
                <a href="register.php" class="modal-btn modal-btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    Registrarse
                </a>
            </div>
        </div>
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

        // Sistema de estrellas para la reseña
        const stars = document.querySelectorAll('#ratingStars .star');
        const selectedRating = document.getElementById('selectedRating');
        
        if (stars.length > 0) {
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    selectedRating.value = rating;
                    
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.textContent = '★';
                            s.classList.add('filled');
                        } else {
                            s.textContent = '☆';
                            s.classList.remove('filled');
                        }
                    });
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = this.getAttribute('data-rating');
                    
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.textContent = '★';
                        } else {
                            s.textContent = '☆';
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    const currentRating = selectedRating.value;
                    
                    stars.forEach((s, index) => {
                        if (index < currentRating) {
                            s.textContent = '★';
                        } else {
                            s.textContent = '☆';
                        }
                    });
                });
            });
        }

        // Mostrar modal de login
        function showLoginModal() {
            document.getElementById('loginModal').style.display = 'flex';
        }

        // Ocultar modal de login
        function hideLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (event.target === document.getElementById('loginModal')) {
                hideLoginModal();
            }
        });
    </script>
</body>
</html>