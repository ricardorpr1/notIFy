<?php
// login.php - formulário + processamento unificados
session_start();

// Se já logado, redireciona para index
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Config DB - ajuste se necessário
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

$errorMsg = null;
$infoMsg = null;

// Processamento do POST (login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $errorMsg = 'Preencha e-mail e senha.';
    } else {
        // Conectar ao DB
        try {
            $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                           $DB_USER, $DB_PASS, [
                             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                             PDO::ATTR_EMULATE_PREPARES => false
                           ]);
        } catch (PDOException $e) {
            $errorMsg = 'Erro de conexão com o banco. Tente novamente mais tarde.';
        }

        if (!$errorMsg) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $errorMsg = 'Usuário não encontrado.';
                } else {
                    $hash = $user['senha'] ?? '';
                    if (!password_verify($senha, $hash)) {
                        $errorMsg = 'Senha incorreta.';
                    } else {
                        // Login OK — popula sessão
                        $_SESSION['usuario_id'] = intval($user['id']);
                        $_SESSION['usuario_nome'] = $user['nome'] ?? '';
                        $_SESSION['usuario_email'] = $user['email'] ?? '';
                        // role: tenta a coluna 'role', fallback para seradmin (booleana)
                        if (isset($user['role'])) {
                            $_SESSION['role'] = intval($user['role']);
                        } else {
                            // seradmin true => treat as dev (2) otherwise 0
                            if (isset($user['seradmin'])) $_SESSION['role'] = $user['seradmin'] ? 2 : 0;
                            else $_SESSION['role'] = 0;
                        }
                        $_SESSION['foto_url'] = $user['foto_url'] ?? '';

                        // redireciona para index.php
                        header('Location: index.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log('login.php DB error: ' . $e->getMessage());
                $errorMsg = 'Erro no servidor. Tente novamente mais tarde.';
            }
        }
    }
}

// Se veio ?error=... via query string mostra mensagem amigável (mantive por compatibilidade)
if (!$errorMsg) {
    $qsErr = $_GET['error'] ?? '';
    if ($qsErr === 'empty') $errorMsg = 'Preencha e-mail e senha.';
    elseif ($qsErr === 'notfound') $errorMsg = 'Usuário não encontrado.';
    elseif ($qsErr === 'badpass') $errorMsg = 'Senha incorreta.';
    elseif ($qsErr === 'server') $errorMsg = 'Erro no servidor. Tente novamente mais tarde.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Login - notIFy</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
    .card { background:#fff; padding:28px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:360px; }
    h2 { margin:0 0 12px 0; color:#045c3f; text-align:center; }
    input { width:100%; padding:10px 12px; margin:8px 0; border-radius:8px; border:1px solid #ccc; box-sizing:border-box; }
    button { width:100%; padding:10px; background:#045c3f; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; margin-top:10px; }
    .error { margin-top:10px; padding:10px; background:#ffdede; color:#a94442; border-radius:6px; display:block; }
    .info { margin-top:10px; padding:10px; background:#e6f7ea; color:#0b6b33; border-radius:6px; display:block; }
    .link { text-align:center; margin-top:12px; font-size:14px; color:#666; }
    .link a { color:#045c3f; text-decoration:none; font-weight:600; }
  </style>
</head>
<body>
  <div class="card" role="main">
    <h2>Entrar — notIFy</h2>

    <?php if ($errorMsg): ?>
      <div id="errorBox" class="error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($infoMsg): ?>
      <div class="info"><?= htmlspecialchars($infoMsg) ?></div>
    <?php endif; ?>

    <form id="loginForm" action="login.php" method="POST" autocomplete="on">
      <input type="email" name="email" id="email" placeholder="E-mail" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" />
      <input type="password" name="senha" id="senha" placeholder="Senha" required />
      <button type="submit">Entrar</button>
    </form>

    <div class="link">
      Não tem conta? <a href="register_user.php">Cadastre-se</a>
    </div>
  </div>
</body>
</html>
