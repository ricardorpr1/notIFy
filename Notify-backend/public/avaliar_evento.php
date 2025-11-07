<?php
// avaliar_evento.php
// Híbrido: GET (mostra form) e POST (salva avaliação)
session_start();

// 1. Requer Login
if (!isset($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(401);
        echo json_encode(['erro' => 'Usuário não autenticado.']);
        exit;
    }
    header('Location: telainicio.html');
    exit;
}
$userId = intval($_SESSION['usuario_id']);

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db";
$dbuser = "tcc_notify"; $dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco.");
}

// 2. Lógica de API (quando o formulário envia um POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json; charset=UTF-8");
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    $eventId = isset($data['event_id']) ? intval($data['event_id']) : 0;
    $nota = isset($data['nota']) ? intval($data['nota']) : 0;
    $comentario = isset($data['comentario']) ? trim($data['comentario']) : '';

    if ($eventId <= 0) { http_response_code(400); echo json_encode(['erro' => 'ID do evento inválido.']); exit; }
    if ($nota < 1 || $nota > 5) { http_response_code(400); echo json_encode(['erro' => 'Nota inválida (deve ser 1-5).']); exit; }

    // (Verificações de permissão são feitas antes de salvar)
    try {
        $stmt = $pdo->prepare("SELECT data_hora_fim, presencas FROM eventos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch();
        if (!$event) { http_response_code(404); echo json_encode(['erro' => 'Evento não encontrado.']); exit; }
        
        // Evento já terminou?
        date_default_timezone_set('America/Sao_Paulo'); 
        $agora = new DateTime();
        $fim_evento = new DateTime($event['data_hora_fim']);
        if ($agora <= $fim_evento) {
            http_response_code(403); echo json_encode(['erro' => 'Você só pode avaliar este evento após o término.']); exit;
        }
        
        // Usuário estava presente?
        $presencas = json_decode($event['presencas'] ?? '[]', true);
        if (!is_array($presencas) || !in_array($userId, $presencas)) {
            http_response_code(403); echo json_encode(['erro' => 'Você precisa ter a presença validada para avaliar.']); exit;
        }
        
        // Salvar no DB (INSERT... ON DUPLICATE KEY UPDATE)
        $sql = "INSERT INTO avaliacoes_evento (evento_id, usuario_id, nota, comentario)
                VALUES (:eid, :uid, :nota, :comentario)
                ON DUPLICATE KEY UPDATE nota = VALUES(nota), comentario = VALUES(comentario), data_avaliacao = CURRENT_TIMESTAMP";
        
        $stmt_save = $pdo->prepare($sql);
        $stmt_save->execute([
            ':eid' => $eventId,
            ':uid' => $userId,
            ':nota' => $nota,
            ':comentario' => $comentario
        ]);

        echo json_encode(['mensagem' => 'Avaliação salva com sucesso!']);
        exit;
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['erro' => $e->getMessage()]); exit;
    }
}

// 3. Lógica da Página (quando o usuário acessa com GET)
$eventId_get = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId_get <= 0) die("ID do evento não fornecido.");

$evento_nome = '';
$avaliacao_anterior = null;
$erro_permissao = null;

try {
    $stmt = $pdo->prepare("SELECT nome, data_hora_fim, presencas FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId_get]);
    $event = $stmt->fetch();
    if (!$event) die("Evento não encontrado.");
    $evento_nome = $event['nome'];

    // Checar se pode avaliar
    date_default_timezone_set('America/Sao_Paulo'); 
    $agora = new DateTime();
    $fim_evento = new DateTime($event['data_hora_fim']);
    if ($agora <= $fim_evento) $erro_permissao = 'Você só pode avaliar este evento após o término.';
    
    $presencas = json_decode($event['presencas'] ?? '[]', true);
    if (!is_array($presencas) || !in_array($userId, $presencas)) $erro_permissao = 'Sua presença não foi registrada neste evento, por isso não é possível avaliá-lo.';

    // Buscar avaliação anterior (se houver)
    $stmt_prev = $pdo->prepare("SELECT nota, comentario FROM avaliacoes_evento WHERE evento_id = :eid AND usuario_id = :uid LIMIT 1");
    $stmt_prev->execute([':eid' => $eventId_get, ':uid' => $userId]);
    $avaliacao_anterior = $stmt_prev->fetch();

} catch (Exception $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Avaliar Evento — notIFy</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f6f7fb; margin: 0; padding: 20px; }
    .card { background: #fff; max-width: 600px; margin: 20px auto; padding: 18px; border-radius: 10px; box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06); }
    .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .top h2 { margin: 0; color: #333; font-size: 20px; }
    .btn-back { background: #6c757d; color: #fff; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
    
    .rating-css { display: inline-block; }
    .rating-css > input { display: none; }
    .rating-css > label { color: #ccc; cursor: pointer; font-size: 40px; }
    .rating-css > label:hover,
    .rating-css > label:hover ~ label,
    .rating-css > input:checked ~ label { color: #FFD700; }
    /* Inverte a ordem das estrelas (para CSS hover funcionar) */
    .rating-css { unicode-bidi: bidi-override; direction: rtl; text-align: left; }
    .rating-css > label { padding: 0 5px; }
    
    textarea { width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; padding: 10px; font-family: Arial; min-height: 120px; margin-top: 10px; }
    .btn-save { background: #228b22; color: #fff; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; margin-top: 15px; }
    .btn-save:disabled { background: #aaa; }
    .error-box { background: #fdecea; color: #a94442; border: 1px solid #f3c6c6; padding: 15px; border-radius: 6px; }
    .msg-box { padding: 10px; border-radius: 6px; margin-top: 10px; text-align: center; }
    .msg-success { background: #e6f7ea; color: #0b6b33; }
    .msg-error { background: #fdecea; color: #a94442; }
</style>
</head>
<body>
    <div class="card">
        <div class="top">
            <h2>Avaliar Evento</h2>
            <a href="meus_eventos.php" class="btn-back">Voltar</a>
        </div>
        <h3 style="margin: 10px 0; font-weight: normal;"><?= htmlspecialchars($evento_nome) ?></h3>
        
        <?php if ($erro_permissao): ?>
            <div class="error-box"><?= htmlspecialchars($erro_permissao) ?></div>
        <?php else: ?>
            <form id="formAvaliacao">
                <p>Sua nota (de 1 a 5 estrelas):</p>
                <div class="rating-css">
                    <input type="radio" id="rating5" name="rating" value="5" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 5) ? 'checked' : '' ?>><label for="rating5">★</label>
                    <input type="radio" id="rating4" name="rating" value="4" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 4) ? 'checked' : '' ?>><label for="rating4">★</label>
                    <input type="radio" id="rating3" name="rating" value="3" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 3) ? 'checked' : '' ?>><label for="rating3">★</label>
                    <input type="radio" id="rating2" name="rating" value="2" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 2) ? 'checked' : '' ?>><label for="rating2">★</label>
                    <input type="radio" id="rating1" name="rating" value="1" <?= ($avaliacao_anterior && $avaliacao_anterior['nota'] == 1) ? 'checked' : '' ?>><label for="rating1">★</label>
                </div>

                <p style="margin-top: 20px;">Deixe um comentário (opcional):</p>
                <textarea id="comentario" name="comentario"><?= htmlspecialchars($avaliacao_anterior['comentario'] ?? '') ?></textarea>

                <button type="submit" id="btnSalvar" class="btn-save">Salvar Avaliação</button>
            </form>
            <div id="msgBox" class="msg-box" style="display: none;"></div>
        <?php endif; ?>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formAvaliacao');
    if (!form) return; // Sai se o form não existir (em caso de erro de permissão)

    const btnSalvar = document.getElementById('btnSalvar');
    const msgBox = document.getElementById('msgBox');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const notaSelecionada = document.querySelector('input[name="rating"]:checked');
        if (!notaSelecionada) {
            alert('Por favor, selecione uma nota (de 1 a 5 estrelas).');
            return;
        }

        const payload = {
            event_id: <?= $eventId_get; ?>,
            nota: parseInt(notaSelecionada.value, 10),
            comentario: document.getElementById('comentario').value.trim()
        };

        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';
        msgBox.style.display = 'none';

        try {
            const res = await fetch('avaliar_evento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const json = await res.json();

            if (res.ok) {
                msgBox.textContent = json.mensagem;
                msgBox.className = 'msg-box msg-success';
                msgBox.style.display = 'block';
                // Redireciona após 2s
                setTimeout(() => {
                    window.location.href = 'meus_eventos.php';
                }, 2000);
            } else {
                throw new Error(json.erro || 'Erro desconhecido');
            }
        } catch (err) {
            msgBox.textContent = err.message;
            msgBox.className = 'msg-box msg-error';
            msgBox.style.display = 'block';
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Salvar Avaliação';
        }
    });
});
</script>
</body>
</html>