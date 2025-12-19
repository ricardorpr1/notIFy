<?php
// avaliar_evento.php — Com Sidebar e Header Responsivos
session_start();

// 1. Requer Login
if (!isset($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(401); echo json_encode(['erro' => 'Usuário não autenticado.']); exit;
    }
    header('Location: telainicio.html'); exit;
}
$userId = intval($_SESSION['usuario_id']);
$userRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db"; $dbuser = "tcc_notify"; $dbpass = "108Xk:C";
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) { die("Erro de conexão."); }

// 2. Lógica de API (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json; charset=UTF-8");
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    $eventId = isset($data['event_id']) ? intval($data['event_id']) : 0;
    $nota = isset($data['nota']) ? intval($data['nota']) : 0;
    $comentario = isset($data['comentario']) ? trim($data['comentario']) : '';

    if ($eventId <= 0) { http_response_code(400); echo json_encode(['erro' => 'ID inválido.']); exit; }
    if ($nota < 1 || $nota > 5) { http_response_code(400); echo json_encode(['erro' => 'Nota inválida.']); exit; }

    try {
        $stmt = $pdo->prepare("SELECT data_hora_fim, presencas FROM eventos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch();
        if (!$event) { http_response_code(404); echo json_encode(['erro' => 'Evento não encontrado.']); exit; }
        
        date_default_timezone_set('America/Sao_Paulo'); 
        if (new DateTime() <= new DateTime($event['data_hora_fim'])) {
            http_response_code(403); echo json_encode(['erro' => 'Evento ainda não terminou.']); exit;
        }
        
        $presencas = json_decode($event['presencas'] ?? '[]', true);
        if (!is_array($presencas) || !in_array($userId, $presencas)) {
            http_response_code(403); echo json_encode(['erro' => 'Presença não confirmada.']); exit;
        }
        
        $sql = "INSERT INTO avaliacoes_evento (evento_id, usuario_id, nota, comentario) VALUES (:eid, :uid, :nota, :comentario)
                ON DUPLICATE KEY UPDATE nota = VALUES(nota), comentario = VALUES(comentario), data_avaliacao = CURRENT_TIMESTAMP";
        $stmt_save = $pdo->prepare($sql);
        $stmt_save->execute([':eid' => $eventId, ':uid' => $userId, ':nota' => $nota, ':comentario' => $comentario]);

        echo json_encode(['mensagem' => 'Avaliação salva!']); exit;
    } catch (Exception $e) { http_response_code(500); echo json_encode(['erro' => $e->getMessage()]); exit; }
}

// 3. Lógica da Página (GET)
$eventId_get = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId_get <= 0) die("ID inválido.");

$evento_nome = ''; $avaliacao_anterior = null; $erro_permissao = null;

try {
    $stmt = $pdo->prepare("SELECT nome, data_hora_fim, presencas FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId_get]);
    $event = $stmt->fetch();
    if (!$event) die("Evento não encontrado.");
    $evento_nome = $event['nome'];

    date_default_timezone_set('America/Sao_Paulo'); 
    if (new DateTime() <= new DateTime($event['data_hora_fim'])) $erro_permissao = 'Você só pode avaliar após o término do evento.';
    
    $presencas = json_decode($event['presencas'] ?? '[]', true);
    if (!is_array($presencas) || !in_array($userId, $presencas)) $erro_permissao = 'Sua presença não foi registrada neste evento.';

    $stmt_prev = $pdo->prepare("SELECT nota, comentario FROM avaliacoes_evento WHERE evento_id = :eid AND usuario_id = :uid LIMIT 1");
    $stmt_prev->execute([':eid' => $eventId_get, ':uid' => $userId]);
    $avaliacao_anterior = $stmt_prev->fetch();
} catch (Exception $e) { die("Erro: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
<title>Avaliar Evento — notIFy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* --- LAYOUT PADRÃO --- */
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

    .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 800px; }

    /* --- ESTILOS DE AVALIAÇÃO --- */
    .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
    .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .top h2 { margin: 0; color: #045c3f; font-size: 22px; }
    .btn-back { background: #6c757d; color: #fff; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; }
    
    .rating-css { display: inline-block; margin-bottom: 15px; }
    .rating-css > input { display: none; }
    .rating-css > label { color: #ccc; cursor: pointer; font-size: 45px; transition: 0.2s; }
    .rating-css > label:hover,
    .rating-css > label:hover ~ label,
    .rating-css > input:checked ~ label { color: #FFD700; }
    /* Inverte a ordem para efeito visual correto */
    .rating-css { unicode-bidi: bidi-override; direction: rtl; text-align: left; }
    .rating-css > label { padding: 0 5px; }
    
    textarea { width: 100%; border: 1px solid #ccc; border-radius: 8px; padding: 12px; font-family: inherit; min-height: 120px; margin-top: 5px; box-sizing: border-box; resize: vertical; }
    .btn-save { background: #228b22; color: #fff; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 20px; width: 100%; }
    .btn-save:disabled { background: #aaa; cursor: not-allowed; }
    
    .error-box { background: #fdecea; color: #a94442; border: 1px solid #f3c6c6; padding: 15px; border-radius: 8px; text-align: center; }
    .msg-box { padding: 15px; border-radius: 8px; margin-top: 15px; text-align: center; font-weight: bold; }
    .msg-success { background: #e6f7ea; color: #0b6b33; }
    .msg-error { background: #fdecea; color: #a94442; }

    @media (max-width: 768px) {
        #mobileMenuBtn { display: block; }
        #sidebar { transform: translateX(-100%); width: 260px; }
        #sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 20px 15px; }
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
            <h2>Avaliar Evento</h2>
            <a href="meus_eventos.php" class="btn-back">Voltar</a>
        </div>
        <h3 style="margin-top:0; color:#555; font-weight:normal;">Evento: <strong style="color:#045c3f;"><?= htmlspecialchars($evento_nome) ?></strong></h3>
        
        <?php if ($erro_permissao): ?>
            <div class="error-box"><?= htmlspecialchars($erro_permissao) ?></div>
        <?php else: ?>
            <form id="formAvaliacao">
                <p style="font-weight:600; margin-bottom:5px;">Sua nota:</p>
                <div class="rating-css">
                    <input type="radio" id="rating5" name="rating" value="5" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 5) ? 'checked' : '' ?>><label for="rating5">★</label>
                    <input type="radio" id="rating4" name="rating" value="4" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 4) ? 'checked' : '' ?>><label for="rating4">★</label>
                    <input type="radio" id="rating3" name="rating" value="3" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 3) ? 'checked' : '' ?>><label for="rating3">★</label>
                    <input type="radio" id="rating2" name="rating" value="2" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 2) ? 'checked' : '' ?>><label for="rating2">★</label>
                    <input type="radio" id="rating1" name="rating" value="1" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 1) ? 'checked' : '' ?>><label for="rating1">★</label>
                </div>

                <p style="font-weight:600; margin-bottom:5px;">Comentário (opcional):</p>
                <textarea id="comentario" name="comentario" placeholder="Escreva o que achou do evento..."><?= htmlspecialchars($avaliacao_anterior['comentario'] ?? '') ?></textarea>

                <button type="submit" id="btnSalvar" class="btn-save">Salvar Avaliação</button>
            </form>
            <div id="msgBox" class="msg-box" style="display: none;"></div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        sb.classList.toggle('active');
        bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
    }

    const form = document.getElementById('formAvaliacao');
    if (form) {
        const btnSalvar = document.getElementById('btnSalvar');
        const msgBox = document.getElementById('msgBox');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const notaSelecionada = document.querySelector('input[name="rating"]:checked');
            if (!notaSelecionada) { alert('Por favor, selecione uma nota.'); return; }

            btnSalvar.disabled = true;
            btnSalvar.textContent = 'Enviando...';
            msgBox.style.display = 'none';

            try {
                const res = await fetch('avaliar_evento.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        event_id: <?= $eventId_get; ?>,
                        nota: parseInt(notaSelecionada.value, 10),
                        comentario: document.getElementById('comentario').value.trim()
                    })
                });
                
                const json = await res.json();
                if (res.ok) {
                    msgBox.textContent = json.mensagem;
                    msgBox.className = 'msg-box msg-success';
                    msgBox.style.display = 'block';
                    setTimeout(() => { window.location.href = 'meus_eventos.php'; }, 2000);
                } else { throw new Error(json.erro || 'Erro desconhecido'); }
            } catch (err) {
                msgBox.textContent = err.message;
                msgBox.className = 'msg-box msg-error';
                msgBox.style.display = 'block';
                btnSalvar.disabled = false;
                btnSalvar.textContent = 'Salvar Avaliação';
            }
        });
    }
</script>
</body>
</html>