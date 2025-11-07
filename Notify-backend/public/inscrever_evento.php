<?php
// inscrever_evento.php - toggles inscrição do usuário logado no evento (usa sessão)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

session_start();

// Verifica sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["erro" => "Usuário não autenticado."]);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);

// Só POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["erro" => "Método inválido. Use POST."]);
    exit;
}

// ler JSON do corpo
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["erro" => "JSON inválido: " . json_last_error_msg()]);
    exit;
}

$eventoId = isset($data['id']) ? intval($data['id']) : 0;
if ($eventoId <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "ID do evento inválido."]);
    exit;
}

// Config DB
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

// --- NOVA LÓGICA DE DATA ---
// Define o fuso horário correto para a comparação
date_default_timezone_set('America/Sao_Paulo');
// --- FIM DA NOVA LÓGICA ---

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // usar transação para evitar race conditions
    $pdo->beginTransaction();

    // --- QUERY ATUALIZADA ---
    // Busca as inscrições E a data de fim do evento
    $stmt = $pdo->prepare("SELECT inscricoes, data_hora_fim FROM eventos WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $eventoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["erro" => "Evento não encontrado."]);
        exit;
    }

    // --- NOVA CHECAGEM DE DATA ---
    if (!empty($row['data_hora_fim'])) {
        $agora = new DateTime();
        $fim_evento = new DateTime($row['data_hora_fim']);
        
        // Se o horário atual for MAIOR que o horário de fim do evento
        if ($agora > $fim_evento) {
            $pdo->rollBack();
            http_response_code(403); // 403 Forbidden
            echo json_encode(["erro" => "Inscrições encerradas. Este evento já terminou."]);
            exit;
        }
    }
    // --- FIM DA CHECAGEM ---

    $inscricoes = [];
    if (!empty($row['inscricoes'])) {
        $tmp = json_decode($row['inscricoes'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $inscricoes = array_values($tmp);
    }

    // garantir que IDs sejam inteiros
    $inscricoes = array_map('intval', $inscricoes);

    // verificar se usuário já está inscrito
    $index = array_search($usuario_id, $inscricoes, true);
    $inscritoAgora = false;

    if ($index === false) {
        // adicionar
        $inscricoes[] = $usuario_id;
        $inscritoAgora = true;
    } else {
        // remover
        array_splice($inscricoes, $index, 1);
        $inscritoAgora = false;
    }

    // atualizar DB
    $jsonNovo = json_encode(array_values($inscricoes), JSON_UNESCAPED_UNICODE);
    $upd = $pdo->prepare("UPDATE eventos SET inscricoes = :inscricoes WHERE id = :id");
    $upd->execute([':inscricoes' => $jsonNovo, ':id' => $eventoId]);

    $pdo->commit();

    echo json_encode([
        "mensagem" => $inscritoAgora ? "Inscrito com sucesso." : "Inscrição removida.",
        "inscrito" => $inscritoAgora,
        "inscricoes" => $inscricoes
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro em inscrever_evento.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao processar a inscrição."]);
    exit;
}