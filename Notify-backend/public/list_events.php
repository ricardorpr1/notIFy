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
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Busca todos os eventos
    $sql = "SELECT id, nome, descricao, local, horario_inicio, horario_fim, capa_url, data_evento FROM eventos";
    $stmt = $pdo->query($sql);
    $eventos = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Monta o formato aceito pelo FullCalendar
        $start = $row['data_evento'] . 'T' . $row['horario_inicio'];
        $end = $row['data_evento'] . 'T' . $row['horario_fim'];
        $eventos[] = [
            "id" => $row["id"],
            "title" => $row["nome"],
            "start" => $start,
            "end" => $end,
            "description" => $row["descricao"],
            "location" => $row["local"],
            "image" => $row["capa_url"]
        ];
    }

    echo json_encode($eventos);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao buscar eventos: " . $e->getMessage()]);
}
?>
