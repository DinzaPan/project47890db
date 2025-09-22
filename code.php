<?php
// Configuración de rutas para Vercel
$is_vercel = (!empty($_ENV['VERCEL']) || getenv('VERCEL') === '1');
$base_url = $is_vercel ? '/' : './';
$img_path = $base_url . 'img/';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCPixel - Addons para Minecraft</title>
    <style>
        :root {
            --primary-color: #3B82F6;
            --dark-bg: #0F172A;
            --dark-card: #1E293B;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--dark-bg);
            color: #F8FAFC;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--dark-card);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            border-bottom: 2px solid #3B82F6;
        }
        
        .logo {
            height: 80px;
            width: auto;
            margin-bottom: 1rem;
            border-radius: 50%;
            border: 3px solid #3B82F6;
        }
        
        h1 {
            color: #3B82F6;
            margin-bottom: 1rem;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: rgba(30, 41, 59, 0.5);
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        
        h2 {
            color: #F59E0B;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        pre {
            background: #0F172A;
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
            margin: 1rem 0;
            color: #10B981;
            border: 1px solid #334155;
        }
        
        .path-info {
            background: #1E293B;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 1rem 0;
            display: inline-block;
            color: #94a3b8;
        }
        
        .success {
            color: #10B981;
            padding: 0.5rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .error {
            color: #EF4444;
            padding: 0.5rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .test-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-top: 1rem;
            border: 2px solid #3B82F6;
        }
        
        .btn {
            display: inline-block;
            background: #3B82F6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 1rem;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2563EB;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Solución de Rutas en Vercel</h1>
            <div class="path-info">Usando: <strong><?php echo $base_url; ?></strong> como base URL</div>
        </div>
        
        <div class="content">
            <div class="card">
                <h2>📁 Estructura de Archivos</h2>
                <p>Tu proyecto debe tener esta estructura:</p>
                <pre>
/
├── index.php
├── vercel.json
├── img/
│   └── logo.png
├── uploads/
├── api/
└── assets/
                </pre>
                
                <h2>🖼️ Prueba de Imagen</h2>
                <p>Intentando cargar logo.png desde: <code><?php echo $img_path; ?>logo.png</code></p>
                
                <?php
                // Probamos diferentes métodos para cargar la imagen
                $image_paths = [
                    $img_path . 'logo.png',
                    '/img/logo.png',
                    './img/logo.png',
                    'img/logo.png'
                ];
                
                $image_found = false;
                
                foreach ($image_paths as $path) {
                    if (@getimagesize($path)) {
                        echo '<div class="success">✓ Imagen encontrada en: ' . $path . '</div>';
                        echo '<img src="' . $path . '" alt="Logo" class="test-image">';
                        $image_found = true;
                        break;
                    }
                }
                
                if (!$image_found) {
                    echo '<div class="error">✗ No se pudo cargar la imagen con ninguna ruta probada</div>';
                    echo '<p>Posibles soluciones:</p>';
                    echo '<ul>';
                    echo '<li>Verifica que el archivo logo.png existe en la carpeta img/</li>';
                    echo '<li>Verifica los permisos del archivo</li>';
                    echo '<li>Prueba con rutas absolutas usando /img/logo.png</li>';
                    echo '</ul>';
                }
                ?>
            </div>
            
            <div class="card">
                <h2>⚙️ Configuración Vercel</h2>
                <p>Tu archivo <code>vercel.json</code> debe incluir:</p>
                <pre>
{
  "version": 2,
  "builds": [
    {
      "src": "*.php",
      "use": "vercel-php"
    }
  ],
  "routes": [
    {
      "src": "/img/(.*)",
      "dest": "/img/$1"
    },
    {
      "src": "/(.*\\.(png|jpg|jpeg|gif|ico|svg))",
      "dest": "/$1"
    },
    {
      "src": "/(.*\\.php)",
      "dest": "/$1"
    },
    {
      "src": "/(.*)",
      "dest": "/index.php"
    }
  ]
}
                </pre>
                
                <h2>🔍 Detección de Entorno</h2>
                <p>El código detecta automáticamente si estás en Vercel:</p>
                <pre>
$is_vercel = (!empty($_ENV['VERCEL']) || getenv('VERCEL') === '1');
$base_url = $is_vercel ? '/' : './';
$img_path = $base_url . 'img/';
                </pre>
                
                <p>Resultado: <strong><?php echo $is_vercel ? 'Estás en Vercel' : 'No estás en Vercel'; ?></strong></p>
                
                <a href="https://vercel.com/docs/project-configuration" class="btn" target="_blank">Documentación de Vercel</a>
            </div>
        </div>
    </div>
</body>
</html>
