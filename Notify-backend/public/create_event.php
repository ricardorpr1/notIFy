<?php
// create_event.php - cria evento de forma robusta e detecta colunas dinamicamente
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

session_start();

// DB config - ajuste se necessário
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

// Allow OPTIONS for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ["erro" => "Método não permitido. Use POST."]);
}

// read input (JSON preferred, fallback to POST)
$raw = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, ["erro" => "JSON inválido: " . json_last_error_msg()]);
    }
} else {
    $data = $_POST;
}

// minimal validation
if (empty($data['nome']) || empty($data['data_hora_inicio']) || empty($data['data_hora_fim'])) {
    respond(400, ["erro" => "Campos obrigatórios: nome, data_hora_inicio, data_hora_fim."]);
}

// helpers
function normalize_datetime($s) {
    if (!$s) return null;
    // accept "YYYY-MM-DDTHH:MM" or "YYYY-MM-DD HH:MM:SS" or similar
    $s2 = str_replace('T', ' ', $s);
    try {
        $d = new DateTime($s2);
        return $d->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function to_json_or_null($v) {
    if ($v === null) return json_encode([]);
    if (is_array($v)) return json_encode(array_values($v), JSON_UNESCAPED_UNICODE);
    // if string and looks like JSON, keep; else try convert CSV->array
    $decoded = json_decode($v, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
    // else CSV
    $parts = array_filter(array_map('trim', explode(',', (string)$v)));
    return json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
}

// normalize input
$nome = trim($data['nome']);
$descricao = isset($data['descricao']) ? trim($data['descricao']) : null;
$local = isset($data['local']) ? trim($data['local']) : null;
$data_hora_inicio = normalize_datetime($data['data_hora_inicio']);
$data_hora_fim = normalize_datetime($data['data_hora_fim']);
$icone_url = isset($data['icone_url']) ? trim($data['icone_url']) : null;
$capa_url = isset($data['capa_url']) ? trim($data['capa_url']) : null;
$limite_participantes = isset($data['limite_participantes']) && $data['limite_participantes'] !== '' ? (int)$data['limite_participantes'] : null;
$turmas_json = isset($data['turmas_permitidas']) ? to_json_or_null($data['turmas_permitidas']) : json_encode([], JSON_UNESCAPED_UNICODE);
$colaboradores_json = isset($data['colaboradores']) ? to_json_or_null($data['colaboradores']) : json_encode([], JSON_UNESCAPED_UNICODE);
$inscricoes_json = json_encode([], JSON_UNESCAPED_UNICODE);
$colaboradores_ids_json = json_encode([], JSON_UNESCAPED_UNICODE);

// validate datetimes
if (!$data_hora_inicio || !$data_hora_fim) {
    respond(400, ["erro" => "Formato de data/hora inválido. Use 'YYYY-MM-DDTHH:MM' ou 'YYYY-MM-DD HH:MM:SS'."]);
}

// created_by from session if exists
$created_by = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : null;

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("create_event.php DB connect error: " . $e->getMessage());
    respond(500, ["erro" => "Erro de conexão com o banco."]);
}

// detect existing columns in eventos table
try {
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'eventos'");
    $colsStmt->execute([':db' => $DB_NAME]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
} catch (PDOException $e) {
    error_log("create_event.php schema detect error: " . $e->getMessage());
    $cols = [];
}

// build insert dynamically based on existing columns
$insertCols = [];
$placeholders = [];
$params = [];

$map = [
    'nome' => $nome,
    'descricao' => $descricao,
    'local' => $local,
    'data_hora_inicio' => $data_hora_inicio,
    'data_hora_fim' => $data_hora_fim,
    'icone_url' => $icone_url,
    'capa_url' => $capa_url,
    'limite_participantes' => $limite_participantes,
    'turmas_permitidas' => $turmas_json,
    'colaboradores' => $colaboradores_json,
    'inscricoes' => $inscricoes_json,
    'colaboradores_ids' => $colaboradores_ids_json,
    'created_by' => $created_by
];

foreach ($map as $col => $val) {
    if (in_array($col, $cols)) {
        $insertCols[] = $col;
        $placeholders[] = ":$col";
        // bind null for empty
        if ($val === null) $params[":$col"] = null;
        else $params[":$col"] = $val;
    }
}

// fallback: if none of expected columns exist (shouldn't happen) -> error
if (empty($insertCols)) {
    respond(500, ["erro" => "A tabela 'eventos' não possui colunas esperadas (verificar esquema)."]);
}

// perform insert
$sql = "INSERT INTO eventos (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
try {
    $stmt = $pdo->prepare($sql);

    // bind types loosely; JSON/text as string
    foreach ($params as $k => $v) {
        if ($v === null) $stmt->bindValue($k, null, PDO::PARAM_NULL);
        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }

    $stmt->execute();
    $newId = $pdo->lastInsertId();
    respond(201, ["mensagem" => "Evento criado com sucesso.", "id" => $newId, "created_by" => $created_by]);

} catch (PDOException $e) {
    error_log("create_event.php insert error: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao inserir evento: " . $e->getMessage()]);
}
