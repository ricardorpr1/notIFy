<?php
// meus_eventos.php — Com Sidebar e Header Responsivos
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
$userId = intval($_SESSION['usuario_id']);
$userRole = intval($_SESSION['role'] ?? 0);
$userName = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário');
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db"; $dbuser = "tcc_notify"; $dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("Erro de conexão: " . $e->getMessage()); }

// Buscar eventos (mesma lógica anterior)
$eventos = [];
try {
    $sql = "SELECT id, nome, data_hora_inicio, data_hora_fim, local, created_by, colaboradores_ids, palestrantes_ids, inscricoes, presencas 
            FROM eventos 
            WHERE created_by = :uid 
            OR JSON_CONTAINS(colaboradores_ids, CAST(:uid AS JSON), '$') 
            OR JSON_CONTAINS(palestrantes_ids, CAST(:uid AS JSON), '$') 
            OR JSON_CONTAINS(inscricoes, CAST(:uid AS JSON), '$')
            ORDER BY data_hora_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $eventos = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback para versões antigas do MySQL se JSON falhar
    $sql = "SELECT * FROM eventos WHERE created_by = :uid ORDER BY data_hora_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $eventos = $stmt->fetchAll();
}

function formatarData($s) {
    if (!$s) return '—';
    try { return (new DateTime($s))->format('d/m/Y \à\s H:i'); } catch (Exception $e) { return 'Data inválida'; }
}
$agora = new DateTime();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Meus Eventos — notIFy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- ESTILOS GERAIS E RESPONSIVOS (PADRÃO) --- */
        body { margin:0; font-family: 'Inter', sans-serif; background:#f0f2f5; color:#333; overflow-x: hidden; padding-top: 60px; }
        
        /* HEADER */
        header { position: fixed; top: 0; left: 0; width: 100%; background-color: #045c3f; color: white; display: flex; align-items: center; justify-content: center; height: 60px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 3000; }
        header h1 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -1px; }
        header span { color: #c00000; font-weight: 900; }
        #mobileMenuBtn { display: none; position: absolute; left: 15px; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }

        /* SIDEBAR */
        #sidebar { position: fixed; top: 60px; left: 0; width: 250px; height: calc(100vh - 60px); background: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 12px; border-right: 1px solid #e0e0e0; box-shadow: 4px 0 16px rgba(0,0,0,0.08); z-index: 2000; transition: transform 0.3s ease; }
        .sidebar-btn { background: #045c3f; color: #fff; border: none; padding: 14px 20px; border-radius: 10px; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.3s; width: 100%; box-sizing: border-box; text-decoration: none; }
        .sidebar-btn:hover { background: #05774f; transform: translateY(-2px); }
        .sidebar-btn.active { background: #03442e; border: 1px solid #022c1e; }
        #sidebarBackdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; }

        /* AREA DO USUÁRIO */
        #userArea { position: fixed; top: 8px; right: 15px; z-index: 3100; display: flex; gap: 10px; align-items: center; }
        #profileImg { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; cursor: pointer; }

        /* CONTEÚDO PRINCIPAL */
        .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 900px; }
        
        .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 8px 26px rgba(0,0,0,0.06); }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .top h2 { margin: 0; color: #045c3f; }

        /* LISTA DE EVENTOS */
        .event-list { list-style: none; padding: 0; }
        .event-item { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; padding: 20px 0; border-bottom: 1px solid #f1f1f1; }
        .event-info h3 { margin: 0 0 5px 0; color: #333; font-size: 18px; }
        .event-info p { margin: 2px 0; font-size: 14px; color: #666; }
        .btn-action { padding: 8px 14px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; color: #fff; display: inline-block; cursor: pointer; border: none; }
        .btn-cert { background: #17a2b8; }
        .btn-avaliar { background: #ffc107; color: #212529; }
        .btn-cert:disabled { background: #ccc; cursor: not-allowed; }

        /* MOBILE QUERY */
        @media (max-width: 768px) {
            #mobileMenuBtn { display: block; }
            #sidebar { transform: translateX(-100%); width: 260px; }
            #sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .event-item { flex-direction: column; align-items: flex-start; }
            .event-actions { width: 100%; display: flex; gap: 10px; }
            .btn-action { flex: 1; text-align: center; }
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
    <a href="meus_eventos.php" class="sidebar-btn active">📅 Meus Eventos</a>
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
            <h2>Meus Eventos</h2>
        </div>
        <ul class="event-list">
            <?php if (empty($eventos)): ?>
                <li style="text-align:center; padding:30px; color:#777;">Você não possui vínculos com eventos.</li>
            <?php else: ?>
                <?php foreach ($eventos as $evento): ?>
                    <?php
                    $terminou = false; $presente = false;
                    if (!empty($evento['data_hora_fim'])) {
                        try { if ($agora > new DateTime($evento['data_hora_fim'])) $terminou = true; } catch(Exception $e){}
                    }
                    $presencas = json_decode($evento['presencas'] ?? '[]', true);
                    if (is_array($presencas) && in_array($userId, $presencas)) $presente = true;
                    
                    // Papeis
                    $papeis = [];
                    if ($evento['created_by'] == $userId) $papeis[] = 'Criador';
                    $palestrantes = json_decode($evento['palestrantes_ids'] ?? '[]', true);
                    if (is_array($palestrantes) && in_array($userId, $palestrantes)) $papeis[] = 'Palestrante';
                    $colabs = json_decode($evento['colaboradores_ids'] ?? '[]', true);
                    if (is_array($colabs) && in_array($userId, $colabs)) $papeis[] = 'Colaborador';
                    $inscritos = json_decode($evento['inscricoes'] ?? '[]', true);
                    if (is_array($inscritos) && in_array($userId, $inscritos)) $papeis[] = 'Inscrito';
                    ?>
                    <li class="event-item">
                        <div class="event-info">
                            <h3><?= htmlspecialchars($evento['nome']) ?></h3>
                            <p><strong>Início:</strong> <?= formatarData($evento['data_hora_inicio']) ?></p>
                            <p><strong>Papel:</strong> <?= implode(', ', array_unique($papeis)) ?></p>
                        </div>
                        <div class="event-actions">
                            <?php if ($terminou && $presente): ?>
                                <a href="avaliar_evento.php?event_id=<?= $evento['id'] ?>" class="btn-action btn-avaliar">Avaliar</a>
                                <a href="gerar_certificado.php?event_id=<?= $evento['id'] ?>" class="btn-action btn-cert" target="_blank">Certificado</a>
                            <?php elseif ($terminou): ?>
                                <button class="btn-action btn-cert" disabled>Ausente</button>
                            <?php else: ?>
                                <span style="font-size:12px; color:#045c3f; font-weight:bold; padding:8px;">Em breve</span>
                            <?php endif; ?>
                        </div>
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