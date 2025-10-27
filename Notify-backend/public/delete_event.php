<?php
// delete_event.php - deleta evento com verificação de role/permission
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

session_start();

// Requer login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não autenticado.']);
    exit;
}
$usuario_id = intval($_SESSION['usuario_id']);
$role = intval($_SESSION['role'] ?? 0);

// Somente POST para exclusão
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método inválido. Use POST.']);
    exit;
}

// ler JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido ou ID ausente.']);
    exit;
}
$eventoId = intval($data['id']);
if ($eventoId <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID do evento inválido.']);
    exit;
}

// DB config
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com o banco.']);
    exit;
}

try {
    // Ler evento (tentar também obter created_by se existir)
    $createdBy = null;
    $row = null;
    try {
        $stmt = $pdo->prepare("SELECT id, nome, created_by FROM eventos WHERE id = :id");
        $stmt->execute([':id' => $eventoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // Se a coluna created_by não existe, a query pode falhar — esse try/catch cobre isso.
    } catch (PDOException $ex) {
        // tentar sem created_by (fallback): obter pelo menos id/nome
        $stmt2 = $pdo->prepare("SELECT id, nome FROM eventos WHERE id = :id");
        $stmt2->execute([':id' => $eventoId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        $createdBy = null; // não sabemos
    }

    if (!$row) {
        http_response_code(404);
        echo json_encode(['erro' => 'Evento não encontrado.']);
        exit;
    }

    // se a coluna created_by foi lida, setar $createdBy
    if (array_key_exists('created_by', $row)) {
        $createdBy = $row['created_by'] !== null ? intval($row['created_by']) : null;
    } else {
        $createdBy = null;
    }

    // Verificar permissões:
    // - DEV (2) -> pode tudo
    // - ORGANIZADOR (1) -> só pode se for criador (quando created_by existe)
    // - USER (0) -> não pode
    $allowed = false;
    if ($role === 2) {
        $allowed = true;
    } elseif ($role === 1) {
        if ($createdBy === null) {
            // não temos created_by no esquema => permissivo (recomendo adicionar created_by para restringir)
            $allowed = true;
        } else {
            $allowed = ($createdBy === $usuario_id);
        }
    } else {
        $allowed = false;
    }

    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['erro' => 'Permissão negada.']);
        exit;
    }

    // Excluir o evento (transação simples)
    $pdo->beginTransaction();
    $del = $pdo->prepare("DELETE FROM eventos WHERE id = :id");
    $del->execute([':id' => $eventoId]);
    $pdo->commit();

    echo json_encode(['mensagem' => 'Evento excluído com sucesso.']);
    exit;

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro em delete_event.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao excluir evento.']);
    exit;
}
