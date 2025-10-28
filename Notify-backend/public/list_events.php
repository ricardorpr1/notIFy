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

try {
    // fetch all columns to be resilient to schema changes
    $stmt = $pdo->query("SELECT * FROM eventos ORDER BY data_hora_inicio ASC, id ASC");
    $rows = $stmt->fetchAll();
    $out = [];

    foreach ($rows as $r) {
        // normalize fields (handle multiple possible column names)
        $id = isset($r['id']) ? (string)$r['id'] : null;
        $nome = $r['nome'] ?? ($r['title'] ?? '');
        // prefer data_hora_inicio / data_hora_fim, fallback to other names
        $start = $r['data_hora_inicio'] ?? ($r['start'] ?? null) ;
        $end   = $r['data_hora_fim'] ?? ($r['end'] ?? null) ;
        // description/local
        $descricao = $r['descricao'] ?? ($r['description'] ?? '');
        $local = $r['local'] ?? ($r['location'] ?? '');
        $capa = $r['capa_url'] ?? ($r['capa'] ?? ($r['image'] ?? null));
        $icone = $r['icone_url'] ?? ($r['icone'] ?? null);
        $limite = array_key_exists('limite_participantes', $r) ? $r['limite_participantes'] : ($r['limit'] ?? null);

        // inscricoes: may be JSON string or CSV
        $inscricoes = [];
        if (!empty($r['inscricoes'])) {
            if (is_array($r['inscricoes'])) $inscricoes = array_values($r['inscricoes']);
            else {
                $tmp = json_decode($r['inscricoes'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $inscricoes = $tmp;
                else $inscricoes = array_filter(array_map('trim', explode(',', (string)$r['inscricoes'])));
            }
        }

        // turmas_permitidas and colaboradores may be JSON
        $turmas = [];
        if (!empty($r['turmas_permitidas'])) {
            $tmp = json_decode($r['turmas_permitidas'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $turmas = $tmp;
            else $turmas = array_filter(array_map('trim', explode(',', (string)$r['turmas_permitidas'])));
        }
        $colabs = [];
        if (!empty($r['colaboradores'])) {
            $tmp = json_decode($r['colaboradores'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $colabs = $tmp;
            else $colabs = array_filter(array_map('trim', explode(',', (string)$r['colaboradores'])));
        }

        // colaboradores_ids (JSON array of ints)
        $colaboradores_ids = [];
        if (!empty($r['colaboradores_ids'])) {
            $tmp = json_decode($r['colaboradores_ids'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $colaboradores_ids = array_map('intval', $tmp);
            else {
                $tmp2 = array_filter(array_map('trim', explode(',', (string)$r['colaboradores_ids'])));
                $colaboradores_ids = array_map('intval', $tmp2);
            }
        }

        // created_by
        $created_by = array_key_exists('created_by', $r) && $r['created_by'] !== null ? intval($r['created_by']) : null;

        // ensure dates are strings (mysql returns as string)
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
            "colaboradores" => $colabs,
            "colaboradores_ids" => $colaboradores_ids,
            "inscricoes" => $inscricoes,
            "created_by" => $created_by,
            "extendedProps" => [
                "descricao" => $descricao,
                "local" => $local,
                "capa_url" => $capa,
                "icone_url" => $icone,
                "limite_participantes" => $limite !== null ? (int)$limite : null,
                "turmas_permitidas" => $turmas,
                "colaboradores" => $colabs,
                "colaboradores_ids" => $colaboradores_ids,
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
