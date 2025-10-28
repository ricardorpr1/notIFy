<?php
// register_user.php
// Unifica tela de cadastro + processamento em um único arquivo
session_start();

// --- Configuração do banco (ajuste se necessário) ---
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

$messages = [
  'success' => null,
  'error' => null
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // aceitar tanto application/json quanto form-urlencoded
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = [];
    } else {
        $data = $_POST;
    }

    // sanitização básica
    $nome = trim($data['nome'] ?? '');
    $email = trim($data['email'] ?? '');
    $senha_raw = $data['senha'] ?? '';
    $telefone = trim($data['telefone'] ?? '');
    $cpf_raw = trim($data['cpf'] ?? '');
    $nascimento = trim($data['nascimento'] ?? '');
    $ra = trim($data['ra'] ?? '');
    $naoAluno = isset($data['nao_aluno']) && ($data['nao_aluno'] === '1' || $data['nao_aluno'] === 'on' || $data['nao_aluno'] === 'true');

    // validações
    $missing = [];
    if ($nome === '') $missing[] = 'nome';
    if ($email === '') $missing[] = 'email';
    if ($senha_raw === '') $missing[] = 'senha';
    if ($cpf_raw === '') $missing[] = 'cpf';
    if ($nascimento === '') $missing[] = 'nascimento';
    if (!$naoAluno && $ra === '') $missing[] = 'ra';

    if (count($missing) > 0) {
        $messages['error'] = 'Preencha todos os campos obrigatórios: ' . implode(', ', $missing);
    } else {
        // valida email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $messages['error'] = 'E-mail inválido.';
        } else {
            // normaliza CPF: mantém apenas dígitos
            $cpf_digits = preg_replace('/\D+/', '', $cpf_raw);
            if (strlen($cpf_digits) !== 11) {
                $messages['error'] = 'CPF inválido. Deve conter 11 dígitos.';
            } else {
                // preparar inserção
                try {
                    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]);
                } catch (PDOException $e) {
                    $messages['error'] = 'Erro de conexão com o banco.';
                }

                if ($messages['error'] === null) {
                    // hash da senha
                    $senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);

                    // preparar valores
                    $registro_academico_db = $naoAluno ? null : ($ra === '' ? null : $ra);
                    $foto_url = null; // por enquanto vazio; front-end pode permitir upload/URL depois
                    $role_default = 0; // novo usuário é role 0 por padrão

                    // tentativa 1: inserir incluindo coluna role (se existir)
                    $sql1 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url, role)
                             VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :foto_url, :role)";

                    // tentativa 2 (fallback): inserir sem role (compatibilidade com schema antigo)
                    $sql2 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url)
                             VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :foto_url)";

                    try {
                        $stmt = $pdo->prepare($sql1);
                        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
                        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                        $stmt->bindValue(':senha', $senha_hash, PDO::PARAM_STR);
                        $stmt->bindValue(':telefone', $telefone ?: null, PDO::PARAM_STR);
                        $stmt->bindValue(':cpf', $cpf_digits, PDO::PARAM_STR);
                        $stmt->bindValue(':data_nascimento', $nascimento ?: null, PDO::PARAM_STR);
                        if ($registro_academico_db === null) $stmt->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                        else $stmt->bindValue(':registro_academico', $registro_academico_db, PDO::PARAM_STR);
                        $stmt->bindValue(':foto_url', $foto_url ?: null, PDO::PARAM_STR);
                        $stmt->bindValue(':role', $role_default, PDO::PARAM_INT);
                        $stmt->execute();
                        $newId = $pdo->lastInsertId();
                        // sucesso
                        $messages['success'] = 'Usuário criado com sucesso. Você será redirecionado para login.';
                        // redirecionar para login depois de 1.2s
                        header("Refresh:1.2; url=telalogin.html");
                    } catch (PDOException $e) {
                        // se erro por coluna desconhecida (ex.: column 'role' doesn't exist), tenta SQL2
                        $msg = $e->getMessage();
                        $code = $e->getCode();
                        if (stripos($msg, 'Unknown column') !== false || stripos($msg, 'column not found') !== false || stripos($msg, 'role') !== false) {
                            try {
                                $stmt2 = $pdo->prepare($sql2);
                                $stmt2->bindValue(':nome', $nome, PDO::PARAM_STR);
                                $stmt2->bindValue(':email', $email, PDO::PARAM_STR);
                                $stmt2->bindValue(':senha', $senha_hash, PDO::PARAM_STR);
                                $stmt2->bindValue(':telefone', $telefone ?: null, PDO::PARAM_STR);
                                $stmt2->bindValue(':cpf', $cpf_digits, PDO::PARAM_STR);
                                $stmt2->bindValue(':data_nascimento', $nascimento ?: null, PDO::PARAM_STR);
                                if ($registro_academico_db === null) $stmt2->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                                else $stmt2->bindValue(':registro_academico', $registro_academico_db, PDO::PARAM_STR);
                                $stmt2->bindValue(':foto_url', $foto_url ?: null, PDO::PARAM_STR);
                                $stmt2->execute();
                                $newId = $pdo->lastInsertId();
                                $messages['success'] = 'Usuário criado com sucesso. Você será redirecionado para login.';
                                header("Refresh:1.2; url=telalogin.html");
                            } catch (PDOException $e2) {
                                // mensagem amigável
                                if ($e2->getCode() == 23000 || stripos($e2->getMessage(), 'Duplicate entry') !== false) {
                                    $messages['error'] = 'Já existe um usuário com este e-mail, CPF ou RA.';
                                } else {
                                    $messages['error'] = 'Erro ao inserir usuário: ' . $e2->getMessage();
                                }
                            }
                        } else {
                            // tratar chave única (MySQL code 23000)
                            if ($code == 23000 || stripos($msg, 'Duplicate entry') !== false) {
                                // identificar campo duplicado pela mensagem
                                $field = "valor já existente";
                                if (stripos($msg, 'email') !== false) $field = "email já cadastrado";
                                elseif (stripos($msg, 'cpf') !== false) $field = "CPF já cadastrado";
                                elseif (stripos($msg, 'registro_academico') !== false || stripos($msg, 'registro') !== false) $field = "RA já cadastrado";
                                $messages['error'] = 'Conflito: ' . $field;
                            } else {
                                $messages['error'] = 'Erro ao inserir usuário: ' . $msg;
                            }
                        }
                    }
                } // end DB connected
            } // end CPF valid
        } // end email valid
    } // end missing fields
} // end POST

// --- Mostrar formulário (GET) ou resultado (POST com mensagens) ---
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cadastro — notIFy</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
    .card { background:#fff; padding:24px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:380px; }
    h2 { margin:0 0 10px 0; color:#045c3f; text-align:center; }
    label { display:block; margin-top:8px; font-size:14px; color:#444; }
    input, select { width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; }
    input[disabled] { background:#efefef; color:#666; }
    .row { display:flex; gap:8px; align-items:center; margin-top:8px; }
    .row input[type="checkbox"] { width:auto; }
    button { width:100%; padding:12px; margin-top:14px; border:none; border-radius:8px; background:#045c3f; color:#fff; font-weight:700; cursor:pointer; }
    .msg { padding:10px; border-radius:6px; margin-top:10px; display:none; }
    .msg.success { background:#e6f7ea; color:#0b6b33; border:1px solid #cde9d2; }
    .msg.error { background:#fdecea; color:#a94442; border:1px solid #f3c6c6; }
    .small { font-size:13px; color:#666; text-align:center; margin-top:8px; }
  </style>
</head>
<body>
  <div class="card" role="main">
    <h2>Criar conta — notIFy</h2>

    <?php if ($messages['success']): ?>
      <div class="msg success" style="display:block"><?= htmlspecialchars($messages['success']) ?></div>
    <?php endif; ?>
    <?php if ($messages['error']): ?>
      <div class="msg error" style="display:block"><?= htmlspecialchars($messages['error']) ?></div>
    <?php endif; ?>

    <form id="formCadastro" method="post" novalidate>
      <label for="nome">Nome completo *</label>
      <input id="nome" name="nome" type="text" required value="<?= isset($nome) ? htmlspecialchars($nome) : '' ?>">

      <label for="email">E-mail *</label>
      <input id="email" name="email" type="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">

      <label for="senha">Senha *</label>
      <input id="senha" name="senha" type="password" required>

      <label for="telefone">Telefone</label>
      <input id="telefone" name="telefone" type="tel" value="<?= isset($telefone) ? htmlspecialchars($telefone) : '' ?>">

      <label for="cpf">CPF * (somente números ou formatado)</label>
      <input id="cpf" name="cpf" type="text" placeholder="123.456.789-10" required value="<?= isset($cpf_raw) ? htmlspecialchars($cpf_raw) : '' ?>">

      <label for="nascimento">Data de nascimento *</label>
      <input id="nascimento" name="nascimento" type="date" required value="<?= isset($nascimento) ? htmlspecialchars($nascimento) : '' ?>">

      <div class="row" style="margin-top:10px; align-items:center;">
        <input id="naoAluno" name="nao_aluno" type="checkbox" value="1" <?= isset($naoAluno) && $naoAluno ? 'checked' : '' ?> aria-describedby="naoAlunoLabel">
        <label for="naoAluno" id="naoAlunoLabel" style="margin:0;font-size:14px;color:#444;">Não sou aluno do IFMG</label>
      </div>

      <label for="ra" style="margin-top:8px;">Registro Acadêmico (RA) <span style="font-size:12px;color:#666">(se for aluno)</span></label>
      <input id="ra" name="ra" type="text" required value="<?= isset($ra) ? htmlspecialchars($ra) : '' ?>">

      <button type="submit">Cadastrar</button>
    </form>

    <div class="small">Já tem conta? <a href="telalogin.html">Entrar</a></div>
  </div>

  <script>
    (function(){
      const naoAluno = document.getElementById('naoAluno');
      const raInput = document.getElementById('ra');

      function updateRAState() {
        if (naoAluno.checked) {
          raInput.value = '';
          raInput.setAttribute('disabled','disabled');
          raInput.removeAttribute('required');
        } else {
          raInput.removeAttribute('disabled');
          raInput.setAttribute('required','required');
        }
      }

      naoAluno.addEventListener('change', updateRAState);
      // inicializar estado conforme server-side (se já enviado antes)
      updateRAState();

      // client-side: normaliza cpf antes de submit (mantém apenas dígitos)
      const form = document.getElementById('formCadastro');
      form.addEventListener('submit', function(e){
        const cpfEl = document.getElementById('cpf');
        cpfEl.value = cpfEl.value.replace(/\D/g,''); // enviar só dígitos (server valida)
      });
    })();
  </script>
</body>
</html>
