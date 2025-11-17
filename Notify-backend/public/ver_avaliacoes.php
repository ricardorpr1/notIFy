<?php
// ver_avaliacoes.php
// Mostra todas as avaliações de um evento (Apenas Criador e DEV)
session_start();

// 1. Requer Login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
$userId = intval($_SESSION['usuario_id']);
$userRole = intval($_SESSION['role'] ?? 0);

// 2. ID do Evento
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) die("ID do evento inválido.");

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db";
$dbuser = "tcc_notify"; $dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco.");
}

$evento_nome = '';
$avaliacoes = [];
$media_notas = 0;

try {
    // 3. Buscar Evento e Checar Permissão (Criador ou DEV)
    $stmt = $pdo->prepare("SELECT nome, created_by FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) die("Evento não encontrado.");
    $evento_nome = $event['nome'];

    $isDev = ($userRole === 2);
    $isCreator = ($event['created_by'] == $userId);

    if (!$isDev && !$isCreator) {
        die("Acesso negado. Apenas o criador do evento e DEVs podem ver as avaliações.");
    }

    // 4. Buscar todas as avaliações (juntando com nome do usuário)
    $sql_reviews = "SELECT a.*, u.nome AS nome_usuario 
                    FROM avaliacoes_evento a
                    JOIN usuarios u ON a.usuario_id = u.id
                    WHERE a.evento_id = :eid
                    ORDER BY a.data_avaliacao DESC";
    $stmt_reviews = $pdo->prepare($sql_reviews);
    $stmt_reviews->execute([':eid' => $eventId]);
    $avaliacoes = $stmt_reviews->fetchAll();

    // 5. Calcular Média
    if (count($avaliacoes) > 0) {
        $soma = 0;
        foreach ($avaliacoes as $a) {
            $soma += intval($a['nota']);
        }
        $media_notas = $soma / count($avaliacoes);
    }

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

function estrelas($nota) {
    return str_repeat('★', $nota) . str_repeat('☆', 5 - $nota);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Avaliações — notIFy</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f6f7fb; margin: 0; padding: 20px; }
    .card { background: #fff; max-width: 900px; margin: 20px auto; padding: 18px; border-radius: 10px; box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06); }
    .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    .top h2 { margin: 0; color: #333; }
    .btn-back { background: #6c757d; color: #fff; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
    .summary { background: #f9f9f9; border: 1px solid #eee; padding: 15px; border-radius: 8px; text-align: center; }
    .summary h3 { margin: 0 0 5px 0; }
    .summary .media { font-size: 24px; font-weight: bold; color: #FFD700; }
    
    .review-list { list-style: none; padding: 0; margin-top: 20px; }
    .review-item { padding: 14px 10px; border-bottom: 1px solid #f1f1f1; }
    .review-item:last-child { border-bottom: none; }
    .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .review-header strong { font-size: 16px; color: #0056b3; }
    .review-header .nota { font-size: 18px; color: #FFD700; }
    .review-item blockquote { margin: 0; padding: 10px; background: #fafafa; border-left: 3px solid #ddd; border-radius: 4px; color: #444; white-space: pre-wrap; }
    .review-item .data { font-size: 12px; color: #888; margin-top: 5px; }
    .no-reviews { text-align: center; color: #777; padding: 30px; }
</style>
</head>
<body>
    <div class="card">
        <div class="top">
            <h2>Avaliações do Evento</h2>
            <a href="index.php" class="btn-back">Voltar ao Calendário</a>
        </div>
        <h3 style="font-weight: normal;"><?= htmlspecialchars($evento_nome) ?></h3>

        <div class="summary">
            <h3>Média das Avaliações</h3>
            <div class="media" title="<?= number_format($media_notas, 2, ',', '.') ?> de 5">
                <?= estrelas(round($media_notas)) ?>
            </div>
            (<?= count($avaliacoes) ?> avaliações)
        </div>

        <ul class="review-list">
            <?php if (empty($avaliacoes)): ?>
                <li class="no-reviews">Nenhuma avaliação foi recebida para este evento.</li>
            <?php else: ?>
                <?php foreach ($avaliacoes as $a): ?>
                    <li class="review-item">
                        <div class="review-header">
                            <strong><?= htmlspecialchars($a['nome_usuario']) ?></strong>
                            <span class="nota" title="<?= $a['nota'] ?> de 5"><?= estrelas($a['nota']) ?></span>
                        </div>
                        <?php if (!empty($a['comentario'])): ?>
                            <blockquote><?= htmlspecialchars($a['comentario']) ?></blockquote>
                        <?php endif; ?>
                        <div class="data">Avaliado em: <?= (new DateTime($a['data_avaliacao']))->format('d/m/Y H:i') ?></div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>