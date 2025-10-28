<?php
// perfil_editar.php
session_start();

// se não autenticado, redireciona para tela inicial
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}

$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

// upload config (mesma lógica do register_user.php)
$UPLOAD_DIR = __DIR__ . '/uploads';
$UPLOAD_WEBPATH = 'uploads';
$MAX_FILE_BYTES = 3 * 1024 * 1024;
$ALLOWED_MIMES = [
    'image/jpeg' => '.jpg',
    'image/jpg'  => '.jpg',
    'image/png'  => '.png',
    'image/webp' => '.webp'
];

if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);

function gen_filename($ext = '.jpg') {
    return bin2hex(random_bytes(10)) . $ext;
}

function safeTrim($v) { return is_string($v) ? trim($v) : $v; }

$erro = '';
$mensagem = '';

$userid = intval($_SESSION['usuario_id']);

// conectar DB
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                   $DB_USER, $DB_PASS, [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                       PDO::ATTR_EMULATE_PREPARES => false
                   ]);
} catch (PDOException $e) {
    // não expor detalhes em produção
    die("Erro ao conectar ao banco de dados.");
}

// buscar dados atuais do usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: telainicio.html');
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar usuário.");
}

// valores iniciais para popular o formulário
$nome = $user['nome'] ?? '';
$email = $user['email'] ?? '';
$telefone = $user['telefone'] ?? '';
$cpf = $user['cpf'] ?? '';
$data_nascimento = isset($user['data_nascimento']) ? $user['data_nascimento'] : '';
$registro_academico = $user['registro_academico'] ?? '';
$foto_url = $user['foto_url'] ?? '';
$naoAlunoChecked = ($registro_academico === null || $registro_academico === '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ler campos
    $nome_post = safeTrim($_POST['nome'] ?? '');
    $email_post = safeTrim($_POST['email'] ?? '');
    $telefone_post = safeTrim($_POST['telefone'] ?? '');
    $cpf_post = safeTrim($_POST['cpf'] ?? '');
    $data_nascimento_post = safeTrim($_POST['nascimento'] ?? '');
    $naoAluno = isset($_POST['nao_aluno']) && ($_POST['nao_aluno'] === '1' || $_POST['nao_aluno'] === 'on');
    $ra_post = $naoAluno ? null : safeTrim($_POST['ra'] ?? '');
    $nova_senha = $_POST['senha'] ?? '';

    // validações básicas
    if ($nome_post === '' || $email_post === '' || $cpf_post === '' || $data_nascimento_post === '') {
        $erro = 'Preencha os campos obrigatórios (nome, e-mail, CPF e data de nascimento).';
    } elseif (!filter_var($email_post, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } else {
        // normaliza cpf
        $cpf_digits = preg_replace('/\D+/', '', $cpf_post);
        if (strlen($cpf_digits) !== 11) {
            $erro = 'CPF inválido (deve conter 11 dígitos).';
        }
    }

    // processar upload de foto (se houver e sem erros previos)
    $new_foto_url = $foto_url; // por padrão manter atual
    if ($erro === '') {
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
                    $ext = $ALLOWED_MIMES[$fType];
                    $fname = gen_filename($ext);
                    $dest = $UPLOAD_DIR . '/' . $fname;
                    if (!move_uploaded_file($tmpPath, $dest)) {
                        $erro = 'Falha ao salvar a imagem enviada.';
                    } else {
                        @chmod($dest, 0644);
                        $new_foto_url = $UPLOAD_WEBPATH . '/' . $fname;
                    }
                }
            }
        }
    }

    // se nenhum erro, tentar atualizar DB
    if ($erro === '') {
        try {
            // validar unicidade para email, cpf e ra (se alterados)
            // checar email
            if ($email_post !== $email) {
                $s = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1");
                $s->execute([':email' => $email_post, ':id' => $userid]);
                if ($s->fetch()) { $erro = 'E-mail já está em uso por outro usuário.'; }
            }
            // checar cpf
            if ($erro === '' && $cpf_digits !== $cpf) {
                $s = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = :cpf AND id <> :id LIMIT 1");
                $s->execute([':cpf' => $cpf_digits, ':id' => $userid]);
                if ($s->fetch()) { $erro = 'CPF já cadastrado por outro usuário.'; }
            }
            // checar ra (se usado)
            if ($erro === '' && $ra_post !== null && $ra_post !== '') {
                if ($ra_post !== $registro_academico) {
                    $s = $pdo->prepare("SELECT id FROM usuarios WHERE registro_academico = :ra AND id <> :id LIMIT 1");
                    $s->execute([':ra' => $ra_post, ':id' => $userid]);
                    if ($s->fetch()) { $erro = 'RA já cadastrado por outro usuário.'; }
                }
            }

            if ($erro === '') {
                // construir SQL dinâmico para atualizar somente os campos necessários
                $updateFields = [
                    'nome = :nome',
                    'email = :email',
                    'telefone = :telefone',
                    'cpf = :cpf',
                    'data_nascimento = :data_nascimento',
                    'registro_academico = :registro_academico',
                    'foto_url = :foto_url'
                ];
                $params = [
                    ':id' => $userid,
                    ':nome' => $nome_post,
                    ':email' => $email_post,
                    ':telefone' => $telefone_post ?: null,
                    ':cpf' => $cpf_digits,
                    ':data_nascimento' => $data_nascimento_post ?: null,
                    ':registro_academico' => ($ra_post === null || $ra_post === '') ? null : $ra_post,
                    ':foto_url' => $new_foto_url ?: null
                ];

                // senha: se foi fornecida uma nova senha, adicionar ao update
                if ($nova_senha !== null && $nova_senha !== '') {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $updateFields[] = 'senha = :senha';
                    $params[':senha'] = $senha_hash;
                }

                $sqlUpdate = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = :id";
                $stmt = $pdo->prepare($sqlUpdate);
                $stmt->execute($params);

                // atualizar variáveis locais e sessão foto/nome se necessário
                $mensagem = 'Perfil atualizado com sucesso.';
                // atualizar variáveis para preencher o formulário novamente
                $nome = $nome_post;
                $email = $email_post;
                $telefone = $telefone_post;
                $cpf = $cpf_digits;
                $data_nascimento = $data_nascimento_post;
                $registro_academico = ($ra_post === null || $ra_post === '') ? null : $ra_post;
                $foto_url = $new_foto_url;
                $naoAlunoChecked = ($registro_academico === null || $registro_academico === '');

                // atualizar sessão (nome/foto) caso queira refletir imediatamente
                $_SESSION['usuario_nome'] = $nome;
                if (!empty($foto_url)) $_SESSION['foto_url'] = $foto_url;
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar perfil.';
        }
    }
}

// helper para exibir valor seguro
function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Editar Perfil — notIFy</title>
<style>
  body { font-family:Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
  .card { background:#fff; padding:22px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:520px; max-width:95%; }
  h2 { margin:0 0 12px 0; color:#045c3f; text-align:center; }
  label { display:block; margin-top:10px; font-weight:600; color:#333; }
  input[type="text"], input[type="email"], input[type="date"], input[type="password"], input[type="file"] { width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; }
  .row { display:flex; gap:10px; align-items:center; margin-top:8px; }
  .btns { display:flex; gap:8px; margin-top:14px; justify-content:flex-end; }
  button { padding:10px 14px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
  .btn-save { background:#045c3f; color:#fff; }
  .btn-cancel { background:#6c757d; color:#fff; }
  .msg { padding:10px; border-radius:6px; margin-top:10px; }
  .msg.sucesso { background:#e6f7ea; color:#0b6b33; border:1px solid #cde9d2; }
  .msg.erro { background:#fdecea; color:#a94442; border:1px solid #f3c6c6; }
  #raContainer { transition: all 0.25s ease; }
  .thumb { width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd; display:block; margin-top:8px; }
</style>
</head>
<body>
  <div class="card" role="main">
    <h2>Editar perfil</h2>

    <?php if ($mensagem): ?>
      <div class="msg sucesso"><?= esc($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="msg erro"><?= esc($erro) ?></div>
    <?php endif; ?>

    <form method="post" action="perfil_editar.php" enctype="multipart/form-data" novalidate>
      <label for="nome">Nome</label>
      <input type="text" id="nome" name="nome" required value="<?= esc($nome) ?>">

      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" required value="<?= esc($email) ?>">

      <label for="telefone">Telefone</label>
      <input type="text" id="telefone" name="telefone" value="<?= esc($telefone) ?>">

      <label for="cpf">CPF</label>
      <input type="text" id="cpf" name="cpf" value="<?= esc($cpf) ?>">

      <label for="nascimento">Data de nascimento</label>
      <input type="date" id="nascimento" name="nascimento" value="<?= esc($data_nascimento) ?>">

      <div id="raContainer">
        <label for="ra">Registro Acadêmico (RA)</label>
        <input type="text" id="ra" name="ra" value="<?= esc($registro_academico) ?>">
      </div>

      <div class="row" style="margin-top:8px;">
        <input id="nao_aluno" name="nao_aluno" type="checkbox" value="1" <?= $naoAlunoChecked ? 'checked' : '' ?>>
        <label for="nao_aluno" style="margin:0">Não sou aluno do IFMG</label>
      </div>

      <label for="senha">Nova senha (deixe em branco para manter)</label>
      <input type="password" id="senha" name="senha" placeholder="Nova senha (opcional)">

      <label for="foto">Foto de perfil (JPG / PNG / WEBP) — opcional</label>
      <input type="file" id="foto" name="foto" accept="image/*">
      <?php if (!empty($foto_url)): ?>
        <img src="<?= esc($foto_url) ?>" alt="Foto atual" class="thumb" onerror="this.style.display='none'">
      <?php endif; ?>

      <div class="btns">
        <button type="button" class="btn-cancel btn" onclick="location.href='index.php'">Cancelar</button>
        <button type="submit" class="btn-save btn">Salvar</button>
      </div>
    </form>
  </div>

<script>
(function(){
  const naoAluno = document.getElementById('nao_aluno');
  const raContainer = document.getElementById('raContainer');
  const raInput = document.getElementById('ra');

  function updateRA() {
    if (naoAluno.checked) {
      raContainer.style.display = 'none';
      if (raInput) raInput.value = '';
    } else {
      raContainer.style.display = 'block';
    }
  }
  naoAluno.addEventListener('change', updateRA);
  updateRA();

  // normalize CPF before submit
  document.querySelector('form').addEventListener('submit', function(e){
    const cpfEl = document.getElementById('cpf');
    if (cpfEl) cpfEl.value = cpfEl.value.replace(/\D/g,'');
  });
})();
</script>
</body>
</html>
