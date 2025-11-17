<?php
// api_gerenciar_inscricoes.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
session_start();

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db";
$dbuser = "tcc_notify"; $dbpass = "108Xk:C";

function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Verificar Login
if (!isset($_SESSION['usuario_id'])) {
    respond(401, ['erro' => 'Usuário não autenticado.']);
}
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);

// 2. Conectar ao DB
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    respond(500, ['erro' => 'Erro de conexão com o banco.']);
}

// 3. Ler Ação (POST ou GET)
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$eventId = intval($_POST['event_id'] ?? $_GET['event_id'] ?? 0);

if ($eventId <= 0) {
    respond(400, ['erro' => 'ID do evento inválido.']);
}

// 4. Verificar Permissão (Criador, Colaborador ou DEV)
try {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        respond(404, ['erro' => 'Evento não encontrado.']);
    }

    $createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
    $isDev = ($myRole === 2);
    $isCollaborator = false;
    if (!empty($event['colaboradores_ids'])) {
        $tmp = json_decode($event['colaboradores_ids'], true);
        if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
    }
    
    if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) {
        respond(403, ['erro' => 'Permissão negada.']);
    }

} catch (PDOException $e) {
    respond(500, ['erro' => 'Erro ao verificar permissão: ' . $e->getMessage()]);
}


// 5. Executar Ação
try {
    switch ($action) {
        
        case 'get_data':
            $inscricoes_ids = array_map('intval', json_decode($event['inscricoes'] ?? '[]', true));
            $presencas_ids = array_map('intval', json_decode($event['presencas'] ?? '[]', true));
            $usuarios_inscritos = [];
            if (!empty($inscricoes_ids)) {
                $placeholders = implode(',', array_fill(0, count($inscricoes_ids), '?'));
                $sql = "SELECT u.id, u.nome, u.email, u.cpf, u.registro_academico, t.nome_exibicao AS turma_nome
                        FROM usuarios u
                        LEFT JOIN turmas t ON u.turma_id = t.id
                        WHERE u.id IN ($placeholders)
                        ORDER BY u.nome ASC";
                $stmt_users = $pdo->prepare($sql);
                $stmt_users->execute($inscricoes_ids);
                $usuarios_inscritos = $stmt_users->fetchAll();
            }
            respond(200, [
                'evento' => [ 'id' => $event['id'], 'nome' => $event['nome'], 'limite_participantes' => $event['limite_participantes'] ],
                'inscricoes_ids' => $inscricoes_ids,
                'presencas_ids' => $presencas_ids,
                'usuarios' => $usuarios_inscritos
            ]);
            break;

        case 'update_limit':
            $new_limit = (isset($_POST['limite']) && $_POST['limite'] !== '') ? intval($_POST['limite']) : null;
            $stmt = $pdo->prepare("UPDATE eventos SET limite_participantes = :limite WHERE id = :id");
            $stmt->execute([':limite' => $new_limit, ':id' => $eventId]);
            respond(200, ['mensagem' => 'Limite de participantes atualizado.']);
            break;

        case 'remover_inscricao':
            $user_ids = $_POST['user_ids'] ?? [];
            if (empty($user_ids) || !is_array($user_ids)) respond(400, ['erro' => 'Nenhum usuário selecionado.']);
            $inscricoes = array_map('intval', json_decode($event['inscricoes'] ?? '[]', true));
            $presencas = array_map('intval', json_decode($event['presencas'] ?? '[]', true));
            $novas_inscricoes = array_diff($inscricoes, $user_ids);
            $novas_presencas = array_diff($presencas, $user_ids);
            $stmt = $pdo->prepare("UPDATE eventos SET inscricoes = :insc, presencas = :pres WHERE id = :id");
            $stmt->execute([ ':insc' => json_encode(array_values($novas_inscricoes)), ':pres' => json_encode(array_values($novas_presencas)), ':id' => $eventId ]);
            respond(200, ['mensagem' => 'Inscrições removidas com sucesso.']);
            break;

        case 'marcar_presenca':
            $user_ids = $_POST['user_ids'] ?? [];
            if (empty($user_ids) || !is_array($user_ids)) respond(400, ['erro' => 'Nenhum usuário selecionado.']);
            $presencas = array_map('intval', json_decode($event['presencas'] ?? '[]', true));
            $novas_presencas = array_unique(array_merge($presencas, $user_ids));
            $stmt = $pdo->prepare("UPDATE eventos SET presencas = :pres WHERE id = :id");
            $stmt->execute([':pres' => json_encode(array_values($novas_presencas)), ':id' => $eventId]);
            respond(200, ['mensagem' => 'Presenças marcadas com sucesso.']);
            break;

        case 'remover_presenca':
            $user_ids = $_POST['user_ids'] ?? [];
            if (empty($user_ids) || !is_array($user_ids)) respond(400, ['erro' => 'Nenhum usuário selecionado.']);
            $presencas = array_map('intval', json_decode($event['presencas'] ?? '[]', true));
            $novas_presencas = array_diff($presencas, $user_ids);
            $stmt = $pdo->prepare("UPDATE eventos SET presencas = :pres WHERE id = :id");
            $stmt->execute([':pres' => json_encode(array_values($novas_presencas)), ':id' => $eventId]);
            respond(200, ['mensagem' => 'Presenças removidas com sucesso.']);
            break;

        case 'get_all_users_for_manual_add':
            $presencas_ids = array_map('intval', json_decode($event['presencas'] ?? '[]', true));
            $sql = "SELECT u.id, u.nome, u.email, u.cpf, u.registro_academico, 
                           t.nome_exibicao AS turma_nome, c.sigla AS curso_sigla
                    FROM usuarios u
                    LEFT JOIN turmas t ON u.turma_id = t.id
                    LEFT JOIN cursos c ON t.curso_id = c.id
                    ORDER BY u.nome ASC";
            $all_users = $pdo->query($sql)->fetchAll();
            respond(200, [
                'evento_nome' => $event['nome'],
                'presencas_ids' => $presencas_ids,
                'all_users' => $all_users
            ]);
            break;

        // --- ATUALIZAÇÃO AQUI ---
        case 'add_manual_presence':
            $user_ids = $_POST['user_ids'] ?? [];
            if (empty($user_ids) || !is_array($user_ids)) respond(400, ['erro' => 'Nenhum usuário selecionado.']);
            
            $user_ids = array_map('intval', $user_ids);
            
            // Puxa ambas as listas
            $presencas = array_map('intval', json_decode($event['presencas'] ?? '[]', true));
            $inscricoes = array_map('intval', json_decode($event['inscricoes'] ?? '[]', true));
            
            // Mescla os IDs em ambas as listas
            $novas_presencas = array_unique(array_merge($presencas, $user_ids));
            $novas_inscricoes = array_unique(array_merge($inscricoes, $user_ids));

            // Salva ambas as listas
            $stmt = $pdo->prepare("UPDATE eventos SET presencas = :pres, inscricoes = :insc WHERE id = :id");
            $stmt->execute([
                ':pres' => json_encode(array_values($novas_presencas)),
                ':insc' => json_encode(array_values($novas_inscricoes)),
                ':id' => $eventId
            ]);
            respond(200, ['mensagem' => 'Presenças e inscrições manuais registradas com sucesso.']);
            break;
        // --- FIM DA ATUALIZAÇÃO ---

        default:
            respond(400, ['erro' => 'Ação inválida.']);
            break;
    }
} catch (PDOException $e) {
    respond(500, ['erro' => 'Erro fatal de banco de dados: ' . $e->getMessage()]);
}
?>