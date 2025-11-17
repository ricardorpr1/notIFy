<?php
// validar_manualmente.php
session_start();
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) die("ID do evento inválido.");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Validar Presença Manualmente</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f6f7fb; margin: 20px; }
    .card { background: #fff; padding: 18px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.06); max-width: 1200px; margin: 0 auto; }
    .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
    .topbar h2 { margin: 0; }
    .btn { padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; color: #fff; text-decoration: none; font-size: 14px; }
    .btn-back { background: #6c757d; }
    .btn-action { background: #28a745; }
    .controls { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin: 20px 0; padding: 10px; background: #f9f9f9; border-radius: 8px; }
    .controls label { font-weight: bold; font-size: 14px; }
    .controls input[type="search"] { padding: 8px; border: 1px solid #ccc; border-radius: 5px; width: 300px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
    th { background: #fafafa; font-size: 14px; }
    td { font-size: 13px; }
    tr.is-present td { background: #f0fdf4; color: #555; }
    tr.is-present input[type="checkbox"] { display: none; }
    #msgBox { margin-top: 10px; padding: 10px; border-radius: 6px; display: none; }
    #msgBox.success { background: #e6f7ea; color: #0b6b33; }
    #msgBox.error { background: #fdecea; color: #a94442; }
</style>
</head>
<body>
    <div class="card">
        <div class="topbar">
            <h2 id="eventName">Validar Presença Manualmente</h2>
            <a href="gerenciar_inscricoes.php?event_id=<?= $eventId ?>" class="btn btn-back">Voltar ao Gerenciador</a>
        </div>
        <div id="msgBox"></div>
        <div class="controls">
            <div>
                <label for="searchBox">Filtrar por nome, e-mail, CPF, RA, curso ou turma:</label><br>
                <input type="search" id="searchBox" placeholder="Pesquisar em todos os usuários...">
            </div>
            <div>
                <button id="applyBulkBtn" class="btn btn-action">Validar Presença dos Selecionados</button>
            </div>
        </div>
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

<script>
const EVENT_ID = <?= $eventId; ?>;
const API_URL = 'api_gerenciar_inscricoes.php';
const eventNameEl = document.getElementById('eventName');
const usersTbody = document.getElementById('usersTbody');
const searchBox = document.getElementById('searchBox');
const selectAll = document.getElementById('selectAll');
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
        const res = await fetch(`${API_URL}?action=get_all_users_for_manual_add&event_id=${EVENT_ID}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.erro);
        eventNameEl.textContent = `Validar Presença: ${data.evento_nome}`;
        allUsersData = data.all_users;
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
        if (!query) return true; 
        const searchString = [
            user.nome, user.email, user.cpf, user.registro_academico,
            user.turma_nome, user.curso_sigla
        ].join(' ').toLowerCase();
        return searchString.includes(query);
    });

    if (usuariosFiltrados.length === 0) {
        usersTbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum usuário encontrado.</td></tr>';
        return;
    }

    usuariosFiltrados.forEach(user => {
        // --- CORREÇÃO AQUI (String vs Número) ---
        const isPresente = presencasIds.includes(Number(user.id));
        // --- FIM DA CORREÇÃO ---
        const rowClass = isPresente ? 'is-present' : '';

        const row = document.createElement('tr');
        row.dataset.userId = user.id;
        row.className = rowClass;
        
        row.innerHTML = `
            <td><input type="checkbox" class="user-checkbox" value="${user.id}" ${isPresente ? 'disabled' : ''}></td>
            <td>${escapeHtml(user.nome)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.cpf || 'N/A')} / ${escapeHtml(user.registro_academico || 'N/A')}</td>
            <td>${escapeHtml(user.curso_sigla || 'N/A')} / ${escapeHtml(user.turma_nome || 'N/A')}</td>
            <td>${isPresente ? 'Presente' : 'Ausente'}</td>
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
    return Array.from(document.querySelectorAll('.user-checkbox:checked:not(:disabled)')).map(cb => cb.value);
}
document.addEventListener('DOMContentLoaded', carregarDados);
searchBox.addEventListener('input', renderTabela);
selectAll.addEventListener('change', (e) => {
    document.querySelectorAll('.user-checkbox:not(:disabled)').forEach(cb => {
        cb.checked = e.target.checked;
    });
});
applyBulkBtn.addEventListener('click', async () => {
    const userIds = getSelectedUserIds();
    if (userIds.length === 0) {
        alert('Selecione pelo menos um usuário (que não esteja já presente).');
        return;
    }
    if (!confirm(`Tem certeza que quer marcar presença para ${userIds.length} usuário(s)?`)) {
        return;
    }
    const formData = new FormData();
    userIds.forEach(id => formData.append('user_ids[]', id));
    try {
        await apiCall('add_manual_presence', formData);
        await carregarDados(); 
    } catch (err) { /* Erro já foi mostrado */ }
});
</script>
</body>
</html>