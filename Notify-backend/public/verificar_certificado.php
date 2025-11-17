<?php
// verificar_certificado.php
// Página pública (não requer login) para verificar um hash.
// Híbrido: GET (mostra form) e POST (processa API)

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db";
$dbuser = "tcc_notify"; $dbpass = "108Xk:C";

function respond($code, $payload) {
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- LÓGICA DA API (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $hash = $data['hash'] ?? '';

    if (empty($hash)) {
        respond(400, ['valido' => false, 'erro' => 'Hash não fornecido.']);
    }

    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        respond(500, ['valido' => false, 'erro' => 'Erro de conexão com o banco.']);
    }

    try {
        // Busca o hash na tabela 'certificados' e junta com usuarios e eventos
        $sql = "SELECT c.funcao, c.data_emissao, u.nome AS nome_usuario, e.nome AS nome_evento, e.data_hora_fim 
                FROM certificados c
                JOIN usuarios u ON c.usuario_id = u.id
                JOIN eventos e ON c.evento_id = e.id
                WHERE c.hash = :hash 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hash' => $hash]);
        $resultado = $stmt->fetch();

        if ($resultado) {
            // Hash encontrado, certificado é válido
            respond(200, ['valido' => true, 'dados' => $resultado]);
        } else {
            // Hash não encontrado
            respond(404, ['valido' => false, 'erro' => 'Código de verificação inválido.']);
        }
    } catch (PDOException $e) {
        respond(500, ['valido' => false, 'erro' => 'Erro ao consultar o banco.']);
    }
}

// --- PÁGINA (GET) ---
// Se não for POST, exibe a página HTML
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Verificador de Certificado - notIFy</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; }
    .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    h2 { text-align: center; color: #333; }
    input { display: block; margin: 10px 0; padding: 10px; border-radius: 5px; border: 1px solid #ccc; width: 300px; box-sizing: border-box; }
    button { background: #2196F3; color: white; border: none; cursor: pointer; padding: 10px; border-radius: 5px; width: 100%; font-size: 16px; }
    button:hover { background: #1976D2; }
    .resultado { margin-top: 15px; padding: 10px; border-radius: 6px; display: none; }
    .resultado.valido { background: #e6f7ea; color: #0b6b33; border: 1px solid #cde9d2; }
    .resultado.invalido { background: #fdecea; color: #a94442; border: 1px solid #f3c6c6; }
    .btn-back { display: inline-block; margin-top: 15px; color: #555; }
  </style>
</head>
<body>

<div class="card">
  <h2>Verificar Autenticidade</h2>
  <form id="verificadorHash">
    <input type="text" id="hashInput" placeholder="Digite o Código de Verificação" required>
    <button type="button" id="verifyBtn">Verificar</button>
  </form>
  <div class="resultado" id="resultado"></div>
  <a href="telainicio.html" class="btn-back">← Voltar</a>
</div>

<script>
document.getElementById('verifyBtn').addEventListener('click', async () => {
    const hashInput = document.getElementById('hashInput').value.trim();
    const resultadoDiv = document.getElementById('resultado');
    const btn = document.getElementById('verifyBtn');

    if (!hashInput) {
        alert("Por favor, digite o código de verificação.");
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Verificando...';
    resultadoDiv.style.display = 'none';

    try {
        const res = await fetch('verificar_certificado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ hash: hashInput })
        });

        const json = await res.json();

        if (res.ok && json.valido) {
            const dados = json.dados;
            // Formata a data para dd/mm/YYYY
            const dataFim = new Date(dados.data_hora_fim).toLocaleDateString('pt-BR');
            
            resultadoDiv.className = 'resultado valido';
            resultadoDiv.innerHTML = `
                <strong>Certificado VÁLIDO</strong><br>
                Emitido para: <strong>${dados.nome_usuario}</strong><br>
                Evento: <strong>${dados.nome_evento}</strong><br>
                (Encerrado em ${dataFim})<br>
                Função: ${dados.funcao}
            `;
            resultadoDiv.style.display = 'block';
        } else {
            resultadoDiv.className = 'resultado invalido';
            resultadoDiv.textContent = json.erro || 'Código de verificação inválido.';
            resultadoDiv.style.display = 'block';
        }

    } catch (err) {
        resultadoDiv.className = 'resultado invalido';
        resultadoDiv.textContent = 'Erro de conexão. Tente novamente.';
        resultadoDiv.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Verificar';
    }
});
</script>

</body>
</html>