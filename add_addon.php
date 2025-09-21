<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $tags = array_filter(array_map('trim', explode(',', $_POST['tag'] ?? '')));
    $tags = !empty($tags) ? implode(',', $tags) : null;
    $downloadLink = trim($_POST['downloadLink'] ?? '');
    
    if (empty($title)) {
        $errors[] = "El nombre del addon es requerido";
    }
    
    if (empty($description)) {
        $errors[] = "La descripción es requerida";
    }
    
    if (empty($version)) {
        $errors[] = "La versión de Minecraft es requerida";
    } elseif (!preg_match('/^[0-9\.\-]+$/', $version)) {
        $errors[] = "La versión solo puede contener números, puntos y guiones";
    }
    
    if (empty($tags)) {
        $errors[] = "Debes añadir al menos una etiqueta";
    }
    
    if (empty($downloadLink)) {
        $errors[] = "El enlace de descarga es requerido";
    } elseif (!filter_var($downloadLink, FILTER_VALIDATE_URL)) {
        $errors[] = "El enlace de descarga no es válido";
    } elseif (!preg_match('/mediafire\.com|mega\.(io|nz)/i', $downloadLink)) {
        $errors[] = "Solo se permiten enlaces de MediaFire o MEGA";
    }
    
    // Procesar imagen de portada
    $coverImage = '';
    if (isset($_FILES['coverImage']) && $_FILES['coverImage']['error'] === UPLOAD_ERR_OK) {
        list($successUpload, $result) = uploadFile($_FILES['coverImage'], UPLOAD_DIR);
        
        if ($successUpload) {
            $coverImage = $result;
        } else {
            $errors = array_merge($errors, $result);
        }
    } else {
        $errors[] = "La imagen de portada es requerida";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO addons (user_id, title, description, version, tag, download_link, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $version,
                $tags,
                $downloadLink,
                $coverImage
            ]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Error al guardar el addon: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publicar Addon - MCPixel</title>
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
            max-width: 50rem;
            margin: 0 auto;
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

        .addon-form {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--dark-border);
        }

        body[data-theme="light"] .addon-form {
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

        .cover-preview {
            width: 100%;
            height: 16rem;
            background-color: var(--dark-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            margin-bottom: 1rem;
            border-radius: var(--rounded);
            overflow: hidden;
            position: relative;
            border: 2px dashed var(--dark-border);
        }

        body[data-theme="light"] .cover-preview {
            background-color: var(--light-bg);
            border-color: var(--light-border);
        }

        .cover-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .cover-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--dark-text);
            text-align: center;
            padding: 1rem;
        }

        body[data-theme="light"] .cover-placeholder {
            color: var(--light-text);
        }

        .cover-placeholder svg {
            width: 2.5rem;
            height: 2.5rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color);
        }

        .cover-input {
            display: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            border-radius: 2rem;
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
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background-color: var(--dark-border);
            color: var(--dark-text);
        }

        body[data-theme="light"] .btn-secondary {
            background-color: var(--light-border);
            color: var(--light-text);
        }

        .btn-secondary:hover {
            background-color: #64748b;
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--rounded);
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .tag-hint {
            font-size: 0.8125rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        body[data-theme="light"] .tag-hint {
            color: #64748b;
        }

        .error-text {
            color: var(--error-color);
            font-size: 0.8125rem;
            margin-top: 0.25rem;
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
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
        <h1 class="page-title">Publicar nuevo Addon</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ¡Addon publicado con éxito! <a href="index.php" style="color: var(--success-color); font-weight: 600;">Volver al inicio</a>
            </div>
        <?php elseif (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="list-style-type: none;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="addon-form">
            <div class="form-group">
                <div class="cover-preview" id="coverPreview">
                    <div class="cover-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Haz clic para subir una imagen de portada</span>
                    </div>
                </div>
                <input type="file" id="coverImage" name="coverImage" accept="image/*" class="cover-input" required>
                <div class="error-text" id="coverImageError">La imagen de portada es requerida</div>
            </div>
            <div class="form-group">
                <label for="title" class="form-label">Nombre del Addon</label>
                <input type="text" id="title" name="title" class="form-control" required>
                <div class="error-text" id="titleError">El nombre del addon es requerido</div>
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" rows="4" class="form-control" required></textarea>
                <div class="error-text" id="descriptionError">La descripción es requerida</div>
            </div>
            <div class="form-group">
                <label for="version" class="form-label">Versión de Minecraft</label>
                <input type="text" id="version" name="version" class="form-control" required>
                <div class="error-text" id="versionError">La versión es requerida y solo puede contener números, puntos y guiones</div>
            </div>
            <div class="form-group">
                <label for="tag" class="form-label">Etiquetas</label>
                <input type="text" id="tag" name="tag" class="form-control" placeholder="Separadas por comas (ejemplo: texturas, 1.20, realista)" required>
                <div class="error-text" id="tagError">Debes añadir al menos una etiqueta</div>
                <div class="tag-hint">Puedes añadir múltiples etiquetas separadas por comas</div>
            </div>
            <div class="form-group">
                <label for="downloadLink" class="form-label">Link de descarga (MediaFire o MEGA)</label>
                <input type="url" id="downloadLink" name="downloadLink" class="form-control" required>
                <div class="error-text" id="downloadLinkError">El enlace de descarga es requerido y debe ser de MediaFire o MEGA</div>
            </div>
            <div class="form-actions">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Publicar</button>
            </div>
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

        // Vista previa de la imagen de portada
        const coverPreview = document.getElementById('coverPreview');
        const coverImage = document.getElementById('coverImage');
        
        coverPreview.addEventListener('click', function() {
            coverImage.click();
        });
        
        coverImage.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    coverPreview.innerHTML = `<img src="${event.target.result}" alt="Previsualización">`;
                    document.getElementById('coverImageError').style.display = 'none';
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Validación del formulario
        const form = document.querySelector('.addon-form');
        const inputs = form.querySelectorAll('input, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const errorElement = document.getElementById(`${this.id}Error`);
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            });
        });

        // Validar versión (solo números, puntos y guiones)
        document.getElementById('version').addEventListener('input', function() {
            if (!/^[0-9\.\-]*$/.test(this.value)) {
                this.value = this.value.replace(/[^0-9\.\-]/g, '');
            }
        });

        // Validar enlace de descarga
        document.getElementById('downloadLink').addEventListener('change', function() {
            const url = this.value;
            if (url && !url.includes('mediafire.com') && !url.includes('mega.io') && !url.includes('mega.nz')) {
                document.getElementById('downloadLinkError').style.display = 'block';
                this.value = '';
            }
        });

        // Limpiar espacios en las etiquetas
        document.getElementById('tag').addEventListener('blur', function() {
            this.value = this.value.split(',').map(tag => tag.trim()).filter(tag => tag !== '').join(', ');
        });

        // Validación al enviar el formulario
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validar imagen
            if (coverImage.files.length === 0) {
                document.getElementById('coverImageError').style.display = 'block';
                isValid = false;
            }
            
            // Validar título
            const title = document.getElementById('title').value.trim();
            if (title === '') {
                document.getElementById('titleError').style.display = 'block';
                isValid = false;
            }
            
            // Validar descripción
            const description = document.getElementById('description').value.trim();
            if (description === '') {
                document.getElementById('descriptionError').style.display = 'block';
                isValid = false;
            }
            
            // Validar versión
            const version = document.getElementById('version').value.trim();
            if (version === '' || !/^[0-9\.\-]+$/.test(version)) {
                document.getElementById('versionError').style.display = 'block';
                isValid = false;
            }
            
            // Validar etiquetas
            const tags = document.getElementById('tag').value.trim();
            if (tags === '') {
                document.getElementById('tagError').style.display = 'block';
                isValid = false;
            }
            
            // Validar enlace de descarga
            const downloadLink = document.getElementById('downloadLink').value.trim();
            if (downloadLink === '' || 
                (!downloadLink.includes('mediafire.com') && 
                 !downloadLink.includes('mega.io') && 
                 !downloadLink.includes('mega.nz'))) {
                document.getElementById('downloadLinkError').style.display = 'block';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                // Desplazarse al primer error
                const firstError = form.querySelector('.error-text[style="display: block;"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</body>
</html>