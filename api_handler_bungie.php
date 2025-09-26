<?php

function log_api_error($message, $data = null) {
    $log_file = __DIR__ . '/api_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    if ($data) {
        $log_entry .= "Datos recibidos: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    $log_entry .= "----------------------------------------\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function make_bungie_api_request($url, $access_token, $api_key) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['X-API-Key: ' . $api_key];
    if ($access_token) {  // Omitir si access_token está vacío
        $headers[] = 'Authorization: Bearer ' . $access_token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode($response, true);
    curl_close($ch);
    if ($http_code !== 200 || (isset($data['ErrorCode']) && $data['ErrorCode'] != 1)) {
        log_api_error("Error en la petición a la API a la URL: $url (Código HTTP: $http_code)", $data);
        return ['error' => $data['Message'] ?? 'Error desconocido en la API', 'ErrorCode' => $data['ErrorCode'] ?? -1];
    }
    return $data;
}

function get_user_characters_and_profile($access_token, $api_key) {
    $membership_url = 'https://www.bungie.net/Platform/User/GetMembershipsForCurrentUser/';
    $membership_data = make_bungie_api_request($membership_url, $access_token, $api_key);
    if (isset($membership_data['error']) || !isset($membership_data['Response']['destinyMemberships'][0])) {
        log_api_error("No se pudo encontrar una cuenta de Destiny vinculada al obtener membresías.", $membership_data);
        return ['error' => 'No se pudo encontrar una cuenta de Destiny vinculada.'];
    }
    $destiny_membership = $membership_data['Response']['destinyMemberships'][0];
    $membership_type = $destiny_membership['membershipType'];
    $membership_id = $destiny_membership['membershipId'];
    $bungie_name = $destiny_membership['bungieGlobalDisplayName'] . '#' . $destiny_membership['bungieGlobalDisplayNameCode'];
    $profile_url = "https://www.bungie.net/Platform/Destiny2/{$membership_type}/Profile/{$membership_id}/?components=200";
    $profile_data = make_bungie_api_request($profile_url, $access_token, $api_key);
    if (isset($profile_data['error']) || !isset($profile_data['Response']['characters']['data'])) {
        log_api_error("No se pudieron obtener los personajes del perfil.", $profile_data);
        return ['error' => 'No se pudieron obtener los personajes.'];
    }
    return [
        'bungieName' => $bungie_name,
        'membershipType' => $membership_type,
        'membershipId' => $membership_id,
        'characters' => $profile_data['Response']['characters']['data']
    ];
}

function get_character_details($membership_type, $membership_id, $character_id, $access_token, $api_key) {
    $components = '205,300,302,304';
    $url = "https://www.bungie.net/Platform/Destiny2/{$membership_type}/Profile/{$membership_id}/Character/{$character_id}/?components={$components}";
    return make_bungie_api_request($url, $access_token, $api_key);
}

function get_destiny_year($season_number) {
    if ($season_number >= 24) return 7;
    if ($season_number >= 20) return 6;
    if ($season_number >= 16) return 5;
    if ($season_number >= 12) return 4;
    if ($season_number >= 8)  return 3;
    if ($season_number >= 4)  return 2;
    if ($season_number >= 1)  return 1;
    return 1;
}

function get_manifest_item_details(array $item_hashes) {
    if (empty($item_hashes)) return [];
    $manifest_path = __DIR__ . '/manifest/manifest_en.sqlite';  // Cambiado a inglés para mayor completitud
    if (!file_exists($manifest_path)) return [];
    
    // Ruta para cache de fallbacks
    $cache_dir = __DIR__ . '/manifest/cache/';
    $cache_file = $cache_dir . 'fallback_cache.json';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true); // Crea el directorio si no existe
    }
    $cache = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
    
    try {
        $pdo = new PDO('sqlite:' . $manifest_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $placeholders = implode(',', array_fill(0, count($item_hashes), '?'));
        $signed_hashes = array_map(fn($hash) => ($hash & 0x7FFFFFFF) - ($hash & 0x80000000), $item_hashes);

        // --- PASO 1: Obtener las definiciones de los objetos (armaduras, mods, etc.) ---
        $query_items = "SELECT id, json FROM DestinyInventoryItemDefinition WHERE id IN ($placeholders)";
        $stmt_items = $pdo->prepare($query_items);
        $stmt_items->execute(array_values($signed_hashes));  // Reindexar para evitar error 25
        
        $results = [];
        $season_hashes_to_find = [];
        while ($row = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
            $json_data = json_decode($row['json'], true);
            $original_hash = $row['id'] > 0 ? $row['id'] : $row['id'] + 4294967296;
            $results[$original_hash] = $json_data;
            if (isset($json_data['quality']['seasonHash'])) {
                $season_hashes_to_find[] = $json_data['quality']['seasonHash'];
            }
        }

        // --- PASO 2: Obtener las definiciones de las temporadas necesarias ---
        $unique_season_hashes = array_unique($season_hashes_to_find);
        if (!empty($unique_season_hashes)) {
            $placeholders = implode(',', array_fill(0, count($unique_season_hashes), '?'));
            $signed_season_hashes = array_map(fn($hash) => ($hash & 0x7FFFFFFF) - ($hash & 0x80000000), $unique_season_hashes);
            
            $query_seasons = "SELECT id, json FROM DestinySeasonDefinition WHERE id IN ($placeholders)";
            $stmt_seasons = $pdo->prepare($query_seasons);
            $stmt_seasons->execute(array_values($signed_season_hashes));  // Reindexar para evitar error 25

            while($row = $stmt_seasons->fetch(PDO::FETCH_ASSOC)){
                $season_json = json_decode($row['json'], true);
                $original_hash = $row['id'] > 0 ? $row['id'] : $row['id'] + 4294967296;
                // Guardamos la definición completa de la temporada en nuestros resultados
                $results[$original_hash] = $season_json;
            }
        }

        // --- Fallback para hashes no encontrados localmente ---
        foreach ($item_hashes as $hash) {
            if (!isset($results[$hash])) {
                // Verificar cache primero
                if (isset($cache[$hash])) {
                    $results[$hash] = $cache[$hash];
                    continue;
                }
                
                log_api_error("Usando fallback API para hash no encontrado en manifiesto local: $hash");
                // Llamada a API individual (usa inglés)
                $fallback_url = "https://www.bungie.net/Platform/Destiny2/Manifest/DestinyInventoryItemDefinition/$hash/";
                $fallback_data = make_bungie_api_request($fallback_url, '', '0e6f1d0e6971472e93bb632a5e027b2b'); // No necesita access_token para manifiesto público
                if (!isset($fallback_data['error'])) {
                    $results[$hash] = $fallback_data['Response'];
                    // Guardar en cache
                    $cache[$hash] = $results[$hash];
                    file_put_contents($cache_file, json_encode($cache, JSON_PRETTY_PRINT));
                }
            }
        }
        
        return $results;

    } catch (PDOException $e) {
        log_api_error("Error de base de datos del Manifiesto", ['error' => $e->getMessage() . ' | Trace: ' . $e->getTraceAsString()]);
        return [];
    }
}


// --- Inicio -> FUNCIÓN PARA LA HERRAMIENTA 'all_stats.php' ---
function get_single_item_details($membership_type, $membership_id, $item_instance_id, $access_token, $api_key) {
    $components = '300,302,304,305,307,308,309,310';
    $url = "https://www.bungie.net/Platform/Destiny2/{$membership_type}/Profile/{$membership_id}/Item/{$item_instance_id}/?components={$components}";
    
    $item_data = make_bungie_api_request($url, $access_token, $api_key);

    if (isset($item_data['error'])) {
        return $item_data;
    }

    if (isset($item_data['Response'])) {
        $hashes_to_find = [];
        $response = $item_data['Response'];

        if (isset($response['item']['data']['itemHash'])) {
            $hashes_to_find[] = $response['item']['data']['itemHash'];
        }
        if (isset($response['sockets']['data']['sockets'])) {
            foreach ($response['sockets']['data']['sockets'] as $socket) {
                if (isset($socket['plugHash'])) {
                    $hashes_to_find[] = $socket['plugHash'];
                }
            }
        }
        
        // --- CORRECCIÓN CLAVE: Buscar también el seasonHash si existe ---
        $temp_manifest = get_manifest_item_details([$response['item']['data']['itemHash']]);
        if (isset($temp_manifest[$response['item']['data']['itemHash']]['quality']['seasonHash'])) {
            $hashes_to_find[] = $temp_manifest[$response['item']['data']['itemHash']]['quality']['seasonHash'];
        }
        
        if (!empty($hashes_to_find)) {
            $manifest_details = get_manifest_item_details(array_unique($hashes_to_find));
            $item_data['manifestDetails'] = $manifest_details;
        }
    }
    
    return $item_data;
}
// --- Fin -> FUNCIÓN PARA LA HERRAMIENTA 'all_stats.php' ---

?>