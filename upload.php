<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configuración local del servidor
$server_config = [
    'web_root' => '/var/www/sites',
    'nginx_sites_available' => '/etc/nginx/sites-available',
    'nginx_sites_enabled' => '/etc/nginx/sites-enabled'
];

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Verificar límites del plan
$plan_limits = [
    'basic' => ['sites' => 2, 'name' => 'Básico'],
    'professional' => ['sites' => 5, 'name' => 'Profesional']
];

$current_plan = $plan_limits[$user['plan']] ?? $plan_limits['basic'];

// Contar sitios actuales
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$sites_count = $stmt->fetchColumn();

if ($sites_count >= $current_plan['sites']) {
    $_SESSION['error'] = "Has alcanzado el límite de sitios para tu plan actual";
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Función para generar UUID v4
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Función para crear directorio local con permisos seguros
function createSiteDirectory($site_uuid, $web_root) {
    $site_path = $web_root . '/' . $site_uuid;
    
    // Crear directorio principal si no existe
    if (!is_dir($web_root)) {
        if (!mkdir($web_root, 0755, true)) {
            throw new Exception("Error al crear directorio web root");
        }
        // Establecer propietario correcto del directorio web root
        exec("sudo chown www-data:www-data " . escapeshellarg($web_root));
    }
    
    // Crear directorio del sitio
    if (!mkdir($site_path, 0755, true)) {
        throw new Exception("Error al crear directorio del sitio");
    }
    
    // Establecer permisos correctos: lectura, escritura y ejecución para directorios
    chmod($site_path, 0755);
    
    // Establecer propietario correcto
    exec("sudo chown www-data:www-data " . escapeshellarg($site_path));
    
    return $site_path;
}

// Función para extraer y copiar archivos localmente
function extractAndCopyFiles($zip_file, $destination_path) {
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        // Crear directorio temporal para extracción
        $temp_extract = sys_get_temp_dir() . '/extract_' . uniqid();
        if (!mkdir($temp_extract, 0755, true)) {
            throw new Exception("Error al crear directorio temporal");
        }
        
        $zip->extractTo($temp_extract);
        $zip->close();
        
        // Buscar archivo index.html
        $index_file = null;
        $possible_index = ['index.html', 'index.htm', 'home.html'];
        
        foreach ($possible_index as $index) {
            if (file_exists($temp_extract . '/' . $index)) {
                $index_file = $index;
                break;
            }
        }
        
        if (!$index_file) {
            // Limpiar directorio temporal
            exec("rm -rf " . escapeshellarg($temp_extract));
            throw new Exception("No se encontró archivo index.html en el ZIP");
        }
        
        // Contador de archivos copiados para verificación
        $files_copied = 0;
        
        // Copiar archivos al destino final
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_extract, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $dest_file = $destination_path . '/' . $iterator->getSubPathName();
            
            if ($file->isDir()) {
                if (!is_dir($dest_file)) {
                    mkdir($dest_file, 0755, true);
                    // Establecer propietario correcto para directorios
                    exec("sudo chown www-data:www-data " . escapeshellarg($dest_file));
                }
            } else {
                // Verificar que el archivo no sea ejecutable
                $file_extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                $allowed_extensions = ['html', 'htm', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'txt', 'json', 'xml', 'woff', 'woff2', 'ttf', 'eot'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    continue; // Saltar archivos no permitidos
                }
                
                // Crear directorio padre si no existe
                $dest_dir = dirname($dest_file);
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                    exec("sudo chown www-data:www-data " . escapeshellarg($dest_dir));
                }
                
                // Copiar archivo con verificación
                if (copy($file->getRealPath(), $dest_file)) {
                    // Establecer permisos seguros (sin ejecución)
                    chmod($dest_file, 0644);
                    // Establecer propietario correcto
                    exec("sudo chown www-data:www-data " . escapeshellarg($dest_file));
                    $files_copied++;
                } else {
                    throw new Exception("Error al copiar archivo: " . $file->getFilename());
                }
            }
        }
        
        // Limpiar directorio temporal
        exec("rm -rf " . escapeshellarg($temp_extract));
        
        // Verificar que se copiaron archivos
        if ($files_copied === 0) {
            throw new Exception("No se copiaron archivos al directorio de destino");
        }
        
        // Establecer permisos finales en todo el directorio
        exec("sudo chown -R www-data:www-data " . escapeshellarg($destination_path));
        exec("sudo find " . escapeshellarg($destination_path) . " -type d -exec chmod 755 {} \;");
        exec("sudo find " . escapeshellarg($destination_path) . " -type f -exec chmod 644 {} \;");
        
        return true;
    } else {
        throw new Exception("Error al abrir el archivo ZIP");
    }
}

// Función para crear configuración de Nginx localmente
function createNginxConfig($domain, $site_uuid, $config) {
    $site_path = $config['web_root'] . '/' . $site_uuid;
    
    $config_content = "server {
    listen 80;
    server_name {$domain};

    root {$site_path};
    index index.html index.htm;

    # Configuración de seguridad
    location / {
        try_files \$uri \$uri/ =404;
    }

    # Prevenir ejecución de scripts
    location ~* \.(php|pl|py|jsp|asp|sh|cgi)$ {
        deny all;
    }

    # Configuración para archivos estáticos
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control \"public, immutable\";
        add_header X-Content-Type-Options nosniff;
    }

    # Seguridad adicional
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection \"1; mode=block\";
    add_header X-Content-Type-Options nosniff;
}";
    
    // Crear archivo temporal
    $temp_config = sys_get_temp_dir() . '/nginx_' . $site_uuid . '.conf';
    if (file_put_contents($temp_config, $config_content) === false) {
        throw new Exception("Error al crear archivo temporal de configuración");
    }
    
    // Mover archivo con sudo
    $config_file = $config['nginx_sites_available'] . '/' . $site_uuid;
    exec("sudo cp " . escapeshellarg($temp_config) . " " . escapeshellarg($config_file) . " 2>&1", $output, $return_code);
    
    if ($return_code !== 0) {
        unlink($temp_config);
        throw new Exception("Error al crear archivo de configuración de Nginx: " . implode("\n", $output));
    }
    
    // Limpiar archivo temporal
    unlink($temp_config);
    
    // Habilitar sitio creando enlace simbólico con sudo
    $enabled_link = $config['nginx_sites_enabled'] . '/' . $site_uuid;
    exec("sudo ln -sf " . escapeshellarg($config_file) . " " . escapeshellarg($enabled_link) . " 2>&1", $link_output, $link_code);
    
    if ($link_code !== 0) {
        // Limpiar archivo de configuración si falla el enlace
        exec("sudo rm -f " . escapeshellarg($config_file));
        throw new Exception("Error al habilitar sitio en Nginx: " . implode("\n", $link_output));
    }
    
    // Establecer propietario correcto
    exec("sudo chown -R www-data:www-data " . escapeshellarg($site_path));
    
    // Probar configuración y recargar Nginx
    exec("sudo nginx -t 2>&1", $test_output, $test_code);
    if ($test_code !== 0) {
        // Si hay error, eliminar configuración
        exec("sudo rm -f " . escapeshellarg($config_file));
        exec("sudo rm -f " . escapeshellarg($enabled_link));
        throw new Exception("Error en configuración de Nginx: " . implode("\n", $test_output));
    }
    
    // Recargar Nginx
    exec("sudo nginx -s reload 2>&1", $reload_output, $reload_code);
    if ($reload_code !== 0) {
        throw new Exception("Error al recargar Nginx: " . implode("\n", $reload_output));
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $site_name = trim($_POST['site_name']);
    $site_description = trim($_POST['site_description']);
    $custom_domain = trim($_POST['custom_domain']);
    
    // Validaciones
    if (empty($site_name)) {
        $errors[] = "El nombre del sitio es requerido";
    }
    
    if (empty($site_description)) {
        $errors[] = "La descripción del sitio es requerida";
    }
    
    if (empty($custom_domain)) {
        $errors[] = "El dominio personalizado es requerido";
    } else {
        // Validar formato de dominio
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.sytes\.net$/', $custom_domain)) {
            $errors[] = "El dominio debe tener el formato: nombreusuario.sytes.net";
        }
        
        // Verificar que el dominio no esté ya registrado
        $stmt = $pdo->prepare("SELECT id FROM sites WHERE domain = ? AND user_id != ?");
        $stmt->execute([$custom_domain, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = "Este dominio ya está siendo utilizado";
        }
    }
    
    // Validar archivo
    if (!isset($_FILES['site_zip']) || $_FILES['site_zip']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Debes seleccionar un archivo ZIP válido";
    } else {
        $file = $_FILES['site_zip'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension !== 'zip') {
            $errors[] = "Solo se permiten archivos ZIP";
        }
        
        if ($file['size'] > 50 * 1024 * 1024) { // 50MB límite
            $errors[] = "El archivo no puede ser mayor a 50MB";
        }
    }
    
    if (empty($errors)) {
        try {
            // Generar UUID único para el sitio
            $site_uuid = generateUUID();
            
            // Crear directorio del sitio
            $site_path = createSiteDirectory($site_uuid, $server_config['web_root']);
            
            // Mover archivo ZIP a ubicación temporal
            $temp_zip = sys_get_temp_dir() . '/upload_' . $site_uuid . '.zip';
            if (!move_uploaded_file($file['tmp_name'], $temp_zip)) {
                throw new Exception("Error al procesar el archivo subido");
            }
            
            // Extraer y copiar archivos
            extractAndCopyFiles($temp_zip, $site_path);
            
            // Eliminar archivo ZIP temporal
            unlink($temp_zip);
            
            // Crear configuración de Nginx
            createNginxConfig($custom_domain, $site_uuid, $server_config);
            
            // Generar URL del sitio
            $site_url = "http://" . $custom_domain;
            
            // Insertar en base de datos
            $stmt = $pdo->prepare("INSERT INTO sites (user_id, name, description, url, domain, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$_SESSION['user_id'], $site_name, $site_description, $site_url, $custom_domain]);
            
            $site_id = $pdo->lastInsertId();
            
            // Registrar archivos subidos en la base de datos
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site_path, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file_info) {
                if ($file_info->isFile()) {
                    $relative_path = str_replace($site_path . DIRECTORY_SEPARATOR, '', $file_info->getPathname());
                    $stmt = $pdo->prepare("INSERT INTO uploads (site_id, filename, file_path, file_size, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$site_id, $file_info->getFilename(), $relative_path, $file_info->getSize()]);
                }
            }
            
            $success = true;
            $_SESSION['success'] = "Sitio web desplegado exitosamente en {$custom_domain}";
            
        } catch (Exception $e) {
            $errors[] = "Error al procesar el sitio: " . $e->getMessage();
            
            // Limpiar en caso de error
            if (isset($site_path) && is_dir($site_path)) {
                exec("rm -rf " . escapeshellarg($site_path));
            }
            
            if (isset($temp_zip) && file_exists($temp_zip)) {
                unlink($temp_zip);
            }
            
            // Limpiar configuración de Nginx si se creó
            if (isset($site_uuid)) {
                $config_file = $server_config['nginx_sites_available'] . '/' . $site_uuid;
                $enabled_link = $server_config['nginx_sites_enabled'] . '/' . $site_uuid;
                
                if (file_exists($config_file)) {
                    unlink($config_file);
                }
                if (file_exists($enabled_link)) {
                    unlink($enabled_link);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desplegar Sitio Web - StaticHost</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-blue-600">StaticHost</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">Dashboard</a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Desplegar Nuevo Sitio Web</h1>
            <p class="text-gray-600">Despliega tu sitio web estático localmente en el servidor con dominio personalizado de NO-IP</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>¡Sitio web desplegado exitosamente! <a href="dashboard.php" class="underline">Ver en dashboard</a></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <!-- Información del plan -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    <span class="text-blue-800">
                        Plan <?php echo $current_plan['name']; ?>: 
                        <?php echo $sites_count; ?>/<?php echo $current_plan['sites']; ?> sitios utilizados
                    </span>
                </div>
            </div>

            <!-- Instrucciones -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Instrucciones:</h3>
                <ul class="list-disc list-inside text-gray-600 space-y-1">
                    <li>Tu sitio debe estar en formato ZIP</li>
                    <li>Debe contener un archivo <code class="bg-gray-100 px-1 rounded">index.html</code> en la raíz</li>
                    <li>Tamaño máximo: 50MB</li>
                    <li>Solo archivos estáticos (HTML, CSS, JS, imágenes)</li>
                    <li>El dominio debe ser de NO-IP con formato: <code class="bg-gray-100 px-1 rounded">nombreusuario.sytes.net</code></li>
                    <li><strong>Seguridad:</strong> Los archivos se almacenan sin permisos de ejecución</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="site_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nombre del sitio *
                    </label>
                    <input type="text" id="site_name" name="site_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Mi sitio web"
                           value="<?php echo isset($_POST['site_name']) ? htmlspecialchars($_POST['site_name']) : ''; ?>">
                    <p class="text-sm text-gray-500 mt-1">Solo para identificación en tu dashboard</p>
                </div>

                <div>
                    <label for="site_description" class="block text-sm font-medium text-gray-700 mb-2">
                        Descripción *
                    </label>
                    <textarea id="site_description" name="site_description" required rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Describe tu sitio web..."><?php echo isset($_POST['site_description']) ? htmlspecialchars($_POST['site_description']) : ''; ?></textarea>
                </div>

                <div>
                    <label for="custom_domain" class="block text-sm font-medium text-gray-700 mb-2">
                        Dominio personalizado de NO-IP *
                    </label>
                    <input type="text" id="custom_domain" name="custom_domain" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="nombreusuario.sytes.net"
                           pattern="^[a-zA-Z0-9][a-zA-Z0-9-]*\.sytes\.net$"
                           value="<?php echo isset($_POST['custom_domain']) ? htmlspecialchars($_POST['custom_domain']) : ''; ?>">
                    <p class="text-sm text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Debe ser un dominio válido de NO-IP (ej: miusuario.sytes.net)
                    </p>
                </div>

                <div>
                    <label for="site_zip" class="block text-sm font-medium text-gray-700 mb-2">
                        Archivo ZIP del sitio *
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                        <input type="file" id="site_zip" name="site_zip" accept=".zip" required
                               class="hidden" onchange="updateFileName(this)">
                        <label for="site_zip" class="cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                            <p class="text-gray-600">Haz clic para seleccionar tu archivo ZIP</p>
                            <p class="text-sm text-gray-500 mt-1">Máximo 50MB</p>
                        </label>
                        <p id="file-name" class="mt-2 text-sm text-blue-600 hidden"></p>
                    </div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-1"></i>
                        <div class="text-sm text-yellow-800">
                            <p class="font-semibold mb-1">Importante:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>El sitio se desplegará localmente en el servidor</li>
                                <li>Se creará la configuración de Nginx automáticamente</li>
                                <li>Los archivos se almacenan con permisos seguros (sin ejecución)</li>
                                <li>Asegúrate de que tu dominio NO-IP esté configurado correctamente</li>
                                <li>El proceso puede tomar unos minutos</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-rocket mr-2"></i>Desplegar Sitio
                    </button>
                    <a href="dashboard.php" 
                       class="bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                fileName.textContent = 'Archivo seleccionado: ' + input.files[0].name;
                fileName.classList.remove('hidden');
            } else {
                fileName.classList.add('hidden');
            }
        }
    </script>
</body>
</html>