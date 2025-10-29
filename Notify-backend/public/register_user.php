<?php
// register_user.php — cadastro com upload + geração de miniatura 200x200
session_start();

// DB config — ajuste se necessário
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

// Upload config
$UPLOAD_DIR = __DIR__ . '/uploads';
$UPLOAD_WEBPATH = 'uploads';
$MAX_FILE_BYTES = 3 * 1024 * 1024; // 3 MB
$ALLOWED_MIMES = [
    'image/jpeg' => '.jpg',
    'image/jpg'  => '.jpg',
    'image/png'  => '.png',
    'image/webp' => '.webp'
];
$THUMB_SIZE = 200; // px

// ensure upload dir
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);

// helpers
function gen_filename($ext = '.jpg') {
    return bin2hex(random_bytes(10)) . $ext;
}
function safeTrim($v) { return is_string($v) ? trim($v) : $v; }

// image utils using GD (robust)
function create_image_from_file($path) {
    $data = @file_get_contents($path);
    if ($data === false) return false;
    return @imagecreatefromstring($data); // supports jpeg/png/gif/webp if GD compiled
}
function save_image_to_file($img, $path, $mime) {
    // mime like image/jpeg etc -> choose output function
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return imagejpeg($img, $path, 85);
    } elseif ($ext === 'png') {
        // PNG quality param inverse (0-9 compression), map 85 -> 6
        return imagepng($img, $path, 6);
    } elseif ($ext === 'webp') {
        if (function_exists('imagewebp')) {
            return imagewebp($img, $path, 80);
        } else {
            // fallback to jpg
            return imagejpeg($img, preg_replace('/\.\w+$/','.' . 'jpg', $path), 85);
        }
    } else {
        // fallback to jpeg
        return imagejpeg($img, $path, 85);
    }
}
function create_center_crop_thumbnail($srcPath, $destPath, $thumbSize, $ext) {
    $srcImg = create_image_from_file($srcPath);
    if (!$srcImg) return false;
    $w = imagesx($srcImg);
    $h = imagesy($srcImg);
    if ($w <= 0 || $h <= 0) { imagedestroy($srcImg); return false; }

    // calculate square crop
    if ($w > $h) {
        $s = $h;
        $srcX = intval(($w - $h) / 2);
        $srcY = 0;
    } else {
        $s = $w;
        $srcX = 0;
        $srcY = intval(($h - $w) / 2);
    }

    $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
    // preserve transparency for PNG/WebP
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefill($thumb, 0, 0, $transparent);

    $ok = imagecopyresampled($thumb, $srcImg, 0, 0, $srcX, $srcY, $thumbSize, $thumbSize, $s, $s);
    if (!$ok) { imagedestroy($srcImg); imagedestroy($thumb); return false; }

    $res = save_image_to_file($thumb, $destPath, null);

    imagedestroy($srcImg);
    imagedestroy($thumb);
    return $res;
}

// processing
$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = safeTrim($_POST['nome'] ?? '');
    $email = safeTrim($_POST['email'] ?? '');
    $senha_raw = $_POST['senha'] ?? '';
    $telefone = safeTrim($_POST['telefone'] ?? '');
    $cpf_raw = safeTrim($_POST['cpf'] ?? '');
    $data_nascimento = safeTrim($_POST['nascimento'] ?? '');
    $ra = safeTrim($_POST['ra'] ?? '');
    $naoAluno = isset($_POST['nao_aluno']) && ($_POST['nao_aluno'] === '1' || $_POST['nao_aluno'] === 'on');

    // basic required
    $missing = [];
    if ($nome === '') $missing[] = 'nome';
    if ($email === '') $missing[] = 'email';
    if ($senha_raw === '') $missing[] = 'senha';
    if ($cpf_raw === '') $missing[] = 'cpf';
    if ($data_nascimento === '') $missing[] = 'data de nascimento';
    if (!$naoAluno && $ra === '') $missing[] = 'RA (marque "Não sou aluno" se aplicável)';

    if (count($missing) > 0) {
        $erro = 'Preencha: ' . implode(', ', $missing);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } else {
        // normalize cpf digits
        $cpf_digits = preg_replace('/\D+/', '', $cpf_raw);
        if (strlen($cpf_digits) !== 11) {
            $erro = 'CPF inválido (precisa ter 11 dígitos).';
        } else {
            // handle upload if exists
            $foto_thumb_web = null; // path saved on DB
            if (isset($_FILES['foto']) && is_array($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                $fErr = $_FILES['foto']['error'];
                if ($fErr !== UPLOAD_ERR_OK) {
                    $erro = 'Erro no upload da imagem (código ' . intval($fErr) . ').';
                } else {
                    $tmpPath = $_FILES['foto']['tmp_name'];
                    $fSize = filesize($tmpPath);
                    $fMime = mime_content_type($tmpPath) ?: $_FILES['foto']['type'];

                    if ($fSize > $MAX_FILE_BYTES) {
                        $erro = 'Imagem muito grande. Máx ' . ($MAX_FILE_BYTES / (1024*1024)) . ' MB.';
                    } elseif (!array_key_exists($fMime, $ALLOWED_MIMES)) {
                        $erro = 'Tipo de imagem não permitido. Use JPG, PNG ou WEBP.';
                    } else {
                        $ext = $ALLOWED_MIMES[$fMime];
                        $origName = gen_filename($ext);
                        $thumbName = 'thumb_' . $origName;
                        $origDest = $UPLOAD_DIR . '/' . $origName;
                        $thumbDest = $UPLOAD_DIR . '/' . $thumbName;

                        // move original
                        if (!move_uploaded_file($tmpPath, $origDest)) {
                            $erro = 'Falha ao salvar imagem.';
                        } else {
                            @chmod($origDest, 0644);
                            // create thumb (center crop)
                            $okthumb = create_center_crop_thumbnail($origDest, $thumbDest, $THUMB_SIZE, $ext);
                            if (!$okthumb) {
                                // cleanup original if thumb failed? we'll keep original but no thumb
                                $erro = 'Falha ao gerar miniatura.';
                            } else {
                                @chmod($thumbDest, 0644);
                                $foto_thumb_web = $UPLOAD_WEBPATH . '/' . $thumbName; // save thumb path
                            }
                        }
                    }
                }
            } // end upload handling

            if ($erro === '') {
                // insert into DB
                try {
                    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                                   $DB_USER, $DB_PASS, [
                                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                        PDO::ATTR_EMULATE_PREPARES => false
                                   ]);
                } catch (PDOException $e) {
                    $erro = 'Erro de conexão com o banco.';
                }

                if ($erro === '') {
                    $senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);
                    $registro_academico_db = $naoAluno ? null : ($ra === '' ? null : $ra);
                    $role_default = 0;

                    $sql1 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url, role)
                             VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :foto_url, :role)";
                    $sql2 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url)
                             VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :foto_url)";

                    try {
                        $stmt = $pdo->prepare($sql1);
                        $stmt->bindValue(':nome', $nome);
                        $stmt->bindValue(':email', $email);
                        $stmt->bindValue(':senha', $senha_hash);
                        $stmt->bindValue(':telefone', $telefone ?: null);
                        $stmt->bindValue(':cpf', $cpf_digits);
                        $stmt->bindValue(':data_nascimento', $data_nascimento ?: null);
                        if ($registro_academico_db === null) $stmt->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                        else $stmt->bindValue(':registro_academico', $registro_academico_db);
                        $stmt->bindValue(':foto_url', $foto_thumb_web ?: null);
                        $stmt->bindValue(':role', $role_default, PDO::PARAM_INT);
                        $stmt->execute();

                        $mensagem = 'Usuário criado com sucesso!';
                        header("Refresh:1.2; url=login.php");
                        exit;
                    } catch (PDOException $e) {
                        $msg = $e->getMessage();
                        if (stripos($msg, 'Unknown column') !== false || stripos($msg, 'role') !== false) {
                            // fallback without role
                            try {
                                $stmt2 = $pdo->prepare($sql2);
                                $stmt2->bindValue(':nome', $nome);
                                $stmt2->bindValue(':email', $email);
                                $stmt2->bindValue(':senha', $senha_hash);
                                $stmt2->bindValue(':telefone', $telefone ?: null);
                                $stmt2->bindValue(':cpf', $cpf_digits);
                                $stmt2->bindValue(':data_nascimento', $data_nascimento ?: null);
                                if ($registro_academico_db === null) $stmt2->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                                else $stmt2->bindValue(':registro_academico', $registro_academico_db);
                                $stmt2->bindValue(':foto_url', $foto_thumb_web ?: null);
                                $stmt2->execute();

                                $mensagem = 'Usuário criado com sucesso!';
                                header("Refresh:1.2; url=login.php");
                                exit;
                            } catch (PDOException $e2) {
                                if ($e2->getCode() == 23000) $erro = 'E-mail, CPF ou RA já cadastrados.';
                                else $erro = 'Erro ao inserir usuário: ' . htmlspecialchars($e2->getMessage());
                            }
                        } else {
                            if ($e->getCode() == 23000) $erro = 'E-mail, CPF ou RA já cadastrados.';
                            else $erro = 'Erro ao inserir usuário: ' . htmlspecialchars($msg);
                        }
                    }
                } // end DB connected
            } // end no upload error
        } // end cpf ok
    } // end email ok
} // end POST

// Render form
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Cadastro — notIFy</title>
<style>
  body { font-family:Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
  .card { background:#fff; padding:24px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:460px; }
  h2 { margin:0 0 12px 0; color:#045c3f; text-align:center; }
  label { display:block; margin-top:8px; font-weight:600; color:#333; }
  input, select { width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; }
  .row { display:flex; gap:8px; align-items:center; margin-top:8px; }
  button { width:100%; padding:12px; margin-top:14px; border:none; border-radius:8px; background:#045c3f; color:#fff; font-weight:700; cursor:pointer; }
  .msg { padding:10px; border-radius:6px; margin-top:10px; text-align:center; }
  .sucesso { background:#e6f7ea; color:#0b6b33; border:1px solid #cde9d2; }
  .erro { background:#fdecea; color:#a94442; border:1px solid #f3c6c6; }
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

    <form method="post" action="register_user.php" enctype="multipart/form-data" novalidate>
      <label for="nome">Nome completo *</label>
      <input id="nome" name="nome" type="text" required value="<?= isset($nome) ? htmlspecialchars($nome) : '' ?>">

      <label for="email">E-mail *</label>
      <input id="email" name="email" type="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">

      <label for="senha">Senha *</label>
      <input id="senha" name="senha" type="password" required>

      <label for="telefone">Telefone</label>
      <input id="telefone" name="telefone" type="text" value="<?= isset($telefone) ? htmlspecialchars($telefone) : '' ?>">

      <label for="cpf">CPF * (somente números)</label>
      <input id="cpf" name="cpf" type="text" placeholder="12345678910" required value="<?= isset($cpf_raw) ? htmlspecialchars($cpf_raw) : '' ?>">

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
    updateRAState();

    document.querySelector('form').addEventListener('submit', function(e){
      const cpfEl = document.getElementById('cpf');
      if (cpfEl) cpfEl.value = cpfEl.value.replace(/\D/g,'');
    });
  })();
</script>
</body>
</html>
