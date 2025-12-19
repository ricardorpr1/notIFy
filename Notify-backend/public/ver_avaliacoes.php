<?php
// ver_avaliacoes.php — Com Sidebar e Header Responsivos
session_start();

// 1. Requer Login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
$userId = intval($_SESSION['usuario_id']);
$userRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

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
} catch (PDOException $e) { die("Erro de conexão com o banco."); }

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

    // 4. Buscar todas as avaliações
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
        foreach ($avaliacoes as $a) { $soma += intval($a['nota']); }
        $media_notas = $soma / count($avaliacoes);
    }

} catch (PDOException $e) { die("Erro ao carregar dados: " . $e->getMessage()); }

function estrelas($nota) { return str_repeat('★', $nota) . str_repeat('☆', 5 - $nota); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
<title>Avaliações — notIFy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* --- LAYOUT PADRÃO (Header/Sidebar) --- */
    body { margin:0; font-family: 'Inter', sans-serif; background:#f0f2f5; color:#333; overflow-x: hidden; padding-top: 60px; }
    
    header { position: fixed; top: 0; left: 0; width: 100%; background-color: #045c3f; color: white; display: flex; align-items: center; justify-content: center; height: 60px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 3000; }
    header h1 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -1px; }
    header span { color: #c00000; font-weight: 900; }
    #mobileMenuBtn { display: none; position: absolute; left: 15px; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }

    #sidebar { position: fixed; top: 60px; left: 0; width: 250px; height: calc(100vh - 60px); background: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 12px; border-right: 1px solid #e0e0e0; box-shadow: 4px 0 16px rgba(0,0,0,0.08); z-index: 2000; transition: transform 0.3s ease; }
    .sidebar-btn { background: #045c3f; color: #fff; border: none; padding: 14px 20px; border-radius: 10px; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.3s; width: 100%; box-sizing: border-box; text-decoration: none; }
    .sidebar-btn:hover { background: #05774f; transform: translateY(-2px); }
    #sidebarBackdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; }

    #userArea { position: fixed; top: 8px; right: 15px; z-index: 3100; display: flex; gap: 10px; align-items: center; }
    #profileImg { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; cursor: pointer; }

    .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 900px; }

    /* --- ESTILOS DA PÁGINA DE AVALIAÇÃO --- */
    .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
    .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .top h2 { margin: 0; color: #045c3f; font-size: 22px; }
    
    .btn-back { background: #6c757d; color: #fff; padding: 8px 14px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: bold; font-size: 14px; }
    
    .summary { background: #f9f9f9; border: 1px solid #eee; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 25px; }
    .summary h3 { margin: 0 0 5px 0; color: #555; font-size: 16px; }
    .summary .media { font-size: 32px; font-weight: bold; color: #FFD700; margin: 5px 0; }
    
    .review-list { list-style: none; padding: 0; margin-top: 20px; }
    .review-item { padding: 15px 0; border-bottom: 1px solid #f1f1f1; }
    .review-item:last-child { border-bottom: none; }
    .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .review-header strong { font-size: 16px; color: #045c3f; }
    .review-header .nota { font-size: 18px; color: #FFD700; letter-spacing: 2px; }
    .review-item blockquote { margin: 0; padding: 12px; background: #fafafa; border-left: 4px solid #045c3f; border-radius: 4px; color: #444; white-space: pre-wrap; font-size: 14px; }
    .review-item .data { font-size: 12px; color: #888; margin-top: 8px; text-align: right; }
    .no-reviews { text-align: center; color: #777; padding: 30px; font-style: italic; }

    @media (max-width: 768px) {
        #mobileMenuBtn { display: block; }
        #sidebar { transform: translateX(-100%); width: 260px; }
        #sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 20px 15px; }
        .top { flex-direction: column; align-items: flex-start; gap: 10px; }
        .btn-back { width: 100%; text-align: center; box-sizing: border-box; }
    }
</style>
</head>
<body>

<header>
    <button id="mobileMenuBtn" onclick="toggleSidebar()">☰</button>
    <h1>Not<span>IF</span>y</h1>
</header>

<div id="sidebarBackdrop" onclick="toggleSidebar()"></div>
<div id="sidebar">
    <a href="index.php" class="sidebar-btn">🏠 Calendário</a>
    <a href="meus_eventos.php" class="sidebar-btn">📅 Meus Eventos</a>
    <?php if ($userRole >= 1): ?>
        <a href="adicionarevento.php" class="sidebar-btn">➕ Adicionar Evento</a>
    <?php endif; ?>
    <?php if ($userRole == 2): ?>
        <a href="permissions.php" class="sidebar-btn">🔐 Permissões</a>
        <a href="gerenciar_cursos.php" class="sidebar-btn">🏫 Gerenciar Cursos</a>
    <?php endif; ?>
</div>

<div id="userArea">
    <img id="profileImg" src="<?= htmlspecialchars($userPhoto) ?>" alt="Perfil" onclick="location.href='index.php'"/>
</div>

<div class="main-content">
    <div class="card">
        <div class="top">
            <h2>Avaliações</h2>
            <a href="index.php" class="btn-back">Voltar ao Calendário</a>
        </div>
        <h3 style="font-weight: normal; margin-top:0; color:#666;">Evento: <strong style="color:#333;"><?= htmlspecialchars($evento_nome) ?></strong></h3>

        <div class="summary">
            <h3>Média Geral</h3>
            <div class="media" title="<?= number_format($media_notas, 2, ',', '.') ?> de 5">
                <?= estrelas(round($media_notas)) ?>
            </div>
            <small>(Baseado em <?= count($avaliacoes) ?> avaliações)</small>
        </div>

        <ul class="review-list">
            <?php if (empty($avaliacoes)): ?>
                <li class="no-reviews">Nenhuma avaliação foi recebida para este evento ainda.</li>
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
                        <div class="data"><?= (new DateTime($a['data_avaliacao']))->format('d/m/Y H:i') ?></div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        sb.classList.toggle('active');
        bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
    }
</script>
</body>
</html>