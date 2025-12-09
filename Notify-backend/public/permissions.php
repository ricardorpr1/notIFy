<?php
// permissions.php - página de administração de roles (apenas DEV)
session_start();

// exige login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}

// só DEV (role == 2)
$role = intval($_SESSION['role'] ?? 0);
if ($role !== 2) {
    http_response_code(403);
    echo "Acesso negado. Somente contas DEV podem acessar esta página.";
    exit;
}

// DB config
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro de conexão com o banco.";
    exit;
}

// buscar usuários
try {
    $stmt = $pdo->query("SELECT id, nome, email, cpf, registro_academico, role FROM usuarios ORDER BY nome ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro ao buscar usuários.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1.0, user-scalable=no" />
<title>Permissões — notIFy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; margin: 0; padding: 20px; background: #f0f2f5; color: #333; }
  
  .card { 
      background: #fff; 
      padding: 24px; 
      border-radius: 12px; 
      box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
      max-width: 1100px; 
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
  
  .btn-back { 
      background: #6c757d; color: #fff; 
      padding: 10px 16px; border: none; 
      border-radius: 8px; cursor: pointer; 
      font-weight: 600; text-decoration: none;
      font-size: 14px;
  }
  .status { margin-left: 10px; font-size: 13px; color: #666; font-weight: 500; }

  /* Filtro de Pesquisa */
  .search-container { margin-bottom: 20px; }
  #searchInput {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
      box-sizing: border-box;
  }

  /* Tabela Desktop */
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { padding: 14px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
  th { background: #f8f9fa; font-weight: 700; color: #555; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
  td { font-size: 14px; }
  
  /* Controles dentro da tabela */
  select { 
      padding: 8px; 
      border-radius: 6px; 
      border: 1px solid #ccc; 
      font-family: inherit;
      width: 100%;
      max-width: 160px;
  }
  .saveBtn { 
      background: #045c3f; color: #fff; 
      border: none; padding: 8px 14px; 
      border-radius: 6px; cursor: pointer; 
      font-weight: 600; transition: 0.2s;
  }
  .saveBtn:hover { background: #05774f; }
  .saveBtn:disabled { background: #ccc; cursor: not-allowed; }
  .msg { font-size: 12px; margin-left: 8px; display: inline-block; min-width: 60px; }

  /* === MODO MOBILE (CARD VIEW) === */
  @media (max-width: 768px) {
      body { padding: 10px; }
      .card { padding: 15px; }
      .topbar { flex-direction: column; align-items: stretch; text-align: center; }
      .topbar div { display: flex; flex-direction: column; gap: 10px; }
      
      /* Esconde o cabeçalho da tabela */
      thead { display: none; }
      
      /* Transforma linhas em blocos (cards) */
      tr { 
          display: block; 
          margin-bottom: 15px; 
          border: 1px solid #e0e0e0; 
          border-radius: 10px; 
          background: #fff;
          box-shadow: 0 2px 5px rgba(0,0,0,0.03);
          padding: 15px;
      }
      
      /* Transforma células em linhas flex */
      td { 
          display: flex; 
          justify-content: space-between; 
          align-items: center; 
          padding: 8px 0; 
          border-bottom: 1px dashed #eee;
          font-size: 14px;
      }
      
      td:last-child { border-bottom: none; flex-direction: column; gap: 10px; margin-top: 10px; }
      
      /* Adiciona labels via data-label */
      td::before {
          content: attr(data-label);
          font-weight: 700;
          color: #555;
          margin-right: 15px;
          min-width: 80px;
      }

      /* Ajustes de controles no mobile */
      select { max-width: 100%; text-align: right; border: none; background: transparent; font-weight: 600; color: #045c3f; }
      .saveBtn { width: 100%; padding: 12px; font-size: 16px; margin-top: 5px; }
      
      /* Ocultar ID no mobile para economizar espaço */
      td[data-label="ID"] { display: none; }
  }
</style>
</head>
<body>
  <div class="card">
    <div class="topbar">
      <h2>Gerenciar Permissões</h2>
      <div>
        <span class="status">Logado como: <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'DEV') ?></strong></span>
        <a href="index.php" class="btn-back">Voltar ao Calendário</a>
      </div>
    </div>

    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Buscar por nome, email ou CPF...">
    </div>

    <table id="usersTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>CPF / RA</th>
          <th>Permissão Atual</th>
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
                <?= !empty($u['registro_academico']) ? '<br><small style="color:#666">RA: '.htmlspecialchars($u['registro_academico']).'</small>' : '' ?>
            </td>
            <td data-label="Permissão">
              <select class="roleSelect">
                <option value="0" <?= intval($u['role'])===0 ? 'selected' : '' ?>>Usuário (0)</option>
                <option value="1" <?= intval($u['role'])===1 ? 'selected' : '' ?>>Organizador (1)</option>
                <option value="2" <?= intval($u['role'])===2 ? 'selected' : '' ?>>Dev (2)</option>
              </select>
            </td>
            <td data-label="Ação">
                <button class="saveBtn">Salvar Alteração</button> 
                <span class="msg"></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Filtro de Pesquisa
  const searchInput = document.getElementById('searchInput');
  const tableRows = document.querySelectorAll('#usersTbody tr');

  searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase();
      tableRows.forEach(row => {
          const text = row.innerText.toLowerCase();
          row.style.display = text.includes(term) ? '' : 'none';
      });
  });

  // Lógica de Salvar
  tableRows.forEach(row => {
    const uid = row.getAttribute('data-user-id');
    const select = row.querySelector('.roleSelect');
    const btn = row.querySelector('.saveBtn');
    const msg = row.querySelector('.msg');

    // Detectar mudança para destacar
    select.addEventListener('change', () => {
        row.style.background = '#fff8e1'; // Highlight amarelo
    });

    btn.addEventListener('click', async () => {
      const newRole = parseInt(select.value, 10);
      
      const originalText = btn.textContent;
      btn.textContent = 'Salvando...';
      btn.disabled = true;
      msg.textContent = '';
      
      try {
        const res = await fetch('update_role.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: parseInt(uid,10), role: newRole })
        });
        
        const text = await res.text();
        let json = {};
        try { json = text ? JSON.parse(text) : {}; } catch(e) { json = { erro: 'Resposta inválida' }; }
        
        if (res.ok) {
          msg.textContent = 'Salvo!';
          msg.style.color = 'green';
          row.style.background = 'white'; // Remove highlight
        } else {
          msg.textContent = json.erro || 'Erro';
          msg.style.color = 'crimson';
        }
      } catch (err) {
        console.error(err);
        msg.textContent = 'Erro rede';
        msg.style.color = 'crimson';
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
        setTimeout(() => { msg.textContent = ''; }, 3000);
      }
    });
  });
});
</script>
</body>
</html>