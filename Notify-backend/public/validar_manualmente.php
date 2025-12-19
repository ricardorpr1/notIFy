<?php
// validar_manualmente.php
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
<title>Validar Presença Manualmente</title>
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

    .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 1200px; }

    .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .topbar h2 { margin: 0; font-size: 24px; color: #045c3f; }
    
    .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; color: #fff; text-decoration: none; font-weight: 600; display: inline-flex; justify-content: center; }
    .btn-back { background: #6c757d; }
    .btn-action { background: #28a745; }
    
    .controls { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
    .controls input[type="search"] { padding: 10px; border: 1px solid #ccc; border-radius: 6px; flex: 1; min-width: 250px; }
    
    .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #eee; }
    table { width: 100%; border-collapse: collapse; min-width: 800px; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; }
    th { background: #f8f9fa; font-weight: 700; color: #555; }
    tr.is-present td { background: #f0fdf4; color: #555; }
    tr.is-present input[type="checkbox"] { display: none; }
    
    #msgBox { margin-bottom: 15px; padding: 15px; border-radius: 8px; display: none; font-weight: 500; }
    #msgBox.success { background: #d1e7dd; color: #0f5132; }
    #msgBox.error { background: #f8d7da; color: #842029; }

    @media (max-width: 768px) {
        #mobileMenuBtn { display: block; }
        #sidebar { transform: translateX(-100%); width: 260px; }
        #sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 20px 15px; }
        .controls, .topbar { flex-direction: column; align-items: stretch; }
        .btn, .controls input { width: 100%; margin-bottom: 10px; box-sizing: border-box; }
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
            <h2 id="eventName">Validar Presença Manualmente</h2>
            <a href="gerenciar_inscricoes.php?event_id=<?= $eventId ?>" class="btn btn-back">Voltar</a>
        </div>
        <div id="msgBox"></div>
        <div class="controls">
            <div style="flex: 1;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Filtrar por nome, CPF, RA, etc:</label>
                <input type="search" id="searchBox" placeholder="Pesquisar...">
            </div>
            <div>
                <button id="applyBulkBtn" class="btn btn-action">Validar Selecionados</button>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>CPF / RA</th>
                        <th>Curso / Turma</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="usersTbody">
                    <tr><td colspan="6" style="text-align: center;">Carregando...</td></tr>
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
const usersTbody = document.getElementById('usersTbody');
const searchBox = document.getElementById('searchBox');
const selectAll = document.getElementById('selectAll');
const applyBulkBtn = document.getElementById('applyBulkBtn');
const msgBox = document.getElementById('msgBox');

let allUsersData = []; let presencasIds = []; 

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
        const res = await fetch(`${API_URL}?action=get_all_users_for_manual_add&event_id=${EVENT_ID}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.erro);
        eventNameEl.textContent = `Validar: ${data.evento_nome}`;
        allUsersData = data.all_users; presencasIds = data.presencas_ids;
        renderTabela();
    } catch (err) { showMessage(err.message, 'error'); usersTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">${err.message}</td></tr>`; }
}

function renderTabela() {
    usersTbody.innerHTML = '';
    const query = searchBox.value.trim().toLowerCase();
    const usuariosFiltrados = allUsersData.filter(user => {
        if (!query) return true; 
        const searchString = [user.nome, user.email, user.cpf, user.registro_academico, user.turma_nome, user.curso_sigla].join(' ').toLowerCase();
        return searchString.includes(query);
    });

    if (usuariosFiltrados.length === 0) { usersTbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum usuário encontrado.</td></tr>'; return; }

    usuariosFiltrados.forEach(user => {
        const isPresente = presencasIds.includes(Number(user.id));
        const rowClass = isPresente ? 'is-present' : '';
        const row = document.createElement('tr');
        row.className = rowClass;
        row.innerHTML = `
            <td><input type="checkbox" class="user-checkbox" value="${user.id}" ${isPresente ? 'disabled' : ''}></td>
            <td>${escapeHtml(user.nome)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.cpf || 'N/A')} / ${escapeHtml(user.registro_academico || 'N/A')}</td>
            <td>${escapeHtml(user.curso_sigla || 'N/A')} / ${escapeHtml(user.turma_nome || 'N/A')}</td>
            <td>${isPresente ? 'Presente' : 'Ausente'}</td>`;
        usersTbody.appendChild(row);
    });
}
function showMessage(msg, type = 'success') {
    msgBox.textContent = msg;
    msgBox.className = type === 'success' ? 'success' : 'error';
    msgBox.style.display = 'block';
    setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
}
function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function getSelectedUserIds() { return Array.from(document.querySelectorAll('.user-checkbox:checked:not(:disabled)')).map(cb => cb.value); }

document.addEventListener('DOMContentLoaded', carregarDados);
searchBox.addEventListener('input', renderTabela);
selectAll.addEventListener('change', (e) => { document.querySelectorAll('.user-checkbox:not(:disabled)').forEach(cb => cb.checked = e.target.checked); });
applyBulkBtn.addEventListener('click', async () => {
    const userIds = getSelectedUserIds();
    if (userIds.length === 0) { alert('Selecione pelo menos um usuário.'); return; }
    if (!confirm(`Tem certeza que quer marcar presença para ${userIds.length} usuário(s)?`)) return;
    const formData = new FormData();
    userIds.forEach(id => formData.append('user_ids[]', id));
    try { await apiCall('add_manual_presence', formData); await carregarDados(); } catch (err) {}
});
</script>
</body>
</html>