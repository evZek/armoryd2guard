<?php
// --- CONFIGURACIÓN ---
// Es crucial que esta clave de API sea la misma que usas en el resto de la aplicación.
$api_key = '0e6f1d0e6971472e93bb632a5e027b2b';

// Directorio en tu servidor donde se guardará la base de datos del Manifiesto.
// DEBES CREAR ESTA CARPETA en la raíz de tu proyecto.
$manifest_dir = 'manifest/'; 

// Ruta al archivo de la base de datos local (cambiado a inglés).
$local_db_path = $manifest_dir . 'manifest_en.sqlite';

// Ruta al archivo que guardará el número de la versión local.
$local_version_file = $manifest_dir . 'version.txt';

// --- INICIO DE LA LÓGICA ---

// Función para mostrar mensajes de forma clara en el navegador.
function log_message($message, $type = 'info') {
    $color = 'black';
    if ($type === 'success') $color = 'green';
    if ($type === 'error') $color = 'red';
    echo "<p style='color: {$color}; margin: 5px 0;'>{$message}</p>";
}

echo "<h1>Gestor del Manifiesto de Destiny 2</h1>";

// 1. VERIFICAR SI EL DIRECTORIO 'manifest' EXISTE Y ES ESCRIBIBLE.
if (!is_dir($manifest_dir)) {
    if (!mkdir($manifest_dir, 0755, true)) {
        log_message("ERROR: No se pudo crear el directorio '{$manifest_dir}'. Por favor, créalo manualmente y dale permisos de escritura.", 'error');
        exit();
    }
}
if (!is_writable($manifest_dir)) {
    log_message("ERROR: El directorio '{$manifest_dir}' no tiene permisos de escritura.", 'error');
    exit();
}

// 2. OBTENER LA DEFINICIÓN DEL MANIFIESTO DESDE LA API DE BUNGIE.
log_message("Contactando a la API de Bungie para verificar la última versión del Manifiesto...");
$manifest_url = 'https://www.bungie.net/platform/Destiny2/Manifest/';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $manifest_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $api_key]);
$response = curl_exec($ch);
curl_close($ch);
$manifest_data = json_decode($response, true);

if (!isset($manifest_data['Response']['version'])) {
    log_message("ERROR: No se pudo obtener la información del Manifiesto desde la API de Bungie.", 'error');
    exit();
}

// Extraemos la versión remota y la URL de descarga para el Manifiesto en inglés (cambiado para mayor completitud).
$remote_version = $manifest_data['Response']['version'];
$remote_db_url = 'https://www.bungie.net' . $manifest_data['Response']['mobileWorldContentPaths']['en'];
log_message("Última versión disponible: {$remote_version}");

// 3. COMPARAR LA VERSIÓN REMOTA CON LA LOCAL.
$local_version = file_exists($local_version_file) ? file_get_contents($local_version_file) : null;
log_message("Versión local actual: " . ($local_version ?: 'Ninguna'));

if ($remote_version === $local_version && file_exists($local_db_path)) {
    log_message("¡El Manifiesto ya está actualizado!", 'success');
    exit();
}

// 4. DESCARGAR Y PROCESAR EL NUEVO MANIFIESTO.
log_message("Se necesita una nueva versión. Iniciando descarga desde: {$remote_db_url}");
$zip_path = $manifest_dir . 'manifest.zip';
$new_content = file_get_contents($remote_db_url);

if ($new_content === false) {
    log_message("ERROR: Falló la descarga del archivo ZIP del Manifiesto.", 'error');
    exit();
}

$download_size = strlen($new_content);
log_message("Tamaño de descarga: {$download_size} bytes");

if ($download_size < 10000000) {  // Umbral mínimo ~10 MB para detectar descargas incompletas
    log_message("ERROR: Descarga incompleta (tamaño: {$download_size} bytes). Posible problema de red o servidor de Bungie.", 'error');
    exit();
}

file_put_contents($zip_path, $new_content);
log_message("Descarga completada. Descomprimiendo archivo...");

// Descomprimir el archivo ZIP.
$zip = new ZipArchive;
if ($zip->open($zip_path) === TRUE) {
    // Buscamos el nombre del archivo de la base de datos dentro del ZIP.
    $db_filename_in_zip = $zip->getNameIndex(0);
    $zip->extractTo($manifest_dir);
    $zip->close();

    // Renombramos el archivo extraído a nuestro nombre estándar.
    $extracted_path = $manifest_dir . $db_filename_in_zip;
    rename($extracted_path, $local_db_path);

    // Guardamos el nuevo número de versión.
    file_put_contents($local_version_file, $remote_version);

    // Limpiamos el archivo ZIP descargado.
    unlink($zip_path);

    log_message("¡Manifiesto actualizado con éxito a la versión {$remote_version}!", 'success');
} else {
    log_message("ERROR: No se pudo abrir el archivo ZIP descargado.", 'error');
}

?>