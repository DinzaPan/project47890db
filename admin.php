<?php
require_once 'config.php';

// Verificar autenticación y permisos de administrador
function checkAdminAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $_SESSION['error_message'] = 'Acceso denegado: Se requieren privilegios de administrador';
        header('Location: index.php');
        exit;
    }
}

checkAdminAccess();

// Constantes
define('PER_PAGE', 15);

// Funciones de administración
class AdminActions {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function deleteUser($userId) {
        try {
            $this->pdo->beginTransaction();
            
            // Obtener addons del usuario
            $stmt = $this->pdo->prepare("SELECT id, cover_image FROM addons WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Eliminar cada addon y sus archivos
            while ($addon = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->deleteAddonFiles($addon['id'], $addon['cover_image']);
            }
            
            // Eliminar registros relacionados
            $this->pdo->prepare("DELETE FROM favoritos WHERE user_id = ?")->execute([$userId]);
            $this->pdo->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$userId]);
            $this->pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? OR following_id = ?")->execute([$userId, $userId]);
            $this->pdo->prepare("DELETE FROM addons WHERE user_id = ?")->execute([$userId]);
            $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$userId]);
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting user: " . $e->getMessage());
            return false;
        }
    }
    
    public function toggleBanUser($userId, $ban = true) {
        try {
            $this->pdo->prepare("UPDATE usuarios SET is_banned = ? WHERE id = ?")
                ->execute([$ban ? 1 : 0, $userId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error toggling user ban: " . $e->getMessage());
            return false;
        }
    }
    
    public function toggleVerifyUser($userId, $verify = true) {
        try {
            $this->pdo->prepare("UPDATE usuarios SET is_verified = ? WHERE id = ?")
                ->execute([$verify ? 1 : 0, $userId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error toggling user verification: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteAddon($addonId) {
        try {
            $this->pdo->beginTransaction();
            
            // Obtener información del addon
            $stmt = $this->pdo->prepare("SELECT cover_image FROM addons WHERE id = ?");
            $stmt->execute([$addonId]);
            $addon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($addon) {
                $this->deleteAddonFiles($addonId, $addon['cover_image']);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting addon: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteReview($reviewId) {
        try {
            $this->pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$reviewId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error deleting review: " . $e->getMessage());
            return false;
        }
    }
    
    private function deleteAddonFiles($addonId, $coverImage) {
        try {
            // Eliminar imagen de portada si existe
            if (!empty($coverImage) && file_exists(UPLOAD_DIR . $coverImage)) {
                @unlink(UPLOAD_DIR . $coverImage);
            }
            
            // Eliminar de favoritos
            $this->pdo->prepare("DELETE FROM favoritos WHERE addon_id = ?")->execute([$addonId]);
            
            // Eliminar reseñas
            $this->pdo->prepare("DELETE FROM reviews WHERE addon_id = ?")->execute([$addonId]);
            
            // Eliminar estadísticas
            $this->pdo->prepare("DELETE FROM addon_stats WHERE addon_id = ?")->execute([$addonId]);
            
            // Eliminar addon
            $this->pdo->prepare("DELETE FROM addons WHERE id = ?")->execute([$addonId]);
        } catch (PDOException $e) {
            throw $e;
        }
    }
}

// Procesar acciones administrativas
$adminActions = new AdminActions($pdo);
$actionSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_user'])) {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($userId) {
                $actionSuccess = $adminActions->deleteUser($userId);
                $_SESSION['message'] = $actionSuccess ? 'Usuario eliminado correctamente' : 'Error al eliminar usuario';
            }
        } elseif (isset($_POST['ban_user'])) {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($userId) {
                $actionSuccess = $adminActions->toggleBanUser($userId, true);
                $_SESSION['message'] = $actionSuccess ? 'Usuario bloqueado correctamente' : 'Error al bloquear usuario';
            }
        } elseif (isset($_POST['unban_user'])) {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($userId) {
                $actionSuccess = $adminActions->toggleBanUser($userId, false);
                $_SESSION['message'] = $actionSuccess ? 'Usuario desbloqueado correctamente' : 'Error al desbloquear usuario';
            }
        } elseif (isset($_POST['verify_user'])) {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($userId) {
                $actionSuccess = $adminActions->toggleVerifyUser($userId, true);
                $_SESSION['message'] = $actionSuccess ? 'Usuario verificado correctamente' : 'Error al verificar usuario';
            }
        } elseif (isset($_POST['unverify_user'])) {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($userId) {
                $actionSuccess = $adminActions->toggleVerifyUser($userId, false);
                $_SESSION['message'] = $actionSuccess ? 'Verificación removida correctamente' : 'Error al remover verificación';
            }
        } elseif (isset($_POST['delete_addon'])) {
            $addonId = filter_input(INPUT_POST, 'addon_id', FILTER_VALIDATE_INT);
            if ($addonId) {
                $actionSuccess = $adminActions->deleteAddon($addonId);
                $_SESSION['message'] = $actionSuccess ? 'Addon eliminado correctamente' : 'Error al eliminar addon';
            }
        } elseif (isset($_POST['delete_review'])) {
            $reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
            if ($reviewId) {
                $actionSuccess = $adminActions->deleteReview($reviewId);
                $_SESSION['message'] = $actionSuccess ? 'Reseña eliminada correctamente' : 'Error al eliminar reseña';
            }
        }
    } catch (Exception $e) {
        error_log("Admin action error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Ocurrió un error al procesar la acción';
    }
    
    // Redirigir para evitar reenvío de formulario
    header("Location: admin.php?" . http_build_query($_GET));
    exit;
}

// Obtener parámetros de búsqueda/filtro
$filterType = isset($_GET['filter']) && in_array($_GET['filter'], ['users', 'addons', 'reviews']) ? $_GET['filter'] : 'users';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Clase para manejar la obtención de datos
class AdminData {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getUsers($searchQuery = '', $page = 1, $perPage = PER_PAGE) {
        $query = "SELECT * FROM usuarios WHERE 1=1";
        $params = [];
        
        if (!empty($searchQuery)) {
            $query .= " AND (username LIKE ? OR email LIKE ? OR id = ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = $searchQuery;
        }
        
        return $this->getPaginatedResults($query, $params, $page, $perPage);
    }
    
    public function getAddons($searchQuery = '', $page = 1, $perPage = PER_PAGE) {
        $query = "SELECT a.*, u.username FROM addons a JOIN usuarios u ON a.user_id = u.id WHERE 1=1";
        $params = [];
        
        if (!empty($searchQuery)) {
            $query .= " AND (a.title LIKE ? OR a.description LIKE ? OR u.username LIKE ? OR a.id = ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = $searchQuery;
        }
        
        return $this->getPaginatedResults($query, $params, $page, $perPage);
    }
    
    public function getReviews($searchQuery = '', $page = 1, $perPage = PER_PAGE) {
        $query = "SELECT r.*, u.username, u.profile_pic, a.title as addon_title 
                 FROM reviews r 
                 JOIN usuarios u ON r.user_id = u.id 
                 JOIN addons a ON r.addon_id = a.id 
                 WHERE 1=1";
        $params = [];
        
        if (!empty($searchQuery)) {
            $query .= " AND (u.username LIKE ? OR a.title LIKE ? OR r.comment LIKE ? OR r.id = ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = $searchQuery;
        }
        
        return $this->getPaginatedResults($query, $params, $page, $perPage);
    }
    
    public function getStats() {
        $query = "
            SELECT 
                (SELECT COUNT(*) FROM usuarios) as total_users,
                (SELECT COUNT(*) FROM usuarios WHERE is_banned = 1) as banned_users,
                (SELECT COUNT(*) FROM usuarios WHERE is_verified = 1) as verified_users,
                (SELECT COUNT(*) FROM addons) as total_addons,
                (SELECT COUNT(*) FROM favoritos) as total_favorites,
                (SELECT COUNT(*) FROM reviews) as total_reviews
        ";
        
        $stmt = $this->pdo->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getPaginatedResults($query, $params, $page, $perPage) {
        // Contar total
        $countQuery = preg_replace('/SELECT .*? FROM/i', 'SELECT COUNT(*) FROM', $query, 1);
        $countStmt = $this->pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalItems = $countStmt->fetchColumn();
        
        // Obtener datos paginados
        $query .= " ORDER BY created_at DESC LIMIT :offset, :limit";
        $stmt = $this->pdo->prepare($query);
        
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $totalItems,
            'pages' => ceil($totalItems / $perPage)
        ];
    }
}

// Obtener datos
$adminData = new AdminData($pdo);
$stats = $adminData->getStats();

if ($filterType === 'users') {
    $data = $adminData->getUsers($searchQuery, $page, PER_PAGE);
} elseif ($filterType === 'addons') {
    $data = $adminData->getAddons($searchQuery, $page, PER_PAGE);
} else {
    $data = $adminData->getReviews($searchQuery, $page, PER_PAGE);
}

$items = $data['items'] ?? [];
$totalItems = $data['total'] ?? 0;
$totalPages = $data['pages'] ?? 1;

// Función para truncar texto largo
function truncate($text, $length = 20) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

// Obtener tema actual
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="es" class="<?= $theme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | MCPixel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .admin-table th {
            background-color: rgba(15, 23, 42, 0.05);
        }
        .dark .admin-table th {
            background-color: rgba(255, 255, 255, 0.05);
        }
        .addon-cover {
            width: 4rem;
            height: 3rem;
            object-fit: cover;
            border-radius: 0.25rem;
        }
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            object-fit: cover;
        }
        .review-comment {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tooltip {
            position: relative;
            display: inline-block;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        .scrollable-table {
            overflow-x: auto;
            max-width: 100%;
        }
        .verified-badge {
            color: #3b82f6;
            margin-left: 2px;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">
    <!-- Barra de navegación -->
    <nav class="bg-indigo-600 text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="./img/logo.png" alt="MCPixel Logo" class="h-10">
                <h1 class="text-xl font-bold">Panel de Administración</h1>
            </div>
            
            <div class="flex space-x-4">
                <a href="admin.php?filter=users" class="px-3 py-2 rounded hover:bg-indigo-700 <?= $filterType === 'users' ? 'bg-indigo-800' : '' ?>">
                    <i class="fas fa-users mr-2"></i>Usuarios
                </a>
                <a href="admin.php?filter=addons" class="px-3 py-2 rounded hover:bg-indigo-700 <?= $filterType === 'addons' ? 'bg-indigo-800' : '' ?>">
                    <i class="fas fa-cubes mr-2"></i>Addons
                </a>
                <a href="admin.php?filter=reviews" class="px-3 py-2 rounded hover:bg-indigo-700 <?= $filterType === 'reviews' ? 'bg-indigo-800' : '' ?>">
                    <i class="fas fa-comments mr-2"></i>Reseñas
                </a>
                <a href="index.php" class="px-3 py-2 rounded hover:bg-indigo-700">
                    <i class="fas fa-arrow-left mr-2"></i>Volver al sitio
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container mx-auto px-4 py-6">
        <!-- Mensajes -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['message']); ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-gray-500 dark:text-gray-400 text-sm">Usuarios totales</div>
                <div class="text-2xl font-bold"><?= $stats['total_users'] ?></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-gray-500 dark:text-gray-400 text-sm">Usuarios bloqueados</div>
                <div class="text-2xl font-bold"><?= $stats['banned_users'] ?></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-gray-500 dark:text-gray-400 text-sm">Usuarios verificados</div>
                <div class="text-2xl font-bold"><?= $stats['verified_users'] ?></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-gray-500 dark:text-gray-400 text-sm">Addons totales</div>
                <div class="text-2xl font-bold"><?= $stats['total_addons'] ?></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-gray-500 dark:text-gray-400 text-sm">Favoritos totales</div>
                <div class="text-2xl font-bold"><?= $stats['total_favorites'] ?></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-gray-500 dark:text-gray-400 text-sm">Reseñas totales</div>
                <div class="text-2xl font-bold"><?= $stats['total_reviews'] ?></div>
            </div>
        </div>

        <!-- Barra de búsqueda -->
        <form method="get" class="mb-6">
            <input type="hidden" name="filter" value="<?= $filterType ?>">
            <div class="flex gap-2">
                <input type="text" name="search" class="flex-grow px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                       placeholder="Buscar <?= $filterType === 'users' ? 'usuarios...' : ($filterType === 'addons' ? 'addons...' : 'reseñas...') ?>" 
                       value="<?= htmlspecialchars($searchQuery) ?>">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <i class="fas fa-search mr-2"></i>Buscar
                </button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="admin.php?filter=<?= $filterType ?>" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        <i class="fas fa-times mr-2"></i>Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Contenido según filtro -->
        <?php if ($filterType === 'users'): ?>
            <!-- Listado de usuarios -->
            <?php if (!empty($items)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
                    <div class="scrollable-table">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 admin-table">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Registro</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($items as $user): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $user['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img src="./uploads/<?= htmlspecialchars($user['profile_pic'] ?? 'default.png') ?>" alt="Avatar" class="user-avatar mr-3">
                                                <div>
                                                    <div class="font-medium tooltip">
                                                        <?= htmlspecialchars(truncate($user['username'], 15)) ?>
                                                        <?php if ($user['is_verified']): ?>
                                                            <i class="fas fa-check-circle verified-badge" title="Usuario verificado"></i>
                                                        <?php endif; ?>
                                                        <?php if (strlen($user['username']) > 15): ?>
                                                            <span class="tooltiptext"><?= htmlspecialchars($user['username']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-gray-500 dark:text-gray-400 text-sm"><?= htmlspecialchars(truncate($user['email'], 20)) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($user['is_banned']): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Bloqueado</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Activo</span>
                                            <?php endif; ?>
                                            <?php if ($user['is_verified']): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Verificado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex space-x-2">
                                                <?php if ($user['is_banned']): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" name="unban_user" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                                            <i class="fas fa-unlock mr-1"></i> Desbloquear
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" name="ban_user" class="px-3 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 text-sm">
                                                            <i class="fas fa-lock mr-1"></i> Bloquear
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($user['is_verified']): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" name="unverify_user" class="px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
                                                            <i class="fas fa-times-circle mr-1"></i> Remover verificación
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" name="verify_user" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                                            <i class="fas fa-check-circle mr-1"></i> Verificar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" onsubmit="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="delete_user" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm">
                                                        <i class="fas fa-trash mr-1"></i> Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                    <div class="text-gray-400 dark:text-gray-500 text-5xl mb-4">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No se encontraron usuarios</h3>
                    <p class="text-gray-500 dark:text-gray-400">No hay usuarios que coincidan con tu búsqueda</p>
                </div>
            <?php endif; ?>
        <?php elseif ($filterType === 'addons'): ?>
            <!-- Listado de addons -->
            <?php if (!empty($items)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
                    <div class="scrollable-table">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 admin-table">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Portada</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Título</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Creador</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($items as $addon): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $addon['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <img src="./uploads/<?= htmlspecialchars($addon['cover_image'] ?? 'default-cover.png') ?>" alt="Portada" class="addon-cover">
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium tooltip">
                                                <?= htmlspecialchars(truncate($addon['title'], 20)) ?>
                                                <?php if (strlen($addon['title']) > 20): ?>
                                                    <span class="tooltiptext"><?= htmlspecialchars($addon['title']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-400 text-sm line-clamp-2"><?= htmlspecialchars(truncate($addon['description'], 50)) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap tooltip">
                                            <?= htmlspecialchars(truncate($addon['username'], 12)) ?>
                                            <?php if (strlen($addon['username']) > 12): ?>
                                                <span class="tooltiptext"><?= htmlspecialchars($addon['username']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y', strtotime($addon['created_at'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex space-x-2">
                                                <a href="index.php?view=addon&id=<?= $addon['id'] ?>" class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm" target="_blank">
                                                    <i class="fas fa-eye mr-1"></i> Ver
                                                </a>
                                                <form method="post" onsubmit="return confirm('¿Estás seguro de eliminar este addon? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="addon_id" value="<?= $addon['id'] ?>">
                                                    <button type="submit" name="delete_addon" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm">
                                                        <i class="fas fa-trash mr-1"></i> Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                    <div class="text-gray-400 dark:text-gray-500 text-5xl mb-4">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No se encontraron addons</h3>
                    <p class="text-gray-500 dark:text-gray-400">No hay addons que coincidan con tu búsqueda</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Listado de reseñas -->
            <?php if (!empty($items)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
                    <div class="scrollable-table">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 admin-table">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Addon</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Calificación</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Comentario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($items as $review): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $review['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img src="./uploads/<?= htmlspecialchars($review['profile_pic'] ?? 'default.png') ?>" alt="Avatar" class="user-avatar mr-3">
                                                <div class="tooltip">
                                                    <?= htmlspecialchars(truncate($review['username'], 12)) ?>
                                                    <?php if (strlen($review['username']) > 12): ?>
                                                        <span class="tooltiptext"><?= htmlspecialchars($review['username']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap tooltip">
                                            <?= htmlspecialchars(truncate($review['addon_title'], 15)) ?>
                                            <?php if (strlen($review['addon_title']) > 15): ?>
                                                <span class="tooltiptext"><?= htmlspecialchars($review['addon_title']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $review['rating']): ?>
                                                        <span class="text-yellow-500">★</span>
                                                    <?php else: ?>
                                                        <span class="text-gray-300">☆</span>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="review-comment tooltip">
                                                <?= htmlspecialchars(truncate($review['comment'], 30)) ?>
                                                <?php if (strlen($review['comment']) > 30): ?>
                                                    <span class="tooltiptext"><?= htmlspecialchars($review['comment']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y', strtotime($review['created_at'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="post" onsubmit="return confirm('¿Estás seguro de eliminar esta reseña? Esta acción no se puede deshacer.');">
                                                <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                                <button type="submit" name="delete_review" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm">
                                                    <i class="fas fa-trash mr-1"></i> Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                    <div class="text-gray-400 dark:text-gray-500 text-5xl mb-4">
                        <i class="fas fa-comment-slash"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No se encontraron reseñas</h3>
                    <p class="text-gray-500 dark:text-gray-400">No hay reseñas que coincidan con tu búsqueda</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="inline-flex rounded-md shadow">
                    <?php if ($page > 1): ?>
                        <a href="?filter=<?= $filterType ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $page - 1 ?>" class="px-3 py-2 rounded-l-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <i class="fas fa-chevron-left mr-1"></i> Anterior
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Mostrar solo algunas páginas alrededor de la actual
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="?filter=<?= $filterType ?>&search=<?= urlencode($searchQuery) ?>&page=1" class="px-3 py-2 border-t border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                            1
                        </a>
                        <?php if ($startPage > 2): ?>
                            <span class="px-3 py-2 border-t border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                                ...
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?filter=<?= $filterType ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $i ?>" class="px-3 py-2 border-t border-b border-gray-300 dark:border-gray-700 <?= $i === $page ? 'bg-indigo-50 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-200 font-medium' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="px-3 py-2 border-t border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                                ...
                            </span>
                        <?php endif; ?>
                        <a href="?filter=<?= $filterType ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $totalPages ?>" class="px-3 py-2 border-t border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <?= $totalPages ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?filter=<?= $filterType ?>&search=<?= urlencode($searchQuery) ?>&page=<?= $page + 1 ?>" class="px-3 py-2 rounded-r-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Siguiente <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Confirmación para acciones peligrosas
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.onsubmit = function(e) {
                if (!confirm(this.getAttribute('data-confirm') || '¿Estás seguro de realizar esta acción?')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            };
        });
        
        // Manejo del tema oscuro/claro
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</body>
</html>