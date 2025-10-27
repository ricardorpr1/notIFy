<?php
// export_inscricoes.php - exporta CSV com verificação de permissões
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();

// Requer sessão
if (!isset($_SESSION['usuario_id'])) {
    // Se acesso via navegador, mostrar mensagem simples
    http_response_code(401);
    echo "Acesso negado. Faça login.";
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$role = intval($_SESSION['role'] ?? 0);

// pegar id via GET (já que o front usa window.location.href)
$eventoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($eventoId <= 0) {
    http_response_code(400);
    echo "ID do evento inválido.";
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro de conexão com o banco.";
    exit;
}

try {
    // Tentar obter created_by se existir
    $createdBy = null;
    $row = null;
    try {
        $stmt = $pdo->prepare("SELECT id, nome, inscricoes, created_by FROM eventos WHERE id = :id");
        $stmt->execute([':id' => $eventoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        // fallback sem created_by
        $stmt2 = $pdo->prepare("SELECT id, nome, inscricoes FROM eventos WHERE id = :id");
        $stmt2->execute([':id' => $eventoId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        $createdBy = null;
    }

    if (!$row) {
        http_response_code(404);
        echo "Evento não encontrado.";
        exit;
    }

    if (array_key_exists('created_by', $row)) {
        $createdBy = $row['created_by'] !== null ? intval($row['created_by']) : null;
    } else {
        $createdBy = null;
    }

    // Checar permissões: role 2 (DEV) => ok; role 1 (ORGANIZADOR) => só se criador (ou permissivo se created_by ausente)
    $allowed = false;
    if ($role === 2) {
        $allowed = true;
    } elseif ($role === 1) {
        if ($createdBy === null) {
            // permissivo quando created_by não existe (recomenda-se adicionar created_by para restringir)
            $allowed = true;
        } else {
            $allowed = ($createdBy === $usuario_id);
        }
    } else {
        $allowed = false;
    }

    if (!$allowed) {
        http_response_code(403);
        echo "Permissão negada.";
        exit;
    }

    // decodificar inscricoes (JSON) -> array de ids
    $inscricoes = [];
    if (!empty($row['inscricoes'])) {
        $tmp = json_decode($row['inscricoes'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $inscricoes = array_values($tmp);
        } else {
            // fallback split
            $inscricoes = array_filter(array_map('trim', explode(',', $row['inscricoes'])));
        }
    }

    if (empty($inscricoes)) {
        http_response_code(204);
        echo "Nenhum inscrito encontrado.";
        exit;
    }

    // buscar dados dos usuários inscritos
    $placeholders = implode(',', array_fill(0, count($inscricoes), '?'));
    $sql = "SELECT id, nome, cpf, registro_academico FROM usuarios WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($inscricoes);
    $usuarios = $stmt->fetchAll();

    // preparar CSV
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['nome'] ?? 'evento');
    $filename = "inscricoes_evento_{$safeName}_{$eventoId}.csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // cabeçalho
    fputcsv($out, ['Nome', 'CPF', 'Registro Acadêmico', 'ID de Usuário'], ';');

    foreach ($usuarios as $u) {
        fputcsv($out, [
            $u['nome'] ?? '',
            $u['cpf'] ?? '',
            $u['registro_academico'] ?? '',
            $u['id'] ?? ''
        ], ';');
    }
    fclose($out);
    exit;

} catch (PDOException $e) {
    error_log("Erro em export_inscricoes.php: " . $e->getMessage());
    http_response_code(500);
    echo "Erro ao gerar CSV.";
    exit;
}
