<?php
// update_palestrantes.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

session_start();

// validar login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não autenticado.']);
    exit;
}
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);

// apenas POST com JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método inválido. Use POST.']);
    exit;
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido.']);
    exit;
}

$eventId = isset($data['event_id']) ? intval($data['event_id']) : 0;
// 'add_ids' agora contém o array completo de IDs selecionados
$palestranteIds = isset($data['add_ids']) && is_array($data['add_ids']) ? array_map('intval', $data['add_ids']) : [];

// --- CORREÇÃO AQUI ---
// Adicionado 'exit;' após a verificação
if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID de evento inválido.']);
    exit; // <--- O BUG ESTAVA AQUI (FALTA DO EXIT)
}
// --- FIM DA CORREÇÃO ---

// DB config
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com o banco.']);
    exit;
}

try {
    // obter evento com created_by e colaboradores_ids
    $stmt = $pdo->prepare("SELECT id, created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['erro' => 'Evento não encontrado.']);
        exit;
    }

    // Lógica de Permissão (DEV, Criador ou Colaborador)
    $createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
    $isDev = ($myRole === 2);
    $isCreator = ($createdBy !== null && $createdBy === $me);

    $isCollaborator = false;
    if (!empty($event['colaboradores_ids'])) {
        $tmp = json_decode($event['colaboradores_ids'], true);
        if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
    }

    if (!($isDev || $isCreator || $isCollaborator)) {
        http_response_code(403);
        echo json_encode(['erro' => 'Permissão negada.']);
        exit;
    }

    // IDs unicos
    $merged = array_values(array_unique($palestranteIds));

    // salvar (encode JSON)
    $jsonMerged = json_encode($merged, JSON_UNESCAPED_UNICODE);
    $upd = $pdo->prepare("UPDATE eventos SET palestrantes_ids = :col WHERE id = :id");
    $upd->execute([':col' => $jsonMerged, ':id' => $eventId]);

    echo json_encode(['mensagem' => 'Palestrantes atualizados.', 'palestrantes' => $merged]);
    exit;

} catch (PDOException $e) {
    error_log("Erro update_palestrantes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao atualizar palestrantes.']);
    exit;
}