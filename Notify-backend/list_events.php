<?php
// list_events.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$port = "5432";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro de conexão: " . $e->getMessage()]);
    exit;
}

try {
    $sql = "SELECT id, nome, descricao, local, data_evento, horario_inicio, horario_fim FROM eventos";
    $stmt = $pdo->query($sql);
    $eventos = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Monta o campo start e end juntando data + hora
        $start = $row["data_evento"] . "T" . ($row["horario_inicio"] ?? "00:00:00");
        $end = $row["data_evento"] . "T" . ($row["horario_fim"] ?? "00:00:00");

        $eventos[] = [
            "id" => $row["id"],
            "title" => $row["nome"],
            "start" => $start,
            "end" => $end,
            "extendedProps" => [
                "descricao" => $row["descricao"],
                "local" => $row["local"]
            ]
        ];
    }

    echo json_encode($eventos);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao buscar: " . $e->getMessage()]);
}
