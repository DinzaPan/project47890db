<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserInfo($pdo, $_SESSION['user_id']);

$error = '';
$success = '';
$errors = [];
$formData = [
    'username' => $user['username'],
    'profile_pic' => $user['profile_pic'],
    'bio' => $user['bio'] ?? ''
];

// Procesar cambios en los ajustes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cambios en el perfil
    if (isset($_POST['update_profile'])) {
        $formData['username'] = trim($_POST['username'] ?? '');
        $formData['bio'] = trim($_POST['bio'] ?? '');
        
        // Procesar imagen de perfil
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            list($successUpload, $result) = uploadFile($_FILES['profile_pic'], UPLOAD_DIR);
            
            if ($successUpload) {
                $formData['profile_pic'] = $result;
            } else {
                $errors = array_merge($errors, $result);
            }
        }
        
        // Validar nombre de usuario
        if (empty($formData['username'])) {
            $errors[] = "El nombre de usuario es obligatorio";
        } elseif (strlen($formData['username']) < 3) {
            $errors[] = "El nombre de usuario debe tener al menos 3 caracteres";
        }
        
        // Validar biografía
        if (strlen($formData['bio']) > 500) {
            $errors[] = "La biografía no puede exceder los 500 caracteres";
        }
        
        // Verificar si el nombre de usuario ya existe (si cambió)
        if ($formData['username'] !== $user['username']) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
            $stmt->execute([$formData['username'], $user['id']]);
            
            if ($stmt->fetch()) {
                $errors[] = "El nombre de usuario ya está en uso";
            }
        }
        
        // Verificar si han pasado 30 días desde la última actualización
        if ($user['last_profile_update'] && 
            (time() - strtotime($user['last_profile_update'])) < 30 * 24 * 60 * 60) {
            $errors[] = "Solo puedes cambiar tu perfil cada 30 días";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, profile_pic = ?, bio = ?, last_profile_update = NOW() WHERE id = ?");
                $stmt->execute([$formData['username'], $formData['profile_pic'], $formData['bio'], $user['id']]);
                
                // Eliminar la imagen anterior si no es la predeterminada
                if ($user['profile_pic'] !== 'default.png' && $formData['profile_pic'] !== $user['profile_pic']) {
                    @unlink(UPLOAD_DIR . $user['profile_pic']);
                }
                
                $success = "Perfil actualizado correctamente";
                $user = getUserInfo($pdo, $user['id']); // Actualizar datos del usuario
            } catch (PDOException $e) {
                $errors[] = "Error al actualizar el perfil: " . $e->getMessage();
            }
        }
    }
    
    // Cambio de contraseña
    if (isset($_POST['update_password'])) {
        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        
        if (empty($currentPassword)) {
            $errors[] = "La contraseña actual es obligatoria";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "La contraseña actual es incorrecta";
        }
        
        if (empty($newPassword)) {
            $errors[] = "La nueva contraseña es obligatoria";
        } elseif (strlen($newPassword) < 6) {
            $errors[] = "La nueva contraseña debe tener al menos 6 caracteres";
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = "Las contraseñas no coinciden";
        }
        
        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                
                $success = "Contraseña actualizada correctamente";
            } catch (PDOException $e) {
                $errors[] = "Error al actualizar la contraseña: " . $e->getMessage();
            }
        }
    }
    
    // Cambiar tema
    if (isset($_POST['update_theme'])) {
        $theme = $_POST['theme'] === 'light' ? 'light' : 'dark';
        setcookie('theme', $theme, time() + (30 * 24 * 60 * 60), '/');
        $stmt = $pdo->prepare("UPDATE usuarios SET theme = ? WHERE id = ?");
        $stmt->execute([$theme, $user['id']]);
        $user['theme'] = $theme;
        $success = "Preferencias de tema actualizadas";
    }
    
    // Eliminar cuenta
    if (isset($_POST['delete_account'])) {
        $deletePassword = trim($_POST['delete_password'] ?? '');
        
        if (empty($deletePassword)) {
            $errors[] = "La contraseña es obligatoria para eliminar la cuenta";
        } elseif (!password_verify($deletePassword, $user['password'])) {
            $errors[] = "Contraseña incorrecta. No se pudo eliminar la cuenta";
        } else {
            try {
                // Iniciar transacción
                $pdo->beginTransaction();
                
                // Eliminar addons del usuario
                $stmt = $pdo->prepare("SELECT cover_image FROM addons WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($addons as $addon) {
                    @unlink(UPLOAD_DIR . $addon['cover_image']);
                }
                
                // Eliminar favoritos
                $pdo->prepare("DELETE FROM favoritos WHERE user_id = ?")->execute([$user['id']]);
                
                // Eliminar addons
                $pdo->prepare("DELETE FROM addons WHERE user_id = ?")->execute([$user['id']]);
                
                // Eliminar usuario
                $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$user['id']]);
                
                // Eliminar imagen de perfil si no es la predeterminada
                if ($user['profile_pic'] !== 'default.png') {
                    @unlink(UPLOAD_DIR . $user['profile_pic']);
                }
                
                // Confirmar transacción
                $pdo->commit();
                
                // Cerrar sesión y redirigir
                session_destroy();
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Error al eliminar la cuenta: " . $e->getMessage();
            }
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $user['theme']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes | MCPixel</title>
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
            max-width: 48rem;
            margin: 0 auto;
            width: 100%;
        }

        .card {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: var(--transition);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body[data-theme="light"] .section-title {
            color: var(--light-text);
        }

        .section-title svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
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
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            border: 1px solid var(--dark-border);
            border-radius: var(--rounded);
            background-color: var(--dark-card);
            color: var(--dark-text);
            transition: var(--transition);
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

        .form-textarea {
            min-height: 8rem;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--rounded);
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .avatar-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .avatar-preview {
            width: 8rem;
            height: 8rem;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            cursor: pointer;
            margin-bottom: 0.75rem;
            transition: var(--transition);
        }

        .avatar-preview:hover {
            transform: scale(1.05);
        }

        .avatar-label {
            font-size: 0.875rem;
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .avatar-label:hover {
            text-decoration: underline;
        }

        .avatar-input {
            display: none;
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .text-muted {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        body[data-theme="light"] .text-muted {
            color: #64748b;
        }

        .theme-selector {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .theme-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .theme-radio {
            appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            border: 2px solid var(--dark-border);
            outline: none;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
        }

        body[data-theme="light"] .theme-radio {
            border-color: var(--light-border);
        }

        .theme-radio:checked {
            border-color: var(--primary-color);
        }

        .theme-radio:checked::after {
            content: '';
            position: absolute;
            width: 0.75rem;
            height: 0.75rem;
            background-color: var(--primary-color);
            border-radius: 50%;
            top: 0.125rem;
            left: 0.125rem;
        }

        .danger-zone {
            border: 2px solid var(--error-color);
            border-radius: var(--rounded);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .danger-zone-title {
            color: var(--error-color);
            margin-top: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-zone-title svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .hidden {
            display: none;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--rounded);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .items-center {
            align-items: center;
        }

        .gap-4 {
            gap: 1rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        .w-full {
            width: 100%;
        }

        .bio-counter {
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: right;
            margin-top: 0.25rem;
        }

        body[data-theme="light"] .bio-counter {
            color: #64748b;
        }

        .bio-counter.warning {
            color: var(--warning-color);
        }

        .bio-counter.error {
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem 1rem;
            }
            
            .card {
                padding: 1.5rem;
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

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="section-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Perfil
            </h2>
            
            <form method="post" enctype="multipart/form-data">
                <div class="avatar-upload">
                    <img src="./uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Foto de perfil" class="avatar-preview" id="avatarPreview">
                    <label for="avatarInput" class="avatar-label">Cambiar foto de perfil</label>
                    <input type="file" id="avatarInput" name="profile_pic" accept="image/*" class="avatar-input">
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">Nombre de usuario</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bio" class="form-label">Biografía</label>
                    <textarea id="bio" name="bio" class="form-control form-textarea"><?php echo htmlspecialchars($formData['bio']); ?></textarea>
                    <div id="bioCounter" class="bio-counter"><?php echo strlen($formData['bio']); ?>/500</div>
                </div>
                
                <p class="text-muted">Solo puedes cambiar tu foto de perfil y nombre de usuario cada 30 días.</p>
                
                <div class="form-group mt-4">
                    <button type="submit" name="update_profile" class="btn btn-primary w-full">Guardar cambios</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="section-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Contraseña
            </h2>
            
            <form method="post">
                <div class="form-group">
                    <label for="current_password" class="form-label">Contraseña actual</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">Nueva contraseña</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" name="update_password" class="btn btn-primary w-full">Cambiar contraseña</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="section-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                </svg>
                Apariencia
            </h2>
            
            <form method="post">
                <div class="form-group">
                    <label>Tema de la interfaz</label>
                    <div class="theme-selector">
                        <label class="theme-option">
                            <input type="radio" name="theme" value="dark" <?php echo $user['theme'] === 'dark' ? 'checked' : ''; ?> class="theme-radio">
                            Oscuro
                        </label>
                        <label class="theme-option">
                            <input type="radio" name="theme" value="light" <?php echo $user['theme'] === 'light' ? 'checked' : ''; ?> class="theme-radio">
                            Claro
                        </label>
                    </div>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" name="update_theme" class="btn btn-primary w-full">Guardar preferencias</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="danger-zone">
                <h2 class="danger-zone-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Zona de riesgo
                </h2>
                
                <p class="text-muted">Estas acciones son irreversibles. Ten cuidado.</p>
                
                <div class="flex justify-between items-center gap-4 mt-4">
                    <a href="logout.php" class="btn btn-warning w-full">Cerrar sesión</a>
                    <button id="showDeleteForm" class="btn btn-danger w-full">Eliminar cuenta</button>
                </div>
                
                <form method="post" id="deleteAccountForm" class="hidden" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label for="delete_password" class="form-label">Contraseña (para confirmar)</label>
                        <input type="password" id="delete_password" name="delete_password" class="form-control" required>
                    </div>
                    
                    <input type="hidden" name="delete_account" value="1">
                    <div class="flex gap-4 mt-4">
                        <button type="button" id="cancelDelete" class="btn btn-secondary w-full">Cancelar</button>
                        <button type="submit" class="btn btn-danger w-full">Confirmar eliminación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Vista previa de la imagen de perfil
        const avatarPreview = document.getElementById('avatarPreview');
        const avatarInput = document.getElementById('avatarInput');

        avatarInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    avatarPreview.src = event.target.result;
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Contador de caracteres para la biografía
        const bioTextarea = document.getElementById('bio');
        const bioCounter = document.getElementById('bioCounter');

        if (bioTextarea && bioCounter) {
            bioTextarea.addEventListener('input', function() {
                const length = this.value.length;
                bioCounter.textContent = `${length}/500`;
                
                if (length > 450 && length <= 500) {
                    bioCounter.classList.add('warning');
                    bioCounter.classList.remove('error');
                } else if (length > 500) {
                    bioCounter.classList.remove('warning');
                    bioCounter.classList.add('error');
                } else {
                    bioCounter.classList.remove('warning');
                    bioCounter.classList.remove('error');
                }
            });
        }

        // Formulario de eliminación de cuenta
        const showDeleteForm = document.getElementById('showDeleteForm');
        const deleteAccountForm = document.getElementById('deleteAccountForm');
        const cancelDelete = document.getElementById('cancelDelete');

        showDeleteForm.addEventListener('click', function() {
            deleteAccountForm.classList.remove('hidden');
            this.style.display = 'none';
        });

        cancelDelete.addEventListener('click', function() {
            deleteAccountForm.classList.add('hidden');
            showDeleteForm.style.display = 'block';
        });

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