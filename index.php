<?php
require_once 'config.php';

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

// Añadir condiciones de búsqueda si hay una consulta
if (!empty($searchQuery)) {
    $query .= " WHERE a.title LIKE :search OR u.username LIKE :search ";
}

$query .= " GROUP BY a.id ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);

if (!empty($searchQuery)) {
    $searchParam = "%$searchQuery%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
$addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si estamos mostrando favoritos
$showFavorites = isset($_GET['view']) && $_GET['view'] === 'favorites' && isLoggedIn();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCPixel - Addons para Minecraft</title>
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
        }

        body[data-theme="light"] {
            background-color: var(--light-bg);
            color: var(--light-text);
        }

        /* Estilos para la interfaz de redes sociales */
        .social-widget {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            box-shadow: var(--shadow-lg);
            padding: 1rem;
            width: 300px;
            z-index: 1001;
            border: 1px solid var(--dark-border);
            display: none;
        }

        body[data-theme="light"] .social-widget {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .widget-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--dark-text);
        }

        body[data-theme="light"] .widget-title {
            color: var(--light-text);
        }

        .widget-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .widget-close:hover {
            color: var(--error-color);
            transform: rotate(90deg);
        }

        .widget-content {
            margin-bottom: 1rem;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        body[data-theme="light"] .widget-content {
            color: #64748b;
        }

        .widget-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .widget-btn {
            flex: 1;
            padding: 0.5rem;
            border-radius: var(--rounded);
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .widget-btn-discord {
            background-color: #5865F2;
            color: white;
        }

        .widget-btn-discord:hover {
            background-color: #4752C4;
            transform: translateY(-2px);
        }

        .widget-btn-whatsapp {
            background-color: #25D366;
            color: white;
        }

        .widget-btn-whatsapp:hover {
            background-color: #1DA851;
            transform: translateY(-2px);
        }

        .widget-icon {
            width: 1.25rem;
            height: 1.25rem;
        }

        /* Resto de tus estilos existentes... */
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

        /* Estilos para el buscador */
        .search-section {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1));
            padding: 2rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border-bottom: 1px solid rgba(59, 130, 246, 0.2);
        }

        body[data-theme="light"] .search-section {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(16, 185, 129, 0.05));
            border-bottom-color: rgba(59, 130, 246, 0.1);
        }

        .search-container {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }

        .search-form {
            display: flex;
            align-items: center;
        }

        .search-input {
            flex-grow: 1;
            padding: 0.75rem 1.5rem;
            padding-right: 3rem;
            border-radius: 2rem;
            border: 2px solid var(--dark-border);
            background-color: var(--dark-card);
            color: var(--dark-text);
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        body[data-theme="light"] .search-input {
            background-color: var(--light-card);
            color: var(--light-text);
            border-color: var(--light-border);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .search-button {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            transition: var(--transition);
            background-color: var(--primary-color);
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-button svg {
            width: 1rem;
            height: 1rem;
        }

        .search-button:hover {
            background-color: var(--primary-hover);
            transform: scale(1.05);
        }

        .search-results-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: var(--dark-card);
            border-radius: 0 0 var(--rounded) var(--rounded);
            box-shadow: var(--shadow-lg);
            max-height: 20rem;
            overflow-y: auto;
            z-index: 1001;
            border: 1px solid var(--dark-border);
            border-top: none;
        }

        body[data-theme="light"] .search-results-dropdown {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--dark-border);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        body[data-theme="light"] .search-result-item {
            border-bottom-color: var(--light-border);
        }

        .search-result-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        body[data-theme="light"] .search-result-item:hover {
            background-color: #f3f4f6;
        }

        .search-result-item img {
            width: 2.5rem;
            height: 2.5rem;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .search-result-info {
            flex-grow: 1;
        }

        .search-result-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--dark-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .search-result-author {
            font-size: 0.75rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .search-result-author img {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
        }

        .search-result-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: var(--secondary-color);
        }

        .no-results {
            padding: 1rem;
            text-align: center;
            color: var(--dark-text);
            font-size: 0.9rem;
        }

        .container {
            padding: 0 2rem 2rem;
            max-width: 90rem;
            margin: 0 auto;
        }

        .search-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .search-results-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-text);
            background: linear-gradient(90deg, #3B82F6, #10B981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        
        .search-results-count {
            font-size: 0.9rem;
            color: #94a3b8;
        }
        
        body[data-theme="light"] .search-results-title {
            color: var(--light-text);
        }
        
        .clear-search {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            align-self: flex-start;
            margin-top: 1rem;
            box-shadow: var(--shadow);
        }
        
        .clear-search:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .error-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            text-align: center;
            color: var(--dark-text);
            grid-column: 1 / -1;
            background-color: var(--dark-card);
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
            margin: 2rem 0;
        }
        
        body[data-theme="light"] .error-message {
            color: var(--light-text);
            background-color: var(--light-card);
        }
        
        .error-icon {
            font-size: 3rem;
            color: var(--error-color);
            margin-bottom: 1rem;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(239, 68, 68, 0.1);
        }
        
        .error-text {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .error-details {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            max-width: 30rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--dark-text);
            position: relative;
            padding-bottom: 0.75rem;
        }

        .page-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 4rem;
            height: 0.25rem;
            background: linear-gradient(90deg, #3B82F6, #10B981);
            border-radius: 0.25rem;
        }

        body[data-theme="light"] .page-title {
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
            text-decoration: none;
            color: inherit;
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

        .addon-rating {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .rating-stars {
            display: flex;
            gap: 0.1rem;
        }

        .rating-star {
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .empty-star {
            color: #94a3b8;
            font-size: 1rem;
        }

        body[data-theme="light"] .empty-star {
            color: #64748b;
        }

        .rating-count {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        body[data-theme="light"] .rating-count {
            color: #64748b;
        }

        .addon-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-footer {
            color: #64748b;
            border-top-color: var(--light-border);
        }

        .addon-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            max-width: 70%;
        }

        .author-pic {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            flex-shrink: 0;
        }

        .author-name {
            text-decoration: none;
            color: inherit;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .addon-version {
            background-color: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .verified-icon {
            color: var(--accent-color);
            margin-left: 0.25rem;
            font-size: 0.875rem;
        }

        .verified-user-icon {
            color: #94a3b8;
            margin-left: 0.15rem;
            font-size: 1rem;
            vertical-align: middle;
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
        }

        body[data-theme="light"] .verified-user-icon {
            color: #64748b;
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

        .add-addon-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            border: none;
            transition: var(--transition);
            text-decoration: none;
        }

        .add-addon-btn:hover {
            background-color: var(--primary-hover);
            transform: scale(1.1) translateY(-0.25rem);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);
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
            
            .search-section {
                padding: 1.5rem 1rem;
            }
            
            .login-modal-buttons {
                flex-direction: column;
            }
            
            .modal-btn {
                width: 100%;
            }

            .social-widget {
                width: calc(100% - 2rem);
                left: 1rem;
                right: 1rem;
                bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar">
        <div class="logo-container">
            <img src="/img/logo.png" alt="MCPixel Logo" class="logo">
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

    <!-- Sección del buscador -->
    <section class="search-section">
        <div class="search-container">
            <form id="searchForm" method="GET" action="" class="search-form">
                <input type="text" class="search-input" id="searchInput" placeholder="Buscar addons por título o autor..." 
                       value="<?php echo htmlspecialchars($searchQuery); ?>">
                <button type="submit" class="search-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </form>
            <div class="search-results-dropdown" id="searchResults"></div>
        </div>
    </section>

    <!-- Contenido principal -->
    <div class="container">
        <?php if (!empty($searchQuery)): ?>
            <div class="search-header">
                <h2 class="search-results-title">Resultados Encontrados</h2>
                <span class="search-results-count"><?php echo count($addons); ?> resultados</span>
                
                <?php if (count($addons) > 0): ?>
                    <a href="?" class="clear-search">Limpiar búsqueda</a>
                <?php endif; ?>
            </div>
            
            <?php if (count($addons) > 0): ?>
                <div class="addons-grid">
                    <?php foreach ($addons as $addon): ?>
                        <?php if (isLoggedIn()): ?>
                            <a href="addon.php?id=<?php echo $addon['id']; ?>" class="addon-card">
                        <?php else: ?>
                            <div class="addon-card" onclick="showLoginModal()">
                        <?php endif; ?>
                                <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada del addon" class="addon-cover">
                                <div class="addon-info">
                                    <h3 class="addon-title"><?php echo htmlspecialchars($addon['title']); ?>
                                        <?php if ($addon['verified']): ?>
                                            <span class="verified-icon" title="Addon verificado">✓</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="addon-description"><?php echo htmlspecialchars($addon['description']); ?></p>
                                    
                                    <div class="addon-rating">
                                        <div class="rating-stars">
                                            <?php
                                            $avgRating = round($addon['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $avgRating) {
                                                    echo '<span class="rating-star">★</span>';
                                                } else {
                                                    echo '<span class="empty-star">☆</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-count"><?php echo $addon['review_count']; ?> reseña(s)</span>
                                    </div>
                                    
                                    <div class="addon-footer">
                                        <div class="addon-author">
                                            <img src="./uploads/<?php echo htmlspecialchars($addon['profile_pic']); ?>" alt="Autor" class="author-pic">
                                            <span class="author-name"><?php echo htmlspecialchars($addon['username']); ?>
                                                <?php if ($addon['is_verified']): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-user-icon" width="16" height="16">
                                                        <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <span class="addon-version"><?php echo htmlspecialchars($addon['version']); ?></span>
                                    </div>
                                </div>
                        <?php if (isLoggedIn()): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="error-message">
                    <div class="error-icon">!</div>
                    <h3 class="error-text">Error de búsqueda</h3>
                    <p class="error-details">No se encontró el complemento que buscabas</p>
                    <a href="?" class="clear-search">Limpiar búsqueda</a>
                </div>
            <?php endif; ?>
        <?php elseif ($showFavorites): ?>
            <h1 class="page-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="1.5rem" height="1.5rem" style="vertical-align: middle; margin-right: 0.5rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                Tus Addons Favoritos
            </h1>
            <?php 
                $stmt = $pdo->prepare("
                    SELECT a.*, u.username, u.profile_pic, u.is_verified,
                           AVG(r.rating) as avg_rating, 
                           COUNT(r.id) as review_count 
                    FROM addons a 
                    JOIN usuarios u ON a.user_id = u.id 
                    JOIN favoritos f ON a.id = f.addon_id 
                    LEFT JOIN reviews r ON a.id = r.addon_id 
                    WHERE f.user_id = ? 
                    GROUP BY a.id 
                    ORDER BY f.created_at DESC
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $favoriteAddons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($favoriteAddons) > 0): 
            ?>
                <div class="addons-grid">
                    <?php foreach ($favoriteAddons as $addon): ?>
                        <a href="addon.php?id=<?php echo $addon['id']; ?>" class="addon-card">
                            <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada del addon" class="addon-cover">
                            <div class="addon-info">
                                <h3 class="addon-title"><?php echo htmlspecialchars($addon['title']); ?>
                                    <?php if ($addon['verified']): ?>
                                        <span class="verified-icon" title="Addon verificado">✓</span>
                                    <?php endif; ?>
                                </h3>
                                <p class="addon-description"><?php echo htmlspecialchars($addon['description']); ?></p>
                                
                                <div class="addon-rating">
                                    <div class="rating-stars">
                                        <?php
                                        $avgRating = round($addon['avg_rating']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $avgRating) {
                                                echo '<span class="rating-star">★</span>';
                                            } else {
                                                echo '<span class="empty-star">☆</span>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <span class="rating-count"><?php echo $addon['review_count']; ?> reseña(s)</span>
                                </div>
                                
                                <div class="addon-footer">
                                    <div class="addon-author">
                                        <img src="./uploads/<?php echo htmlspecialchars($addon['profile_pic']); ?>" alt="Autor" class="author-pic">
                                        <span class="author-name"><?php echo htmlspecialchars($addon['username']); ?>
                                            <?php if ($addon['is_verified']): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-user-icon" width="16" height="16">
                                                    <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                                                </svg>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <span class="addon-version"><?php echo htmlspecialchars($addon['version']); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-addons">
                    <p>No tienes addons favoritos aún.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <h1 class="page-title">Últimos Addons</h1>
            <?php if (count($addons) > 0): ?>
                <div class="addons-grid">
                    <?php foreach ($addons as $addon): ?>
                        <?php if (isLoggedIn()): ?>
                            <a href="addon.php?id=<?php echo $addon['id']; ?>" class="addon-card">
                        <?php else: ?>
                            <div class="addon-card" onclick="showLoginModal()">
                        <?php endif; ?>
                                <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada del addon" class="addon-cover">
                                <div class="addon-info">
                                    <h3 class="addon-title"><?php echo htmlspecialchars($addon['title']); ?>
                                        <?php if ($addon['verified']): ?>
                                            <span class="verified-icon" title="Addon verificado">✓</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="addon-description"><?php echo htmlspecialchars($addon['description']); ?></p>
                                    
                                    <div class="addon-rating">
                                        <div class="rating-stars">
                                            <?php
                                            $avgRating = round($addon['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $avgRating) {
                                                    echo '<span class="rating-star">★</span>';
                                                } else {
                                                    echo '<span class="empty-star">☆</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-count"><?php echo $addon['review_count']; ?> reseña(s)</span>
                                    </div>
                                    
                                    <div class="addon-footer">
                                        <div class="addon-author">
                                            <img src="./uploads/<?php echo htmlspecialchars($addon['profile_pic']); ?>" alt="Autor" class="author-pic">
                                            <span class="author-name"><?php echo htmlspecialchars($addon['username']); ?>
                                                <?php if ($addon['is_verified']): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-user-icon" width="16" height="16">
                                                        <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <span class="addon-version"><?php echo htmlspecialchars($addon['version']); ?></span>
                                    </div>
                                </div>
                        <?php if (isLoggedIn()): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-addons">
                    <p>No hay addons publicados aún.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Botón flotante para añadir addon -->
    <?php if (isLoggedIn()): ?>
        <a href="add_addon.php" class="add-addon-btn">+</a>
    <?php endif; ?>

    <!-- Interfaz de redes sociales -->
    <div class="social-widget" id="socialWidget">
        <div class="widget-header">
            <div class="widget-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#5865F2">
                    <path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
                <span>¡Únete a nuestra comunidad!</span>
            </div>
            <button class="widget-close" id="widgetClose">&times;</button>
        </div>
        <div class="widget-content">
            Hola, Perdona por molestar tu búsqueda de addons, Pero te invitamos cordialmente a Nuestro Grupo de Discord oficial de Nuestro Studio de <strong>MegaPixel</strong>, donde compartiremos nuestros proyectos y resolveremos tus dudas. Nos ayudarías demasiado con tu apoyo.
        </div>
        <div class="widget-buttons">
            <a href="https://discord.gg/RMfzSyNxjT" target="_blank" class="widget-btn widget-btn-discord">
                <svg class="widget-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
                MegaPixel
            </a>
            <a href="https://whatsapp.com/channel/0029VbAYKWw4yltQ6JzedF1W" target="_blank" class="widget-btn widget-btn-whatsapp">
                <svg class="widget-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                MCPixel
            </a>
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
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Para ver y descargar los addons, por favor inicia sesión o regístrate en nuestra plataforma.
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

        // Búsqueda en tiempo real
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const searchForm = document.getElementById('searchForm');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetchSearchResults(query);
            }, 300);
        });

        searchInput.addEventListener('focus', function() {
            const query = this.value.trim();
            if (query.length >= 2) {
                fetchSearchResults(query);
            }
        });

        function fetchSearchResults(query) {
            fetch(`search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    displaySearchResults(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function displaySearchResults(results) {
            searchResults.innerHTML = '';
            
            if (results.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'no-results';
                noResults.textContent = 'No se encontraron resultados';
                searchResults.appendChild(noResults);
            } else {
                results.forEach(result => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.addEventListener('click', () => {
                        window.location.href = `?search=${encodeURIComponent(result.title)}`;
                    });
                    
                    item.innerHTML = `
                        <img src="./uploads/${result.cover_image}" alt="${result.title}">
                        <div class="search-result-info">
                            <div class="search-result-title">${result.title} ${result.verified ? '<span class="verified-icon" title="Addon verificado">✓</span>' : ''}</div>
                            <div class="search-result-author">
                                <img src="./uploads/${result.profile_pic}" alt="${result.username}">
                                ${result.username}
                                ${result.is_verified ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="verified-user-icon" width="16" height="16"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>' : ''}
                            </div>
                        </div>
                        <div class="search-result-rating">
                            ${result.avg_rating ? '★'.repeat(Math.round(result.avg_rating)) : 'Sin valoraciones'}
                        </div>
                    `;
                    
                    searchResults.appendChild(item);
                });
            }
            
            searchResults.style.display = results.length > 0 ? 'block' : 'none';
        }

        // Cerrar resultados al hacer clic fuera
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.search-container')) {
                searchResults.style.display = 'none';
            }
        });

        // Enviar formulario al presionar Enter
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `?search=${encodeURIComponent(query)}`;
            }
        });

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

        // Control de la interfaz de redes sociales
        const socialWidget = document.getElementById('socialWidget');
        const widgetClose = document.getElementById('widgetClose');

        // Mostrar la interfaz al cargar la página (solo si no se ha cerrado antes)
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar si el usuario ya cerró el widget anteriormente
            if (!localStorage.getItem('widgetClosed')) {
                // Mostrar después de 3 segundos
                setTimeout(() => {
                    socialWidget.style.display = 'block';
                }, 3000);
            }
        });

        // Cerrar la interfaz y recordar la preferencia del usuario
        widgetClose.addEventListener('click', function() {
            socialWidget.style.display = 'none';
            // Guardar en localStorage que el usuario cerró el widget
            localStorage.setItem('widgetClosed', 'true');
        });
    </script>
</body>
</html>
