<?php
// get_user.php - retorna JSON com os dados do usuário logado
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não autenticado.']);
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

// DB config - ajuste se necessário
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log("get_user.php DB connect error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com o banco.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $usuarioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['erro' => 'Usuário não encontrado.']);
        exit;
    }

    // Campos que queremos expor (se existirem)
    $resp = [
        'id' => isset($row['id']) ? intval($row['id']) : $usuarioId,
        'nome' => $row['nome'] ?? null,
        'email' => $row['email'] ?? null,
        'cpf' => $row['cpf'] ?? null,
        'registro_academico' => $row['registro_academico'] ?? null,
        'telefone' => $row['telefone'] ?? null,
        'data_nascimento' => isset($row['data_nascimento']) ? (string)$row['data_nascimento'] : null,
        // role: nome da coluna esperada 'role' — se não existir tenta 'seradmin' as fallback to 0
        'role' => isset($row['role']) ? intval($row['role']) : (isset($row['seradmin']) ? ($row['seradmin'] ? 2 : 0) : 0),
        'foto_url' => $row['foto_url'] ?? (isset($_SESSION['foto_url']) ? $_SESSION['foto_url'] : null)
    ];

    // default foto
    if (empty($resp['foto_url'])) $resp['foto_url'] = 'default.jpg';

    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("get_user.php query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar usuário.']);
    exit;
}
