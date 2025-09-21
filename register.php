<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$errors = [];
$formData = [
    'username' => '',
    'profile_pic' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validar que se haya subido una imagen de perfil
    if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "La foto de perfil es obligatoria";
    } else {
        // Procesar imagen de perfil
        list($success, $result) = uploadFile($_FILES['profile_pic'], UPLOAD_DIR);
        
        if ($success) {
            $formData['profile_pic'] = $result;
        } else {
            $errors = array_merge($errors, $result);
        }
    }
    
    // Validaciones
    if (empty($formData['username'])) {
        $errors[] = "El nombre de usuario es obligatorio";
    } elseif (strlen($formData['username']) < 3) {
        $errors[] = "El nombre de usuario debe tener al menos 3 caracteres";
    }
    
    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria";
    } elseif (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Las contraseñas no coinciden";
    }
    
    // Verificar si el nombre de usuario ya existe
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt->execute([$formData['username']]);
        
        if ($stmt->fetch()) {
            $errors[] = "El nombre de usuario ya está en uso";
        }
    }
    
    // Registrar usuario si no hay errores
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, profile_pic) VALUES (?, ?, ?)");
            $stmt->execute([$formData['username'], $hashedPassword, $formData['profile_pic']]);
            
            // Iniciar sesión automáticamente
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error al registrar el usuario: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | MCPixel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        body[data-theme="light"] {
            background-color: var(--light-bg);
            color: var(--light-text);
        }

        .container {
            max-width: 28rem;
            margin: auto;
            padding: 2rem;
            width: 100%;
        }

        .card {
            background-color: var(--dark-card);
            border-radius: var(--rounded-lg);
            box-shadow: var(--shadow-lg);
            padding: 2.5rem;
            border: 1px solid var(--dark-border);
            transition: var(--transition);
            position: relative;
        }

        body[data-theme="light"] .card {
            background-color: var(--light-card);
            border-color: var(--light-border);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }

        .logo {
            height: 2rem;
            width: auto;
            border-radius: 50%;
        }

        .site-title {
            font-size: 1.25rem;
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

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--dark-text);
            position: relative;
            padding-bottom: 0.75rem;
            margin-top: 2.5rem;
        }

        h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 4rem;
            height: 0.25rem;
            background: linear-gradient(90deg, #3B82F6, #10B981);
            border-radius: 0.25rem;
        }

        body[data-theme="light"] h1 {
            color: var(--light-text);
        }

        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
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

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: #94a3b8;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            font-size: 0.9375rem;
            border: 1px solid var(--dark-border);
            border-radius: var(--rounded);
            background-color: var(--dark-card);
            color: var(--dark-text);
            transition: var(--transition);
            position: relative;
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

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            border-radius: var(--rounded);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .avatar-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        .avatar-preview {
            width: 6rem;
            height: 6rem;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            cursor: pointer;
            margin-bottom: 1rem;
            transition: var(--transition);
            background-color: var(--dark-card);
        }

        .avatar-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        body[data-theme="light"] .avatar-preview {
            background-color: var(--light-card);
        }

        .avatar-label {
            font-size: 0.875rem;
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .avatar-label:hover {
            text-decoration: underline;
            color: var(--primary-hover);
        }

        .avatar-input {
            display: none;
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .text-center {
            text-align: center;
        }

        .text-sm {
            font-size: 0.875rem;
        }

        .mt-4 {
            margin-top: 1.5rem;
        }

        .link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .link:hover {
            text-decoration: underline;
            color: var(--primary-hover);
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: var(--rounded);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #94a3b8;
            font-size: 0.75rem;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--dark-border);
        }

        .divider::before {
            margin-right: 0.75rem;
        }

        .divider::after {
            margin-left: 0.75rem;
        }

        body[data-theme="light"] .divider::before,
        body[data-theme="light"] .divider::after {
            border-bottom-color: var(--light-border);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .card {
                padding: 1.75rem;
            }
            
            .card-header {
                top: 1rem;
                right: 1rem;
            }
            
            .logo {
                height: 1.75rem;
            }
            
            .site-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <img src="./img/logo.png" alt="MCPixel Logo" class="logo">
                <span class="site-title">MCPixel</span>
            </div>

            <h1>Crea tu cuenta</h1>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="avatar-upload">
                    <img src="./img/default.png" alt="Foto de perfil" class="avatar-preview" id="avatarPreview">
                    <label for="avatarInput" class="avatar-label">
                        <i class="fas fa-camera"></i>
                        Elegir foto de perfil (obligatorio)
                    </label>
                    <input type="file" id="avatarInput" name="profile_pic" accept="image/*" class="avatar-input" required>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Nombre de usuario</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Registrarse
                </button>

                <div class="divider">¿Ya tienes una cuenta?</div>

                <div class="text-center text-sm">
                    <a href="login.php" class="link">Inicia sesión aquí</a>
                </div>
            </form>
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

        // Mostrar/ocultar contraseña
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const confirmPassword = document.querySelector('#confirm_password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>