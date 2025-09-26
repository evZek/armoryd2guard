<?php
// Iniciar la sesión para poder guardar los tokens.
session_start();

// --- CONFIGURACIÓN DE CREDENCIALES ---
// Es una buena práctica guardar esto en un archivo de configuración separado en un proyecto real.
$client_id = '50779';
$client_secret = 'Mwj5PROdnqaZgxIy6eDu-Yeb4HU1HzKHI6F3jgBUaww';
$api_key = '0e6f1d0e6971472e93bb632a5e027b2b';

// --- CORRECCIÓN CLAVE: La URL de redirección DEBE COINCIDIR EXACTAMENTE con la del portal de Bungie.
// Hemos eliminado el 'www.' para que coincida con la configuración de tu servidor.
$redirect_uri = 'https://armoryd2guard.evzek.net/callback.php';

// --- PASO 1: OBTENER EL CÓDIGO DE AUTORIZACIÓN ---
// Bungie nos redirige aquí con un 'code' en la URL. Si no existe, algo fue mal.
if (!isset($_GET['code'])) {
    // Redirigir al inicio con un mensaje de error.
    header('Location: index.php?error=auth_failed');
    exit();
}
$auth_code = $_GET['code'];


// --- PASO 2: INTERCAMBIAR EL CÓDIGO POR UN TOKEN DE ACCESO ---
$token_url = 'https://www.bungie.net/platform/app/oauth/token/';

// Los datos que enviaremos a Bungie en la petición POST.
$post_fields = http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $auth_code,
    'client_id' => $client_id,
    'client_secret' => $client_secret
]);

// Configuramos cURL para hacer la petición POST.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Bungie requiere una cabecera de 'Content-Type' específica.
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
curl_close($ch);

// Decodificamos la respuesta JSON de Bungie.
$token_data = json_decode($response, true);


// --- PASO 3: GUARDAR LOS TOKENS Y REDIRIGIR ---
// Si la respuesta contiene el 'access_token', el proceso fue exitoso.
if (isset($token_data['access_token'])) {
    // Guardamos los tokens en la sesión del usuario para usarlos en otras páginas.
    $_SESSION['access_token'] = $token_data['access_token'];
    $_SESSION['refresh_token'] = $token_data['refresh_token'];
    $_SESSION['token_expires_in'] = time() + $token_data['expires_in'];
    
    // Redirigimos al usuario al dashboard principal.
    header('Location: dashboard.php');
    exit();
} else {
    // Si no, hubo un error. Redirigimos al inicio mostrando el error.
    // Esto es útil para depurar problemas con las credenciales.
    $error_description = $token_data['error_description'] ?? 'Error desconocido al obtener el token.';
    header('Location: index.php?error=' . urlencode($error_description));
    exit();
}

?>

