<?php
// edit_event.php - atualiza evento (só para criador, colaborador ou dev)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

session_start();

// DB config - ajuste caso necessário
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ["erro" => "Método não permitido. Use POST."]);
}

if (!isset($_SESSION['usuario_id'])) {
    respond(401, ["erro" => "Usuário não autenticado."]);
}
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);

// ler JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    respond(400, ["erro" => "JSON inválido: " . json_last_error_msg()]);
}

// validar id
$eventoId = isset($data['id']) ? intval($data['id']) : 0;
if ($eventoId <= 0) respond(400, ["erro" => "ID do evento inválido."]);

// conectar DB
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("edit_event.php DB connect error: " . $e->getMessage());
    respond(500, ["erro" => "Erro de conexão com o banco."]);
}

// buscar o evento e colunas existentes
try {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventoId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) respond(404, ["erro" => "Evento não encontrado."]);
} catch (PDOException $e) {
    error_log("edit_event.php select error: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao acessar evento."]);
}

// permission check: dev OR created_by == me OR me in colaboradores_ids
$createdBy = array_key_exists('created_by', $event) && $event['created_by'] !== null ? intval($event['created_by']) : null;
$isDev = ($myRole === 2);

// check colaboradores_ids JSON
$isCollaborator = false;
if (!empty($event['colaboradores_ids'])) {
    $tmp = json_decode($event['colaboradores_ids'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $tmp = array_map('intval', $tmp);
        if (in_array($me, $tmp, true)) $isCollaborator = true;
    } else {
        // fallback CSV
        $vals = array_filter(array_map('trim', explode(',', (string)$event['colaboradores_ids'])));
        foreach ($vals as $v) if (intval($v) === $me) { $isCollaborator = true; break; }
    }
}

if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) {
    respond(403, ["erro" => "Permissão negada."]);
}

// normalize incoming fields (only update provided keys)
$updatable = [
    'nome' => 'nome',
    'descricao' => 'descricao',
    'local' => 'local',
    'data_hora_inicio' => 'data_hora_inicio',
    'data_hora_fim' => 'data_hora_fim',
    'icone_url' => 'icone_url',
    'capa_url' => 'capa_url',
    'limite_participantes' => 'limite_participantes',
    'turmas_permitidas' => 'turmas_permitidas',
    'colaboradores' => 'colaboradores',
    'colaboradores_ids' => 'colaboradores_ids'
];

$fieldsToSet = [];
$params = [':id' => $eventoId];

// helper to parse datetime
function parse_dt_or_null($s) {
    if ($s === null || $s === '') return null;
    $s2 = str_replace('T',' ',$s);
    try {
        $d = new DateTime($s2);
        return $d->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

// detect columns available in the fetched row
$availableCols = array_map('strtolower', array_keys($event));

foreach ($updatable as $key => $colName) {
    if (!array_key_exists($key, $data)) continue; // not provided by client

    if (!in_array($colName, $availableCols)) {
        // column not present in DB - skip silently
        continue;
    }

    $val = $data[$key];

    // special handling
    if ($key === 'data_hora_inicio' || $key === 'data_hora_fim') {
        $val = parse_dt_or_null($val);
        if ($val === null) {
            respond(400, ["erro" => "Formato de data/hora inválido para $key."]);
        }
    } elseif ($key === 'turmas_permitidas' || $key === 'colaboradores' || $key === 'colaboradores_ids') {
        // ensure JSON string
        if (is_array($val)) $val = json_encode(array_values($val), JSON_UNESCAPED_UNICODE);
        elseif (is_string($val)) {
            // try parse JSON, else treat as CSV -> array
            $tmp = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $val = json_encode(array_values($tmp), JSON_UNESCAPED_UNICODE);
            else {
                $parts = array_filter(array_map('trim', explode(',', $val)));
                $val = json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
            }
        } else {
            $val = json_encode([], JSON_UNESCAPED_UNICODE);
        }
    } elseif ($key === 'limite_participantes') {
        $val = ($val === null || $val === '') ? null : intval($val);
    } else {
        // string fields - cast and trim
        if ($val === null) $val = null;
        else $val = trim((string)$val);
    }

    // add to update list
    $fieldsToSet[] = "`$colName` = :$colName";
    $params[":$colName"] = $val;
}

// if nothing to update
if (empty($fieldsToSet)) {
    respond(400, ["erro" => "Nenhum campo para atualizar."]);
}

// build and execute update
$sql = "UPDATE eventos SET " . implode(', ', $fieldsToSet) . " WHERE id = :id";
try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        if ($v === null) $stmt->bindValue($k, null, PDO::PARAM_NULL);
        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    respond(200, ["mensagem" => "Evento atualizado com sucesso.", "id" => $eventoId]);
} catch (PDOException $e) {
    error_log("edit_event.php update error: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao atualizar evento."]);
}
