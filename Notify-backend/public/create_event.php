<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Configuração do banco de dados
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

<<<<<<< HEAD
// Função para montar arrays do PostgreSQL
function pg_array_literal($arr) {
    if (!is_array($arr) || count($arr) === 0) return '{}';
    $items = [];
    foreach ($arr as $v) {
        // Escapa aspas duplas
        $v = str_replace('"', '\"', $v);
        $items[] = '"' . $v . '"';
    }
    return '{' . implode(',', $items) . '}';
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
=======
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $password, [
>>>>>>> 4c35dd5983ae29a36ad670acf2eef1719aed00dc
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro de conexão: " . $e->getMessage()]);
    exit;
}

// Lendo os dados enviados pelo fetch
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["erro" => "Dados inválidos ou formato incorreto"]);
    exit;
}

// 🔐 Query atualizada com DATETIME
$sql = "INSERT INTO eventos 
(nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores)
VALUES (:nome, :descricao, :local, :data_hora_inicio, :data_hora_fim, :icone_url, :capa_url, :limite_participantes, :turmas_permitidas, :colaboradores)";

$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([
        ":nome" => $data["nome"] ?? null,
        ":descricao" => $data["descricao"] ?? null,
        ":local" => $data["local"] ?? null,
        ":data_hora_inicio" => $data["data_hora_inicio"] ?? null,
        ":data_hora_fim" => $data["data_hora_fim"] ?? null,
        ":icone_url" => $data["icone_url"] ?? null,
        ":capa_url" => $data["capa_url"] ?? null,
        ":limite_participantes" => $data["limite_participantes"] ?? null,
        ":turmas_permitidas" => isset($data["turmas_permitidas"]) ? json_encode($data["turmas_permitidas"]) : json_encode([]),
        ":colaboradores" => isset($data["colaboradores"]) ? json_encode($data["colaboradores"]) : json_encode([])
    ]);

    http_response_code(201);
    echo json_encode(["mensagem" => "Evento criado com sucesso!"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao inserir: " . $e->getMessage()]);
}
?>