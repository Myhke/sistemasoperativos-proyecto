<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StaticHost - Hosting de Páginas Estáticas Económico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-blue-600">StaticHost</h1>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="#pricing" class="text-gray-700 hover:text-blue-600">Precios</a>
                    <a href="#features" class="text-gray-700 hover:text-blue-600">Características</a>
                    <a href="login.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Iniciar Sesión</a>
                    <a href="register.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Registrarse</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    Hosting de Páginas Estáticas
                    <span class="text-yellow-300">Económico y Confiable</span>
                </h1>
                <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto">
                    Perfecto para vendedores que quieren digitalizar su negocio sin gastar una fortuna en hosting caro
                </p>
                <div class="space-x-4">
                    <a href="register.php" class="bg-yellow-400 text-gray-900 px-8 py-3 rounded-lg text-lg font-semibold hover:bg-yellow-300 transition duration-300">
                        Comenzar Gratis
                    </a>
                    <a href="#features" class="border-2 border-white text-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-white hover:text-blue-600 transition duration-300">
                        Ver Características
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    ¿Por qué elegir StaticHost?
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Diseñado especialmente para vendedores y pequeños negocios que necesitan presencia web sin complicaciones
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-lg shadow-lg text-center">
                    <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-dollar-sign text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">Precios Accesibles</h3>
                    <p class="text-gray-600">Planes desde $5 USD/mes. Sin costos ocultos, sin sorpresas en tu factura.</p>
                </div>
                
                <div class="bg-white p-8 rounded-lg shadow-lg text-center">
                    <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-rocket text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">Fácil de Usar</h3>
                    <p class="text-gray-600">Sube tu página web en minutos. No necesitas conocimientos técnicos avanzados.</p>
                </div>
                
                <div class="bg-white p-8 rounded-lg shadow-lg text-center">
                    <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-shield-alt text-2xl text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">Seguro y Confiable</h3>
                    <p class="text-gray-600">SSL gratuito, backups automáticos y 99.9% de uptime garantizado.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="bg-gray-100 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Planes Diseñados Para Ti
                </h2>
                <p class="text-xl text-gray-600">
                    Elige el plan que mejor se adapte a las necesidades de tu negocio
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Plan Básico -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Básico</h3>
                        <div class="text-4xl font-bold text-blue-600 mb-2">S/ 5</div>
                        <div class="text-gray-600 mb-6">soles/mes</div>
                        <ul class="text-left space-y-3 mb-8">
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> 2 sitios web</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Hosting básico</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> SSL gratuito</li>
                        </ul>
                        <a href="register.php" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition duration-300 block text-center">
                            Comenzar
                        </a>
                    </div>
                </div>
                
                <!-- Plan Profesional -->
                <div class="bg-white rounded-lg shadow-lg p-8 border-4 border-blue-500 relative">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-blue-500 text-white px-4 py-1 rounded-full text-sm font-semibold">Más Popular</span>
                    </div>
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Profesional</h3>
                        <div class="text-4xl font-bold text-blue-600 mb-2">S/ 10</div>
                        <div class="text-gray-600 mb-6">soles/mes</div>
                        <ul class="text-left space-y-3 mb-8">
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> 5 sitios web</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Hosting básico</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> SSL gratuito</li>
                        </ul>
                        <a href="register.php" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition duration-300 block text-center">
                            Comenzar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-blue-600 text-white py-20">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                ¿Listo para llevar tu negocio online?
            </h2>
            <p class="text-xl mb-8">
                Únete a cientos de vendedores que ya confían en StaticHost para su presencia digital
            </p>
            <a href="register.php" class="bg-yellow-400 text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-300 transition duration-300">
                Crear Cuenta Gratis
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">StaticHost</h3>
                    <p class="text-gray-400">Hosting económico y confiable para páginas estáticas.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Producto</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white">Características</a></li>
                        <li><a href="#" class="hover:text-white">Precios</a></li>
                        <li><a href="#" class="hover:text-white">Documentación</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Soporte</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white">Centro de Ayuda</a></li>
                        <li><a href="#" class="hover:text-white">Contacto</a></li>
                        <li><a href="#" class="hover:text-white">Estado del Servicio</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white">Términos de Servicio</a></li>
                        <li><a href="#" class="hover:text-white">Política de Privacidad</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 StaticHost. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
</body>
</html>