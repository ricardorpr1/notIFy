<?php
// permissions.php — Com Sidebar e Header Responsivos
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: telainicio.html'); exit; }

$userRole = intval($_SESSION['role'] ?? 0);
$userPhoto = $_SESSION['foto_url'] ?? 'default.jpg';

if ($userRole !== 2) { die("Acesso negado."); }

$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db"; $DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $stmt = $pdo->query("SELECT id, nome, email, cpf, registro_academico, role FROM usuarios ORDER BY nome ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) { die("Erro de banco."); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
<title>Permissões — notIFy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { margin:0; font-family: 'Inter', sans-serif; background:#f0f2f5; color:#333; overflow-x: hidden; padding-top: 60px; }
  
  /* Padrão Header/Sidebar */
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

  .main-content { padding: 30px; margin-left: 250px; transition: margin 0.3s; max-width: 1100px; }
  
  /* Conteúdo Específico */
  .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
  h2 { margin-top: 0; color: #045c3f; }
  
  #searchInput { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; margin-bottom: 20px; box-sizing: border-box; }
  
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 14px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
  th { background: #f8f9fa; color: #555; text-transform: uppercase; font-size: 13px; font-weight: 700; }
  
  select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; width: 100%; }
  .saveBtn { background: #045c3f; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; }
  .saveBtn:disabled { background: #ccc; }
  
  @media (max-width: 768px) {
      #mobileMenuBtn { display: block; }
      #sidebar { transform: translateX(-100%); width: 260px; }
      #sidebar.active { transform: translateX(0); }
      .main-content { margin-left: 0; padding: 20px 15px; }
      
      /* Card View para Tabela */
      thead { display: none; }
      tr { display: block; margin-bottom: 15px; border: 1px solid #eee; border-radius: 10px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
      td { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #eee; font-size: 14px; }
      td:last-child { border: none; flex-direction: column; align-items: stretch; gap: 10px; margin-top: 10px; }
      td::before { content: attr(data-label); font-weight: 700; color: #666; }
      select { text-align: right; border: none; background: transparent; font-weight: 600; color: #045c3f; width: auto; }
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
    <a href="permissions.php" class="sidebar-btn active">🔐 Permissões</a>
    <a href="gerenciar_cursos.php" class="sidebar-btn">🏫 Gerenciar Cursos</a>
</div>

<div id="userArea">
    <img id="profileImg" src="<?= htmlspecialchars($userPhoto) ?>" alt="Perfil" onclick="location.href='index.php'"/>
</div>

<div class="main-content">
  <div class="card">
    <h2>Gerenciar Permissões</h2>
    <input type="text" id="searchInput" placeholder="Filtrar usuário...">
    
    <table id="usersTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>CPF / RA</th>
          <th>Permissão</th>
          <th>Ação</th>
        </tr>
      </thead>
      <tbody id="usersTbody">
        <?php foreach ($users as $u): ?>
          <tr data-user-id="<?= (int)$u['id'] ?>">
            <td data-label="ID"><?= (int)$u['id'] ?></td>
            <td data-label="Nome"><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
            <td data-label="E-mail"><?= htmlspecialchars($u['email']) ?></td>
            <td data-label="CPF / RA">
                <?= htmlspecialchars($u['cpf']) ?>
                <?= !empty($u['registro_academico']) ? '<br><small>RA: '.htmlspecialchars($u['registro_academico']).'</small>' : '' ?>
            </td>
            <td data-label="Permissão">
              <select class="roleSelect">
                <option value="0" <?= intval($u['role'])===0?'selected':'' ?>>Usuário</option>
                <option value="1" <?= intval($u['role'])===1?'selected':'' ?>>Organizador</option>
                <option value="2" <?= intval($u['role'])===2?'selected':'' ?>>Dev</option>
              </select>
            </td>
            <td data-label="Ação">
                <button class="saveBtn">Salvar</button> 
                <span class="msg"></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        sb.classList.toggle('active');
        bd.style.display = sb.classList.contains('active') ? 'block' : 'none';
    }

    // Lógica da Tabela
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#usersTbody tr');
    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    tableRows.forEach(row => {
        const uid = row.getAttribute('data-user-id');
        const select = row.querySelector('.roleSelect');
        const btn = row.querySelector('.saveBtn');
        const msg = row.querySelector('.msg');
        
        select.addEventListener('change', () => row.style.background = '#fff8e1');

        btn.addEventListener('click', async () => {
            const newRole = parseInt(select.value);
            btn.textContent = '...'; btn.disabled = true;
            try {
                const res = await fetch('update_role.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: uid, role: newRole })
                });
                if(res.ok) { 
                    msg.textContent = 'Ok'; msg.style.color = 'green'; 
                    row.style.background = '';
                } else { msg.textContent = 'Erro'; msg.style.color = 'red'; }
            } catch(e) { msg.textContent = 'Erro'; }
            finally { btn.disabled = false; btn.textContent = 'Salvar'; setTimeout(() => msg.textContent='', 2000); }
        });
    });
</script>
</body>
</html>