<?php
// gerenciar_cursos.php
session_start();

// 1. Requer Login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
// 2. Requer DEV (Role 2)
if (intval($_SESSION['role'] ?? 0) !== 2) {
    die("Acesso negado. Apenas DEVs.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Gerenciar Cursos e Turmas</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f6f7fb; margin: 0; padding: 20px; }
    .container { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
    .card { background: #fff; padding: 18px; border-radius: 10px; box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06); }
    h2, h3 { color: #333; }
    label { display: block; margin-top: 10px; font-weight: bold; }
    input, select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
    .btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: bold; }
    .btn-green { background: #228b22; } .btn-blue { background: #007bff; }
    .btn-red { background: #d9534f; font-size: 12px; padding: 4px 8px; }
    .btn-back { background: #6c757d; text-decoration: none; display: inline-block; margin-bottom: 15px; }
    
    .curso-item { border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; }
    .curso-header { background: #f9f9f9; padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .curso-header h3 { margin: 0; }
    .curso-body { padding: 10px; }
    .turma-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #f9f9f9; }
    .turma-item:last-child { border: none; }
    .form-add-turma { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc; display: flex; gap: 10px; }
    .form-add-turma input { width: 60%; }
    .form-add-turma select { width: 30%; }
    .form-add-turma button { width: 10%; }
    .msg { padding: 10px; border-radius: 6px; margin-top: 10px; text-align: center; }
    .msg-success { background: #e6f7ea; color: #0b6b33; }
    .msg-error { background: #fdecea; color: #a94442; }

    @media (max-width: 800px) { .container { grid-template-columns: 1fr; } }
</style>
</head>
<body>

    <a href="index.php" class="btn btn-back">← Voltar ao Calendário</a>
    <div id="msgGlobal" class="msg" style="display:none;"></div>

    <div class="container">
        <div class="card">
            <h2>Novo Curso</h2>
            <form id="formNovoCurso">
                <label for="curso_nome">Nome do Curso (Ex: Informática)</label>
                <input type="text" id="curso_nome" required>

                <label for="curso_sigla">Sigla (3 letras. Ex: INF)</label>
                <input type="text" id="curso_sigla" required maxlength="3" minlength="3">

                <label for="curso_nivel">Nível</label>
                <select id="curso_nivel" required>
                    <option value="Integrado">Técnico Integrado</option>
                    <option value="Graduação">Graduação</option>
                </select>

                <button type="submit" class="btn btn-green" style="margin-top: 15px;">Criar Curso</button>
            </form>
        </div>

        <div class="card">
            <h2>Cursos e Turmas Existentes</h2>
            <div id="listaCursos">
                </div>
        </div>
    </div>

<script>
const API_URL = 'api_gerenciar_cursos.php';
const listaCursosEl = document.getElementById('listaCursos');
const msgGlobalEl = document.getElementById('msgGlobal');

// --- Funções de Comunicação com API ---

async function apiCall(action, body) {
    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...body })
        });
        const json = await res.json();
        if (!res.ok) {
            throw new Error(json.erro || 'Erro desconhecido');
        }
        return json;
    } catch (err) {
        showGlobalMessage(err.message, 'error');
        throw err; // Repassa o erro para quem chamou
    }
}

// --- Funções de Renderização ---

function showGlobalMessage(msg, type = 'success') {
    msgGlobalEl.textContent = msg;
    msgGlobalEl.className = `msg msg-${type}`;
    msgGlobalEl.style.display = 'block';
    setTimeout(() => { msgGlobalEl.style.display = 'none'; }, 3000);
}

function renderCursos(cursos) {
    listaCursosEl.innerHTML = ''; // Limpa a lista
    if (cursos.length === 0) {
        listaCursosEl.innerHTML = '<p>Nenhum curso cadastrado.</p>';
        return;
    }

    cursos.forEach(curso => {
        const cursoEl = document.createElement('div');
        cursoEl.className = 'curso-item';
        
        let turmasHtml = '';
        if (curso.turmas.length > 0) {
            turmasHtml = curso.turmas.map(t => `
                <div class="turma-item" data-id="${t.id}">
                    <span>${escapeHtml(t.nome_exibicao)} (Ano: ${t.ano})</span>
                    <button class="btn btn-red btnDeleteTurma" data-id="${t.id}">Excluir</button>
                </div>
            `).join('');
        } else {
            turmasHtml = '<p style="font-size:14px; color:#888; padding: 0 8px;">Nenhuma turma cadastrada.</p>';
        }

        cursoEl.innerHTML = `
            <div class="curso-header">
                <h3>${escapeHtml(curso.nome)} (${escapeHtml(curso.sigla)}) <small style="font-weight:normal; color: #555;">[${curso.nivel}]</small></h3>
                <button class="btn btn-red btnDeleteCurso" data-id="${curso.id}">Excluir Curso</button>
            </div>
            <div class="curso-body">
                <h4>Turmas:</h4>
                ${turmasHtml}
                <form class="form-add-turma" data-curso-id="${curso.id}">
                    <input type="text" placeholder="Nome da Turma (Ex: INF 1A)" class="turma_nome" required>
                    <select class="turma_ano" required>
                        <option value="">Ano</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                    <button type="submit" class="btn btn-blue" style="width: auto;">Add</button>
                </form>
            </div>
        `;
        listaCursosEl.appendChild(cursoEl);
    });
}

function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// --- Carregamento Inicial ---

async function carregarTudo() {
    try {
        const data = await apiCall('get_all', {});
        renderCursos(data);
    } catch (err) {
        // Erro já foi mostrado pela apiCall
    }
}

// --- Listeners de Eventos ---

// Listener para Criar Novo Curso
document.getElementById('formNovoCurso').addEventListener('submit', async (e) => {
    e.preventDefault();
    const nome = document.getElementById('curso_nome').value;
    const sigla = document.getElementById('curso_sigla').value;
    const nivel = document.getElementById('curso_nivel').value;
    
    try {
        await apiCall('create_curso', { nome, sigla, nivel });
        showGlobalMessage('Curso criado!', 'success');
        e.target.reset(); // Limpa o formulário
        carregarTudo(); // Recarrega a lista
    } catch (err) {}
});

// Listeners dinâmicos (para botões de excluir e add turma)
listaCursosEl.addEventListener('click', async (e) => {
    // 1. Excluir Curso
    if (e.target.classList.contains('btnDeleteCurso')) {
        const id = e.target.dataset.id;
        if (!confirm('Tem certeza que quer excluir este CURSO? Todas as suas turmas (e alunos nelas) serão perdidos!')) return;
        try {
            await apiCall('delete_curso', { curso_id: id });
            showGlobalMessage('Curso excluído.', 'success');
            carregarTudo();
        } catch (err) {}
    }
    
    // 2. Excluir Turma
    if (e.target.classList.contains('btnDeleteTurma')) {
        const id = e.target.dataset.id;
        if (!confirm('Tem certeza que quer excluir esta TURMA?')) return;
        try {
            await apiCall('delete_turma', { turma_id: id });
            showGlobalMessage('Turma excluída.', 'success');
            carregarTudo();
        } catch (err) {}
    }
});

// Listener para Adicionar Turma (usa 'submit' no container)
listaCursosEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (e.target.classList.contains('form-add-turma')) {
        const form = e.target;
        const curso_id = form.dataset.cursoId;
        const nome_exibicao = form.querySelector('.turma_nome').value;
        const ano = form.querySelector('.turma_ano').value;
        
        try {
            await apiCall('create_turma', { curso_id, nome_exibicao, ano });
            showGlobalMessage('Turma adicionada!', 'success');
            carregarTudo();
        } catch (err) {}
    }
});


// --- Inicialização ---
document.addEventListener('DOMContentLoaded', carregarTudo);
</script>
</body>
</html>