<?php
// validar_presenca.php - Com Sidebar
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();
$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db"; $DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

function respond($code, $payload) { http_response_code($code); header("Content-Type: application/json; charset=UTF-8"); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

if (!isset($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { respond(401, ["erro" => "Usuário não autenticado."]); }
    else { header('Location: telainicio.html'); exit; }
}
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

try { $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); } catch (PDOException $e) { respond(500, ["erro" => "Erro de conexão."]); }

// Lógica de POST (Registrar presença) - Mantida idêntica para não quebrar API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) respond(400, ["erro" => "JSON inválido."]);

    $eventId = isset($data['event_id']) ? intval($data['event_id']) : 0;
    $cpf = isset($data['cpf']) ? preg_replace('/\D/', '', (string)$data['cpf']) : '';

    if ($eventId <= 0) respond(400, ["erro" => "ID inválido."]);
    if (strlen($cpf) !== 11) respond(400, ["erro" => "CPF inválido."]);

    // Permissões
    try {
        $stmt = $pdo->prepare("SELECT created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch();
        if (!$event) respond(404, ["erro" => "Evento não encontrado."]);
    } catch (PDOException $e) { respond(500, ["erro" => "Erro DB."]); }

    $createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
    $isDev = ($myRole === 2);
    $isCollaborator = false;
    if (!empty($event['colaboradores_ids'])) {
        $tmp = json_decode($event['colaboradores_ids'], true);
        if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
    }
    if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) { respond(403, ["erro" => "Permissão negada."]); }

    // Busca usuário
    try {
        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE cpf = :cpf LIMIT 1");
        $stmt->execute([':cpf' => $cpf]);
        $user = $stmt->fetch();
        if (!$user) respond(404, ["erro" => "CPF não encontrado."]);
        $userId = intval($user['id']);
        $userName = $user['nome'];
    } catch (PDOException $e) { respond(500, ["erro" => "Erro DB User."]); }

    // Salva
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT presencas, inscricoes FROM eventos WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch();
        
        $inscricoes = [];
        if (!empty($row['inscricoes'])) {
            $tmp_insc = json_decode($row['inscricoes'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp_insc)) $inscricoes = array_map('intval', $tmp_insc);
        }
        
        if (!in_array($userId, $inscricoes, true)) {
            $pdo->rollBack();
            respond(403, ["erro" => "O usuário $userName não está inscrito.", "status" => "nao_inscrito"]);
        }

        $presencas = [];
        if (!empty($row['presencas'])) {
            $tmp = json_decode($row['presencas'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $presencas = array_map('intval', $tmp);
        }

        if (in_array($userId, $presencas, true)) {
            $pdo->rollBack();
            respond(200, ["mensagem" => "Presença de $userName já registrada.", "status" => "duplicado"]);
        }

        $presencas[] = $userId;
        $jsonNovo = json_encode(array_values(array_unique($presencas)), JSON_UNESCAPED_UNICODE);
        $upd = $pdo->prepare("UPDATE eventos SET presencas = :presencas WHERE id = :id");
        $upd->execute([':presencas' => $jsonNovo, ':id' => $eventId]);
        $pdo->commit();

        respond(201, ["mensagem" => "Presença de $userName confirmada!", "status" => "registrado"]);
    } catch (PDOException $e) { if($pdo->inTransaction()) $pdo->rollBack(); respond(500, ["erro" => "Erro DB Save."]); }
    exit;
}

// Lógica GET
$eventId_get = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId_get <= 0) die("ID inválido.");

try {
    $stmt = $pdo->prepare("SELECT id, nome, created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId_get]);
    $event = $stmt->fetch();
    if (!$event) die("Evento não encontrado.");
    
    // Permissão GET
    $createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
    $isDev = ($myRole === 2);
    $isCollaborator = false;
    if (!empty($event['colaboradores_ids'])) {
        $tmp = json_decode($event['colaboradores_ids'], true);
        if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
    }
    if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) die("Permissão negada.");
    
    $eventName_php = $event['nome'];
    $eventId_php = $event['id'];
} catch (PDOException $e) { die("Erro DB."); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Validar Presença - notIFy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.7/html5-qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin:0; font-family: 'Inter', sans-serif; background:#f0f2f5; color:#333; overflow-x: hidden; padding-top: 60px; text-align: center; }
        
        header { position: fixed; top: 0; left: 0; width: 100%; background-color: #045c3f; color: white; display: flex; align-items: center; justify-content: center; height: 60px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 3000; }
        header h1 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -1px; }
        header span { color: #c00000; font-weight: 900; }
        #mobileMenuBtn { display: none; position: absolute; left: 15px; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }

        #sidebar { position: fixed; top: 60px; left: 0; width: 250px; height: calc(100vh - 60px); background: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 12px; border-right: 1px solid #e0e0e0; box-shadow: 4px 0 16px rgba(0,0,0,0.08); z-index: 2000; transition: transform 0.3s ease; text-align: left; }
        .sidebar-btn { background: #045c3f; color: #fff; border: none; padding: 14px 20px; border-radius: 10px; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.3s; width: 100%; box-sizing: border-box; text-decoration: none; }
        .sidebar-btn:hover { background: #05774f; transform: translateY(-2px); }
        #sidebarBackdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; }

        #userArea { position: fixed; top: 8px; right: 15px; z-index: 3100; display: flex; gap: 10px; align-items: center; }
        #profileImg { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; cursor: pointer; }

        .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 100%; display: flex; flex-direction: column; align-items: center; }

        .container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); width: 100%; max-width: 500px; box-sizing: border-box; }
        
        #reader { width: 100%; max-width: 320px; margin: 20px auto; display: none; border: 2px solid #2e5c44; border-radius: 8px; }
        .btn { background-color: #2e5c44; color: white; border: none; padding: 14px 28px; font-size: 16px; border-radius: 8px; cursor: pointer; margin: 10px auto; display: block; width: 100%; max-width: 300px; }
        .btn-secondary { background-color: #6b7280; width: auto; display: inline-block; padding: 10px 20px; }
        .btn-group { display: flex; justify-content: center; gap: 15px; align-items: center; flex-wrap: wrap; margin-top: 15px; }
        .switch-btn { background: #f0f2f5; border: 1px solid #ddd; border-radius: 8px; padding: 10px; cursor: pointer; font-size: 24px; }
        
        #result { margin-top: 20px; font-weight: bold; padding: 15px; border-radius: 8px; }
        #result.success { background-color: #d1e7dd; color: #0f5132; }
        #result.error { background-color: #f8d7da; color: #842029; }
        #result.warning { background-color: #fff3cd; color: #856404; }
        #result.pending { background-color: #cff4fc; color: #055160; }

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
    <?php if ($myRole >= 1): ?>
        <a href="adicionarevento.php" class="sidebar-btn">➕ Adicionar Evento</a>
    <?php endif; ?>
    <?php if ($myRole == 2): ?>
        <a href="permissions.php" class="sidebar-btn">🔐 Permissões</a>
        <a href="gerenciar_cursos.php" class="sidebar-btn">🏫 Gerenciar Cursos</a>
    <?php endif; ?>
</div>

<div id="userArea">
    <img id="profileImg" src="<?= htmlspecialchars($userPhoto) ?>" alt="Perfil" onclick="location.href='index.php'"/>
</div>

<div class="main-content">
    <div class="container">
        <h2 style="color:#045c3f; margin-top:0;">Validar Presença</h2>
        <h3 style="font-weight: normal; margin-top: -10px;">Evento: <strong><?= htmlspecialchars($eventName_php) ?></strong></h3>
        
        <button id="start-camera-btn" class="btn">📷 Escanear QR Code</button>
        
        <div class="btn-group">
            <button id="switch-btn" class="switch-btn" title="Trocar câmera" disabled>🔄</button>
            <button id="manual-input-btn" class="switch-btn" title="Inserir manualmente">⌨️</button>
            <button id="back-btn" class="btn btn-secondary" title="Voltar ao calendário">Voltar</button>
        </div>
        
        <div id="reader"></div>
        <div id="result"></div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        sb.classList.toggle('active');
        bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
    }

    const EVENT_ID = <?= json_encode($eventId_php); ?>;
    const startBtn = document.getElementById("start-camera-btn");
    const switchBtn = document.getElementById("switch-btn");
    const manualBtn = document.getElementById("manual-input-btn");
    const readerElem = document.getElementById("reader");
    const resultElem = document.getElementById("result");
    const backBtn = document.getElementById("back-btn");
    const html5QrCode = new Html5Qrcode("reader");
    let cameras = [];
    let currentCameraIndex = 0;
    let scanning = false;

    function showResult(message, type) { 
        resultElem.textContent = message;
        resultElem.className = type;
    }

    async function registerPresence(cpf) {
        showResult("Registrando CPF: " + cpf + "...", "pending");
        try {
            const response = await fetch('validar_presenca.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_id: EVENT_ID, cpf: cpf })
            });
            const result = await response.json();
            if (response.ok) {
                showResult(result.mensagem, (result.status === 'duplicado' ? 'warning' : 'success'));
            } else {
                showResult(result.erro || 'Erro desconhecido.', 'error');
            }
        } catch (err) { showResult("Erro de conexão.", 'error'); }
        
        setTimeout(() => { if (scanning) showResult("Aponte a câmera...", "pending"); }, 3000);
    }

    startBtn.onclick = async () => {
        if (scanning) return;
        startBtn.disabled = true;
        try {
            cameras = await Html5Qrcode.getCameras();
            if (cameras.length === 0) { alert("Nenhuma câmera encontrada."); startBtn.disabled = false; return; }
            startScan(cameras[currentCameraIndex]);
            switchBtn.disabled = cameras.length < 2;
        } catch (err) { alert("Erro ao acessar câmera. Verifique permissões."); startBtn.disabled = false; }
    };

    switchBtn.onclick = async () => {
        if (!scanning || cameras.length < 2) return;
        await html5QrCode.stop();
        currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
        startScan(cameras[currentCameraIndex]);
    };

    manualBtn.onclick = () => {
        const input = prompt("Digite os 11 dígitos do CPF do usuário:");
        const cpf = input ? input.replace(/\D/g, '') : '';
        if (cpf && cpf.length === 11) registerPresence(cpf);
        else if (input) alert("Insira 11 dígitos numéricos.");
    };

    function startScan(camera) {
        readerElem.style.display = 'block';
        showResult("Aponte a câmera...", "pending");
        html5QrCode.start(
            camera.id, { fps: 10, qrbox: { width: 250, height: 250 } },
            qrCodeMessage => {
                html5QrCode.pause(); 
                const cpf = String(qrCodeMessage).replace(/\D/g, '');
                if (cpf.length !== 11) {
                    showResult(`QR inválido (${qrCodeMessage})`, "error");
                    setTimeout(() => html5QrCode.resume(), 2000);
                    return;
                }
                registerPresence(cpf);
                setTimeout(() => { if (scanning) html5QrCode.resume(); }, 3000); 
            },
            errorMessage => {}
        ).then(() => { scanning = true; startBtn.textContent = "Scanner Ativo"; })
         .catch(err => { startBtn.disabled = false; });
    }

    backBtn.addEventListener('click', () => {
        if (scanning) html5QrCode.stop();
        window.location.href = 'index.php';
    });
</script>
</body>
</html>