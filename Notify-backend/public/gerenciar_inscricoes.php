<?php
// gerenciar_inscricoes.php - Otimizado para Mobile
session_start();

// 1. Requer Login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
// 2. ID do Evento
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
    body { font-family: 'Inter', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
    
    .card { 
        background: #fff; 
        padding: 24px; 
        border-radius: 12px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
        max-width: 1200px; 
        margin: 0 auto; 
    }

    .topbar { 
        display: flex; 
        flex-wrap: wrap; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 20px; 
        gap: 15px; 
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    .topbar h2 { margin: 0; font-size: 24px; color: #045c3f; }

    .btn { 
        padding: 10px 16px; 
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        color: #fff; 
        text-decoration: none; 
        font-size: 14px; 
        font-weight: 600; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center;
        transition: opacity 0.2s;
        white-space: nowrap;
    }
    .btn:hover { opacity: 0.9; }
    .btn-back { background: #6c757d; }
    .btn-export { background: #007bff; }
    .btn-save { background: #28a745; }
    .btn-action { background: #17a2b8; }
    .btn-danger { background: #dc3545; }
    .btn-manual { background: #ffc107; color: #212529; }
    
    .controls { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 20px; 
        align-items: flex-end; 
        margin-bottom: 20px; 
        padding: 20px; 
        background: #f8f9fa; 
        border-radius: 10px; 
        border: 1px solid #e9ecef;
    }
    .controls-highlight { background: #fff8e1; border-color: #ffeeba; }
    
    .control-group { display: flex; flex-direction: column; gap: 5px; }
    .controls label { font-weight: 600; font-size: 13px; color: #555; }
    
    .controls input[type="search"], 
    .controls input[type="number"], 
    .controls select { 
        padding: 10px; 
        border: 1px solid #ccc; 
        border-radius: 6px; 
        font-size: 14px;
        min-width: 200px;
    }
    
    /* Container responsivo para a tabela */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch; /* Suavidade no iOS */
        border-radius: 8px;
        border: 1px solid #eee;
    }

    table { width: 100%; border-collapse: collapse; min-width: 800px; /* Garante largura mínima */ }
    th, td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
    th { background: #f8f9fa; font-size: 13px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
    td { font-size: 14px; color: #333; }
    
    /* Checkbox maior para facilitar toque */
    input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }

    .status-presente { color: #155724; background: #d4edda; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .status-inscrito { color: #856404; background: #fff3cd; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    
    #msgBox { margin-bottom: 20px; padding: 15px; border-radius: 8px; display: none; font-weight: 500; }
    #msgBox.success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    #msgBox.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

    /* --- ESTILOS MOBILE --- */
    @media (max-width: 768px) {
        body { padding: 10px; }
        .card { padding: 15px; }
        
        .topbar { flex-direction: column; align-items: stretch; text-align: center; }
        .topbar h2 { font-size: 20px; margin-bottom: 10px; }
        
        .controls { flex-direction: column; align-items: stretch; gap: 15px; padding: 15px; }
        .control-group { width: 100%; }
        
        .controls input[type="search"], 
        .controls input[type="number"], 
        .controls select { width: 100%; min-width: 0; box-sizing: border-box; }
        
        /* Botões full width no mobile */
        .btn { width: 100%; margin-bottom: 5px; }
        /* Remove margin-bottom do último botão de um grupo */
        .controls .btn { margin-top: 5px; }
        
        /* Ajuste fino na tabela para não ficar colada */
        th, td { padding: 10px; }
    }
</style>
</head>
<body>
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
                <label for="limiteInput">Limite de Participantes (0 = ilimitado)</label>
                <div style="display: flex; gap: 5px;">
                    <input type="number" id="limiteInput" min="0" value="0" style="flex: 1;">
                    <button id="saveLimitBtn" class="btn btn-save" style="width: auto;">Salvar</button>
                </div>
            </div>
            
            <div class="control-group">
                <label style="visibility: hidden; height: 0; margin: 0;">Ações</label> <a href="export_inscricoes.php?id=<?= $eventId ?>" id="exportLink" class="btn btn-export">Exportar CSV</a>
                <a href="validar_manualmente.php?event_id=<?= $eventId ?>" class="btn btn-manual" style="margin-top: 5px;">Validar Manualmente</a>
            </div>
        </div>

        <div class="controls controls-highlight">
            <div class="control-group" style="width: 100%;">
                <label for="bulkAction">Ações em massa para selecionados:</label>
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

<script>
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
    } catch (err) {
        showMessage(err.message, 'error');
        throw err;
    }
}

async function carregarDados() {
    try {
        const res = await fetch(`${API_URL}?action=get_data&event_id=${EVENT_ID}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.erro);
        
        eventNameEl.textContent = `Gerenciar: ${data.evento.nome}`;
        limiteInput.value = data.evento.limite_participantes || 0;
        
        allUsersData = data.usuarios;
        presencasIds = data.presencas_ids; 
        
        renderTabela();
        
    } catch (err) {
        showMessage(err.message, 'error');
        usersTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">${err.message}</td></tr>`;
    }
}

function renderTabela() {
    usersTbody.innerHTML = '';
    const query = searchBox.value.trim().toLowerCase();
    
    const usuariosFiltrados = allUsersData.filter(user => {
        return user.nome.toLowerCase().includes(query) || user.email.toLowerCase().includes(query);
    });

    if (usuariosFiltrados.length === 0) {
        usersTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #666;">Nenhum inscrito encontrado.</td></tr>';
        return;
    }

    usuariosFiltrados.forEach(user => {
        const isPresente = presencasIds.includes(Number(user.id));
        const statusClass = isPresente ? 'status-presente' : 'status-inscrito';
        const statusText = isPresente ? 'Presente' : 'Inscrito';

        const row = document.createElement('tr');
        row.dataset.userId = user.id;
        row.innerHTML = `
            <td style="text-align: center;"><input type="checkbox" class="user-checkbox" value="${user.id}"></td>
            <td><strong>${escapeHtml(user.nome)}</strong></td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.cpf || '-')}</td>
            <td>${escapeHtml(user.turma_nome || 'Externo')}</td>
            <td><span class="${statusClass}">${statusText}</span></td>
        `;
        usersTbody.appendChild(row);
    });
}

function showMessage(msg, type = 'success') {
    msgBox.textContent = msg;
    msgBox.className = type === 'success' ? 'success' : 'error';
    msgBox.style.display = 'block';
    // Scroll para mensagem no mobile
    if (window.innerWidth < 768) {
        msgBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    setTimeout(() => { msgBox.style.display = 'none'; }, 4000);
}
function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function getSelectedUserIds() {
    return Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
}

document.addEventListener('DOMContentLoaded', carregarDados);

saveLimitBtn.addEventListener('click', async () => {
    const formData = new FormData();
    formData.append('limite', limiteInput.value);
    await apiCall('update_limit', formData);
});

searchBox.addEventListener('input', renderTabela);

selectAll.addEventListener('change', (e) => {
    document.querySelectorAll('.user-checkbox').forEach(cb => {
        cb.checked = e.target.checked;
    });
});

applyBulkBtn.addEventListener('click', async () => {
    const action = bulkAction.value;
    const userIds = getSelectedUserIds();

    if (!action) { alert('Selecione uma ação.'); return; }
    if (userIds.length === 0) { alert('Selecione pelo menos um usuário.'); return; }

    if (action === 'remover_inscricao') {
        if (!confirm(`Tem certeza que quer REMOVER ${userIds.length} usuário(s) deste evento? Esta ação não pode ser desfeita.`)) {
            return;
        }
    }
    
    const formData = new FormData();
    userIds.forEach(id => formData.append('user_ids[]', id));
    
    try {
        await apiCall(action, formData);
        await carregarDados(); 
    } catch (err) { /* Erro já foi mostrado */ }
});
</script>
</body>
</html>