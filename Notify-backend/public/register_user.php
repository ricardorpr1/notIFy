<?php
// register_user.php — cadastro com upload + turmas dinâmicas
session_start();

// DB config
$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db";
$DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

// Upload config
$UPLOAD_DIR = __DIR__ . '/uploads'; $UPLOAD_WEBPATH = 'uploads';
$MAX_FILE_BYTES = 3 * 1024 * 1024; $THUMB_SIZE = 200;
$ALLOWED_MIMES = [
    'image/jpeg' => '.jpg', 'image/jpg'  => '.jpg',
    'image/png'  => '.png', 'image/webp' => '.webp'
];

if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);

// Funções helper de imagem (sem alterações)
function gen_filename($ext = '.jpg') { return bin2hex(random_bytes(10)) . $ext; }
function safeTrim($v) { return is_string($v) ? trim($v) : $v; }
function create_image_from_file($path) { $data = @file_get_contents($path); if ($data === false) return false; return @imagecreatefromstring($data); }
function save_image_to_file($img, $path, $mime) { $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); if ($ext === 'jpg' || $ext === 'jpeg') { return imagejpeg($img, $path, 85); } elseif ($ext === 'png') { return imagepng($img, $path, 6); } elseif ($ext === 'webp') { if (function_exists('imagewebp')) { return imagewebp($img, $path, 80); } else { return imagejpeg($img, preg_replace('/\.\w+$/','.' . 'jpg', $path), 85); } } else { return imagejpeg($img, $path, 85); } }
function create_center_crop_thumbnail($srcPath, $destPath, $thumbSize, $ext) { $srcImg = create_image_from_file($srcPath); if (!$srcImg) return false; $w = imagesx($srcImg); $h = imagesy($srcImg); if ($w <= 0 || $h <= 0) { imagedestroy($srcImg); return false; } if ($w > $h) { $s = $h; $srcX = intval(($w - $h) / 2); $srcY = 0; } else { $s = $w; $srcX = 0; $srcY = intval(($h - $w) / 2); } $thumb = imagecreatetruecolor($thumbSize, $thumbSize); imagealphablending($thumb, false); imagesavealpha($thumb, true); $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127); imagefill($thumb, 0, 0, $transparent); $ok = imagecopyresampled($thumb, $srcImg, 0, 0, $srcX, $srcY, $thumbSize, $thumbSize, $s, $s); if (!$ok) { imagedestroy($srcImg); imagedestroy($thumb); return false; } $res = save_image_to_file($thumb, $destPath, null); imagedestroy($srcImg); imagedestroy($thumb); return $res; }

// Conectar ao DB (necessário para buscar cursos/turmas)
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                   $DB_USER, $DB_PASS, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco. A página de registro não pode carregar.");
}

// --- NOVOS DADOS PARA O FORMULÁRIO ---
$cursos = $pdo->query("SELECT id, nome, sigla FROM cursos ORDER BY nome")->fetchAll();
$turmas = $pdo->query("SELECT id, curso_id, nome_exibicao, ano FROM turmas ORDER BY ano, nome_exibicao")->fetchAll();
// --- FIM ---

$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = safeTrim($_POST['nome'] ?? '');
    $email = safeTrim($_POST['email'] ?? '');
    $senha_raw = $_POST['senha'] ?? '';
    $telefone = safeTrim($_POST['telefone'] ?? '');
    $cpf_raw = safeTrim($_POST['cpf'] ?? '');
    $data_nascimento = safeTrim($_POST['nascimento'] ?? '');
    $naoAluno = isset($_POST['nao_aluno']) && ($_POST['nao_aluno'] === '1' || $_POST['nao_aluno'] === 'on');
    
    $ra = safeTrim($_POST['ra'] ?? '');
    $curso_id = safeTrim($_POST['curso_id'] ?? ''); // (Não é mais usado, mas deixamos)
    $turma_id = safeTrim($_POST['turma_id'] ?? ''); // <-- CAMPO PRINCIPAL

    $missing = [];
    if ($nome === '') $missing[] = 'nome';
    if ($email === '') $missing[] = 'email';
    if ($senha_raw === '') $missing[] = 'senha';
    if ($cpf_raw === '') $missing[] = 'cpf';
    if ($data_nascimento === '') $missing[] = 'data de nascimento';

    // --- VALIDAÇÃO ATUALIZADA ---
    if (!$naoAluno && ($ra === '' || $turma_id === '')) {
        $missing[] = 'RA e Turma (ou marque "Não sou aluno")';
    }
    // --- FIM ---

    if (count($missing) > 0) {
        $erro = 'Preencha: ' . implode(', ', $missing);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } else {
        $cpf_digits = preg_replace('/\D+/', '', $cpf_raw);
        if (strlen($cpf_digits) !== 11) $erro = 'CPF inválido (precisa ter 11 dígitos).';
        else {
            $foto_thumb_web = null; 
            if (isset($_FILES['foto']) && is_array($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                // ... (lógica de upload idêntica) ...
            } 

            if ($erro === '') {
                // (Conexão com DB já está aberta)
                $senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);
                $role_default = 0;

                $registro_academico_db = $naoAluno ? null : ($ra === '' ? null : $ra);
                $turma_id_db = $naoAluno ? null : ($turma_id === '' ? null : intval($turma_id));

                // SQL com a nova coluna 'turma_id'
                $sql1 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, turma_id, foto_url, role)
                         VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :turma_id, :foto_url, :role)";
                // Fallback sem 'role'
                $sql2 = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, turma_id, foto_url)
                         VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :registro_academico, :turma_id, :foto_url)";

                try {
                    $stmt = $pdo->prepare($sql1);
                    $stmt->bindValue(':nome', $nome);
                    $stmt->bindValue(':email', $email);
                    $stmt->bindValue(':senha', $senha_hash);
                    $stmt->bindValue(':telefone', $telefone ?: null);
                    $stmt->bindValue(':cpf', $cpf_digits);
                    $stmt->bindValue(':data_nascimento', $data_nascimento ?: null);
                    $stmt->bindValue(':foto_url', $foto_thumb_web ?: null);
                    $stmt->bindValue(':role', $role_default, PDO::PARAM_INT);
                    
                    if ($registro_academico_db === null) $stmt->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                    else $stmt->bindValue(':registro_academico', $registro_academico_db);
                    
                    if ($turma_id_db === null) $stmt->bindValue(':turma_id', null, PDO::PARAM_NULL);
                    else $stmt->bindValue(':turma_id', $turma_id_db, PDO::PARAM_INT);
                    
                    $stmt->execute();
                    $mensagem = 'Usuário criado com sucesso!';
                    header("Refresh:1.2; url=login.php");
                    exit;

                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, 'Unknown column') !== false) {
                        try {
                            $stmt2 = $pdo->prepare($sql2);
                            // Bind (igual ao $stmt)
                            $stmt2->bindValue(':nome', $nome);
                            $stmt2->bindValue(':email', $email);
                            $stmt2->bindValue(':senha', $senha_hash);
                            $stmt2->bindValue(':telefone', $telefone ?: null);
                            $stmt2->bindValue(':cpf', $cpf_digits);
                            $stmt2->bindValue(':data_nascimento', $data_nascimento ?: null);
                            $stmt2->bindValue(':foto_url', $foto_thumb_web ?: null);
                            if ($registro_academico_db === null) $stmt2->bindValue(':registro_academico', null, PDO::PARAM_NULL);
                            else $stmt2->bindValue(':registro_academico', $registro_academico_db);
                            if ($turma_id_db === null) $stmt2->bindValue(':turma_id', null, PDO::PARAM_NULL);
                            else $stmt2->bindValue(':turma_id', $turma_id_db, PDO::PARAM_INT);
                            
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
            } 
        } 
    } 
} 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Cadastro — notIFy</title>
<style>
  body { font-family:Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding: 20px 0; }
  .card { background:#fff; padding:24px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:460px; }
  h2 { margin:0 0 12px 0; color:#045c3f; text-align:center; }
  label { display:block; margin-top:8px; font-weight:600; color:#333; }
  input, select { width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; }
  select:disabled { background: #eee; }
  .row { display:flex; gap:8px; align-items:center; margin-top:8px; }
  button { width:100%; padding:12px; margin-top:14px; border:none; border-radius:8px; background:#045c3f; color:#fff; font-weight:700; cursor:pointer; }
  .msg { padding:10px; border-radius:6px; margin-top:10px; text-align:center; }
  .sucesso { background:#e6f7ea; color:#0b6b33; border:1px solid #cde9d2; }
  .erro { background:#fdecea; color:#a94442; border:1px solid #f3c6c6; }
  #alunoContainer { transition: all 0.25s ease; } 
</style>
</head>
<body>
  <div class="card" role="main">
    <h2>Criar conta — notIFy</h2>

    <?php if ($mensagem): ?><div class="msg sucesso"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="msg erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

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

      <div id="alunoContainer">
        <label for="ra">Registro Acadêmico (RA) *</label>
        <input id="ra" name="ra" type="text" value="<?= isset($ra) ? htmlspecialchars($ra) : '' ?>">
        
        <label for="curso_id">Curso *</label>
        <select id="curso_id" name="curso_id" required>
            <option value="">Selecione seu curso</option>
            </select>

        <label for="turma_id">Turma *</label>
        <select id="turma_id" name="turma_id" required disabled>
            <option value="">Selecione um curso primeiro</option>
            </select>
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
  // --- JAVASCRIPT ATUALIZADO PARA DROPDOWNS DINÂMICOS ---
  
  // 1. Pega os dados do PHP
  const cursosData = <?php echo json_encode($cursos); ?>;
  const turmasData = <?php echo json_encode($turmas); ?>;

  // 2. Elementos do DOM
  const naoAluno = document.getElementById('naoAluno');
  const alunoContainer = document.getElementById('alunoContainer');
  const raInput = document.getElementById('ra');
  const cursoSelect = document.getElementById('curso_id');
  const turmaSelect = document.getElementById('turma_id');

  // 3. Popula o <select> de Cursos
  cursosData.forEach(curso => {
      const option = new Option(`${curso.nome} (${curso.sigla})`, curso.id);
      cursoSelect.add(option);
  });

  // 4. Listener para filtrar as Turmas
  cursoSelect.addEventListener('change', () => {
      const selectedCursoId = cursoSelect.value;
      
      // Limpa turmas anteriores
      turmaSelect.innerHTML = '';
      
      if (!selectedCursoId) {
          turmaSelect.add(new Option('Selecione um curso primeiro', ''));
          turmaSelect.disabled = true;
          return;
      }

      // Filtra as turmas do curso selecionado
      const turmasDoCurso = turmasData.filter(t => t.curso_id == selectedCursoId);
      
      if (turmasDoCurso.length > 0) {
          turmaSelect.add(new Option('Selecione sua turma', ''));
          turmasDoCurso.forEach(turma => {
              const option = new Option(turma.nome_exibicao, turma.id);
              turmaSelect.add(option);
          });
          turmaSelect.disabled = false;
      } else {
          turmaSelect.add(new Option('Nenhuma turma cadastrada', ''));
          turmaSelect.disabled = true;
      }
  });

  // 5. Listener para o checkbox "Não sou aluno"
  function updateAlunoFieldsState() {
    if (naoAluno.checked) {
      alunoContainer.style.display = 'none';
      raInput.value = '';
      cursoSelect.value = '';
      turmaSelect.value = '';
      turmaSelect.innerHTML = '<option value="">Selecione um curso primeiro</option>';
      turmaSelect.disabled = true;
    } else {
      alunoContainer.style.display = 'block';
    }
  }
  naoAluno.addEventListener('change', updateAlunoFieldsState);
  updateAlunoFieldsState(); // Executa ao carregar

  // 6. Limpa CPF (sem alteração)
  document.querySelector('form').addEventListener('submit', function(e){
    const cpfEl = document.getElementById('cpf');
    if (cpfEl) cpfEl.value = cpfEl.value.replace(/\D/g,'');
  });
  // --- FIM DO JAVASCRIPT ---
</script>
</body>
</html>