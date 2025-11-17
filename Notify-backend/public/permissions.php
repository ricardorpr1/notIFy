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

// DB config - ajuste se preciso
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

// buscar usuários ordenados por id
try {
    $stmt = $pdo->query("SELECT id, nome, email, cpf, registro_academico, role FROM usuarios ORDER BY id ASC");
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
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Permissões — notIFy</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f6f7fb}
  .card{background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,0.06);max-width:1000px;margin:0 auto}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
  th{background:#fafafa}
  select{padding:6px;border-radius:6px;border:1px solid #ccc}
  .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
  .btn-back{background:#6c757d;color:#fff;padding:8px 12px;border:none;border-radius:6px;cursor:pointer}
  .status{margin-left:8px;font-size:13px;color:#555}
</style>
</head>
<body>
  <div class="card">
    <div class="topbar">
      <h2>Gerenciar permissões de usuários</h2>
      <div>
        <button class="btn-back" onclick="location.href='index.php'">Voltar</button>
        <span class="status">Logado como: <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'DEV') ?></span>
      </div>
    </div>

    <p>Altere o papel (role) de cada usuário. 0 = Usuário, 1 = Organizador, 2 = Dev.</p>

    <table>
      <thead>
        <tr><th>ID</th><th>Nome</th><th>E-mail</th><th>CPF</th><th>RA</th><th>Permissão</th><th>Ação</th></tr>
      </thead>
      <tbody id="usersTbody">
        <?php foreach ($users as $u): ?>
          <tr data-user-id="<?= (int)$u['id'] ?>">
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['nome']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['cpf']) ?></td>
            <td><?= htmlspecialchars($u['registro_academico']) ?></td>
            <td>
              <select class="roleSelect">
                <option value="0" <?= intval($u['role'])===0 ? 'selected' : '' ?>>Usuário (0)</option>
                <option value="1" <?= intval($u['role'])===1 ? 'selected' : '' ?>>Organizador (1)</option>
                <option value="2" <?= intval($u['role'])===2 ? 'selected' : '' ?>>Dev (2)</option>
              </select>
            </td>
            <td><button class="saveBtn">Salvar</button> <span class="msg" style="margin-left:8px"></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = document.querySelectorAll('#usersTbody tr');
  rows.forEach(row => {
    const uid = row.getAttribute('data-user-id');
    const select = row.querySelector('.roleSelect');
    const btn = row.querySelector('.saveBtn');
    const msg = row.querySelector('.msg');

    btn.addEventListener('click', async () => {
      const newRole = parseInt(select.value, 10);
      msg.textContent = 'Salvando...';
      msg.style.color = '#333';
      btn.disabled = true;
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
          msg.textContent = 'Salvo';
          msg.style.color = 'green';
          // se o usuário que alteramos for o logado e caiu para role!=2, não aplicamos mudança na sessão aqui
        } else {
          msg.textContent = json.erro || 'Erro';
          msg.style.color = 'crimson';
        }
      } catch (err) {
        console.error(err);
        msg.textContent = 'Erro de rede';
        msg.style.color = 'crimson';
      } finally {
        btn.disabled = false;
        setTimeout(()=>msg.textContent='', 2500);
      }
    });
  });
});
</script>
</body>
</html>
