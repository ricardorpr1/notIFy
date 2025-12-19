<?php
// adicionarevento.php — Com Sidebar e Header Responsivos
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: telainicio.html'); exit; }

$userId = intval($_SESSION['usuario_id']);
$userRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

// Verifica permissão (Role 1 ou 2)
if ($userRole < 1) { die("Acesso negado. Apenas organizadores."); }

$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db"; $DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

$cursos_map = [];
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $cursos = $pdo->query("SELECT id, nome, sigla FROM cursos ORDER BY nome")->fetchAll();
    $turmas = $pdo->query("SELECT id, curso_id, nome_exibicao FROM turmas ORDER BY ano, nome_exibicao")->fetchAll();
    foreach ($cursos as $curso) { $cursos_map[$curso['id']] = $curso; $cursos_map[$curso['id']]['turmas'] = []; }
    foreach ($turmas as $turma) { if (isset($cursos_map[$turma['curso_id']])) { $cursos_map[$turma['curso_id']]['turmas'][] = $turma; } }
} catch (PDOException $e) { die("Erro banco: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Adicionar Evento - notIFy</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    /* Estilos Globais idênticos aos anteriores */
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

    .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 800px; }

    /* Formulário */
    form { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    h2 { margin-top: 0; color: #045c3f; }
    label { display: block; margin-top: 15px; font-weight: 600; color: #444; }
    input[type="text"], input[type="datetime-local"], input[type="number"], textarea, input[type="file"] { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-family: inherit; }
    button[type="submit"] { width: 100%; margin-top: 25px; padding: 14px; border: none; border-radius: 8px; background-color: #228B22; color: #fff; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.2s; }
    button[type="submit"]:hover { background-color: #1c731c; }

    .note { font-size: 12px; color: #777; font-weight: normal; }
    .turmas-container { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-top: 5px; max-height: 250px; overflow-y: auto; background: #fafafa; }
    .turma-checkbox { margin-right: 15px; display: inline-flex; align-items: center; padding: 5px 0; }
    .turma-checkbox input { width: auto; margin: 0 8px 0 0; transform: scale(1.2); }

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
    <a href="adicionarevento.php" class="sidebar-btn active">➕ Adicionar Evento</a>
    <?php if ($userRole == 2): ?>
        <a href="permissions.php" class="sidebar-btn">🔐 Permissões</a>
        <a href="gerenciar_cursos.php" class="sidebar-btn">🏫 Gerenciar Cursos</a>
    <?php endif; ?>
</div>

<div id="userArea">
    <img id="profileImg" src="<?= htmlspecialchars($userPhoto) ?>" alt="Perfil" onclick="location.href='index.php'"/>
</div>

<div class="main-content">
  <form id="eventoForm">
    <h2>Criar Novo Evento</h2>
    <label>Nome do Evento:</label>
    <input type="text" id="nome" name="nome" required>
    <label>Descrição:</label>
    <textarea id="descricao" name="descricao" rows="3"></textarea>
    <label>Local:</label>
    <input type="text" id="local" name="local">
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label>Início:</label>
            <input type="datetime-local" id="data_hora_inicio" name="data_hora_inicio" required>
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label>Fim:</label>
            <input type="datetime-local" id="data_hora_fim" name="data_hora_fim" required>
        </div>
    </div>
    
    <label>Imagem de Capa <span class="note">(Será cortada para 3:1)</span></label>
    <input type="file" id="capa_upload" name="capa_upload" accept="image/*">
    <label>Imagem Completa <span class="note">(Exibida no modal)</span></label>
    <input type="file" id="imagem_completa_upload" name="imagem_completa_upload" accept="image/*">
    <label>Limite de Participantes (0 = ilimitado):</label>
    <input type="number" id="limite_participantes" name="limite_participantes">

    <label>Turmas Permitidas</label>
    <div class="turmas-container">
        <?php foreach ($cursos_map as $curso): ?>
            <div class="turma-curso-grupo">
                <strong style="color:#0056b3;"><?= htmlspecialchars($curso['nome']) ?></strong><br>
                <?php if (empty($curso['turmas'])): ?>
                    <small>Nenhuma turma cadastrada</small>
                <?php else: ?>
                    <?php foreach ($curso['turmas'] as $turma): ?>
                        <label class="turma-checkbox">
                            <input type="checkbox" name="turmas_permitidas[]" value="<?= $turma['id'] ?>">
                            <?= htmlspecialchars($turma['nome_exibicao']) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <hr style="border:0; border-top:1px dashed #ccc; margin: 10px 0;">
        <label class="turma-checkbox">
            <input type="checkbox" name="publico_externo" value="1">
            <strong>Público Externo (Não-alunos)</strong>
        </label>
    </div>
    <button type="submit" id="submitBtn">Criar Evento</button>
  </form>
</div>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        sb.classList.toggle('active');
        bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
    }

    document.getElementById("eventoForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      btn.disabled = true; btn.textContent = 'Enviando...';
      const fd = new FormData(e.target);
      
      try {
        const res = await fetch("create_event.php", { method: "POST", body: fd });
        const json = await res.json();
        if (res.ok) {
          alert(json.mensagem || "Sucesso!");
          window.location.href = "index.php";
        } else { alert(json.erro || "Erro."); }
      } catch (err) { alert("Erro conexão."); } 
      finally { btn.disabled = false; btn.textContent = 'Criar Evento'; }
    });
</script>
</body>
</html>