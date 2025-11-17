<?php
// delete_event.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// --- Configuração do banco (altere se necessário) ---
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro de conexão: " . $e->getMessage()]);
    exit;
}

// Permitir preflight OPTIONS caso alguém use CORS complex
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Só aceita POST com JSON { id: <number> }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["erro" => "Método não permitido. Use POST."]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["erro" => "JSON inválido: " . json_last_error_msg()]);
    exit;
}

if (!isset($data['id']) || !is_numeric($data['id'])) {
    http_response_code(400);
    echo json_encode(["erro" => "Parâmetro 'id' obrigatório (numérico)."]);
    exit;
}

$id = (int) $data['id'];

try {
    // Primeiro, opcionalmente, você poderia checar existência antes de apagar
    $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->rowCount();
    if ($rows === 0) {
        http_response_code(404);
        echo json_encode(["erro" => "Evento não encontrado ou já removido."]);
        exit;
    }

    echo json_encode(["mensagem" => "Evento removido com sucesso.", "id" => $id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao deletar: " . $e->getMessage()]);
}
