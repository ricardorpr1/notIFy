<?php
// create_event.php - cria evento e grava created_by (quando disponível)
// comportamento seguro: tenta inserir created_by; se coluna não existir, faz fallback sem ela

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

session_start();

// Config DB - ajuste se necessário
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

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

// Ler entrada - aceita JSON ou form-urlencoded
$raw = file_get_contents("php://input");
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = null;
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, ["erro" => "JSON inválido: " . json_last_error_msg()]);
    }
} else {
    $data = $_POST;
}

// validação básica
$required = ['nome', 'data_hora_inicio', 'data_hora_fim'];
$missing = [];
foreach ($required as $f) {
    if (!isset($data[$f]) || trim((string)$data[$f]) === '') $missing[] = $f;
}
if (!empty($missing)) {
    respond(400, ["erro" => "Campos obrigatórios ausentes: " . implode(', ', $missing)]);
}

// normalização
$nome = trim($data['nome']);
$descricao = isset($data['descricao']) ? trim($data['descricao']) : null;
$local = isset($data['local']) ? trim($data['local']) : null;
$data_hora_inicio = trim($data['data_hora_inicio']);
$data_hora_fim = trim($data['data_hora_fim']);
$icone_url = isset($data['icone_url']) ? trim($data['icone_url']) : null;
$capa_url = isset($data['capa_url']) ? trim($data['capa_url']) : null;
$limite_participantes = isset($data['limite_participantes']) && $data['limite_participantes'] !== '' ? (int)$data['limite_participantes'] : null;

// turmas_permitidas e colaboradores -> JSON string to store in MySQL
$turmas_permitidas = [];
if (isset($data['turmas_permitidas'])) {
    if (is_array($data['turmas_permitidas'])) $turmas_permitidas = array_values($data['turmas_permitidas']);
    else $turmas_permitidas = array_map('trim', explode(',', (string)$data['turmas_permitidas']));
}
$turmas_json = json_encode($turmas_permitidas, JSON_UNESCAPED_UNICODE);

$colaboradores = [];
if (isset($data['colaboradores'])) {
    if (is_array($data['colaboradores'])) $colaboradores = array_values($data['colaboradores']);
    else $colaboradores = array_map('trim', explode(',', (string)$data['colaboradores']));
}
$colaboradores_json = json_encode($colaboradores, JSON_UNESCAPED_UNICODE);

// parse datetimes
function try_parse_datetime($s) {
    if (!$s) return false;
    $s2 = str_replace('T', ' ', $s);
    try {
        $d = new DateTime($s2);
        return $d->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return false;
    }
}

$start_parsed = try_parse_datetime($data_hora_inicio);
$end_parsed = try_parse_datetime($data_hora_fim);
if ($start_parsed === false || $end_parsed === false) {
    respond(400, ["erro" => "Formato de data/hora inválido. Use 'YYYY-MM-DDTHH:MM' ou 'YYYY-MM-DD HH:MM:SS'."]);
}

// obter created_by da sessão (se existir)
$created_by = null;
if (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
    $created_by = intval($_SESSION['usuario_id']);
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    respond(500, ["erro" => "Erro de conexão com o banco: " . $e->getMessage()]);
}

// Tentar inserir incluindo created_by; se coluna não existir, tentar fallback sem created_by
try {
    if ($created_by !== null) {
        $sql = "INSERT INTO eventos 
            (nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores, inscricoes, created_by)
            VALUES
            (:nome, :descricao, :local, :data_hora_inicio, :data_hora_fim, :icone_url, :capa_url, :limite_participantes, :turmas_permitidas, :colaboradores, :inscricoes, :created_by)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':local', $local ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':data_hora_inicio', $start_parsed, PDO::PARAM_STR);
        $stmt->bindValue(':data_hora_fim', $end_parsed, PDO::PARAM_STR);
        $stmt->bindValue(':icone_url', $icone_url ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':capa_url', $capa_url ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':limite_participantes', $limite_participantes !== null ? $limite_participantes : null, PDO::PARAM_INT);
        $stmt->bindValue(':turmas_permitidas', $turmas_json, PDO::PARAM_STR);
        $stmt->bindValue(':colaboradores', $colaboradores_json, PDO::PARAM_STR);
        $stmt->bindValue(':inscricoes', json_encode([], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR); // default empty array
        $stmt->bindValue(':created_by', $created_by, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // created_by não disponível -> inserir sem essa coluna
        $sql = "INSERT INTO eventos 
            (nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores, inscricoes)
            VALUES
            (:nome, :descricao, :local, :data_hora_inicio, :data_hora_fim, :icone_url, :capa_url, :limite_participantes, :turmas_permitidas, :colaboradores, :inscricoes)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':local', $local ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':data_hora_inicio', $start_parsed, PDO::PARAM_STR);
        $stmt->bindValue(':data_hora_fim', $end_parsed, PDO::PARAM_STR);
        $stmt->bindValue(':icone_url', $icone_url ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':capa_url', $capa_url ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':limite_participantes', $limite_participantes !== null ? $limite_participantes : null, PDO::PARAM_INT);
        $stmt->bindValue(':turmas_permitidas', $turmas_json, PDO::PARAM_STR);
        $stmt->bindValue(':colaboradores', $colaboradores_json, PDO::PARAM_STR);
        $stmt->bindValue(':inscricoes', json_encode([], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->execute();
    }

    $newId = $pdo->lastInsertId();
    respond(201, ["mensagem" => "Evento criado com sucesso.", "id" => $newId, "created_by" => $created_by]);

} catch (PDOException $e) {
    $msg = $e->getMessage();

    // fallback: se a query tentou usar created_by e a coluna não existe, tentar inserir sem ela
    if ($created_by !== null && (stripos($msg, 'Unknown column') !== false || stripos($msg, 'column "created_by"') !== false || stripos($msg, 'created_by') !== false && stripos($msg, 'Unknown') !== false)) {
        try {
            $sql = "INSERT INTO eventos 
                (nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores, inscricoes)
                VALUES
                (:nome, :descricao, :local, :data_hora_inicio, :data_hora_fim, :icone_url, :capa_url, :limite_participantes, :turmas_permitidas, :colaboradores, :inscricoes)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindValue(':descricao', $descricao ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':local', $local ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':data_hora_inicio', $start_parsed, PDO::PARAM_STR);
            $stmt->bindValue(':data_hora_fim', $end_parsed, PDO::PARAM_STR);
            $stmt->bindValue(':icone_url', $icone_url ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':capa_url', $capa_url ?: null, PDO::PARAM_STR);
            $stmt->bindValue(':limite_participantes', $limite_participantes !== null ? $limite_participantes : null, PDO::PARAM_INT);
            $stmt->bindValue(':turmas_permitidas', $turmas_json, PDO::PARAM_STR);
            $stmt->bindValue(':colaboradores', $colaboradores_json, PDO::PARAM_STR);
            $stmt->bindValue(':inscricoes', json_encode([], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->execute();
            $newId = $pdo->lastInsertId();
            respond(201, ["mensagem" => "Evento criado com sucesso (fallback, created_by não registrado porque a coluna não existe).", "id" => $newId]);
        } catch (PDOException $e2) {
            error_log("Erro fallback create_event.php: " . $e2->getMessage());
            respond(500, ["erro" => "Erro ao inserir evento (fallback)."]);
        }
    }

    error_log("Erro create_event.php: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao inserir evento: " . $e->getMessage()]);
}
