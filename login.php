<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Email o contraseña incorrectos";
    }
}

// Función para seleccionar imagen aleatoria
function getRandomImage() {
    $images = ['chico_laptop.png', 'chico_telefono.png', 'grupo_feliz.png'];
    return $images[array_rand($images)];
}

$randomImage = getRandomImage();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - StaticHost</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex">
        <!-- Lado Izquierdo - Imagen -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 to-purple-600 relative">
            <div class="absolute inset-0 bg-black bg-opacity-20"></div>
            <div class="relative z-10 flex flex-col justify-center items-center text-white p-12">
                <img src="img/<?php echo $randomImage; ?>" alt="StaticHost" class="max-w-md w-full h-auto object-contain mb-8 rounded-lg shadow-2xl">
                <h1 class="text-4xl font-bold mb-4 text-center">Bienvenido a StaticHost</h1>
                <p class="text-xl text-center opacity-90">Hosting económico y confiable para tus páginas estáticas</p>
            </div>
        </div>
        
        <!-- Lado Derecho - Formulario -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="max-w-md w-full space-y-8">
                <div class="text-center">
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                        Iniciar sesión
                    </h2>
                    <p class="text-sm text-gray-600">
                        ¿No tienes cuenta?
                        <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Regístrate aquí
                        </a>
                    </p>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form class="space-y-6" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input id="email" name="email" type="email" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10"
                                   placeholder="tu@email.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                            <input id="password" name="password" type="password" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10"
                                   placeholder="Tu contraseña">
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                            Iniciar sesión
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="index.php" class="text-blue-600 hover:text-blue-500 text-sm">
                            ← Volver al inicio
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>