<?php
// api_gerenciar_cursos.php
// API restrita para DEVs para gerenciar cursos e turmas.
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

// 1. Verificar Permissão (DEV)
$myRole = intval($_SESSION['role'] ?? 0);
if ($myRole !== 2) {
    respond(403, ['erro' => 'Acesso negado. Apenas DEVs.']);
}

// 2. Conectar ao DB
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    respond(500, ['erro' => 'Erro de conexão com o banco.']);
}

// 3. Ler Ação (POST)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$action = $data['action'] ?? $_GET['action'] ?? 'invalid';

try {
    switch ($action) {
        
        // Ação: Puxar todos os Cursos e Turmas
        case 'get_all':
            $cursos_stmt = $pdo->query("SELECT * FROM cursos ORDER BY nome ASC");
            $turmas_stmt = $pdo->query("SELECT * FROM turmas ORDER BY ano ASC, nome_exibicao ASC");
            
            $cursos = $cursos_stmt->fetchAll();
            $turmas = $turmas_stmt->fetchAll();
            
            // Organiza as turmas dentro dos cursos
            $cursos_map = [];
            foreach ($cursos as $curso) {
                $cursos_map[$curso['id']] = $curso;
                $cursos_map[$curso['id']]['turmas'] = [];
            }
            foreach ($turmas as $turma) {
                if (isset($cursos_map[$turma['curso_id']])) {
                    $cursos_map[$turma['curso_id']]['turmas'][] = $turma;
                }
            }
            respond(200, array_values($cursos_map));
            break;

        // Ação: Criar um novo Curso
        case 'create_curso':
            $nome = trim($data['nome'] ?? '');
            $sigla = trim(strtoupper($data['sigla'] ?? ''));
            $nivel = in_array($data['nivel'], ['Integrado', 'Graduação']) ? $data['nivel'] : 'Integrado';
            
            if (empty($nome) || empty($sigla)) respond(400, ['erro' => 'Nome e Sigla são obrigatórios.']);
            if (strlen($sigla) != 3) respond(400, ['erro' => 'A sigla deve ter 3 caracteres (ex: INF).']);
            
            $stmt = $pdo->prepare("INSERT INTO cursos (nome, sigla, nivel) VALUES (:nome, :sigla, :nivel)");
            $stmt->execute([':nome' => $nome, ':sigla' => $sigla, ':nivel' => $nivel]);
            respond(201, ['mensagem' => 'Curso criado com sucesso.', 'id' => $pdo->lastInsertId()]);
            break;

        // Ação: Criar uma nova Turma
        case 'create_turma':
            $curso_id = intval($data['curso_id'] ?? 0);
            $nome_exibicao = trim($data['nome_exibicao'] ?? '');
            $ano = intval($data['ano'] ?? 0);
            
            if ($curso_id <= 0 || empty($nome_exibicao) || $ano <= 0) respond(400, ['erro' => 'Dados da turma inválidos.']);
            
            $stmt = $pdo->prepare("INSERT INTO turmas (curso_id, nome_exibicao, ano) VALUES (:cid, :nome, :ano)");
            $stmt->execute([':cid' => $curso_id, ':nome' => $nome_exibicao, ':ano' => $ano]);
            respond(201, ['mensagem' => 'Turma adicionada com sucesso.', 'id' => $pdo->lastInsertId()]);
            break;

        // Ação: Excluir um Curso (CASCADE deletará as turmas)
        case 'delete_curso':
            $curso_id = intval($data['curso_id'] ?? 0);
            if ($curso_id <= 0) respond(400, ['erro' => 'ID do curso inválido.']);
            $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = :id");
            $stmt->execute([':id' => $curso_id]);
            respond(200, ['mensagem' => 'Curso e suas turmas foram excluídos.']);
            break;

        // Ação: Excluir uma Turma
        case 'delete_turma':
            $turma_id = intval($data['turma_id'] ?? 0);
            if ($turma_id <= 0) respond(400, ['erro' => 'ID da turma inválido.']);
            $stmt = $pdo->prepare("DELETE FROM turmas WHERE id = :id");
            $stmt->execute([':id' => $turma_id]);
            respond(200, ['mensagem' => 'Turma excluída.']);
            break;

        default:
            respond(400, ['erro' => 'Ação inválida.']);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Chave duplicada
        respond(409, ['erro' => 'Erro: A sigla do curso já existe.']);
    }
    respond(500, ['erro' => $e->getMessage()]);
}
?>