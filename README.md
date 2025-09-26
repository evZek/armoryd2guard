# Resumen Técnico de la Aplicación ArmoryD2Guard

## 1. Objetivo de la Aplicación
ArmoryD2Guard es una aplicación web desarrollada en PHP con un diseño responsivo (usando Tailwind CSS, Flexbox y Media Queries) cuyo propósito es permitir a los jugadores de Destiny 2 autenticarse de forma segura para visualizar y analizar las piezas de armadura equipadas en sus personajes. La aplicación consume la API oficial de Bungie.net para obtener datos en tiempo real sobre perfiles, personajes y equipamiento, y utiliza una copia local del Manifiesto de Destiny 2 (en formato SQLite) para traducir hashes a datos legibles como nombres, íconos y tipos de items. No se almacenan datos sensibles del usuario más allá de tokens de sesión temporales.

## 2. Credenciales y Acceso a la API
La aplicación se comunica con la API de Bungie utilizando las siguientes credenciales, obtenidas al registrar la app en el portal de desarrolladores de Bungie:
- **Clave API (API Key)**: `0e6f1d0e6971472e93bb632a5e027b2b`
  - Función: Identifica a la aplicación en cada petición pública o autenticada a la API. Es requerida en cabeceras HTTP para todas las llamadas.
- **OAuth client_id**: `50779`
  - Función: Identificador público de la aplicación durante el flujo de autorización OAuth.
- **OAuth client_secret**: `Mwj5PROdnqaZgxIy6eDu-Yeb4HU1HzKHI6F3jgBUaww`
  - Función: Clave secreta usada por el servidor para autenticar la aplicación al intercambiar un código de autorización por tokens de acceso. Se maneja exclusivamente en el backend (callback.php) para seguridad.

Las peticiones a la API usan cURL con cabeceras como `X-API-Key` y `Authorization: Bearer [access_token]` cuando es necesario. Errores se registran en `api_errors.log`.

## 3. Flujo de Autenticación (OAuth 2.0)
El proceso para que un usuario acceda a sus datos sigue el estándar OAuth 2.0 con Authorization Code Grant:
1. **Inicio (index.php)**: El usuario accede a la página principal y hace clic en "Identificarse", lo que redirige a la URL de autorización de Bungie con el `client_id` y `response_type=code`.
2. **Redirección a Bungie**: Bungie muestra una página de login donde el usuario inicia sesión con su cuenta (Xbox, PlayStation, Steam, etc.) y autoriza el acceso a datos básicos de Destiny 2.
3. **Callback (callback.php)**: Bungie redirige de vuelta a `callback.php` con un parámetro `code` en la URL. El script verifica el código, lo intercambia por un `access_token` y `refresh_token` vía POST a Bungie (usando `client_id` y `client_secret`), y guarda los tokens en `$_SESSION` para sesiones seguras.
4. **Gestión de Sesión**: El `access_token` (válido por 1 hora) se usa en peticiones subsiguientes. Si expira, se redirige a login. No se implementa refresh automático en el código actual.
5. **Acceso al Dashboard (dashboard.php)**: Redirige al panel principal si el token es válido; de lo contrario, destruye la sesión y redirige a index.php.
6. **Logout (logout.php)**: Destruye la sesión y redirige a index.php.

La redirección URI es `https://armoryd2guard.evzek.net/callback.php`, que debe coincidir exactamente con la registrada en Bungie. `.htaccess` fuerza no-www para consistencia.

## 4. Arquitectura de Archivos
El proyecto está estructurado de forma modular para facilitar el mantenimiento y la depuración. A continuación, se detallan dos versiones de la estructura: una descriptiva completa basada en el análisis del código, y otra en formato de árbol con indicadores "+" para elementos generados automáticamente.

### Versión 1: Estructura Descriptiva (Basada en Código Verificado)
Esta estructura refleja solo lo confirmado en el código proporcionado, incluyendo generación automática de archivos/carpetas.

/armoryd2guard
├── css/                # Carpeta para estilos CSS. No se crea automáticamente; debe crearse manualmente. Almacena hojas de estilo como style.css, referenciadas en archivos PHP como index.php, dashboard.php y all_stats.php para aplicar Tailwind y estilos personalizados. No se genera contenido automáticamente en el código proporcionado.
│   └── style.css       # Hoja de estilos principal (Tailwind y personalizaciones). No se genera automáticamente; debe crearse manualmente. Proporciona estilos CSS para la interfaz de usuario en las páginas HTML/PHP.
├── manifest/           # Carpeta para el Manifiesto de Destiny 2. Se crea automáticamente por manifest_handler.php si no existe (usando mkdir). Almacena la base de datos local del manifiesto, versión y archivos temporales/caché relacionados con la API de Bungie.
│   ├── cache/          # Subcarpeta para caché de fallbacks del manifiesto. Se crea automáticamente por api_handler_bungie.php si no existe (usando mkdir en get_manifest_item_details). Almacena fallback_cache.json para hashes no encontrados en la base de datos local.
│   │   └── fallback_cache.json # Archivo de caché JSON para definiciones de items fallback. Se genera automáticamente por api_handler_bungie.php cuando se usan fallbacks de API (file_put_contents). Contiene datos cached de llamadas a la API de Bungie para optimizar consultas futuras.
│   ├── manifest_en.sqlite # Base de datos SQLite del Manifiesto en inglés. Se genera automáticamente por manifest_handler.php durante la actualización (descarga ZIP, extrae, renombra). Contiene definiciones de items, seasons, etc., de la API de Bungie para consultas locales rápidas.
│   └── version.txt     # Archivo de texto con la versión actual del Manifiesto. Se genera automáticamente por manifest_handler.php al actualizar (file_put_contents). Almacena el número de versión remota para comparar en futuras ejecuciones.
├── .htaccess           # Configuración de Apache para reescritura de URLs. No se genera automáticamente; debe crearse manualmente. Fuerza redirecciones de www a non-www para el dominio armoryd2guard.evzek.net.
├── all_stats.php       # Inspector detallado para una pieza de armadura individual. No se genera automáticamente; es un script PHP principal. Muestra detalles de un item específico (stats, mods, etc.) usando datos de la API de Bungie y manifiesto; incluye formulario para inspeccionar por instanceId.
├── api_errors.log      # Registro de errores de la API de Bungie. Se genera automáticamente por api_handler_bungie.php (file_put_contents en log_api_error). Registra errores de peticiones a la API, timestamps y datos JSON para depuración.
├── api_handler_bungie.php # Módulo central para la API y el Manifiesto. No se genera automáticamente; es un script PHP con funciones. Maneja peticiones a Bungie API, logging de errores, obtención de perfiles/personajes, detalles de items y consultas al manifiesto local (con fallbacks y caché).
├── callback.php        # Maneja la autenticación OAuth de Bungie. No se genera automáticamente; es un script PHP. Procesa el código de autorización de Bungie, intercambia por tokens y los guarda en sesión para autenticación.
├── dashboard.php       # Panel principal del usuario. No se genera automáticamente; es un script PHP principal. Muestra personajes, equipamiento, stats y resúmenes; usa datos de API para renderizar interfaces de selección y detalles.
├── index.php           # Página de inicio y login. No se genera automáticamente; es un script PHP principal. Presenta la página de login con enlace a autorización de Bungie OAuth.
├── logout.php          # Script para cerrar la sesión del usuario. No se genera automáticamente; es un script PHP simple. Destruye la sesión y redirige a index.php.
└── manifest_handler.php # Herramienta para descargar y actualizar el Manifiesto. No se genera automáticamente; es un script PHP ejecutable. Verifica, descarga y procesa el manifiesto de Bungie, generando archivos en manifest/ (como manifest_en.sqlite y version.txt); incluye logging en pantalla para depuración.

### Notas sobre archivos generados automáticamente:
- **Generados por manifest_handler.php**: manifest_en.sqlite (descargado y renombrado desde ZIP de Bungie), version.txt (escrito con la versión remota). Temporalmente genera manifest.zip (descargado, luego borrado con unlink).
- **Generados por api_handler_bungie.php**: api_errors.log (logs de errores), fallback_cache.json en manifest/cache/ (caché de fallbacks API).
- **Carpetas generadas automáticamente**: manifest/ (por manifest_handler.php si no existe), manifest/cache/ (por api_handler_bungie.php si no existe).
- **No generados automáticamente**: Todos los scripts PHP principales (.php), .htaccess, y style.css (deben crearse manualmente). No hay mención a manifest_handler.log en el código proporcionado, por lo que no se genera (posiblemente de una versión anterior). La carpeta css/ y su contenido no se crean automáticamente; se asumen existentes.

### Versión 2: Estructura en Árbol con "+" para Elementos Generados Automáticamente (Incluyendo Permisos CHMOD)
Esta versión muestra el árbol base (archivos/carpetas manuales), y agrega con "+" los que se generan automáticamente según el código (iniciando con "+"). Incluyo solo lo verificado en código; ignoro extras en capturas como manifest_es.sqlite o cache.json (ya que no coinciden exactamente con el código). Las descripciones breves se mantienen para claridad. He agregado permisos CHMOD recomendados y observados en capturas/estándares PHP web:
- **Carpetas**: CHMOD 755 (drwxr-xr-x) – Permite lectura/ejecución pública, escritura solo por owner (ideal para seguridad en servidores shared).
- **Archivos**: CHMOD 644 (-rw-r--r--) – Lectura pública, escritura solo por owner (previene ediciones no autorizadas; PHP se ejecuta vía interpreter).
- Notas: Estos son permisos por defecto en capturas. Para escritura en logs/caché (ej. por usuario web como www-data), el owner debe ser el usuario del server o usar group writable si es necesario. Usa `chmod` en terminal o FTP para setear.

/armoryd2guard
├── css/                # Carpeta para estilos CSS. No se crea automáticamente; debe crearse manualmente. CHMOD 755 (drwxr-xr-x).
│   └── style.css       # Hoja de estilos principal. No se genera automáticamente; debe crearse manualmente. CHMOD 644 (-rw-r--r--).
├── manifest/           # Carpeta para el Manifiesto. Se crea automáticamente por manifest_handler.php si no existe. CHMOD 755 (drwxr-xr-x).
│   + ├── cache/        # Subcarpeta para caché. Se crea automáticamente por api_handler_bungie.php si no existe. CHMOD 755 (drwxr-xr-x).
│   │   + └── fallback_cache.json # Caché JSON para fallbacks. Se genera automáticamente por api_handler_bungie.php. CHMOD 644 (-rw-r--r--).
│   + ├── manifest_en.sqlite # Base de datos SQLite. Se genera automáticamente por manifest_handler.php. CHMOD 644 (-rw-r--r--).
│   + └── version.txt   # Versión del manifiesto. Se genera automáticamente por manifest_handler.php. CHMOD 644 (-rw-r--r--).
├── .htaccess           # Config de Apache. No se genera automáticamente; debe crearse manualmente. CHMOD 644 (-rw-r--r--).
├── all_stats.php       # Inspector de armadura. No se genera automáticamente; es script principal. CHMOD 644 (-rw-r--r--).
+ ├── api_errors.log    # Log de errores API. Se genera automáticamente por api_handler_bungie.php. CHMOD 644 (-rw-r--r--).
├── api_handler_bungie.php # Módulo API. No se genera automáticamente; es script con funciones. CHMOD 644 (-rw-r--r--).
├── callback.php        # Autenticación OAuth. No se genera automáticamente; es script. CHMOD 644 (-rw-r--r--).
├── dashboard.php       # Panel usuario. No se genera automáticamente; es script principal. CHMOD 644 (-rw-r--r--).
├── index.php           # Página login. No se genera automáticamente; es script principal. CHMOD 644 (-rw-r--r--).
├── logout.php          # Cierre sesión. No se genera automáticamente; es script. CHMOD 644 (-rw-r--r--).
└── manifest_handler.php # Actualizador manifiesto. No se genera automáticamente; es script ejecutable. CHMOD 644 (-rw-r--r--).

## 5. El Manifiesto de Destiny 2
El Manifiesto es una base de datos estática proporcionada por Bungie que contiene definiciones de todos los items, seasons y otros elementos del juego.
- **Por qué lo usamos**: La API de Bungie devuelve hashes numéricos (ej. itemHash: 12345). El Manifiesto traduce estos a datos legibles como nombres ("Máscara Última Disciplina"), íconos, tipos de stats y archetypes.
- **Gestión del Manifiesto (manifest_handler.php)**: Esta herramienta verifica la versión remota vía API, descarga un ZIP del Manifiesto en inglés si es necesario, lo extrae a `manifest_en.sqlite`, y guarda la versión en `version.txt`. Usa cURL para peticiones y ZipArchive para descompresión. Si la descarga falla o es incompleta (<10MB), registra errores en pantalla.
- **Consulta del Manifiesto (api_handler_bungie.php)**: Funciones como `get_manifest_item_details` conectan a `manifest_en.sqlite` vía PDO para queries SQL en tablas como DestinyInventoryItemDefinition y DestinySeasonDefinition. Incluye fallbacks a API de Bungie si un hash no se encuentra localmente, con caching en `fallback_cache.json` para optimizar. Errores se loguean en `api_errors.log`.

## 6. Funcionalidad Actual
### a. Panel Principal (dashboard.php)
- **Selección de Personaje**: Muestra tarjetas para cada personaje (Titán, Cazador, Hechicero) con emblema, clase y nivel de luz, obtenidos vía `get_user_characters_and_profile`.
- **Visualización de Equipamiento**: Para el personaje seleccionado, lista las 5 piezas de armadura equipadas (casco, guanteletes, pecho, piernas, objeto de clase) usando `get_character_details`. Por pieza: ícono, nombre, poder, stats (movilidad, resistencia, etc.), total stats, energía (barra visual), indicador de masterwork, y archetype (ej. Pistolero).
- **Resumen de Estadísticas**: Suma total de las 6 stats del equipamiento actual, con íconos SVG.
- **Enlaces**: Cada pieza tiene un enlace a all_stats.php vía itemInstanceId.

### b. Inspector de Armaduras (all_stats.php)
- **Objetivo**: Análisis detallado de una pieza individual por itemInstanceId (manual o desde dashboard).
- **Funcionamiento**: Usa `get_single_item_details` para datos de API y manifiesto. Muestra en columnas:
  - **Instancia**: Datos no modificables (daño, nivel, quality, equipable, energía, masterwork, etc.), con descripciones.
  - **Estadísticas**: Las 6 stats con íconos, más archetype intrínseco (dinámico o calculado).
  - **Mods/Cosméticos**: Lista de sockets/plugs con nombres, íconos y datos JSON.
- **Fallbacks**: Si un hash no está en manifiesto, usa API de Bungie con logging.

La app no incluye funcionalidades como edición de armaduras o almacenamiento persistente; se enfoca en visualización segura y en tiempo real.