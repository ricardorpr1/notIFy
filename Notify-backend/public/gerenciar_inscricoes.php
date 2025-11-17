<?php
// gerenciar_inscricoes.php
session_start();
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) die("ID do evento inválido.");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Gerenciar Inscrições — notIFy</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f6f7fb; margin: 20px; }
    .card { background: #fff; padding: 18px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.06); max-width: 1200px; margin: 0 auto; }
    .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
    .topbar h2 { margin: 0; }
    .btn { padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; color: #fff; text-decoration: none; font-size: 14px; }
    .btn-back { background: #6c757d; }
    .btn-export { background: #007bff; }
    .btn-save { background: #28a745; }
    .btn-action { background: #17a2b8; }
    .btn-danger { background: #dc3545; }
    .btn-manual { background: #ffc107; color: #212529; }
    .controls { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin: 20px 0; padding: 10px; background: #f9f9f9; border-radius: 8px; }
    .controls label { font-weight: bold; font-size: 14px; }
    .controls input[type="search"], .controls input[type="number"], .controls select { padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
    th { background: #fafafa; font-size: 14px; }
    td { font-size: 13px; }
    .status-presente { color: green; font-weight: bold; }
    .status-inscrito { color: #555; }
    #msgBox { margin-top: 10px; padding: 10px; border-radius: 6px; display: none; }
    #msgBox.success { background: #e6f7ea; color: #0b6b33; }
    #msgBox.error { background: #fdecea; color: #a94442; }
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
            <div>
                <label for="searchBox">Pesquisar por nome ou e-mail:</label><br>
                <input type="search" id="searchBox" placeholder="Pesquisar...">
            </div>
            <div>
                <label for="limiteInput">Limite de Participantes (0 = sem limite)</label><br>
                <input type="number" id="limiteInput" min="0" value="0">
                <button id="saveLimitBtn" class="btn btn-save">Salvar Limite</button>
            </div>
            <div>
                <label style="visibility: hidden;">Ações Extras</label><br>
                <a href="export_inscricoes.php?id=<?= $eventId ?>" id="exportLink" class="btn btn-export">Exportar Lista</a>
                <a href="validar_manualmente.php?event_id=<?= $eventId ?>" class="btn btn-manual">Validar Presença Manualmente</a>
            </div>
        </div>
        <div class="controls" style="background: #fff8e1;">
            <div>
                <label for="bulkAction">Ações em massa para selecionados:</label><br>
                <select id="bulkAction">
                    <option value="">Selecione uma ação...</option>
                    <option value="marcar_presenca">Marcar Presença</option>
                    <option value="remover_presenca">Remover Presença</option>
                    <option value="remover_inscricao" style="color: red;">Remover Inscrição</option>
                </select>
                <button id="applyBulkBtn" class="btn btn-action">Aplicar</button>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>CPF</th>
                    <th>Turma</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="usersTbody">
                <tr><td colspan="6" style="text-align: center;">Carregando...</td></tr>
            </tbody>
        </table>
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
        eventNameEl.textContent = `Gerenciar Inscrições: ${data.evento.nome}`;
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
        usersTbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum inscrito encontrado.</td></tr>';
        return;
    }
    usuariosFiltrados.forEach(user => {
        // --- CORREÇÃO AQUI (String vs Número) ---
        // user.id pode ser string ("12") e presencasIds é array de número [12]
        // Number(user.id) garante que a comparação funcione.
        const isPresente = presencasIds.includes(Number(user.id));
        // --- FIM DA CORREÇÃO ---
        const statusClass = isPresente ? 'status-presente' : 'status-inscrito';
        const statusText = isPresente ? 'Presente' : 'Inscrito';
        
        const row = document.createElement('tr');
        row.dataset.userId = user.id;
        row.innerHTML = `
            <td><input type="checkbox" class="user-checkbox" value="${user.id}"></td>
            <td>${escapeHtml(user.nome)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.cpf || 'N/A')}</td>
            <td>${escapeHtml(user.turma_nome || 'Externo')}</td>
            <td><span class="${statusClass}">${statusText}</span></td>
        `;
        usersTbody.appendChild(row);
    });
}
function showMessage(msg, type = 'success') {
    msgBox.textContent = msg;
    msgBox.className = type === 'success' ? 'msg-box success' : 'msg-box error';
    msgBox.style.display = 'block';
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