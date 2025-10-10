<?php
// list_events.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Configuração do banco
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

try {
    $sql = "SELECT id, nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores 
            FROM eventos";
    $stmt = $pdo->query($sql);
    $eventos = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $eventos[] = [
            "id" => $row["id"],
            "title" => $row["nome"],
            "start" => $row["data_hora_inicio"],
            "end" => $row["data_hora_fim"],
            "extendedProps" => [
                "descricao" => $row["descricao"],
                "local" => $row["local"],
                "icone_url" => $row["icone_url"],
                "capa_url" => $row["capa_url"],
                "limite_participantes" => $row["limite_participantes"],
                "turmas_permitidas" => $row["turmas_permitidas"],
                "colaboradores" => $row["colaboradores"]
            ]
        ];
    }

    echo json_encode($eventos, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao buscar eventos: " . $e->getMessage()]);
}
?>
