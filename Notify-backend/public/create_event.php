<?php
// create_event.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

<<<<<<< HEAD
// Configuração do banco
=======
// Configuração do banco de dados
>>>>>>> 4c35dd5983ae29a36ad670acf2eef1719aed00dc
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

<<<<<<< HEAD
=======
// Lendo os dados enviados pelo fetch
>>>>>>> 4c35dd5983ae29a36ad670acf2eef1719aed00dc
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["erro" => "Dados inválidos ou ausentes"]);
    exit;
}

$sql = "INSERT INTO eventos (nome, descricao, local, horario_inicio, horario_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores, data_evento)
        VALUES (:nome, :descricao, :local, :horario_inicio, :horario_fim, :icone_url, :capa_url, :limite_participantes, :turmas_permitidas, :colaboradores, :data_evento)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $data['nome'] ?? null,
        ':descricao' => $data['descricao'] ?? null,
        ':local' => $data['local'] ?? null,
        ':horario_inicio' => $data['horario_inicio'] ?? null,
        ':horario_fim' => $data['horario_fim'] ?? null,
        ':icone_url' => $data['icone_url'] ?? null,
        ':capa_url' => $data['capa_url'] ?? null,
        ':limite_participantes' => $data['limite_participantes'] ?? null,
        ':turmas_permitidas' => isset($data['turmas_permitidas']) ? json_encode($data['turmas_permitidas']) : json_encode([]),
        ':colaboradores' => isset($data['colaboradores']) ? json_encode($data['colaboradores']) : json_encode([]),
        ':data_evento' => $data['data_evento'] ?? null
    ]);

    http_response_code(201);
    echo json_encode(["mensagem" => "Evento criado com sucesso!"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao inserir: " . $e->getMessage()]);
}
?>
