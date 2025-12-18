<?php
// adicionarevento.php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html'); exit;
}

// DB config
$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db";
$DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

// Buscar Cursos e Turmas
$cursos_map = [];
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $cursos = $pdo->query("SELECT id, nome, sigla FROM cursos ORDER BY nome")->fetchAll();
    $turmas = $pdo->query("SELECT id, curso_id, nome_exibicao FROM turmas ORDER BY ano, nome_exibicao")->fetchAll();
    
    // Agrupar turmas por curso
    foreach ($cursos as $curso) {
        $cursos_map[$curso['id']] = $curso;
        $cursos_map[$curso['id']]['turmas'] = [];
    }
    foreach ($turmas as $turma) {
        if (isset($cursos_map[$turma['curso_id']])) {
            $cursos_map[$turma['curso_id']]['turmas'][] = $turma;
        }
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados de turmas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Adicionar Evento - notIFy</title>
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
      box-sizing: border-box; /* Importante para mobile */
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
      bottom: 0;
      left: 0;
      width: 100%;
      text-align: center;
      font-weight: bold;
      font-size: 16px; /* Reduzido um pouco para mobile */
      color: #045c3f;
      user-select: none;
      background-color: #f4f6f8; /* Fundo para não sobrepor texto */
      padding: 10px 0;
      z-index: 100;
    }

    footer span {
      color: #c00000;
    }

    body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; padding-bottom: 60px; /* Espaço para o footer */ }
    
    h2 { text-align: center; color: #333; margin-top: 80px; /* Espaço para o header */ }
    
    form { 
        background: #fff; 
        padding: 20px; 
        border-radius: 8px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        max-width: 500px; 
        margin: 20px auto; 
        width: 100%; /* Mobile full width */
        box-sizing: border-box;
    }
    
    label { display: block; margin-top: 10px; font-weight: bold; }
    
    input[type="text"], input[type="datetime-local"], input[type="number"], textarea { 
        width: 100%; 
        padding: 12px; /* Padding maior para toque */
        margin-top: 5px; 
        border: 1px solid #ccc; 
        border-radius: 5px; 
        box-sizing: border-box; 
    }
    
    button { 
        width: 100%; /* Botão full width */
        margin-top: 20px; 
        padding: 12px 20px; 
        border: none; 
        border-radius: 8px; 
        background-color: #228B22; 
        color: #fff; 
        cursor: pointer; 
        font-size: 16px;
        font-weight: bold;
    }
    button:hover { background-color: #1c731c; }
    
    /* --- BOTÃO VOLTAR (ESTILIZADO) --- */
    .voltar { 
        display: block; 
        max-width: 500px; /* Mesma largura do form */
        margin: 15px auto 40px auto; /* Margem inferior extra para não colar no footer */
        padding: 12px;
        background-color: #6c757d; /* Cor cinza padrão para 'cancelar/voltar' */
        color: #fff; 
        text-align: center; 
        text-decoration: none; 
        border-radius: 8px;
        font-weight: bold;
        box-sizing: border-box;
        transition: background 0.2s;
    }
    .voltar:hover { background-color: #5a6268; }
    /* --- FIM --- */

    input[type="file"] { background: #f9f9f9; padding: 10px; width: 100%; box-sizing: border-box; }
    .note { font-size: 12px; color: #666; font-weight: normal; }
    
    /* --- CSS PARA AS TURMAS --- */
    .turmas-container {
        border: 1px solid #ddd; border-radius: 6px; padding: 10px;
        margin-top: 5px; max-height: 200px; overflow-y: auto;
    }
    .turma-curso-grupo { margin-bottom: 8px; }
    .turma-curso-grupo strong { font-size: 14px; color: #0056b3; }
    .turma-checkbox { margin-right: 15px; display: inline-block; padding: 5px 0; } /* Padding para toque */
    .turma-checkbox input { width: auto; margin-right: 5px; transform: scale(1.2); }
    /* --- FIM --- */
  </style>
</head>
<body>
<header>
    <h1>Not<span>IF</span>y</h1>
  </header>

  <h2>Adicionar Novo Evento</h2>
  
  <form id="eventoForm">
    <label>Nome do Evento:</label>
    <input type="text" id="nome" name="nome" required>
    <label>Descrição:</label>
    <textarea id="descricao" name="descricao" rows="3"></textarea>
    <label>Local:</label>
    <input type="text" id="local" name="local">
    <label>Data e Hora de Início:</label>
    <input type="datetime-local" id="data_hora_inicio" name="data_hora_inicio" required>
    <label>Data e Hora de Fim:</label>
    <input type="datetime-local" id="data_hora_fim" name="data_hora_fim" required>
    <label>Imagem de Capa <span class="note">(Será cortada para 3:1)</span></label>
    <input type="file" id="capa_upload" name="capa_upload" accept="image/jpeg,image/png,image/webp">
    <label>Imagem Completa <span class="note">(Exibida no modal)</span></label>
    <input type="file" id="imagem_completa_upload" name="imagem_completa_upload" accept="image/jpeg,image/png,image/webp">
    <label>Limite de Participantes:</label>
    <input type="number" id="limite_participantes" name="limite_participantes">

    <label>Turmas Permitidas</label>
    <div class="turmas-container">
        <?php foreach ($cursos_map as $curso): ?>
            <div class="turma-curso-grupo">
                <strong><?= htmlspecialchars($curso['nome']) ?></strong><br>
                <?php if (empty($curso['turmas'])): ?>
                    <small>Nenhuma turma cadastrada</small>
                <?php else: ?>
                    <?php foreach ($curso['turmas'] as $turma): ?>
                        <label class="turma-checkbox">
                            <input type="checkbox" name="turmas_permitidas[]" value="<?= $turma['id'] ?>">
                            <?= htmlspecialchars($turma['nome_exibicao']) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <hr style="border:0; border-top:1px dashed #ccc; margin: 8px 0;">
        <label class="turma-checkbox">
            <input type="checkbox" name="publico_externo" value="1">
            <strong>Público Externo (Não-alunos)</strong>
        </label>
    </div>
    <button type="submit" id="submitBtn">Criar Evento</button>
  </form>

  <a href="index.php" class="voltar">← Voltar para o calendário</a>

  <footer>
    Not<span>IF</span>y © 2025
  </footer>

  <script>
    document.getElementById("eventoForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const form = e.target;
      const submitBtn = document.getElementById('submitBtn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Enviando...';

      const formData = new FormData();
      
      // Adiciona campos de texto
      formData.append('nome', document.getElementById("nome").value);
      formData.append('descricao', document.getElementById("descricao").value);
      formData.append('local', document.getElementById("local").value);
      formData.append('data_hora_inicio', document.getElementById("data_hora_inicio").value);
      formData.append('data_hora_fim', document.getElementById("data_hora_fim").value);
      formData.append('limite_participantes', document.getElementById("limite_participantes").value);
      
      // Adiciona arquivos
      const capaFile = document.getElementById("capa_upload").files[0];
      if (capaFile) formData.append('capa_upload', capaFile);
      const imagemCompletaFile = document.getElementById("imagem_completa_upload").files[0];
      if (imagemCompletaFile) formData.append('imagem_completa_upload', imagemCompletaFile);

      // --- LÓGICA DE TURMAS ATUALIZADA ---
      // Adiciona os IDs das turmas selecionadas
      const turmasCheckboxes = form.querySelectorAll('input[name="turmas_permitidas[]"]:checked');
      turmasCheckboxes.forEach(chk => {
          formData.append('turmas_permitidas[]', chk.value);
      });
      
      // Adiciona o público externo
      if (form.querySelector('input[name="publico_externo"]:checked')) {
          formData.append('publico_externo', '1');
      }
      // --- FIM DA LÓGICA ---
      
      try {
        const response = await fetch("create_event.php", {
          method: "POST",
          body: formData 
        });
        const result = await response.json();
        if (response.ok) {
          alert(result.mensagem || "Evento criado com sucesso!");
          window.location.href = "index.php";
        } else {
          alert(result.erro || "Erro ao criar o evento.");
        }
      } catch (err) {
        alert("Erro na requisição: " + err.message);
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Criar Evento';
      }
    });
  </script>
</body>
</html>