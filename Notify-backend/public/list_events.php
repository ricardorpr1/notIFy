<?php
// list_events.php - retorna todos os eventos normalizados para o frontend (JSON)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log("list_events.php DB connect error: " . $e->getMessage());
    respond(500, ["erro" => "Erro de conexão com o banco."]);
}

// Helper para decodificar JSON (robusto)
function decodeJsonArray($jsonString) {
    if (empty($jsonString)) return [];
    if (is_array($jsonString)) return array_values($jsonString); // Já pode ser um array
    
    $decoded = json_decode($jsonString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return array_values($decoded);
    }
    
    // Fallback para CSV (se não for JSON válido)
    return array_filter(array_map('trim', explode(',', (string)$jsonString)));
}

try {
    // fetch all columns to be resilient to schema changes
    $stmt = $pdo->query("SELECT * FROM eventos ORDER BY data_hora_inicio ASC, id ASC");
    $rows = $stmt->fetchAll();
    $out = [];

    foreach ($rows as $r) {
        // normalize fields
        $id = isset($r['id']) ? (string)$r['id'] : null;
        $nome = $r['nome'] ?? ($r['title'] ?? '');
        $start = $r['data_hora_inicio'] ?? ($r['start'] ?? null) ;
        $end   = $r['data_hora_fim'] ?? ($r['end'] ?? null) ;
        $descricao = $r['descricao'] ?? ($r['description'] ?? '');
        $local = $r['local'] ?? ($r['location'] ?? '');
        $capa = $r['capa_url'] ?? ($r['capa'] ?? ($r['image'] ?? null));
        $icone = $r['icone_url'] ?? ($r['icone'] ?? null);
        $limite = array_key_exists('limite_participantes', $r) ? $r['limite_participantes'] : ($r['limit'] ?? null);

        // Decodificar arrays JSON
        $inscricoes = decodeJsonArray($r['inscricoes'] ?? null);
        $turmas = decodeJsonArray($r['turmas_permitidas'] ?? null);
        $colabs_nomes = decodeJsonArray($r['colaboradores'] ?? null); // Coluna antiga (nomes)
        $colabs_ids = decodeJsonArray($r['colaboradores_ids'] ?? null);
        
        // --- MUDANÇA AQUI ---
        // Decodificar novo array de palestrantes
        $palestrantes_ids = decodeJsonArray($r['palestrantes_ids'] ?? null);
        // --- FIM DA MUDANÇA ---

        $created_by = array_key_exists('created_by', $r) && $r['created_by'] !== null ? intval($r['created_by']) : null;

        // Montar objeto de evento para o FullCalendar
        $event = [
            "id" => $id,
            "nome" => $nome,
            "title" => $nome,
            "start" => $start,
            "end" => $end,
            "descricao" => $descricao,
            "description" => $descricao,
            "local" => $local,
            "location" => $local,
            "capa_url" => $capa,
            "icone_url" => $icone,
            "limite_participantes" => $limite !== null ? (int)$limite : null,
            "turmas_permitidas" => $turmas,
            "colaboradores" => $colabs_nomes,
            "colaboradores_ids" => $colabs_ids,
            "palestrantes_ids" => $palestrantes_ids, // <-- Adicionado
            "inscricoes" => $inscricoes,
            "created_by" => $created_by,
            
            // extendedProps redundantes (mas o index.php usa)
            "extendedProps" => [
                "descricao" => $descricao,
                "local" => $local,
                "capa_url" => $capa,
                "icone_url" => $icone,
                "limite_participantes" => $limite !== null ? (int)$limite : null,
                "turmas_permitidas" => $turmas,
                "colaboradores" => $colabs_nomes,
                "colaboradores_ids" => $colabs_ids,
                "palestrantes_ids" => $palestrantes_ids, // <-- Adicionado
                "inscricoes" => $inscricoes,
                "created_by" => $created_by
            ]
        ];

        $out[] = $event;
    }

    respond(200, $out);

} catch (PDOException $e) {
    error_log("list_events.php query error: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao buscar eventos."]);
}