<?php
// gerenciar_cursos.php — Com Sidebar e Header Responsivos
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: telainicio.html'); exit; }

$userRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

if ($userRole !== 2) { die("Acesso negado."); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
<title>Gerenciar Cursos e Turmas</title>
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
    .sidebar-btn.active { background: #03442e; border: 1px solid #022c1e; }
    #sidebarBackdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1900; }

    #userArea { position: fixed; top: 8px; right: 15px; z-index: 3100; display: flex; gap: 10px; align-items: center; }
    #profileImg { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; cursor: pointer; }

    .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 1000px; }
    
    .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
    .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    h2 { margin-top: 0; color: #333; }
    
    input, select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
    label { font-weight: 600; margin-top: 10px; display: block; }
    
    .btn { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: bold; width: 100%; margin-top: 15px; }
    .btn-green { background: #228b22; }
    .btn-red { background: #d9534f; width: auto; font-size: 12px; padding: 6px 10px; margin: 0; }
    .btn-blue { background: #007bff; width: auto; margin: 0; }

    .curso-item { border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; }
    .curso-header { background: #f9f9f9; padding: 12px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .curso-header h3 { margin: 0; font-size: 16px; }
    .curso-body { padding: 12px; }
    .turma-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #eee; font-size: 14px; }
    .form-add-turma { display: flex; gap: 10px; margin-top: 10px; }
    
    @media (max-width: 800px) {
        #mobileMenuBtn { display: block; }
        #sidebar { transform: translateX(-100%); width: 260px; }
        #sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 20px 15px; }
        .grid { grid-template-columns: 1fr; }
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
    <a href="adicionarevento.php" class="sidebar-btn">➕ Adicionar Evento</a>
    <a href="permissions.php" class="sidebar-btn">🔐 Permissões</a>
    <a href="gerenciar_cursos.php" class="sidebar-btn active">🏫 Gerenciar Cursos</a>
</div>

<div id="userArea">
    <img id="profileImg" src="<?= htmlspecialchars($userPhoto) ?>" alt="Perfil" onclick="location.href='index.php'"/>
</div>

<div class="main-content">
    <div id="msgGlobal" style="display:none; padding:10px; margin-bottom:15px; border-radius:6px; background:#e6f7ea; color:green; text-align:center;"></div>

    <div class="grid">
        <div class="card">
            <h2>Novo Curso</h2>
            <form id="formNovoCurso">
                <label>Nome (Ex: Informática)</label>
                <input type="text" id="curso_nome" required>
                <label>Sigla (3 letras)</label>
                <input type="text" id="curso_sigla" required maxlength="3" minlength="3">
                <label>Nível</label>
                <select id="curso_nivel" required>
                    <option value="Integrado">Técnico Integrado</option>
                    <option value="Graduação">Graduação</option>
                </select>
                <button type="submit" class="btn btn-green">Criar Curso</button>
            </form>
        </div>

        <div class="card">
            <h2>Cursos Existentes</h2>
            <div id="listaCursos">Carregando...</div>
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

    const API_URL = 'api_gerenciar_cursos.php';
    const listaCursosEl = document.getElementById('listaCursos');
    const msg = document.getElementById('msgGlobal');

    async function apiCall(action, body) {
        try {
            const res = await fetch(API_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...body })
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.erro || 'Erro');
            return json;
        } catch (err) { alert(err.message); throw err; }
    }

    function renderCursos(cursos) {
        listaCursosEl.innerHTML = '';
        if (cursos.length === 0) { listaCursosEl.innerHTML = '<p>Nenhum curso.</p>'; return; }
        cursos.forEach(curso => {
            let turmasHtml = curso.turmas.map(t => `
                <div class="turma-item">
                    <span>${t.nome_exibicao} (${t.ano}º ano)</span>
                    <button class="btn btn-red btnDeleteTurma" data-id="${t.id}">Excluir</button>
                </div>`).join('');
            
            const div = document.createElement('div');
            div.className = 'curso-item';
            div.innerHTML = `
                <div class="curso-header">
                    <h3>${curso.nome} (${curso.sigla})</h3>
                    <button class="btn btn-red btnDeleteCurso" data-id="${curso.id}">X</button>
                </div>
                <div class="curso-body">
                    ${turmasHtml}
                    <form class="form-add-turma" data-cid="${curso.id}">
                        <input type="text" class="t_nome" placeholder="Turma" required>
                        <input type="number" class="t_ano" placeholder="Ano" min="1" max="5" required style="width:60px;">
                        <button class="btn btn-blue">+</button>
                    </form>
                </div>`;
            listaCursosEl.appendChild(div);
        });
    }

    async function carregar() {
        try { const data = await apiCall('get_all', {}); renderCursos(data); } catch(e){}
    }

    document.getElementById('formNovoCurso').addEventListener('submit', async (e) => {
        e.preventDefault();
        await apiCall('create_curso', { 
            nome: document.getElementById('curso_nome').value, 
            sigla: document.getElementById('curso_sigla').value, 
            nivel: document.getElementById('curso_nivel').value 
        });
        e.target.reset(); carregar();
    });

    listaCursosEl.addEventListener('click', async (e) => {
        if(e.target.classList.contains('btnDeleteCurso')) {
            if(confirm('Excluir curso?')) { await apiCall('delete_curso', { curso_id: e.target.dataset.id }); carregar(); }
        }
        if(e.target.classList.contains('btnDeleteTurma')) {
            if(confirm('Excluir turma?')) { await apiCall('delete_turma', { turma_id: e.target.dataset.id }); carregar(); }
        }
    });

    listaCursosEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        if(e.target.classList.contains('form-add-turma')) {
            await apiCall('create_turma', { 
                curso_id: e.target.dataset.cid, 
                nome_exibicao: e.target.querySelector('.t_nome').value, 
                ano: e.target.querySelector('.t_ano').value 
            });
            carregar();
        }
    });

    document.addEventListener('DOMContentLoaded', carregar);
</script>
</body>
</html>