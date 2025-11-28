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

$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db";
$DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com o banco.']);
    exit;
}

try {
    // --- QUERY ATUALIZADA COM JOIN ---
    $sql = "SELECT u.*, 
                   t.nome_exibicao AS turma_nome, 
                   c.nome AS curso_nome,
                   c.sigla AS curso_sigla
            FROM usuarios u
            LEFT JOIN turmas t ON u.turma_id = t.id
            LEFT JOIN cursos c ON t.curso_id = c.id
            WHERE u.id = :id 
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $usuarioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    // --- FIM DA QUERY ---

    if (!$row) {
        session_destroy();
        http_response_code(401); 
        echo json_encode(['erro' => 'ID de usuário da sessão é inválido ou não existe mais.']);
        exit;
    }

    // --- RESPOSTA ATUALIZADA ---
    $resp = [
        'id' => isset($row['id']) ? intval($row['id']) : $usuarioId,
        'nome' => $row['nome'] ?? null,
        'email' => $row['email'] ?? null,
        'cpf' => $row['cpf'] ?? null,
        'registro_academico' => $row['registro_academico'] ?? null,
        'turma_id' => $row['turma_id'] ?? null,        // ID da turma
        'turma_nome' => $row['turma_nome'] ?? null,    // Ex: "INF 1A"
        'curso_nome' => $row['curso_nome'] ?? null,    // Ex: "Informática"
        'curso_sigla' => $row['curso_sigla'] ?? null,  // Ex: "INF"
        'telefone' => $row['telefone'] ?? null,
        'data_nascimento' => isset($row['data_nascimento']) ? (string)$row['data_nascimento'] : null,
        'role' => isset($row['role']) ? intval($row['role']) : (isset($row['seradmin']) ? ($row['seradmin'] ? 2 : 0) : 0),
        'foto_url' => $row['foto_url'] ?? (isset($_SESSION['foto_url']) ? $_SESSION['foto_url'] : null)
    ];
    // --- FIM ---

    if (empty($resp['foto_url'])) $resp['foto_url'] = 'default.jpg';

    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("get_user.php query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar usuário.']);
    exit;
}