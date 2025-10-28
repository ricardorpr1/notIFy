<?php
// register_user.php — formulário + backend unificados com upload de foto
session_start();
header("Content-Type: text/html; charset=UTF-8");

// === Configurações do banco (ajuste se necessário) ===
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

// === Configurações de upload ===
$UPLOAD_DIR = __DIR__ . '/uploads'; // caminho físico para salvar arquivos
$UPLOAD_WEBPATH = 'uploads';        // caminho relativo salvo no DB (usado para exibir imagens)
$MAX_FILE_BYTES = 3 * 1024 * 1024; // 3 MB
$ALLOWED_MIMES = [
    'image/jpeg' => '.jpg',
    'image/jpg'  => '.jpg',
    'image/png'  => '.png',
    'image/webp' => '.webp'
];

// mensagens para mostrar ao usuário
$mensagem = "";
$erro = "";

// cria uploads dir se não existir
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}

// função utilitária para gerar nome de arquivo seguro
function gen_filename($ext = '.jpg') {
    return bin2hex(random_bytes(10)) . $ext;
}

// função utilitária para limpar strings
function safeTrim($v) { return is_string($v) ? trim($v) : $v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campos do formulário
    $nome = safeTrim($_POST['nome'] ?? '');
    $email = safeTrim($_POST['email'] ?? '');
    $senha_raw = $_POST['senha'] ?? '';
    $telefone = safeTrim($_POST['telefone'] ?? '');
    $cpf_raw = safeTrim($_POST['cpf'] ?? '');
    $data_nascimento = safeTrim($_POST['nascimento'] ?? '');
    $ra = safeTrim($_POST['ra'] ?? '');
    $naoAluno = isset($_POST['nao_aluno']) && ($_POST['nao_aluno'] === '1' || $_POST['nao_aluno'] === 'on');

    // validações básicas do lado servidor
    $required = [];
    if ($nome === '') $required[] = 'nome';
    if ($email === '') $required[] = 'email';
    if ($senha_raw === '') $required[] = 'senha';
    if ($cpf_raw === '') $required[] = 'cpf';
    if ($data_nascimento === '') $required[] = 'data de nascimento';
    if (!$naoAluno && $ra === '') $required[] = 'RA (ou marque "Não sou aluno")';

    if (count($required) > 0) {
        $erro = 'Preencha os campos obrigatórios: ' . implode(', ', $required) . '.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } else {
        // normaliza cpf (apenas dígitos)
        $cpf_digits = preg_replace('/\D+/', '', $cpf_raw);
        if (strlen($cpf_digits) !== 11) {
            $erro = 'CPF inválido. Deve conter 11 dígitos.';
        } else {
            // processar upload de foto (se enviado)
            $foto_url_db = null;
            if (isset($_FILES['foto']) && is_array($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                $fErr = $_FILES['foto']['error'];
                if ($fErr !== UPLOAD_ERR_OK) {
                    $erro = 'Erro no upload da imagem (código ' . intval($fErr) . ').';
                } else {
                    $tmpPath = $_FILES['foto']['tmp_name'];
                    $fSize = filesize($tmpPath);
                    $fType = mime_content_type($tmpPath) ?: $_FILES['foto']['type'];

                    if ($fSize > $MAX_FILE_BYTES) {
                        $erro = 'Imagem muito grande. Máx ' . ($MAX_FILE_BYTES / (1024*1024)) . ' MB.';
                    } elseif (!array_key_exists($fType, $ALLOWED_MIMES)) {
                        $erro = 'Tipo de arquivo não permitido. Use JPG, PNG ou WEBP.';
                    } else {
                        // gerar nome e mover
                        $ext = $ALLOWED_MIMES[$fType];
                        $fname = gen_filename($ext);
                        $dest = $UPLOAD_DIR . '/' . $fname;

                        if (!move_uploaded_file($tmpPath, $dest)) {
                            $erro = 'Falha ao salvar o arquivo enviado.';
                        } else {
                            // opcional: ajustar permissões
                            @chmod($dest, 0644);
                            // gravar caminho relativo no DB para exibição posterior
                            $foto_url_db = $UPLOAD_WEBPATH . '/' . $fname;
                        }
                    }
                }
            }

            // se não houve erro até aqui, inserir no banco
            if ($erro === "") {
                try {
                    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                                   $DB_USER, $DB_PASS, [
                                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                       PDO::ATTR_EMULATE_PREPARES => false
                                   ]);
                } catch (PDOException $e) {
                    $erro = 'Erro de conexão com o banco de dados.';
                }

                if ($erro === "") {
                    $senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);
                    $registro_academico_db = $naoAluno ? null : ($ra === '' ? null : $ra);
                    $role_default = 0;

                    // Tenta inserir com coluna role; se falhar (coluna não existe) tenta sem role
                    $sql1 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url, role)
                             VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :foto_url, :role)";
                    $sql2 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url)
                             VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :foto_url)";

                    try {
                        $stmt = $pdo->prepare($sql1);
                        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
                        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                        $stmt->bindValue(':senha', $senha_hash, PDO::PARAM_STR);
                        $stmt->bindValue(':telefone', $telefone ?: null, PDO::PARAM_STR);
                        $stmt->bindValue(':cpf', $cpf_digits, PDO::PARAM_STR);
                        $stmt->bindValue(':data_nascimento', $data_nascimento ?: null, PDO::PARAM_STR);
                        if ($registro_academico_db === null) $stmt->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                        else $stmt->bindValue(':registro_academico', $registro_academico_db, PDO::PARAM_STR);
                        $stmt->bindValue(':foto_url', $foto_url_db ?: null, PDO::PARAM_STR);
                        $stmt->bindValue(':role', $role_default, PDO::PARAM_INT);
                        $stmt->execute();

                        $mensagem = "Usuário cadastrado com sucesso!";
                        // redireciona para login após 1.2s
                        header("Refresh:1.2; url=telalogin.html");
                    } catch (PDOException $e) {
                        $msg = $e->getMessage();
                        $code = $e->getCode();
                        // se erro por coluna desconhecida, tenta fallback sem role (compatibilidade)
                        if (stripos($msg, 'Unknown column') !== false || stripos($msg, "column 'role'") !== false || stripos($msg, 'role') !== false) {
                            try {
                                $stmt2 = $pdo->prepare($sql2);
                                $stmt2->bindValue(':nome', $nome, PDO::PARAM_STR);
                                $stmt2->bindValue(':email', $email, PDO::PARAM_STR);
                                $stmt2->bindValue(':senha', $senha_hash, PDO::PARAM_STR);
                                $stmt2->bindValue(':telefone', $telefone ?: null, PDO::PARAM_STR);
                                $stmt2->bindValue(':cpf', $cpf_digits, PDO::PARAM_STR);
                                $stmt2->bindValue(':data_nascimento', $data_nascimento ?: null, PDO::PARAM_STR);
                                if ($registro_academico_db === null) $stmt2->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                                else $stmt2->bindValue(':registro_academico', $registro_academico_db, PDO::PARAM_STR);
                                $stmt2->bindValue(':foto_url', $foto_url_db ?: null, PDO::PARAM_STR);
                                $stmt2->execute();

                                $mensagem = "Usuário cadastrado com sucesso!";
                                header("Refresh:1.2; url=telalogin.html");
                            } catch (PDOException $e2) {
                                if ($e2->getCode() == 23000 || stripos($e2->getMessage(), 'Duplicate entry') !== false) {
                                    $erro = 'E-mail, CPF ou RA já cadastrados.';
                                } else {
                                    $erro = 'Erro ao inserir usuário: ' . htmlspecialchars($e2->getMessage());
                                }
                            }
                        } else {
                            // tratar chave única (MySQL code 23000)
                            if ($code == 23000 || stripos($msg, 'Duplicate entry') !== false) {
                                $erro = 'E-mail, CPF ou RA já cadastrados.';
                            } else {
                                $erro = 'Erro ao inserir usuário: ' . htmlspecialchars($msg);
                            }
                        }
                    }
                } // fim db conectado
            } // fim sem erro processamento
        } // fim cpf ok
    } // fim email ok
} // fim POST
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cadastro — notIFy</title>
  <style>
    body { font-family:Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
    .card { background:#fff; padding:24px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:420px; }
    h2 { margin:0 0 12px 0; color:#045c3f; text-align:center; }
    label { display:block; margin-top:8px; font-weight:600; color:#333; }
    input, select { width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; }
    .row { display:flex; gap:8px; align-items:center; margin-top:8px; }
    button { width:100%; padding:12px; margin-top:14px; border:none; border-radius:8px; background:#045c3f; color:#fff; font-weight:700; cursor:pointer; }
    .msg { padding:10px; border-radius:6px; margin-top:10px; text-align:center; }
    .sucesso { background:#e6f7ea; color:#0b6b33; border:1px solid #cde9d2; }
    .erro { background:#fdecea; color:#a94442; border:1px solid #f3c6c6; }
    .small { font-size:13px; color:#666; text-align:center; margin-top:8px; }
    #raContainer { transition: all 0.25s ease; }
  </style>
</head>
<body>
  <div class="card" role="main">
    <h2>Criar conta — notIFy</h2>

    <?php if ($mensagem): ?>
      <div class="msg sucesso"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="msg erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form id="formCadastro" method="post" action="register_user.php" enctype="multipart/form-data" novalidate>
      <label for="nome">Nome completo *</label>
      <input id="nome" name="nome" type="text" required value="<?= isset($nome) ? htmlspecialchars($nome) : '' ?>">

      <label for="email">E-mail *</label>
      <input id="email" name="email" type="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">

      <label for="senha">Senha *</label>
      <input id="senha" name="senha" type="password" required>

      <label for="telefone">Telefone</label>
      <input id="telefone" name="telefone" type="text" value="<?= isset($telefone) ? htmlspecialchars($telefone) : '' ?>">

      <label for="cpf">CPF * (somente números ou formatado)</label>
      <input id="cpf" name="cpf" type="text" placeholder="123.456.789-10" required value="<?= isset($cpf_raw) ? htmlspecialchars($cpf_raw) : '' ?>">

      <label for="nascimento">Data de nascimento *</label>
      <input id="nascimento" name="nascimento" type="date" required value="<?= isset($data_nascimento) ? htmlspecialchars($data_nascimento) : '' ?>">

      <div id="raContainer">
        <label for="ra">Registro Acadêmico (RA) <span style="font-size:12px;color:#666">(se for aluno)</span></label>
        <input id="ra" name="ra" type="text" value="<?= isset($ra) ? htmlspecialchars($ra) : '' ?>">
      </div>

      <div class="row" style="margin-top:10px; align-items:center;">
        <input id="naoAluno" name="nao_aluno" type="checkbox" value="1" <?= isset($naoAluno) && $naoAluno ? 'checked' : '' ?> aria-describedby="naoAlunoLabel">
        <label for="naoAluno" id="naoAlunoLabel" style="margin:0;font-size:14px;color:#444;">Não sou aluno do IFMG</label>
      </div>

      <label for="foto">Foto de perfil (JPG, PNG ou WEBP) — opcional</label>
      <input id="foto" name="foto" type="file" accept="image/*">

      <button type="submit">Cadastrar</button>
    </form>

    <div class="small">Já tem conta? <a href="login.php">Entrar</a></div>
  </div>

  <script>
    (function(){
      const naoAluno = document.getElementById('naoAluno');
      const raContainer = document.getElementById('raContainer');

      function updateRAState() {
        if (naoAluno.checked) {
          raContainer.style.display = 'none';
          document.getElementById('ra').value = '';
        } else {
          raContainer.style.display = 'block';
        }
      }

      naoAluno.addEventListener('change', updateRAState);
      // inicializar estado (caso reenviado)
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
