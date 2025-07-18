<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "El nombre es requerido";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email válido es requerido";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden";
    }
    
    // Verificar si el email ya existe
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Este email ya está registrado";
        }
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
        
        if ($stmt->execute([$name, $email, $hashed_password])) {
            $_SESSION['success'] = "Cuenta creada exitosamente. Puedes iniciar sesión ahora.";
            header('Location: login.php');
            exit;
        } else {
            $errors[] = "Error al crear la cuenta";
        }
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
    <title>Registro - StaticHost</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex">
        <!-- Lado Izquierdo - Imagen -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-green-600 to-blue-600 relative">
            <div class="absolute inset-0 bg-black bg-opacity-20"></div>
            <div class="relative z-10 flex flex-col justify-center items-center text-white p-12">
                <img src="img/<?php echo $randomImage; ?>" alt="StaticHost" class="max-w-md w-full h-auto object-contain mb-8 rounded-lg shadow-2xl">
                <h1 class="text-4xl font-bold mb-4 text-center">Únete a StaticHost</h1>
                <p class="text-xl text-center opacity-90">Comienza tu presencia digital hoy mismo</p>
            </div>
        </div>
        
        <!-- Lado Derecho - Formulario -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="max-w-md w-full space-y-8">
                <div class="text-center">
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                        Crear tu cuenta
                    </h2>
                    <p class="text-sm text-gray-600">
                        ¿Ya tienes cuenta?
                        <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Inicia sesión aquí
                        </a>
                    </p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form class="space-y-6" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nombre completo</label>
                            <input id="name" name="name" type="text" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10"
                                   placeholder="Tu nombre completo"
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
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
                                   placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                            <input id="confirm_password" name="confirm_password" type="password" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10"
                                   placeholder="Repite tu contraseña">
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                            Crear cuenta
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