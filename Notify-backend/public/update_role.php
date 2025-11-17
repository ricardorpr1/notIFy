<?php
// update_role.php - altera o role de um usuário (somente DEV)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

session_start();

// autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não autenticado.']);
    exit;
}

// apenas DEV podem acessar
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);
if ($myRole !== 2) {
    http_response_code(403);
    echo json_encode(['erro' => 'Permissão negada.']);
    exit;
}

// somente POST
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

$targetId = isset($data['id']) ? intval($data['id']) : 0;
$newRole = isset($data['role']) ? intval($data['role']) : null;
if ($targetId <= 0 || !in_array($newRole, [0,1,2], true)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetros inválidos.']);
    exit;
}

// prevenir demissão do próprio usuário DEV (evita travar o sistema)
if ($targetId === $me && $newRole !== 2) {
    http_response_code(400);
    echo json_encode(['erro' => 'Você não pode alterar seu próprio role (remover privilégios de DEV).']);
    exit;
}

// DB config (ajuste se necessário)
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com o banco.']);
    exit;
}

// atualizar
try {
    $stmt = $pdo->prepare("UPDATE usuarios SET role = :role WHERE id = :id");
    $stmt->execute([':role' => $newRole, ':id' => $targetId]);

    echo json_encode(['mensagem' => 'Role atualizado com sucesso.', 'id' => $targetId, 'role' => $newRole]);
    exit;
} catch (PDOException $e) {
    error_log("Erro update_role.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao atualizar role.']);
    exit;
}
