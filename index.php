<?php
// --- CONFIGURACIÓN ---
// URL de autorización de Bungie. El único botón apuntará aquí.
$auth_url = 'https://www.bungie.net/es/OAuth/Authorize?client_id=50779&response_type=code';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArmoryD2Guard - Login</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Hoja de Estilos Personalizada -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="text-gray-300">

    <!-- Contenedor principal centrado -->
    <main class="min-h-screen flex flex-col items-center justify-center p-4">
        
        <!-- Tarjeta de Login -->
        <div class="w-full max-w-md bg-gray-900/80 backdrop-blur-sm border border-gray-700 rounded-2xl shadow-2xl shadow-blue-500/10 p-8 text-center">

            <!-- Título Principal -->
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2 tracking-tighter">
                Armory<span class="text-blue-400">D2</span>Guard
            </h1>

            <!-- Subtítulo -->
            <p class="text-lg text-gray-400 mb-8">
                Gestiona tus armaduras de Destiny 2 de forma inteligente.
            </p>

            <!-- Separador -->
            <div class="h-px bg-gray-700 w-1/2 mx-auto mb-8"></div>
            
            <!-- Botón de Conexión Único -->
            <a href="<?php echo $auth_url; ?>"
               class="group inline-flex items-center justify-center w-full px-6 py-4 bg-blue-600 border border-transparent rounded-lg text-lg font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 focus:ring-blue-500 transition-transform duration-200 ease-in-out transform hover:scale-105">
                <!-- Icono de Flecha -->
                <svg class="w-6 h-6 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
                </svg>
                Identificarse
            </a>

            <!-- Nota de afiliación -->
            <p class="text-xs text-gray-500 mt-8">
                ArmoryD2Guard no está afiliado con Bungie, Inc.
            </p>
        </div>

    </main>

</body>
</html>

