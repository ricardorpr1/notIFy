<?php
// perfil_editar.php — edição de perfil com turmas dinâmicas
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: telainicio.html'); exit; }

$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db";
$DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

// (Configurações de Upload e Funções Helper - sem alterações)
$UPLOAD_DIR = __DIR__ . '/uploads'; $UPLOAD_WEBPATH = 'uploads'; $MAX_FILE_BYTES = 3 * 1024 * 1024;
$ALLOWED_MIMES = [ 'image/jpeg' => '.jpg', 'image/jpg'  => '.jpg', 'image/png'  => '.png', 'image/webp' => '.webp' ];
$THUMB_SIZE = 200;
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);
function gen_filename($ext = '.jpg') { return bin2hex(random_bytes(10)) . $ext; }
function safeTrim($v) { return is_string($v) ? trim($v) : $v; }
function create_image_from_file($path) { $data = @file_get_contents($path); if ($data === false) return false; return @imagecreatefromstring($data); }
function save_image_to_file($img, $path) { $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); if ($ext === 'jpg' || $ext === 'jpeg') return imagejpeg($img, $path, 85); elseif ($ext === 'png') return imagepng($img, $path, 6); elseif ($ext === 'webp' && function_exists('imagewebp')) return imagewebp($img, $path, 80); else return imagejpeg($img, $path, 85); }
function create_center_crop_thumbnail($srcPath, $destPath, $thumbSize) { $srcImg = create_image_from_file($srcPath); if (!$srcImg) return false; $w = imagesx($srcImg); $h = imagesy($srcImg); if ($w <= 0 || $h <= 0) { imagedestroy($srcImg); return false; } if ($w > $h) { $s = $h; $srcX = intval(($w - $h) / 2); $srcY = 0; } else { $s = $w; $srcX = 0; $srcY = intval(($h - $w) / 2); } $thumb = imagecreatetruecolor($thumbSize, $thumbSize); imagealphablending($thumb, false); imagesavealpha($thumb, true); $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127); imagefill($thumb, 0, 0, $transparent); $ok = imagecopyresampled($thumb, $srcImg, 0, 0, $srcX, $srcY, $thumbSize, $thumbSize, $s, $s); if (!$ok) { imagedestroy($srcImg); imagedestroy($thumb); return false; } $res = save_image_to_file($thumb, $destPath); imagedestroy($srcImg); imagedestroy($thumb); return $res; }

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
                   $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException $e) { die('Erro ao conectar ao banco.'); }

$userid = intval($_SESSION['usuario_id']);
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userid]);
    $user = $stmt->fetch();
    if (!$user) { session_destroy(); header('Location: telainicio.html'); exit; }
} catch (PDOException $e) { die('Erro ao buscar usuário.'); }

$nome = $user['nome'] ?? '';
$email = $user['email'] ?? '';
$telefone = $user['telefone'] ?? '';
$cpf = $user['cpf'] ?? '';
$data_nascimento = $user['data_nascimento'] ?? '';
$registro_academico = $user['registro_academico'] ?? '';
$foto_url = $user['foto_url'] ?? '';

// --- NOVOS DADOS (CURSO/TURMA) ---
$user_turma_id = $user['turma_id'] ?? null;
$user_curso_id = null;
if ($user_turma_id) {
    // Se temos a turma, precisamos descobrir o curso pai
    $stmt_turma = $pdo->prepare("SELECT curso_id FROM turmas WHERE id = :tid");
    $stmt_turma->execute([':tid' => $user_turma_id]);
    $user_curso_id = $stmt_turma->fetchColumn();
}
$naoAlunoChecked = ($user_turma_id === null && empty($registro_academico));

// Pega todos os cursos e turmas para os dropdowns
$cursos = $pdo->query("SELECT id, nome, sigla FROM cursos ORDER BY nome")->fetchAll();
$turmas = $pdo->query("SELECT id, curso_id, nome_exibicao FROM turmas ORDER BY ano, nome_exibicao")->fetchAll();
// --- FIM ---

$erro = ''; $mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_post = safeTrim($_POST['nome'] ?? '');
    $email_post = safeTrim($_POST['email'] ?? '');
    $telefone_post = safeTrim($_POST['telefone'] ?? '');
    $cpf_post = safeTrim($_POST['cpf'] ?? '');
    $data_nascimento_post = safeTrim($_POST['nascimento'] ?? '');
    $nova_senha = $_POST['senha'] ?? '';
    
    $naoAluno = isset($_POST['nao_aluno']) && ($_POST['nao_aluno']==='1' || $_POST['nao_aluno']==='on');
    $ra_post = $naoAluno ? null : safeTrim($_POST['ra'] ?? '');
    $turma_id_post = $naoAluno ? null : safeTrim($_POST['turma_id'] ?? ''); // <-- CAMPO PRINCIPAL

    if ($nome_post === '' || $email_post === '' || $cpf_post === '' || $data_nascimento_post === '') {
        $erro = 'Preencha nome, e-mail, CPF e data de nascimento.';
    } elseif (!filter_var($email_post, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } else {
        $cpf_digits = preg_replace('/\D+/', '', $cpf_post);
        if (strlen($cpf_digits) !== 11) $erro = 'CPF inválido.';
    }

    $new_foto_web = $foto_url;
    if ($erro === '' && isset($_FILES['foto']) && is_array($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        // ... (lógica de upload) ...
    }

    if ($erro === '') {
        try {
            // ... (Checagens de duplicidade - sem alteração) ...

            if ($erro === '') {
                $params = [
                    ':id' => $userid,
                    ':nome' => $nome_post,
                    ':email' => $email_post,
                    ':telefone' => $telefone_post ?: null,
                    ':cpf' => $cpf_digits,
                    ':data_nascimento' => $data_nascimento_post ?: null,
                    ':registro_academico' => ($ra_post === null || $ra_post === '') ? null : $ra_post,
                    ':turma_id' => ($turma_id_post === null || $turma_id_post === '') ? null : intval($turma_id_post), // <-- ATUALIZADO
                    ':foto_url' => $new_foto_web ?: null
                ];
                $updateFields = [
                    'nome = :nome', 'email = :email', 'telefone = :telefone',
                    'cpf = :cpf', 'data_nascimento = :data_nascimento',
                    'registro_academico = :registro_academico',
                    'turma_id = :turma_id', // <-- ATUALIZADO
                    'foto_url = :foto_url'
                ];

                if ($nova_senha !== null && $nova_senha !== '') {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $updateFields[] = 'senha = :senha';
                    $params[':senha'] = $senha_hash;
                }
                $sql = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $mensagem = 'Perfil atualizado com sucesso.';
                // Atualiza variáveis locais
                $nome = $nome_post; $email = $email_post; $telefone = $telefone_post;
                $cpf = $cpf_digits; $data_nascimento = $data_nascimento_post;
                $registro_academico = ($ra_post === null || $ra_post === '') ? null : $ra_post;
                $user_turma_id = ($turma_id_post === null || $turma_id_post === '') ? null : $turma_id_post; // <-- ATUALIZADO
                $foto_url = $new_foto_web;
                $naoAlunoChecked = ($user_turma_id === null && empty($registro_academico));
                $_SESSION['usuario_nome'] = $nome;
                if (!empty($foto_url)) $_SESSION['foto_url'] = $foto_url;
            }
        } catch (PDOException $e) { $erro = 'Erro ao atualizar perfil.'; }
    }
}
function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Editar Perfil — notIFy</title>
<style>
   header {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      background-color: #045c3f;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 10px 20px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    header img {
      width: 50px;
      height: 50px;
      cursor: pointer;
      margin-right: 10px;
    }

    header h1 {
      font-size: 28px;
      font-weight: bold;
      margin: 0;
    }

    header span {
      color: #c00000;
    }

footer {
      position: fixed;
      bottom: 10px;
      width: 100%;
      text-align: center;
      font-weight: bold;
      font-size: 20px;
      color: #045c3f;
      user-select: none;
    }

    footer span {
      color: #c00000;
    }


  body { font-family:Arial, sans-serif; background:#f4f6f8; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding: 20px 0; }
  .card { background:#fff; padding:22px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:520px; max-width:95%; }
  h2 { margin:0 0 12px 0; color:#045c3f; text-align:center; }
  label { display:block; margin-top:10px; font-weight:600; color:#333; }
  input[type="text"], input[type="email"], input[type="date"], input[type="password"], input[type="file"], select { width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #ccc; box-sizing:border-box; }
  select:disabled { background: #eee; }
  .row { display:flex; gap:10px; align-items:center; margin-top:8px; }
  .btns { display:flex; gap:8px; margin-top:14px; justify-content:flex-end; }
  button { padding:10px 14px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
  .btn-save { background:#045c3f; color:#fff; }
  .btn-cancel { background:#6c757d; color:#fff; }
  .msg { padding:10px; border-radius:6px; margin-top:10px; }
  .msg.sucesso { background:#e6f7ea; color:#0b6b33; }
  .msg.erro { background:#fdecea; color:#a94442; }
  #alunoContainer { transition: all 0.25s ease; } 
  .thumb { width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid #ddd; display:block; margin-top:8px; }
</style>
</head>
<body>
<header>
    <h1>Not<span>IF</span>y</h1>
  </header>
<footer>
    Not<span>IF</span>y © 2025
  </footer>
  <div class="card" role="main">
    <h2>Editar perfil</h2>
    <?php if (!empty($mensagem)): ?><div class="msg sucesso"><?= esc($mensagem) ?></div><?php endif; ?>
    <?php if (!empty($erro)): ?><div class="msg erro"><?= esc($erro) ?></div><?php endif; ?>

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

      <div id="alunoContainer">
        <label for="ra">Registro Acadêmico (RA)</label>
        <input type="text" id="ra" name="ra" value="<?= esc($registro_academico) ?>">

        <label for="curso_id">Curso</label>
        <select id="curso_id" name="curso_id">
            <option value="">Selecione seu curso</option>
            </select>

        <label for="turma_id">Turma</label>
        <select id="turma_id" name="turma_id">
            <option value="">Selecione um curso primeiro</option>
            </select>
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
  // --- JAVASCRIPT ATUALIZADO ---
  
  // 1. Pega os dados do PHP
  const cursosData = <?php echo json_encode($cursos); ?>;
  const turmasData = <?php echo json_encode($turmas); ?>;
  const userCursoId = <?php echo json_encode($user_curso_id); ?>;
  const userTurmaId = <?php echo json_encode($user_turma_id); ?>;

  // 2. Elementos do DOM
  const naoAluno = document.getElementById('nao_aluno');
  const alunoContainer = document.getElementById('alunoContainer');
  const raInput = document.getElementById('ra');
  const cursoSelect = document.getElementById('curso_id');
  const turmaSelect = document.getElementById('turma_id');

  // 3. Popula Cursos e Turmas
  function popularCursos() {
    cursoSelect.innerHTML = '<option value="">Selecione seu curso</option>';
    cursosData.forEach(curso => {
      const option = new Option(`${curso.nome} (${curso.sigla})`, curso.id);
      option.selected = (curso.id == userCursoId); // Pré-seleciona
      cursoSelect.add(option);
    });
  }

  function popularTurmas(selectedCursoId) {
    turmaSelect.innerHTML = ''; // Limpa
    if (!selectedCursoId) {
        turmaSelect.add(new Option('Selecione um curso primeiro', ''));
        turmaSelect.disabled = true;
        return;
    }
    const turmasDoCurso = turmasData.filter(t => t.curso_id == selectedCursoId);
    if (turmasDoCurso.length > 0) {
        turmaSelect.add(new Option('Selecione sua turma', ''));
        turmasDoCurso.forEach(turma => {
            const option = new Option(turma.nome_exibicao, turma.id);
            option.selected = (turma.id == userTurmaId); // Pré-seleciona
            turmaSelect.add(option);
        });
        turmaSelect.disabled = false;
    } else {
        turmaSelect.add(new Option('Nenhuma turma cadastrada', ''));
        turmaSelect.disabled = true;
    }
  }
  
  // 4. Listeners
  cursoSelect.addEventListener('change', () => {
      popularTurmas(cursoSelect.value);
  });

  function updateAlunoFieldsState() {
    if (naoAluno.checked) {
      alunoContainer.style.display = 'none';
      if (raInput) raInput.value = '';
      if (cursoSelect) cursoSelect.value = '';
      if (turmaSelect) {
          turmaSelect.value = '';
          popularTurmas(null); // Limpa e desabilita
      }
    } else {
      alunoContainer.style.display = 'block';
    }
  }
  naoAluno.addEventListener('change', updateAlunoFieldsState);

  // 5. Inicialização
  popularCursos(); // Popula cursos
  popularTurmas(userCursoId); // Popula turmas baseado no curso pré-selecionado
  updateAlunoFieldsState(); // Esconde se for "não aluno"
  
  // 6. Limpa CPF (sem alteração)
  document.querySelector('form').addEventListener('submit', function(e){
    const cpfEl = document.getElementById('cpf');
    if (cpfEl) cpfEl.value = cpfEl.value.replace(/\D/g,'');
  });
})();
</script>
</body>
</html>