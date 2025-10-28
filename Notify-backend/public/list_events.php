<?php
// list_events.php - retorna eventos com created_by e inscrições
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// DB config
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

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("DB connection error in list_events.php: " . $e->getMessage());
    respond(500, ["erro" => "Erro de conexão com o banco."]);
}

try {
    // tentar selecionar created_by; se não existir, o fallback tratará
    $sql = "SELECT id, nome, descricao, local, data_hora_inicio, data_hora_fim, icone_url, capa_url, limite_participantes, turmas_permitidas, colaboradores, inscricoes, created_by
            FROM eventos
            ORDER BY data_hora_inicio ASC";
    $stmt = $pdo->query($sql);

    $events = [];
    while ($row = $stmt->fetch()) {
        // inscricoes
        $inscricoes = [];
        if (!empty($row['inscricoes'])) {
            $tmp = json_decode($row['inscricoes'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $inscricoes = array_values($tmp);
            else $inscricoes = array_filter(array_map('trim', explode(',', $row['inscricoes'])));
        }

        // turmas / colaboradores (decodifica JSON ou CSV)
        $turmas = [];
        if (!empty($row['turmas_permitidas'])) {
            $tmp = json_decode($row['turmas_permitidas'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $turmas = $tmp;
            else $turmas = array_map('trim', explode(',', $row['turmas_permitidas']));
        }
        $cols = [];
        if (!empty($row['colaboradores'])) {
            $tmp = json_decode($row['colaboradores'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $cols = $tmp;
            else $cols = array_map('trim', explode(',', $row['colaboradores']));
        }

        // created_by (pode ser NULL se coluna existir ou se não)
        $createdBy = array_key_exists('created_by', $row) ? ($row['created_by'] !== null ? intval($row['created_by']) : null) : null;

        $evt = [
            "id" => (string)$row['id'],
            "nome" => $row['nome'],
            "title" => $row['nome'],
            "descricao" => $row['descricao'],
            "description" => $row['descricao'],
            "local" => $row['local'],
            "location" => $row['local'],
            "data_hora_inicio" => $row['data_hora_inicio'],
            "data_hora_fim" => $row['data_hora_fim'],
            "start" => $row['data_hora_inicio'],
            "end" => $row['data_hora_fim'],
            "icone_url" => $row['icone_url'],
            "capa_url" => $row['capa_url'],
            "limite_participantes" => $row['limite_participantes'],
            "turmas_permitidas" => $turmas,
            "colaboradores" => $cols,
            "inscricoes" => $inscricoes,
            "created_by" => $createdBy,
            "extendedProps" => [
                "descricao" => $row['descricao'],
                "local" => $row['local'],
                "icone_url" => $row['icone_url'],
                "capa_url" => $row['capa_url'],
                "limite_participantes" => $row['limite_participantes'],
                "turmas_permitidas" => $turmas,
                "colaboradores" => $cols,
                "inscricoes" => $inscricoes,
                "created_by" => $createdBy
            ]
        ];

        $events[] = $evt;
    }

    respond(200, $events);

} catch (PDOException $e) {
    error_log("DB query error in list_events.php: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao buscar eventos."]);
}
