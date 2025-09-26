<?php
session_start();

if (isset($_SESSION['token_expires_in']) && time() > $_SESSION['token_expires_in']) {
    session_unset();
    session_destroy();
    header('Location: index.php?error=session_expired');
    exit();
}
if (!isset($_SESSION['access_token'])) {
    header('Location: index.php');
    exit();
}

require_once 'api_handler_bungie.php';

$api_key = '0e6f1d0e6971472e93bb632a5e027b2b';
$access_token = $_SESSION['access_token'];
$selected_character_id = $_GET['character'] ?? null;

if (!isset($_SESSION['user_profile'])) {
    $user_data = get_user_characters_and_profile($access_token, $api_key);
    if (!isset($user_data['error'])) {
        $_SESSION['user_profile'] = $user_data;
    }
} else {
    $user_data = $_SESSION['user_profile'];
}

$bungie_name = $user_data['bungieName'] ?? 'Guardián';
$characters = $user_data['characters'] ?? [];
$error_message = $user_data['error'] ?? null;
$character_details = null;
$manifest_items = [];
$bungie_base_url = 'https://www.bungie.net';

if ($selected_character_id && !$error_message) {
    if (isset($user_data['membershipType']) && isset($user_data['membershipId'])) {
        $membership_type = $user_data['membershipType'];
        $membership_id = $user_data['membershipId'];
        
        $details_data = get_character_details($membership_type, $membership_id, $selected_character_id, $access_token, $api_key);

        if (isset($details_data['Response'])) {
            $character_details = $details_data['Response'];
            $item_hashes = [];
            if (isset($character_details['equipment']['data']['items'])) {
                foreach ($character_details['equipment']['data']['items'] as $item) {
                    $item_hashes[] = $item['itemHash'];
                }
            }
            if (!empty($item_hashes)) {
                $manifest_items = get_manifest_item_details(array_unique($item_hashes));
            }
        } else {
            if(isset($details_data['ErrorCode']) && $details_data['ErrorCode'] != 1) {
                 session_unset(); session_destroy();
                 header('Location: index.php?error=api_token_invalid');
                 exit();
            }
            $error_message = $details_data['error'] ?? 'No se pudo obtener la información del personaje.';
        }
    } else {
        $error_message = "No se encontraron los datos de membresía del usuario.";
    }
}

$class_map = ['3655393761' => 'Titán', '671679327' => 'Cazador', '2271682572' => 'Hechicero'];
$stat_map = [
    '2996146975' => ['name' => 'Movilidad', 'svg' => '<path d="M12.96.65.65,12.96l2.39,2.39L15.35,3.04,12.96.65ZM15.2,19.35,19.35,15.2,6.4,2.25,2.25,6.4l12.95,12.95Z"/>'],
    '392767087'  => ['name' => 'Resistencia', 'svg' => '<path d="M10.8,21.75,20.55,12,10.8,2.25,1.05,12,10.8,21.75ZM4.1,12l6.7,6.7,6.7-6.7-6.7-6.7L4.1,12Z"/>'],
    '1943323491' => ['name' => 'Recuperación', 'svg' => '<path d="M12,2.25h-.75V8.4H5.1v.75H11.25V15h.75v-5.85h6.15v-.75H12V2.25Z"/>'],
    '1735777505' => ['name' => 'Disciplina', 'svg' => '<path d="M12,1.05a1.2,1.2,0,0,1,1.2,1.2V4.8H15.6a1.2,1.2,0,0,1,0,2.4H13.2V9.6H15.6a1.2,1.2,0,0,1,0,2.4H13.2v2.4a1.2,1.2,0,0,1-2.4,0V12H8.4a1.2,1.2,0,1,1,0-2.4H10.8V7.2H8.4a1.2,1.2,0,1,1,0-2.4H10.8V2.25A1.2,1.2,0,0,1,12,1.05Z"/>'],
    '144602215'  => ['name' => 'Intelecto', 'svg' => '<path d="M10.8,3.3,9,5.1V9.3h6v1.8H9v4.2L10.8,17.1,7.2,18.9,5.4,17.1V7.72L2.7,5.05,5.4,2.4,7.2,4.2V3.3ZM12.6,9.3V7.05L10.8,5.25,9,7.05V9.3Zm0,1.8v2.25L10.8,15.15,9,13.35V11.1Z"/>'],
    '4244567218' => ['name' => 'Fuerza', 'svg' => '<path d="M19.95,12a1,1,0,0,1-1,1H5.05a1,1,0,0,1,0-2h13.9A1,1,0,0,1,19.95,12ZM12,5.05a1,1,0,0,1,1,1v13.9a1,1,0,0,1-2,0V6.05A1,1,0,0,1,12,5.05Z"/>']
];
$archetype_map = [
    '3872825032' => 'Pistolero', '3872825033' => 'Ejemplar', '3872825034' => 'Camorrista',
    '3872825035' => 'Granadero', '3872825036' => 'Especialista', '3872825037' => 'Baluarte',
];
$armor_bucket_map = [
    '3448274439' => 'Casco', '3551918588' => 'Guanteletes', '14239492'   => 'Pecho',
    '20886954'   => 'Piernas', '1585787867' => 'Objeto de Clase'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ArmoryD2Guard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="text-gray-300">

    <header class="bg-gray-900/80 backdrop-blur-sm border-b border-gray-700 shadow-lg sticky top-0 z-50">
        <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="dashboard.php" class="text-2xl font-bold text-white tracking-tighter">Armory<span class="text-blue-400">D2</span>Guard</a>
                <div class="flex items-center space-x-4">
                    <span class="hidden sm:block font-semibold text-white"><?php echo htmlspecialchars($bungie_name); ?></span>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Cerrar Sesión</a>
                </div>
            </div>
        </nav>
    </header>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        
        <?php if ($error_message): ?>
            <div class="bg-red-500 border border-red-700 text-white px-4 py-3 rounded-lg" role="alert">
                <strong class="font-bold">¡Error!</strong> <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>

        <?php elseif ($character_details): ?>
            <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 gap-4">
                <h1 class="text-3xl font-bold text-white">Equipamiento de tu <?php echo $class_map[$characters[$selected_character_id]['classHash']] ?? 'Personaje'; ?></h1>
                <a href="dashboard.php" class="text-blue-400 hover:text-blue-300 transition-colors">&larr; Volver a la selección</a>
            </div>

            <div class="space-y-4 mb-12">
                <?php
                $total_stats_summary = array_fill_keys(array_keys($stat_map), 0);
                $item_components = $character_details['itemComponents'] ?? [];
                $equipped_items = $character_details['equipment']['data']['items'] ?? [];

                foreach ($equipped_items as $item):
                    $bucket_hash = $item['bucketHash'];
                    if (array_key_exists($bucket_hash, $armor_bucket_map)):
                        $item_hash = $item['itemHash'];
                        $item_instance_id = $item['itemInstanceId'];
                        $manifest_data = $manifest_items[$item_hash] ?? null;
                        if (!$manifest_data) continue;

                        $power = $item_components['instances']['data'][$item_instance_id]['primaryStat']['value'] ?? 0;
                        $energy_capacity = $item_components['instances']['data'][$item_instance_id]['energy']['energyCapacity'] ?? 0;
                        $is_masterwork = $energy_capacity === 10;
                        
                        $investment_stat_hash = !empty($manifest_data['investmentStats']) ? $manifest_data['investmentStats'][0]['statTypeHash'] : null;
                        $archetype_name = $archetype_map[$investment_stat_hash] ?? '';
                        $current_stats = [];
                        $total_stat_value = 0;

                        if (isset($item_components['stats']['data'][$item_instance_id]['stats'])) {
                            foreach ($item_components['stats']['data'][$item_instance_id]['stats'] as $stat_hash => $stat_data) {
                                if (array_key_exists($stat_hash, $stat_map)) {
                                    $stat_value = $stat_data['value'];
                                    $current_stats[$stat_hash] = $stat_value;
                                    $total_stat_value += $stat_value;
                                    $total_stats_summary[$stat_hash] += $stat_value;
                                }
                            }
                        }
                ?>
                <div class="bg-gray-800/50 border rounded-lg p-4 flex gap-4 items-center <?php echo $is_masterwork ? 'border-yellow-400 shadow-md shadow-yellow-400/20' : 'border-gray-700'; ?>">
                    <div class="flex-shrink-0 text-center">
                        <div class="relative inline-block">
                            <img src="<?php echo $bungie_base_url . $manifest_data['displayProperties']['icon']; ?>" class="w-20 h-20 bg-gray-700 rounded-md" alt="">
                            <span class="absolute top-0 left-0 bg-black/50 text-yellow-400 font-bold text-lg px-1 rounded-br-md"><?php echo $power; ?></span>
                            <div class="absolute bottom-0 left-0 right-0 flex justify-center items-center h-4 bg-black/50 rounded-b-md">
                                <?php for ($i = 0; $i < 10; $i++): ?>
                                    <div class="w-1 h-2 mx-px transform -skew-x-12 <?php echo $i < $energy_capacity ? ($is_masterwork ? 'bg-yellow-400' : 'bg-white') : 'bg-gray-600'; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <a href="all_stats.php?instanceId=<?php echo $item_instance_id; ?>" class="text-xs text-blue-400 hover:underline mt-1 block truncate" title="Inspeccionar <?php echo $item_instance_id; ?>">
                            ID: <?php echo substr($item_instance_id, -6); ?>
                        </a>
                    </div>
                    <div class="flex-grow">
                        <h3 class="font-bold text-white text-lg"><?php echo $manifest_data['displayProperties']['name']; ?></h3>
                        <?php if (isset($manifest_data['year']) && isset($manifest_data['season'])): ?>
                            <p class="text-xs text-gray-400">Año: <?php echo $manifest_data['year']; ?> | Temporada: <?php echo $manifest_data['season']; ?></p>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                            <?php foreach($stat_map as $stat_hash => $stat_info): ?>
                                <div class="flex items-center gap-1.5" title="<?php echo $stat_info['name']; ?>">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><?php echo $stat_info['svg']; ?></svg>
                                    <span class="font-semibold"><?php echo $current_stats[$stat_hash] ?? 0; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2 flex items-center gap-4 border-t border-gray-700 pt-2">
                            <div class="text-center">
                                <p class="text-xs text-gray-400">TOTAL</p>
                                <p class="font-bold text-white text-lg"><?php echo $total_stat_value; ?></p>
                            </div>
                            <?php if ($archetype_name): ?>
                                <div class="text-center">
                                    <p class="text-xs text-gray-400">ARQUETIPO</p>
                                    <p class="font-semibold text-blue-300 text-lg"><?php echo $archetype_name; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
            
            <h2 class="text-2xl font-bold text-white mb-4">Resumen de Estadísticas Totales</h2>
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 grid grid-cols-3 md:grid-cols-6 gap-4">
                <?php foreach ($stat_map as $stat_hash => $stat_info): ?>
                    <div class="text-center">
                        <div class="flex justify-center items-center gap-2">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><?php echo $stat_info['svg']; ?></svg>
                            <p class="text-sm text-gray-400 hidden lg:block"><?php echo $stat_info['name']; ?></p>
                        </div>
                        <p class="text-3xl font-bold text-white mt-1"><?php echo $total_stats_summary[$stat_hash]; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-6">Selecciona un personaje</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach (($characters ?? []) as $char_id => $character): ?>
                    <a href="?character=<?php echo $char_id; ?>" class="block transform hover:scale-105 transition-transform duration-300 group">
                        <div class="bg-gray-900/80 border border-gray-700 rounded-2xl overflow-hidden shadow-lg h-full group-hover:border-blue-500 transition-colors">
                            <div class="h-32 bg-cover bg-center" style="background-image: url('<?php echo $bungie_base_url . $character['emblemBackgroundPath']; ?>');"></div>
                            <div class="p-5">
                                <h2 class="text-2xl font-bold text-white"><?php echo $class_map[$character['classHash']] ?? 'Desconocido'; ?></h2>
                                <p class="text-yellow-400 text-lg">Luz <?php echo $character['light']; ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

