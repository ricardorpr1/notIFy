<?php
// create_event.php - versão robusta e que sempre retorna JSON
ini_set('display_errors', 0);            // não mostrar erros no output (previne HTML inesperado)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Config DB - ajuste se necessário
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Permitir OPTIONS para CORS preflight (se alguma chamada fizer)
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
    // fallback para form data
    $data = $_POST;
}

// validação básica (ajuste conforme necessário)
$required = ['nome', 'data_hora_inicio', 'data_hora_fim'];
$missing = [];
foreach ($required as $f) {
    if (!isset($data[$f]) || trim((string)$data[$f]) === '') $missing[] = $f;
}
if (!empty($missing)) {
    respond(400, ["erro" => "Campos obrigatórios ausentes: " . implode(', ', $missing)]);
}

// sanitização / normalização
$nome = trim($data['nome']);
$descricao = isset($data['descricao']) ? trim($data['descricao']) : null;
$local = isset($data['local']) ? trim($data['local']) : null;
$data_hora_inicio = trim($data['data_hora_inicio']);
$data_hora_fim = trim($data['data_hora_fim']);
$icone_url = isset($data['icone_url']) ? trim($data['icone_url']) : null;
$capa_url = isset($data['capa_url']) ? trim($data['capa_url']) : null;
$limite_participantes = isset($data['limite_participantes']) && $data['limite_participantes'] !== '' ? (int)$data['limite_participantes'] : null;

// turmas_permitidas e colaboradores: armazenar como JSON string (MySQL)
$turmas_permitidas = isset($data['turmas_permitidas']) && is_array($data['turmas_permitidas'])
    ? json_encode(array_values($data['turmas_permitidas']), JSON_UNESCAPED_UNICODE)
    : (isset($data['turmas_permitidas']) && $data['turmas_permitidas'] !== '' ? json_encode(array_map('trim', explode(',', $data['turmas_permitidas'])), JSON_UNESCAPED_UNICODE) : json_encode([]));

$colaboradores = isset($data['colaboradores']) && is_array($data['colaboradores'])
    ? json_encode(array_values($data['colaboradores']), JSON_UNESCAPED_UNICODE)
    : (isset($data['colaboradores']) && $data['colaboradores'] !== '' ? json_encode(array_map('trim', explode(',', $data['colaboradores'])), JSON_UNESCAPED_UNICODE) : json_encode([]));

// validação simples de formato de datetime (YYYY-MM-DDTHH:MM ou YYYY-MM-DD HH:MM:SS ou YYYY-MM-DDTHH:MM:SS)
function try_parse_datetime($s) {
    if (!$s) return false;
    // aceitar 'T' ou espaço
    $s2 = str_replace('T', ' ', $s);
    // tentar criar DateTime
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

// inserir no banco
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $sql = "INSERT INTO eventos 
        (nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores)
        VALUES
        (:nome, :descricao, :local, :data_hora_inicio, :data_hora_fim, :icone_url, :capa_url, :limite_participantes, :turmas_permitidas, :colaboradores)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
    $stmt->bindValue(':descricao', $descricao ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':local', $local ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':data_hora_inicio', $start_parsed, PDO::PARAM_STR);
    $stmt->bindValue(':data_hora_fim', $end_parsed, PDO::PARAM_STR);
    $stmt->bindValue(':icone_url', $icone_url ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':capa_url', $capa_url ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':limite_participantes', $limite_participantes !== null ? $limite_participantes : null, PDO::PARAM_INT);
    $stmt->bindValue(':turmas_permitidas', $turmas_permitidas, PDO::PARAM_STR);
    $stmt->bindValue(':colaboradores', $colaboradores, PDO::PARAM_STR);

    $stmt->execute();
    $newId = $pdo->lastInsertId();

    respond(201, ["mensagem" => "Evento criado com sucesso.", "id" => $newId]);

} catch (PDOException $e) {
    // log do erro para arquivo
    error_log("Erro ao inserir evento: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao inserir evento: " . $e->getMessage()]);
}
