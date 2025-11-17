<?php
// export_inscricoes.php
// Gera CSV das inscrições de um evento, adicionando coluna "Presente" (Sim/Não)
// Uso: export_inscricoes.php?id=123

// --- Config DB - ajuste conforme seu ambiente ---
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "Parâmetro id necessário (ex: ?id=123).";
    exit;
}

$eventId = intval($_GET['id']);

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                   $DB_USER, $DB_PASS, [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                   ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro de conexão com o banco.";
    exit;
}

// buscar evento
try {
    $stmt = $pdo->prepare("SELECT id, nome, inscricoes, presencas FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $evento = $stmt->fetch();
    if (!$evento) {
        http_response_code(404);
        echo "Evento não encontrado.";
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro ao buscar evento.";
    exit;
}

// decodificar JSON (inscricoes e presencas)
$inscricoes = [];
$presencas = [];

if (!empty($evento['inscricoes'])) {
    $decoded = json_decode($evento['inscricoes'], true);
    if (is_array($decoded)) $inscricoes = array_values(array_filter(array_map('intval', $decoded)));
}
if (!empty($evento['presencas'])) {
    $decoded = json_decode($evento['presencas'], true);
    if (is_array($decoded)) $presencas = array_values(array_filter(array_map('intval', $decoded)));
}

// preparar saída CSV
$filename = 'inscricoes_evento_' . $eventId . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// abrir stream de saída
$out = fopen('php://output', 'w');
// escrever BOM UTF-8 para Excel
fwrite($out, "\xEF\xBB\xBF");

// cabeçalho do CSV
fputcsv($out, ['id_usuario', 'nome', 'cpf', 'registro_academico', 'Presente']);

// se não há inscritos, ainda devolve cabeçalho e sai
if (empty($inscricoes)) {
    // sem inscritos, apenas retornar CSV com cabeçalho
    fclose($out);
    exit;
}

// buscar dados dos usuários listados em inscricoes
// criar placeholders dinâmicos
$placeholders = implode(',', array_fill(0, count($inscricoes), '?'));

try {
    $sql = "SELECT id, nome, cpf, registro_academico FROM usuarios WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    // bind por posição
    foreach ($inscricoes as $i => $uid) {
        $stmt->bindValue($i + 1, $uid, PDO::PARAM_INT);
    }
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    // se falhar a query por algum motivo, ainda tentamos exportar linhas vazias
    $users = [];
}

// mapear usuários por id para acesso rápido
$usersById = [];
foreach ($users as $u) {
    $usersById[intval($u['id'])] = $u;
}

// escrever uma linha por cada inscrito na ordem do array inscricoes
foreach ($inscricoes as $uid) {
    $uid = intval($uid);
    $user = $usersById[$uid] ?? null;

    $nome = $user ? $user['nome'] : '';
    $cpf = $user ? $user['cpf'] : '';
    $ra = $user ? $user['registro_academico'] : '';

    $presente = in_array($uid, $presencas, true) ? 'Sim' : 'Não';

    // gravar linha
    fputcsv($out, [$uid, $nome, $cpf, $ra, $presente]);
}

fclose($out);
exit;
