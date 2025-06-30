<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configuración local del servidor (igual que upload.php)
$server_config = [
    'web_root' => '/var/www/sites',
    'nginx_sites_available' => '/etc/nginx/sites-available',
    'nginx_sites_enabled' => '/etc/nginx/sites-enabled'
];

// Obtener ID del sitio
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$site_id = (int)$_GET['id'];

// Verificar que el sitio pertenece al usuario
$stmt = $pdo->prepare("SELECT s.*, u.name as user_name FROM sites s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.user_id = ?");
$stmt->execute([$site_id, $_SESSION['user_id']]);
$site = $stmt->fetch();

if (!$site) {
    $_SESSION['error'] = "Sitio no encontrado o no tienes permisos para gestionarlo";
    header('Location: dashboard.php');
    exit;
}

// Obtener archivos del sitio
$stmt = $pdo->prepare("SELECT * FROM uploads WHERE site_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$site_id]);
$uploads = $stmt->fetchAll();

$errors = [];
$success = false;

// Función para generar UUID v4 (reutilizada de upload.php)
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Función para extraer y copiar archivos (reutilizada de upload.php)
function extractAndCopyFiles($zip_file, $destination_path) {
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        // Limpiar directorio existente
        if (is_dir($destination_path)) {
            exec("rm -rf " . escapeshellarg($destination_path . '/*'));
        }
        
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
            exec("rm -rf " . escapeshellarg($temp_extract));
            throw new Exception("No se encontró archivo index.html en el ZIP");
        }
        
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
                    exec("sudo chown www-data:www-data " . escapeshellarg($dest_file));
                }
            } else {
                $file_extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                $allowed_extensions = ['html', 'htm', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'txt', 'json', 'xml', 'woff', 'woff2', 'ttf', 'eot'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    continue;
                }
                
                $dest_dir = dirname($dest_file);
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                    exec("sudo chown www-data:www-data " . escapeshellarg($dest_dir));
                }
                
                if (copy($file->getRealPath(), $dest_file)) {
                    chmod($dest_file, 0644);
                    exec("sudo chown www-data:www-data " . escapeshellarg($dest_file));
                    $files_copied++;
                } else {
                    throw new Exception("Error al copiar archivo: " . $file->getFilename());
                }
            }
        }
        
        exec("rm -rf " . escapeshellarg($temp_extract));
        
        if ($files_copied === 0) {
            throw new Exception("No se copiaron archivos al directorio de destino");
        }
        
        // Establecer permisos finales
        exec("sudo chown -R www-data:www-data " . escapeshellarg($destination_path));
        exec("sudo find " . escapeshellarg($destination_path) . " -type d -exec chmod 755 {} \;");
        exec("sudo find " . escapeshellarg($destination_path) . " -type f -exec chmod 644 {} \;");
        
        return true;
    } else {
        throw new Exception("Error al abrir el archivo ZIP");
    }
}

// Función para eliminar configuración de Nginx
function removeNginxConfig($site_uuid, $config) {
    $config_file = $config['nginx_sites_available'] . '/' . $site_uuid;
    $enabled_link = $config['nginx_sites_enabled'] . '/' . $site_uuid;
    
    // Eliminar enlace simbólico
    if (file_exists($enabled_link)) {
        exec("sudo rm -f " . escapeshellarg($enabled_link));
    }
    
    // Eliminar archivo de configuración
    if (file_exists($config_file)) {
        exec("sudo rm -f " . escapeshellarg($config_file));
    }
    
    // Probar y recargar Nginx
    exec("sudo nginx -t 2>&1", $test_output, $test_code);
    if ($test_code === 0) {
        exec("sudo nginx -s reload 2>&1");
    }
}

// Función para crear configuración de Nginx (reutilizada de upload.php)
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
    
    $temp_config = sys_get_temp_dir() . '/nginx_' . $site_uuid . '.conf';
    if (file_put_contents($temp_config, $config_content) === false) {
        throw new Exception("Error al crear archivo temporal de configuración");
    }
    
    $config_file = $config['nginx_sites_available'] . '/' . $site_uuid;
    exec("sudo cp " . escapeshellarg($temp_config) . " " . escapeshellarg($config_file) . " 2>&1", $output, $return_code);
    
    if ($return_code !== 0) {
        unlink($temp_config);
        throw new Exception("Error al crear archivo de configuración de Nginx: " . implode("\n", $output));
    }
    
    unlink($temp_config);
    
    $enabled_link = $config['nginx_sites_enabled'] . '/' . $site_uuid;
    exec("sudo ln -sf " . escapeshellarg($config_file) . " " . escapeshellarg($enabled_link) . " 2>&1", $link_output, $link_code);
    
    if ($link_code !== 0) {
        exec("sudo rm -f " . escapeshellarg($config_file));
        throw new Exception("Error al habilitar sitio en Nginx: " . implode("\n", $link_output));
    }
    
    exec("sudo chown -R www-data:www-data " . escapeshellarg($site_path));
    
    exec("sudo nginx -t 2>&1", $test_output, $test_code);
    if ($test_code !== 0) {
        exec("sudo rm -f " . escapeshellarg($config_file));
        exec("sudo rm -f " . escapeshellarg($enabled_link));
        throw new Exception("Error en configuración de Nginx: " . implode("\n", $test_output));
    }
    
    exec("sudo nginx -s reload 2>&1", $reload_output, $reload_code);
    if ($reload_code !== 0) {
        throw new Exception("Error al recargar Nginx: " . implode("\n", $reload_output));
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_info':
                $site_name = trim($_POST['site_name']);
                $site_description = trim($_POST['site_description']);
                $custom_domain = trim($_POST['custom_domain']);
                
                if (empty($site_name)) {
                    throw new Exception("El nombre del sitio es requerido");
                }
                
                if (empty($site_description)) {
                    throw new Exception("La descripción del sitio es requerida");
                }
                
                if (empty($custom_domain)) {
                    throw new Exception("El dominio personalizado es requerido");
                }
                
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.sytes\.net$/', $custom_domain)) {
                    throw new Exception("El dominio debe tener el formato: nombreusuario.sytes.net");
                }
                
                // Verificar que el dominio no esté ya registrado por otro sitio
                $stmt = $pdo->prepare("SELECT id FROM sites WHERE domain = ? AND id != ?");
                $stmt->execute([$custom_domain, $site_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Este dominio ya está siendo utilizado");
                }
                
                // Actualizar información del sitio
                $stmt = $pdo->prepare("UPDATE sites SET name = ?, description = ?, domain = ?, url = ?, updated_at = NOW() WHERE id = ?");
                $site_url = "http://" . $custom_domain;
                $stmt->execute([$site_name, $site_description, $custom_domain, $site_url, $site_id]);
                
                // Si cambió el dominio, actualizar configuración de Nginx
                if ($custom_domain !== $site['domain']) {
                    // Obtener UUID del sitio desde la URL actual
                    $site_uuid = basename(parse_url($site['url'], PHP_URL_PATH));
                    if (empty($site_uuid)) {
                        // Extraer UUID del directorio del sitio
                        $site_path = $server_config['web_root'];
                        $dirs = scandir($site_path);
                        foreach ($dirs as $dir) {
                            if ($dir !== '.' && $dir !== '..' && is_dir($site_path . '/' . $dir)) {
                                $site_uuid = $dir;
                                break;
                            }
                        }
                    }
                    
                    if (!empty($site_uuid)) {
                        createNginxConfig($custom_domain, $site_uuid, $server_config);
                    }
                }
                
                $success = "Información del sitio actualizada exitosamente";
                
                // Recargar datos del sitio
                $stmt = $pdo->prepare("SELECT s.*, u.name as user_name FROM sites s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                $stmt->execute([$site_id]);
                $site = $stmt->fetch();
                
                break;
                
            case 'reupload_files':
                if (!isset($_FILES['site_zip']) || $_FILES['site_zip']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Debes seleccionar un archivo ZIP válido");
                }
                
                $file = $_FILES['site_zip'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if ($file_extension !== 'zip') {
                    throw new Exception("Solo se permiten archivos ZIP");
                }
                
                if ($file['size'] > 50 * 1024 * 1024) {
                    throw new Exception("El archivo no puede ser mayor a 50MB");
                }
                
                // Obtener UUID del sitio
                $site_uuid = basename(parse_url($site['url'], PHP_URL_PATH));
                if (empty($site_uuid)) {
                    // Buscar directorio del sitio
                    $site_path = $server_config['web_root'];
                    $dirs = scandir($site_path);
                    foreach ($dirs as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($site_path . '/' . $dir)) {
                            $site_uuid = $dir;
                            break;
                        }
                    }
                }
                
                if (empty($site_uuid)) {
                    throw new Exception("No se pudo determinar el directorio del sitio");
                }
                
                $site_path = $server_config['web_root'] . '/' . $site_uuid;
                
                // Mover archivo ZIP a ubicación temporal
                $temp_zip = sys_get_temp_dir() . '/reupload_' . $site_uuid . '.zip';
                if (!move_uploaded_file($file['tmp_name'], $temp_zip)) {
                    throw new Exception("Error al procesar el archivo subido");
                }
                
                // Extraer y copiar archivos
                extractAndCopyFiles($temp_zip, $site_path);
                
                // Eliminar archivo ZIP temporal
                unlink($temp_zip);
                
                // Limpiar registros de uploads anteriores
                $stmt = $pdo->prepare("DELETE FROM uploads WHERE site_id = ?");
                $stmt->execute([$site_id]);
                
                // Registrar nuevos archivos
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site_path, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file_info) {
                    if ($file_info->isFile()) {
                        $relative_path = str_replace($site_path . DIRECTORY_SEPARATOR, '', $file_info->getPathname());
                        $stmt = $pdo->prepare("INSERT INTO uploads (site_id, filename, file_path, file_size, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$site_id, $file_info->getFilename(), $relative_path, $file_info->getSize()]);
                    }
                }
                
                // Actualizar timestamp del sitio
                $stmt = $pdo->prepare("UPDATE sites SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$site_id]);
                
                $success = "Archivos del sitio actualizados exitosamente";
                
                // Recargar uploads
                $stmt = $pdo->prepare("SELECT * FROM uploads WHERE site_id = ? ORDER BY uploaded_at DESC");
                $stmt->execute([$site_id]);
                $uploads = $stmt->fetchAll();
                
                break;
                
            case 'toggle_status':
                $new_status = $site['status'] === 'active' ? 'inactive' : 'active';
                
                // Obtener UUID del sitio
                $site_uuid = basename(parse_url($site['url'], PHP_URL_PATH));
                if (empty($site_uuid)) {
                    $site_path = $server_config['web_root'];
                    $dirs = scandir($site_path);
                    foreach ($dirs as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($site_path . '/' . $dir)) {
                            $site_uuid = $dir;
                            break;
                        }
                    }
                }
                
                if ($new_status === 'inactive') {
                    // Desactivar sitio eliminando configuración de Nginx
                    if (!empty($site_uuid)) {
                        removeNginxConfig($site_uuid, $server_config);
                    }
                } else {
                    // Activar sitio creando configuración de Nginx
                    if (!empty($site_uuid)) {
                        createNginxConfig($site['domain'], $site_uuid, $server_config);
                    }
                }
                
                // Actualizar estado en base de datos
                $stmt = $pdo->prepare("UPDATE sites SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $site_id]);
                
                $success = "Estado del sitio cambiado a " . ($new_status === 'active' ? 'activo' : 'inactivo');
                
                // Recargar datos del sitio
                $stmt = $pdo->prepare("SELECT s.*, u.name as user_name FROM sites s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                $stmt->execute([$site_id]);
                $site = $stmt->fetch();
                
                break;
                
            case 'delete_site':
                // Obtener UUID del sitio
                $site_uuid = basename(parse_url($site['url'], PHP_URL_PATH));
                if (empty($site_uuid)) {
                    $site_path = $server_config['web_root'];
                    $dirs = scandir($site_path);
                    foreach ($dirs as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($site_path . '/' . $dir)) {
                            $site_uuid = $dir;
                            break;
                        }
                    }
                }
                
                // Eliminar configuración de Nginx
                if (!empty($site_uuid)) {
                    removeNginxConfig($site_uuid, $server_config);
                    
                    // Eliminar directorio del sitio
                    $site_path = $server_config['web_root'] . '/' . $site_uuid;
                    if (is_dir($site_path)) {
                        exec("rm -rf " . escapeshellarg($site_path));
                    }
                }
                
                // Eliminar registros de la base de datos (uploads se eliminan automáticamente por CASCADE)
                $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
                $stmt->execute([$site_id]);
                
                $_SESSION['success'] = "Sitio eliminado exitosamente";
                header('Location: dashboard.php');
                exit;
                
            default:
                throw new Exception("Acción no válida");
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Calcular tamaño total de archivos
$total_size = 0;
foreach ($uploads as $upload) {
    $total_size += $upload['file_size'];
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Sitio - <?php echo htmlspecialchars($site['name']); ?> - StaticHost</title>
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

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600">
                            <i class="fas fa-home mr-2"></i>Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-500">Gestionar Sitio</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <!-- Título y estado del sitio -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($site['name']); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($site['description']); ?></p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 text-sm rounded-full <?php echo $site['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <i class="fas <?php echo $site['status'] == 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                        <?php echo $site['status'] == 'active' ? 'Activo' : 'Inactivo'; ?>
                    </span>
                    <div class="mt-2">
                        <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" 
                           class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i><?php echo htmlspecialchars($site['domain']); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
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

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Panel principal -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Información del sitio -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>Información del Sitio
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_info">
                        
                        <div>
                            <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del Sitio</label>
                            <input type="text" id="site_name" name="site_name" 
                                   value="<?php echo htmlspecialchars($site['name']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        
                        <div>
                            <label for="site_description" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                            <textarea id="site_description" name="site_description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                      required><?php echo htmlspecialchars($site['description']); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="custom_domain" class="block text-sm font-medium text-gray-700 mb-1">Dominio Personalizado</label>
                            <input type="text" id="custom_domain" name="custom_domain" 
                                   value="<?php echo htmlspecialchars($site['domain']); ?>"
                                   placeholder="nombreusuario.sytes.net"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   required>
                            <p class="text-sm text-gray-500 mt-1">Formato: nombreusuario.sytes.net</p>
                        </div>
                        
                        <button type="submit" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>Actualizar Información
                        </button>
                    </form>
                </div>

                <!-- Resubir archivos -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-upload text-green-600 mr-2"></i>Actualizar Archivos del Sitio
                    </h2>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-1"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-semibold mb-1">Importante:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Esto reemplazará todos los archivos actuales del sitio</li>
                                    <li>El archivo ZIP debe contener un archivo index.html</li>
                                    <li>Solo se permiten archivos web estáticos (HTML, CSS, JS, imágenes)</li>
                                    <li>Tamaño máximo: 50MB</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="reupload_files">
                        
                        <div>
                            <label for="site_zip" class="block text-sm font-medium text-gray-700 mb-1">Archivo ZIP del Sitio</label>
                            <input type="file" id="site_zip" name="site_zip" accept=".zip"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        
                        <button type="submit" 
                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                                onclick="return confirm('¿Estás seguro de que quieres reemplazar todos los archivos actuales?')">
                            <i class="fas fa-upload mr-2"></i>Actualizar Archivos
                        </button>
                    </form>
                </div>

                <!-- Archivos del sitio -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-folder text-purple-600 mr-2"></i>Archivos del Sitio
                        <span class="text-sm font-normal text-gray-500">(<?php echo count($uploads); ?> archivos, <?php echo formatBytes($total_size); ?>)</span>
                    </h2>
                    
                    <?php if (empty($uploads)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-folder-open text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No hay archivos en este sitio</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruta</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subido</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($uploads as $upload): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <i class="fas fa-file text-gray-400 mr-2"></i>
                                                <?php echo htmlspecialchars($upload['filename']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($upload['file_path']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo formatBytes($upload['file_size']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d/m/Y H:i', strtotime($upload['uploaded_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel lateral -->
            <div class="space-y-6">
                <!-- Estadísticas -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-chart-bar text-blue-600 mr-2"></i>Estadísticas
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Estado:</span>
                            <span class="font-medium <?php echo $site['status'] == 'active' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $site['status'] == 'active' ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Archivos:</span>
                            <span class="font-medium"><?php echo count($uploads); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tamaño total:</span>
                            <span class="font-medium"><?php echo formatBytes($total_size); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Creado:</span>
                            <span class="font-medium"><?php echo date('d/m/Y', strtotime($site['created_at'])); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Actualizado:</span>
                            <span class="font-medium"><?php echo date('d/m/Y', strtotime($site['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-tools text-orange-600 mr-2"></i>Acciones
                    </h3>
                    
                    <div class="space-y-3">
                        <!-- Ver sitio -->
                        <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank"
                           class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-center block">
                            <i class="fas fa-external-link-alt mr-2"></i>Ver Sitio
                        </a>
                        
                        <!-- Activar/Desactivar -->
                        <form method="POST" class="w-full">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" 
                                    class="w-full <?php echo $site['status'] == 'active' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-4 py-2 rounded-md"
                                    onclick="return confirm('¿Estás seguro de que quieres <?php echo $site['status'] == 'active' ? 'desactivar' : 'activar'; ?> este sitio?')">
                                <i class="fas <?php echo $site['status'] == 'active' ? 'fa-pause' : 'fa-play'; ?> mr-2"></i>
                                <?php echo $site['status'] == 'active' ? 'Desactivar' : 'Activar'; ?> Sitio
                            </button>
                        </form>
                        
                        <!-- Eliminar sitio -->
                        <form method="POST" class="w-full">
                            <input type="hidden" name="action" value="delete_site">
                            <button type="submit" 
                                    class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700"
                                    onclick="return confirm('¿Estás COMPLETAMENTE SEGURO de que quieres eliminar este sitio?\n\nEsta acción NO se puede deshacer y eliminará:\n- Todos los archivos del sitio\n- La configuración de Nginx\n- Todos los registros de la base de datos\n\nEscribe ELIMINAR para confirmar:') && prompt('Escribe ELIMINAR para confirmar:') === 'ELIMINAR'">
                                <i class="fas fa-trash mr-2"></i>Eliminar Sitio
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Información técnica -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-server text-gray-600 mr-2"></i>Información Técnica
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-gray-600">URL:</span>
                            <div class="font-mono text-xs bg-gray-100 p-2 rounded mt-1 break-all">
                                <?php echo htmlspecialchars($site['url']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <span class="text-gray-600">Dominio:</span>
                            <div class="font-mono text-xs bg-gray-100 p-2 rounded mt-1">
                                <?php echo htmlspecialchars($site['domain']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <span class="text-gray-600">ID del Sitio:</span>
                            <div class="font-mono text-xs bg-gray-100 p-2 rounded mt-1">
                                <?php echo $site['id']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confirmación adicional para eliminar sitio
        function confirmDelete() {
            const confirmation = prompt('Para confirmar la eliminación, escribe "ELIMINAR" (sin comillas):');
            return confirmation === 'ELIMINAR';
        }
    </script>
</body>
</html>