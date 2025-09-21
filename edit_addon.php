<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$addonId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$addonId) {
    $_SESSION['error_message'] = 'Addon no válido';
    header('Location: profile.php');
    exit;
}

// Obtener información del addon
$stmt = $pdo->prepare("SELECT * FROM addons WHERE id = ? AND user_id = ?");
$stmt->execute([$addonId, $_SESSION['user_id']]);
$addon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$addon) {
    $_SESSION['error_message'] = 'Addon no encontrado o no tienes permiso para editarlo';
    header('Location: profile.php');
    exit;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos
        $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING));
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
        $version = trim(filter_input(INPUT_POST, 'version', FILTER_SANITIZE_STRING));
        $tags = trim(filter_input(INPUT_POST, 'tag', FILTER_SANITIZE_STRING));
        
        if (empty($title) || empty($description) || empty($version) || empty($tags)) {
            throw new Exception('Todos los campos obligatorios deben ser completados');
        }

        // Validar versión (solo números, puntos y guiones)
        if (!preg_match('/^[0-9\.\-]+$/', $version)) {
            throw new Exception('La versión solo puede contener números, puntos y guiones');
        }

        // Procesar imagen si se subió una nueva
        $coverImage = $addon['cover_image'];
        if (!empty($_FILES['cover_image']['name'])) {
            // Validar archivo
            $file = $_FILES['cover_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Formato de imagen no válido. Solo se permiten JPG, PNG, GIF o WebP');
            }
            
            if ($file['size'] > $maxSize) {
                throw new Exception('La imagen no puede superar los 2MB');
            }
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid() . '.' . $extension;
            
            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newFilename)) {
                // Eliminar imagen anterior si existe
                if (!empty($coverImage) && file_exists(UPLOAD_DIR . $coverImage)) {
                    @unlink(UPLOAD_DIR . $coverImage);
                }
                $coverImage = $newFilename;
            } else {
                throw new Exception('Error al subir la imagen');
            }
        }
        
        // Actualizar en la base de datos
        $stmt = $pdo->prepare("UPDATE addons SET title = ?, description = ?, version = ?, tag = ?, cover_image = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description, $version, $tags, $coverImage, $addonId]);
        
        $_SESSION['success_message'] = 'Addon actualizado correctamente';
        header('Location: profile.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Obtener información del usuario para mostrar en el formulario
$user = getUserInfo($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar <?php echo htmlspecialchars($addon['title']); ?> | MCPixel</title>
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
            padding: 2rem;
            max-width: 60rem;
            margin: 0 auto;
        }

        .edit-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: 2rem;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            box-shadow: var(--shadow);
        }

        .back-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .edit-form {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .edit-form {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        body[data-theme="light"] .form-label {
            color: var(--light-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
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

        textarea.form-control {
            min-height: 10rem;
            resize: vertical;
        }

        .image-preview {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            margin-top: 1rem;
        }

        .current-image {
            max-width: 100%;
            max-height: 20rem;
            border-radius: var(--rounded);
            box-shadow: var(--shadow);
            object-fit: cover;
        }

        .file-input {
            display: none;
        }

        .file-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: 2rem;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            box-shadow: var(--shadow);
        }

        .file-label:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .submit-btn:hover {
            background-color: #0d9f6e;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--rounded);
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .tag-hint {
            font-size: 0.8125rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        body[data-theme="light"] .tag-hint {
            color: #64748b;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .edit-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .edit-form {
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
        <!-- Mensajes -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="edit-header">
            <h1 class="page-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="1.5rem" height="1.5rem" style="vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Editar Addon: <?php echo htmlspecialchars($addon['title']); ?>
            </h1>
            <a href="profile.php" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Volver al perfil
            </a>
        </div>
        
        <form class="edit-form" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title" class="form-label">Título del Addon</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($addon['title']); ?>" required>
                <div class="error-text" id="titleError"></div>
            </div>
            
            <div class="form-group">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($addon['description']); ?></textarea>
                <div class="error-text" id="descriptionError"></div>
            </div>
            
            <div class="form-group">
                <label for="version" class="form-label">Versión compatible con Minecraft</label>
                <input type="text" id="version" name="version" class="form-control" value="<?php echo htmlspecialchars($addon['version']); ?>" required>
                <div class="error-text" id="versionError"></div>
                <div class="tag-hint">Solo números, puntos y guiones (ejemplo: 1.20.1)</div>
            </div>
            
            <div class="form-group">
                <label for="tag" class="form-label">Etiquetas</label>
                <input type="text" id="tag" name="tag" class="form-control" value="<?php echo htmlspecialchars($addon['tag'] ?? ''); ?>" required>
                <div class="error-text" id="tagError"></div>
                <div class="tag-hint">Separa múltiples etiquetas con comas (ejemplo: texturas, 1.20, realista)</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Imagen de portada</label>
                
                <?php if (!empty($addon['cover_image'])): ?>
                    <div class="image-preview">
                        <img src="./uploads/<?php echo htmlspecialchars($addon['cover_image']); ?>" alt="Portada actual" class="current-image">
                    </div>
                <?php endif; ?>
                
                <input type="file" id="cover_image" name="cover_image" class="file-input" accept="image/jpeg, image/png, image/gif, image/webp">
                <label for="cover_image" class="file-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h7m4 0h6m-6 6h6m-6 6h6"/>
                    </svg>
                    <?php echo empty($addon['cover_image']) ? 'Subir imagen' : 'Cambiar imagen'; ?>
                </label>
                <div class="error-text" id="coverImageError"></div>
                <div class="tag-hint">Formatos aceptados: JPG, PNG, GIF, WebP (Máx. 2MB)</div>
            </div>
            
            <button type="submit" class="submit-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                    <path d="M17 21v-8H7v8M7 3v5h8"/>
                </svg>
                Guardar cambios
            </button>
        </form>
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

        // Mostrar nombre de archivo seleccionado
        document.getElementById('cover_image').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Ningún archivo seleccionado';
            document.querySelector('.file-label').innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h7m4 0h6m-6 6h6m-6 6h6"/>
                </svg>
                ${fileName}
            `;
            document.getElementById('coverImageError').style.display = 'none';
        });

        // Validar versión (solo números, puntos y guiones)
        document.getElementById('version').addEventListener('input', function() {
            if (!/^[0-9\.\-]*$/.test(this.value)) {
                this.value = this.value.replace(/[^0-9\.\-]/g, '');
                document.getElementById('versionError').textContent = 'Solo se permiten números, puntos y guiones';
                document.getElementById('versionError').style.display = 'block';
            } else {
                document.getElementById('versionError').style.display = 'none';
            }
        });

        // Validación del formulario
        document.querySelector('.edit-form').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validar título
            const title = document.getElementById('title').value.trim();
            if (title === '') {
                document.getElementById('titleError').textContent = 'El título es requerido';
                document.getElementById('titleError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('titleError').style.display = 'none';
            }
            
            // Validar descripción
            const description = document.getElementById('description').value.trim();
            if (description === '') {
                document.getElementById('descriptionError').textContent = 'La descripción es requerida';
                document.getElementById('descriptionError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('descriptionError').style.display = 'none';
            }
            
            // Validar versión
            const version = document.getElementById('version').value.trim();
            if (version === '' || !/^[0-9\.\-]+$/.test(version)) {
                document.getElementById('versionError').textContent = 'La versión es requerida y solo puede contener números, puntos y guiones';
                document.getElementById('versionError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('versionError').style.display = 'none';
            }
            
            // Validar etiquetas
            const tags = document.getElementById('tag').value.trim();
            if (tags === '') {
                document.getElementById('tagError').textContent = 'Debes añadir al menos una etiqueta';
                document.getElementById('tagError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('tagError').style.display = 'none';
            }
            
            // Validar imagen si se seleccionó una
            const fileInput = document.getElementById('cover_image');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (!allowedTypes.includes(file.type)) {
                    document.getElementById('coverImageError').textContent = 'Formato de imagen no válido. Solo se permiten JPG, PNG, GIF o WebP';
                    document.getElementById('coverImageError').style.display = 'block';
                    isValid = false;
                } else if (file.size > maxSize) {
                    document.getElementById('coverImageError').textContent = 'La imagen no puede superar los 2MB';
                    document.getElementById('coverImageError').style.display = 'block';
                    isValid = false;
                } else {
                    document.getElementById('coverImageError').style.display = 'none';
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                // Desplazarse al primer error
                const firstError = document.querySelector('.error-text[style="display: block;"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        // Limpiar espacios en las etiquetas
        document.getElementById('tag').addEventListener('blur', function() {
            this.value = this.value.split(',').map(tag => tag.trim()).filter(tag => tag !== '').join(', ');
        });
    </script>
</body>
</html>