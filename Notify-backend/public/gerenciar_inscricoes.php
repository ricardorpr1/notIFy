<?php
// gerenciar_inscricoes.php
session_start();

if (!isset($_SESSION['usuario_id'])) { header('Location: telainicio.html'); exit; }

$userRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) die("ID do evento inválido.");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
<title>Gerenciar Inscrições — notIFy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body { margin:0; font-family: 'Inter', sans-serif; background:#f0f2f5; color:#333; overflow-x: hidden; padding-top: 60px; }
    
    /* LAYOUT BASE (Header/Sidebar) */
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

    .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 1200px; }

    /* Estilos Específicos da Página */
    .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .topbar h2 { margin: 0; font-size: 24px; color: #045c3f; }

    .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; color: #fff; text-decoration: none; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; transition: opacity 0.2s; white-space: nowrap; }
    .btn:hover { opacity: 0.9; }
    .btn-back { background: #6c757d; }
    .btn-export { background: #007bff; }
    .btn-save { background: #28a745; }
    .btn-action { background: #17a2b8; }
    .btn-manual { background: #ffc107; color: #212529; }
    
    .controls { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 10px; border: 1px solid #e9ecef; }
    .controls-highlight { background: #fff8e1; border-color: #ffeeba; }
    .control-group { display: flex; flex-direction: column; gap: 5px; }
    .controls label { font-weight: 600; font-size: 13px; color: #555; }
    .controls input, .controls select { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; min-width: 200px; }
    
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid #eee; }
    table { width: 100%; border-collapse: collapse; min-width: 800px; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
    th { background: #f8f9fa; font-size: 13px; font-weight: 700; color: #555; text-transform: uppercase; }
    .status-presente { color: #155724; background: #d4edda; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .status-inscrito { color: #856404; background: #fff3cd; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    #msgBox { margin-bottom: 20px; padding: 15px; border-radius: 8px; display: none; font-weight: 500; }
    #msgBox.success { background: #d1e7dd; color: #0f5132; } #msgBox.error { background: #f8d7da; color: #842029; }

    @media (max-width: 768px) {
        #mobileMenuBtn { display: block; }
        #sidebar { transform: translateX(-100%); width: 260px; }
        #sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 20px 15px; }
        .controls { flex-direction: column; align-items: stretch; }
        .control-group { width: 100%; }
        .controls input, .controls select, .btn { width: 100%; margin-bottom: 5px; box-sizing: border-box; }
        .topbar { flex-direction: column; text-align: center; }
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
        <div class="topbar">
            <h2 id="eventName">Gerenciar Inscrições</h2>
            <a href="index.php" class="btn btn-back">Voltar ao Calendário</a>
        </div>
        
        <div id="msgBox"></div>

        <div class="controls">
            <div class="control-group" style="flex: 2;">
                <label for="searchBox">Pesquisar por nome ou e-mail:</label>
                <input type="search" id="searchBox" placeholder="Digite para filtrar...">
            </div>
            
            <div class="control-group" style="flex: 1;">
                <label for="limiteInput">Limite (0 = ilimitado)</label>
                <div style="display: flex; gap: 5px;">
                    <input type="number" id="limiteInput" min="0" value="0" style="flex: 1;">
                    <button id="saveLimitBtn" class="btn btn-save" style="width: auto;">Salvar</button>
                </div>
            </div>
            
            <div class="control-group">
                <label style="visibility: hidden; height: 0; margin: 0;">Ações</label>
                <a href="export_inscricoes.php?id=<?= $eventId ?>" id="exportLink" class="btn btn-export">Exportar CSV</a>
                <a href="validar_manualmente.php?event_id=<?= $eventId ?>" class="btn btn-manual" style="margin-top: 5px;">Validar Manualmente</a>
            </div>
        </div>

        <div class="controls controls-highlight">
            <div class="control-group" style="width: 100%;">
                <label for="bulkAction">Ações em massa:</label>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <select id="bulkAction" style="flex: 1;">
                        <option value="">Selecione uma ação...</option>
                        <option value="marcar_presenca">Marcar Presença</option>
                        <option value="remover_presenca">Remover Presença</option>
                        <option value="remover_inscricao" style="color: red;">Remover Inscrição</option>
                    </select>
                    <button id="applyBulkBtn" class="btn btn-action" style="flex: 0 0 auto;">Aplicar</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>CPF</th>
                        <th>Turma</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="usersTbody">
                    <tr><td colspan="6" style="text-align: center; padding: 30px;">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const bd = document.getElementById('sidebarBackdrop');
    sb.classList.toggle('active');
    bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
}

const EVENT_ID = <?= $eventId; ?>;
const API_URL = 'api_gerenciar_inscricoes.php';
const eventNameEl = document.getElementById('eventName');
const limiteInput = document.getElementById('limiteInput');
const saveLimitBtn = document.getElementById('saveLimitBtn');
const usersTbody = document.getElementById('usersTbody');
const searchBox = document.getElementById('searchBox');
const selectAll = document.getElementById('selectAll');
const bulkAction = document.getElementById('bulkAction');
const applyBulkBtn = document.getElementById('applyBulkBtn');
const msgBox = document.getElementById('msgBox');

let allUsersData = []; 
let presencasIds = []; 

async function apiCall(action, formData) {
    formData.append('action', action);
    formData.append('event_id', EVENT_ID);
    msgBox.style.display = 'none';
    try {
        const res = await fetch(API_URL, { method: 'POST', body: formData });
        const json = await res.json();
        if (!res.ok) throw new Error(json.erro || 'Erro desconhecido');
        showMessage(json.mensagem || 'Sucesso!', 'success');
        return json;
    } catch (err) { showMessage(err.message, 'error'); throw err; }
}

async function carregarDados() {
    try {
        const res = await fetch(`${API_URL}?action=get_data&event_id=${EVENT_ID}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.erro);
        eventNameEl.textContent = `Gerenciar: ${data.evento.nome}`;
        limiteInput.value = data.evento.limite_participantes || 0;
        allUsersData = data.usuarios; presencasIds = data.presencas_ids; 
        renderTabela();
    } catch (err) {
        showMessage(err.message, 'error');
        usersTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">${err.message}</td></tr>`;
    }
}

function renderTabela() {
    usersTbody.innerHTML = '';
    const query = searchBox.value.trim().toLowerCase();
    const usuariosFiltrados = allUsersData.filter(user => user.nome.toLowerCase().includes(query) || user.email.toLowerCase().includes(query));

    if (usuariosFiltrados.length === 0) { usersTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px;">Nenhum inscrito.</td></tr>'; return; }

    usuariosFiltrados.forEach(user => {
        const isPresente = presencasIds.includes(Number(user.id));
        const statusClass = isPresente ? 'status-presente' : 'status-inscrito';
        const statusText = isPresente ? 'Presente' : 'Inscrito';
        const row = document.createElement('tr');
        row.innerHTML = `
            <td style="text-align: center;"><input type="checkbox" class="user-checkbox" value="${user.id}"></td>
            <td><strong>${escapeHtml(user.nome)}</strong></td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.cpf || '-')}</td>
            <td>${escapeHtml(user.turma_nome || 'Externo')}</td>
            <td><span class="${statusClass}">${statusText}</span></td>`;
        usersTbody.appendChild(row);
    });
}

function showMessage(msg, type = 'success') {
    msgBox.textContent = msg;
    msgBox.className = type;
    msgBox.style.display = 'block';
    if(window.innerWidth < 768) msgBox.scrollIntoView({behavior:'smooth'});
    setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
}
function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function getSelectedUserIds() { return Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value); }

document.addEventListener('DOMContentLoaded', carregarDados);
saveLimitBtn.addEventListener('click', async () => { const fd = new FormData(); fd.append('limite', limiteInput.value); await apiCall('update_limit', fd); });
searchBox.addEventListener('input', renderTabela);
selectAll.addEventListener('change', (e) => { document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = e.target.checked); });
applyBulkBtn.addEventListener('click', async () => {
    const action = bulkAction.value;
    const userIds = getSelectedUserIds();
    if (!action) { alert('Selecione uma ação.'); return; }
    if (userIds.length === 0) { alert('Selecione pelo menos um usuário.'); return; }
    if (action === 'remover_inscricao' && !confirm('Remover inscrição destes usuários?')) return;
    const fd = new FormData();
    userIds.forEach(id => fd.append('user_ids[]', id));
    try { await apiCall(action, fd); await carregarDados(); } catch (err) {}
});
</script>
</body>
</html>