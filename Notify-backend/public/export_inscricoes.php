<?php
// export_inscricoes.php — exporta lista de inscritos em CSV
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();

// Verifica sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo "Acesso negado. Faça login novamente.";
    exit;
}

// Configuração do banco
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
    error_log("Erro de conexão: " . $e->getMessage());
    http_response_code(500);
    echo "Erro de conexão com o banco de dados.";
    exit;
}

$eventoId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($eventoId <= 0) {
    http_response_code(400);
    echo "ID do evento inválido.";
    exit;
}

// Buscar o campo inscricoes
$stmt = $pdo->prepare("SELECT nome, inscricoes FROM eventos WHERE id = :id");
$stmt->execute([':id' => $eventoId]);
$evento = $stmt->fetch();

if (!$evento) {
    http_response_code(404);
    echo "Evento não encontrado.";
    exit;
}

$inscricoes = json_decode($evento['inscricoes'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($inscricoes) || empty($inscricoes)) {
    http_response_code(204);
    echo "Nenhum inscrito encontrado neste evento.";
    exit;
}

// Buscar dados dos usuários inscritos
$placeholders = implode(',', array_fill(0, count($inscricoes), '?'));
$sql = "SELECT id, nome, cpf, registro_academico FROM usuarios WHERE id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($inscricoes);
$usuarios = $stmt->fetchAll();

// Configurar headers do CSV
$nomeArquivo = 'inscricoes_evento_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $evento['nome']) . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

// Criar CSV
$output = fopen('php://output', 'w');
fputcsv($output, ['Nome', 'CPF', 'Registro Acadêmico', 'ID de Usuário'], ';');

foreach ($usuarios as $u) {
    fputcsv($output, [
        $u['nome'],
        $u['cpf'],
        $u['registro_academico'],
        $u['id']
    ], ';');
}

fclose($output);
exit;
