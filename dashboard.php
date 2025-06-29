<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener información del usuario y sus sitios
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$sites = $stmt->fetchAll();

// Definir límites según el plan
$plan_limits = [
    'basic' => ['sites' => 2, 'name' => 'Básico', 'price' => 'S/ 5'],
    'professional' => ['sites' => 5, 'name' => 'Profesional', 'price' => 'S/ 10']
];

$current_plan = $plan_limits[$user['plan']] ?? $plan_limits['basic'];
$sites_used = count($sites);
$sites_remaining = max(0, $current_plan['sites'] - $sites_used);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StaticHost</title>
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
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Plan Info -->
        <div class="bg-white rounded-lg shadow mb-8 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Plan Actual: <?php echo $current_plan['name']; ?></h2>
                    <p class="text-gray-600"><?php echo $current_plan['price']; ?>/mes</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Sitios utilizados</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $sites_used; ?>/<?php echo $current_plan['sites']; ?></p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-globe text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo $sites_used; ?></h3>
                        <p class="text-gray-600">Sitios Activos</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">99.9%</h3>
                        <p class="text-gray-600">Uptime</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-plus text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo $sites_remaining; ?></h3>
                        <p class="text-gray-600">Sitios Disponibles</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sites Section -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-900">Mis Sitios Web</h2>
                <?php if ($sites_remaining > 0): ?>
                    <a href="upload.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Nuevo Sitio
                    </a>
                <?php else: ?>
                    <span class="bg-gray-400 text-white px-4 py-2 rounded-md cursor-not-allowed">
                        <i class="fas fa-plus mr-2"></i>Límite Alcanzado
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="p-6">
                <?php if (empty($sites)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-globe text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">No tienes sitios web aún</h3>
                        <p class="text-gray-600 mb-6">Sube tu primer sitio web estático y comienza a compartirlo con el mundo</p>
                        <?php if ($sites_remaining > 0): ?>
                            <a href="upload.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700">
                                Subir mi primer sitio
                            </a>
                        <?php else: ?>
                            <p class="text-red-600 font-semibold">Has alcanzado el límite de sitios para tu plan actual</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($sites as $site): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($site['name']); ?></h3>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $site['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $site['status'] == 'active' ? 'Activo' : 'Pendiente'; ?>
                                    </span>
                                </div>
                                <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($site['description']); ?></p>
                                <div class="flex space-x-2">
                                    <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-external-link-alt mr-1"></i>Ver sitio
                                    </a>
                                    <a href="manage_site.php?id=<?php echo $site['id']; ?>" 
                                       class="text-gray-600 hover:text-gray-800 text-sm">
                                        <i class="fas fa-cog mr-1"></i>Gestionar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>