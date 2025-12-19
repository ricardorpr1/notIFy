<?php
// collaborators.php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: telainicio.html'); exit; }

$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db"; $dbuser = "tcc_notify"; $dbpass = "108Xk:C";
try { $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); } catch (PDOException $e) { die("Erro de conexão."); }

$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) die("ID inválido.");

try {
    $stmt = $pdo->prepare("SELECT id, nome, created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) die("Evento não encontrado.");
} catch (PDOException $e) { die("Erro ao buscar evento."); }

// Permissões
$createdBy = array_key_exists('created_by', $event) && $event['created_by'] !== null ? intval($event['created_by']) : null;
$allowed = ($myRole === 2) || ($createdBy !== null && $createdBy === $me);
if (!$allowed) { http_response_code(403); die("Permissão negada."); }

// Usuários
try {
    $stmt = $pdo->query("SELECT id, nome, email FROM usuarios ORDER BY id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) { die("Erro ao listar usuários."); }

$current_collabs = [];
if (!empty($event['colaboradores_ids'])) {
    $tmp = json_decode($event['colaboradores_ids'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $current_collabs = array_map('intval', $tmp);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
    <title>Adicionar Colaboradores</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
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

        .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .top h2 { margin: 0; color: #045c3f; font-size: 22px; }
        .search { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 15px; box-sizing: border-box; font-size: 16px; }
        .list { max-height: 50vh; overflow: auto; border: 1px solid #eee; border-radius: 8px; padding: 10px; background: #fafafa; }
        .user-row { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #f1f1f1; }
        .user-name { flex: 1; padding-left: 15px; }
        
        .btn { padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; }
        .btn-add { background: #228b22; color: #fff; width: 100%; margin-top: 15px; font-size: 16px; }
        .btn-back { background: #6c757d; color: #fff; }

        @media (max-width: 768px) {
            #mobileMenuBtn { display: block; }
            #sidebar { transform: translateX(-100%); width: 260px; }
            #sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .top { flex-direction: column; gap: 10px; align-items: flex-start; }
            .btn-back { width: 100%; }
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
    <div class="card">
        <div class="top">
            <h2>Colaboradores — <?= htmlspecialchars($event['nome']) ?></h2>
            <button class="btn btn-back" onclick="location.href='index.php'">Voltar</button>
        </div>

        <p>Marque quem ajudará a gerenciar este evento:</p>
        <input id="searchBox" class="search" placeholder="Pesquisar..." />

        <div class="list" id="usersList">
            <?php foreach ($users as $u):
                $uid = intval($u['id']);
                if ($uid === $me) continue;
                $checked = in_array($uid, $current_collabs) ? 'checked' : '';
                ?>
                <div class="user-row" data-name="<?= htmlspecialchars(strtolower($u['nome'].' '.$u['email'])) ?>">
                    <input type="checkbox" class="chkUser" data-uid="<?= $uid ?>" <?= $checked ?> style="transform: scale(1.3);">
                    <div class="user-name">
                        <strong><?= htmlspecialchars($u['nome']) ?></strong><br /><small style="color:#666;"><?= htmlspecialchars($u['email']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button id="btnAdd" class="btn btn-add">Salvar Alterações</button>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        sb.classList.toggle('active');
        bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
    }

    const searchBox = document.getElementById('searchBox');
    const usersList = document.getElementById('usersList');

    searchBox.addEventListener('input', () => {
        const q = searchBox.value.trim().toLowerCase();
        Array.from(usersList.querySelectorAll('.user-row')).forEach(row => {
            const name = row.getAttribute('data-name') || '';
            row.style.display = name.indexOf(q) !== -1 ? 'flex' : 'none';
        });
    });

    document.getElementById('btnAdd').addEventListener('click', async () => {
        const checked = Array.from(document.querySelectorAll('.chkUser:checked')).map(cb => parseInt(cb.getAttribute('data-uid'), 10));
        const payload = { event_id: <?= json_encode($eventId) ?>, add_ids: checked };

        try {
            const res = await fetch('update_collaborators.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const text = await res.text();
            let json; try { json = text ? JSON.parse(text) : {}; } catch (e) { alert('Erro no servidor'); return; }

            if (!res.ok) { alert(json.erro || 'Erro ao salvar.'); return; }
            alert('Colaboradores atualizados!');
            window.location.href = 'index.php';
        } catch (err) { alert('Erro de rede.'); }
    });
</script>
</body>
</html>