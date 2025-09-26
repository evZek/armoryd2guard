<?php
session_start();

if (!isset($_SESSION['access_token'])) {
    header('Location: index.php');
    exit();
}

require_once 'api_handler_bungie.php';

$api_key = '0e6f1d0e6971472e93bb632a5e027b2b';
$access_token = $_SESSION['access_token'];
$item_instance_id = $_GET['instanceId'] ?? null;
$item_data = null;
$error_message = null;

// Obtener core settings para armorArchetypePlugSetHash (petición pública, no requiere access_token)
$settings_url = 'https://www.bungie.net/Platform/Settings/';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $settings_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $api_key]);
$settings_response = curl_exec($ch);
curl_close($ch);
$settings_data = json_decode($settings_response, true);
$armorArchetypePlugSetHash = $settings_data['Response']['destiny2CoreSettings']['armorArchetypePlugSetHash'] ?? null;

if ($item_instance_id && isset($_SESSION['user_profile'])) {
    $user_profile = $_SESSION['user_profile'];
    $membership_type = $user_profile['membershipType'];
    $membership_id = $user_profile['membershipId'];

    $result = get_single_item_details($membership_type, $membership_id, $item_instance_id, $access_token, $api_key);
    
    if (isset($result['error'])) {
        $error_message = $result['error'];
    } else {
        $item_data = $result['Response'] ?? null;
        $manifest_details = $result['manifestDetails'] ?? [];
    }
} elseif ($item_instance_id) {
    $error_message = "No se encontró el perfil de usuario en la sesión. Por favor, vuelve al dashboard y selecciona un personaje primero.";
}

$bungie_base_url = 'https://www.bungie.net';

$stat_map = [
    '2996146975' => ['name' => 'Armas', 'icon' => 'https://www.bungie.net/common/destiny2_content/icons/bc69675acdae9e6b9a68a02fb4d62e07.png'],
    '392767087'  => ['name' => 'Salud', 'icon' => 'https://www.bungie.net/common/destiny2_content/icons/717b8b218cc14325a54869bef21d2964.png'],
    '1943323491' => ['name' => 'Clase', 'icon' => 'https://www.bungie.net/common/destiny2_content/icons/7eb845acb5b3a4a9b7e0b2f05f5c43f1.png'],
    '1735777505' => ['name' => 'Granada', 'icon' => 'https://www.bungie.net/common/destiny2_content/icons/065cdaabef560e5808e821cefaeaa22c.png'],
    '144602215'  => ['name' => 'Súper', 'icon' => 'https://www.bungie.net/common/destiny2_content/icons/585ae4ede9c3da96b34086fccccdc8cd.png'],
    '4244567218' => ['name' => 'Cuerpo a Cuerpo', 'icon' => 'https://www.bungie.net/common/destiny2_content/icons/fa534aca76d7f2d7e7b4ba4df4271b42.png']
];

$archetype_map = [
    '3872825032' => 'Pistolero', '3872825033' => 'Ejemplar', '3872825034' => 'Camorrista',
    '3872825035' => 'Granadero', '3872825036' => 'Especialista', '3872825037' => 'Baluarte',
];

$archetype_icon_map = [
    'Baluarte' => 'https://www.bungie.net/common/destiny2_content/icons/v900_armor_archetype_health_class.v2.png',
    'Camorrista' => 'https://www.bungie.net/common/destiny2_content/icons/v900_armor_archetype_melee_class.v2.png',
    'Ejemplar' => 'https://www.bungie.net/common/destiny2_content/icons/v900_armor_archetype_super_grenade.v2.png',
    'Especialista' => 'https://www.bungie.net/common/destiny2_content/icons/v900_armor_archetype_class_super.v2.png',
    'Granadero' => 'https://www.bungie.net/common/destiny2_content/icons/v900_armor_archetype_grenade_heavy.v2.png',
    'Pistolero' => 'https://www.bungie.net/common/destiny2_content/icons/v900_armor_archetype_heavy_special.v2.png'
];

$archetype_calculated_map = [
    2996146975 => [1735777505 => 'Pistolero'], // Movilidad / Disciplina
    1943323491 => [2996146975 => 'Especialista'], // Recuperación / Movilidad
    392767087 => [1943323491 => 'Baluarte'], // Resistencia / Recuperación
    4244567218 => [392767087 => 'Camorrista'], // Fuerza / Resistencia
    1735777505 => [144602215 => 'Granadero'], // Disciplina / Intelecto
    144602215 => [4244567218 => 'Ejemplar'], // Intelecto / Fuerza
];

// Map para descripciones de campos en Instancia
$instance_field_map = [
    'damageType' => 'Tipo de daño elemental de la armadura (e.g., 0=None, 1=Kinético, 2=Arco, 3=Solar, 4=Vacío, 5=Estasis, 6=Strand). Indica la afinidad elemental para mods y builds.',
    'itemLevel' => 'Nivel base del item antes de infusiones, usado para calcular quality y upgrades potenciales.',
    'quality' => 'Calidad de display del item (0-10), relacionado con el potencial de roll de stats base.',
    'isEquipped' => 'Si la armadura está actualmente equipada en el personaje (true/false).',
    'canEquip' => 'Si la armadura se puede equipar ahora, considerando nivel, clase y otros requisitos.',
    'equipRequiredLevel' => 'Nivel mínimo del personaje requerido para equipar la armadura.',
    'unlockHashesRequiredToEquip' => 'Lista de hashes de unlocks (quests o triumphs) necesarios para equipar, común en exóticas o armaduras bloqueadas.',
    'cannotEquipReason' => 'Flags indicando la razón por la que no se puede equipar (e.g., 0=OK, 1=WrongClass, 2=LowLevel).',
    'breakerType' => 'Tipo de breaker para campeones (e.g., 0=None, 3=Stagger). Indica capacidad anti-campeones intrínseca en la armadura.',
    'lockable' => 'Si la armadura se puede bloquear para protegerla contra eliminación accidental.',
    'notTransferrable' => 'Si la armadura no se puede transferir a la bóveda u otro personaje, común en items de quests.',
    'instanceBucketHash' => 'Hash del bucket donde está almacenada la armadura (e.g., equipado, bóveda); se puede traducir via manifiesto a nombre legible como "Equipado".',
    'energy' => 'Detalles de energía de la armadura: tipo (e.g., 1=Arco, 2=Solar, 3=Vacío), capacidad máxima, usada por mods, y no usada (calculada como capacity - used). Esencial para Armor 3.0.',
    'isMasterwork' => 'Si la armadura es una obra maestra (true si energyCapacity alcanza el máximo, permitiendo mods adicionales como artifice).'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspector de Armadura - ArmoryD2Guard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="text-gray-300">
    <header class="bg-gray-900/80 backdrop-blur-sm border-b border-gray-700 shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="dashboard.php" class="text-2xl font-bold text-white tracking-tighter">Armory<span class="text-blue-400">D2</span>Guard</a>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Cerrar Sesión</a>
            </div>
        </nav>
    </header>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Inspector de Armadura</h1>
            <a href="dashboard.php" class="text-blue-400 hover:text-blue-300 transition-colors">&larr; Volver al Dashboard</a>
        </div>
        
        <form method="GET" action="all_stats.php" class="mb-8 bg-gray-800/50 border border-gray-700 rounded-lg p-4 flex items-center gap-4">
            <label for="instanceId" class="font-semibold text-white">Item Instance ID:</label>
            <input type="text" id="instanceId" name="instanceId" value="<?php echo htmlspecialchars($item_instance_id ?? ''); ?>" class="flex-grow bg-gray-900 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Inspeccionar</button>
        </form>

        <?php if ($error_message): ?>
            <div class="bg-red-900/50 border border-red-700 rounded-lg p-4 mb-6">
                <p class="text-red-300 font-semibold"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php elseif ($item_data): ?>
            <?php 
                $main_item_hash = $item_data['item']['data']['itemHash'] ?? null;
                $main_item_def = $manifest_details[$main_item_hash] ?? null;
                
                // Fallback si no se encuentra en manifiesto local
                if (!$main_item_def && $main_item_hash) {
                    $definition_url = "https://www.bungie.net/Platform/Destiny2/Manifest/DestinyInventoryItemDefinition/{$main_item_hash}/";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $definition_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $api_key]);
                    $definition_response = curl_exec($ch);
                    curl_close($ch);
                    $definition_data = json_decode($definition_response, true);
                    if (isset($definition_data['Response'])) {
                        $main_item_def = $definition_data['Response'];
                        log_api_error("Usando fallback API para hash de item no encontrado en manifiesto local: $main_item_hash");
                    } else {
                        log_api_error("Fallback API falló para hash: $main_item_hash", $definition_data);
                    }
                }
                
                $icon_path = $main_item_def['displayProperties']['icon'] ?? '';
                $name = $main_item_def['displayProperties']['name'] ?? 'Item Desconocido';
                $power = $item_data['instance']['data']['primaryStat']['value'] ?? 'N/A';
                $energy = $item_data['instance']['data']['energy'] ?? null;
                $energy_type = $energy['energyType'] ?? 'Desconocido';
                $energy_capacity = $energy['energyCapacity'] ?? 'N/A';
                $energy_used = $energy['energyUsed'] ?? 'N/A';
                $energy_unused = $energy_capacity - $energy_used;
                $masterwork = $item_data['instance']['data']['isMasterwork'] ?? false;
                
                // Preparar instance_data con campos adicionales para 100%
                $instance_data = $item_data['instance']['data'] ?? [];
                $instance_data['energy'] = $energy;
                $instance_data['isMasterwork'] = $masterwork;
            ?>
            
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna Izquierda: Instancia (Caract. No Modificables) -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-white mb-2 border-b-2 border-gray-700 pb-2">Instancia (Caract. No Modificables)</h3>
                    <div class="flex items-center gap-4 bg-gray-800/50 p-3 rounded-lg">
                        <?php if ($icon_path): ?>
                            <img src="<?php echo $bungie_base_url . $icon_path; ?>" class="w-16 h-16 bg-gray-700 rounded-md flex-shrink-0" alt="">
                        <?php endif; ?>
                        <div>
                            <p class="font-bold text-white text-xl"><?php echo htmlspecialchars($name); ?></p>
                            <p class="text-yellow-400 text-lg">Poder: <?php echo $power; ?></p>
                        </div>
                    </div>
                    <?php foreach ($instance_field_map as $key => $desc): ?>
                        <?php if (isset($instance_data[$key])): ?>
                            <?php 
                                $value = is_array($instance_data[$key]) ? json_encode($instance_data[$key], JSON_PRETTY_PRINT) : $instance_data[$key];
                            ?>
                            <div class="bg-gray-800/50 p-3 rounded-lg">
                                <code><?php echo $key; ?> (<?php echo gettype($instance_data[$key]); ?>):</code>
                                <pre class="text-xs text-gray-400 mt-2 whitespace-pre-wrap break-all bg-black/30 p-2 rounded-md"><?php echo htmlspecialchars($value); ?></pre>
                                <p class="text-sm text-gray-400"><?php echo $desc; ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php 
                    // Renderizar campos restantes no en map para 100%
                    foreach ($instance_data as $key => $value): 
                        if (!array_key_exists($key, $instance_field_map) && !in_array($key, ['primaryStat', 'energy', 'isMasterwork'])): 
                            $type = gettype($value);
                            $val_str = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : htmlspecialchars($value);
                            $desc = 'Descripción pendiente (ver docs Bungie para detalles específicos).';
                    ?>
                        <div class="bg-gray-800/50 p-3 rounded-lg">
                            <code><?php echo $key; ?> (<?php echo $type; ?>):</code>
                            <pre class="text-xs text-gray-400 mt-2 whitespace-pre-wrap break-all bg-black/30 p-2 rounded-md"><?php echo $val_str; ?></pre>
                            <p class="text-sm text-gray-400"><?php echo $desc; ?></p>
                        </div>
                    <?php endif; endforeach; ?>
                </div>

                <!-- Columna Central: Estadísticas de Armadura -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-white mb-2 border-b-2 border-gray-700 pb-2">Estadísticas de Armadura</h3>
                    <?php 
                        // Nueva lógica dinámica para encontrar el arquetipo usando armorArchetypePlugSetHash
                        $archetype_name = null;
                        $archetype_icon = null;
                        $plug_hash = null;
                        if ($armorArchetypePlugSetHash && isset($item_data['sockets']['data']['sockets'])) {
                            foreach ($item_data['sockets']['data']['sockets'] as $socket) {
                                if (isset($socket['plugSetHash']) && $socket['plugSetHash'] == $armorArchetypePlugSetHash) {
                                    $plug_hash = $socket['plugHash'] ?? null;
                                    if ($plug_hash && isset($manifest_details[$plug_hash])) {
                                        $archetype_name = $manifest_details[$plug_hash]['displayProperties']['name'] ?? null;
                                        $archetype_icon = $bungie_base_url . ($manifest_details[$plug_hash]['displayProperties']['icon'] ?? '');
                                    }
                                    break;
                                }
                            }
                        }
                        // Fallback a la lógica hardcoded si no se encuentra con la dinámica
                        if (!$archetype_name && isset($item_data['sockets']['data']['sockets'])) {
                            foreach ($item_data['sockets']['data']['sockets'] as $socket) {
                                $plug_hash = $socket['plugHash'] ?? null;
                                if ($plug_hash && isset($archetype_map[$plug_hash])) {
                                    $archetype_name = $archetype_map[$plug_hash];
                                    if (isset($manifest_details[$plug_hash])) {
                                        $archetype_icon = $bungie_base_url . ($manifest_details[$plug_hash]['displayProperties']['icon'] ?? '');
                                    }
                                    break;
                                }
                            }
                        }
                        // Fallback adicional: Calcular basado en valores de estadísticas
                        if (!$archetype_name) {
                            $stat_values = [];
                            foreach (($item_data['stats']['data']['stats'] ?? []) as $stat_hash => $stat_data) {
                                if (isset($stat_map[$stat_hash])) {
                                    $stat_values[$stat_hash] = $stat_data['value'];
                                }
                            }
                            if (count($stat_values) == 6) {
                                arsort($stat_values);
                                $stats_sorted = array_keys($stat_values);
                                $primary_hash = $stats_sorted[0];
                                $secondary_hash = $stats_sorted[1];
                                $archetype_name = $archetype_calculated_map[$primary_hash][$secondary_hash] ?? null;
                            }
                        }
                        // Fallback para ícono si no se obtuvo del manifiesto
                        if ($archetype_name && !$archetype_icon && isset($archetype_icon_map[$archetype_name])) {
                            $archetype_icon = $archetype_icon_map[$archetype_name];
                        }
                        if ($archetype_name):
                    ?>
                        <div class="bg-blue-900/50 border border-blue-700 p-3 rounded-lg">
                            <p class="text-sm font-bold text-blue-300 mb-1">ARQUETIPO INTRÍNSECO</p>
                            <div class="flex items-center gap-2">
                                <?php if ($archetype_icon): ?>
                                    <img src="<?php echo $archetype_icon; ?>" class="w-5 h-5" alt="<?php echo $archetype_name; ?>">
                                <?php endif; ?>
                                <p class="font-bold text-xl text-white"><?php echo $archetype_name; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach (($item_data['stats']['data']['stats'] ?? []) as $stat_hash => $stat_data): ?>
                        <?php if (isset($stat_map[$stat_hash])): ?>
                            <div class="bg-gray-800/50 p-3 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <img src="<?php echo $stat_map[$stat_hash]['icon']; ?>" class="w-5 h-5" alt="<?php echo $stat_map[$stat_hash]['name']; ?>">
                                    <p class="font-semibold text-white"><?php echo $stat_map[$stat_hash]['name']; ?>: <span class="text-lg"><?php echo $stat_data['value']; ?></span></p>
                                </div>
                                <pre class="text-xs text-gray-400 mt-2 whitespace-pre-wrap break-all bg-black/30 p-2 rounded-md"><?php echo htmlspecialchars(json_encode($stat_data, JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <!-- Columna Derecha: Mods/Cosméticos -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-white mb-2 border-b-2 border-gray-700 pb-2">Mods/Cosméticos</h3>
                    <?php foreach (($item_data['sockets']['data']['sockets'] ?? []) as $socket): 
                        $plug_hash = $socket['plugHash'] ?? null;
                        if ($plug_hash) {
                            // Fallback si no se encuentra en manifiesto local
                            if (!isset($manifest_details[$plug_hash])) {
                                $definition_url = "https://www.bungie.net/Platform/Destiny2/Manifest/DestinyInventoryItemDefinition/{$plug_hash}/";
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $definition_url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $api_key]);
                                $definition_response = curl_exec($ch);
                                curl_close($ch);
                                $definition_data = json_decode($definition_response, true);
                                if (isset($definition_data['Response'])) {
                                    $manifest_details[$plug_hash] = $definition_data['Response'];
                                    log_api_error("Usando fallback API para plug hash no encontrado en manifiesto local: $plug_hash en item $main_item_hash");
                                } else {
                                    log_api_error("Fallback API falló para plug hash: $plug_hash", $definition_data);
                                    continue; // Salta si falla
                                }
                            }
                            $plug_def = $manifest_details[$plug_hash];
                    ?>
                        <div class="bg-gray-800/50 p-3 rounded-lg">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($plug_def['displayProperties']['icon'])): ?>
                                    <img src="<?php echo $bungie_base_url . $plug_def['displayProperties']['icon']; ?>" class="w-10 h-10 bg-gray-700 rounded-md flex-shrink-0" alt="">
                                <?php endif; ?>
                                <div>
                                    <p class="font-semibold text-white"><?php echo $plug_def['displayProperties']['name'] ?? 'Plug Desconocido'; ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $plug_def['itemTypeDisplayName'] ?? 'Ventaja'; ?></p>
                                </div>
                            </div>
                             <pre class="text-xs text-gray-400 mt-2 whitespace-pre-wrap break-all bg-black/30 p-2 rounded-md"><?php echo htmlspecialchars(json_encode($socket, JSON_PRETTY_PRINT)); ?></pre>
                        </div>
                    <?php } endforeach; ?>
                </div>

            </div>
        <?php endif; ?>

    </main>
</body>
</html>