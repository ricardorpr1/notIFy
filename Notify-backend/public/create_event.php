<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 🔧 Configuração do banco de dados (ajuste se necessário)
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro de conexão: " . $e->getMessage()]);
    exit;
}

// 📦 Lê o JSON recebido do frontend
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