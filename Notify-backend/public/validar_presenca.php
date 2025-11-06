<?php
// validar_presenca.php
// Híbrido:
// 1. GET: Mostra a UI do scanner (baseado no index4.html)
// 2. POST: Processa o AJAX do scanner (registra o CPF no evento)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();

// DB config - ajuste caso necessário
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

// Função helper para responder JSON e sair
function respond($code, $payload) {
    http_response_code($code);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Autenticação e Permissão (essencial para ambos GET e POST)
if (!isset($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        respond(401, ["erro" => "Usuário não autenticado."]);
    } else {
        header('Location: telainicio.html');
        exit;
    }
}
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);

// Conectar DB
try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPERES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log("validar_presenca.php DB connect error: " . $e->getMessage());
    respond(500, ["erro" => "Erro de conexão com o banco."]);
}

// 2. Lógica de API (quando o scanner envia um POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, ["erro" => "JSON inválido."]);
    }

    $eventId = isset($data['event_id']) ? intval($data['event_id']) : 0;
    $cpf = isset($data['cpf']) ? preg_replace('/\D/', '', (string)$data['cpf']) : '';

    if ($eventId <= 0) respond(400, ["erro" => "ID do evento inválido."]);
    if (strlen($cpf) !== 11) respond(400, ["erro" => "CPF inválido. Deve conter 11 dígitos."]);

    // Verificar permissão (mesma lógica de edit_event.php)
    try {
        $stmt = $pdo->prepare("SELECT created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch();
        if (!$event) respond(404, ["erro" => "Evento não encontrado."]);
    } catch (PDOException $e) {
        respond(500, ["erro" => "Erro ao buscar evento."]);
    }

    $createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
    $isDev = ($myRole === 2);
    $isCollaborator = false;
    if (!empty($event['colaboradores_ids'])) {
        $tmp = json_decode($event['colaboradores_ids'], true);
        if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
    }
    if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) {
        respond(403, ["erro" => "Permissão negada para registrar presença."]);
    }

    // 1. Buscar usuário pelo CPF
    try {
        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE cpf = :cpf LIMIT 1");
        $stmt->execute([':cpf' => $cpf]);
        $user = $stmt->fetch();
        if (!$user) {
            respond(404, ["erro" => "CPF não encontrado no cadastro."]);
        }
        $userId = intval($user['id']);
        $userName = $user['nome'];
    } catch (PDOException $e) {
        respond(500, ["erro" => "Erro ao buscar usuário."]);
    }

    // 2. Iniciar transação e registrar presença
    try {
        $pdo->beginTransaction();
        
        // Lock na linha do evento
        $stmt = $pdo->prepare("SELECT presencas FROM eventos WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch();
        
        $presencas = [];
        if (!empty($row['presencas'])) {
            $tmp = json_decode($row['presencas'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $presencas = array_map('intval', $tmp);
        }

        // Verificar se já está presente
        if (in_array($userId, $presencas, true)) {
            $pdo->rollBack();
            respond(200, [
                "mensagem" => "Presença de $userName (CPF: $cpf) já estava registrada.",
                "status" => "duplicado"
            ]);
        }

        // Adicionar e salvar
        $presencas[] = $userId;
        $jsonNovo = json_encode(array_values(array_unique($presencas)), JSON_UNESCAPED_UNICODE);
        
        $upd = $pdo->prepare("UPDATE eventos SET presencas = :presencas WHERE id = :id");
        $upd->execute([':presencas' => $jsonNovo, ':id' => $eventId]);

        $pdo->commit();

        respond(201, [
            "mensagem" => "Presença de $userName (CPF: $cpf) registrada com sucesso!",
            "status" => "registrado"
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("validar_presenca.php update error: " . $e->getMessage());
        respond(500, ["erro" => "Erro ao salvar presença."]);
    }
    // Fim da lógica POST
}


// 3. Lógica de Página (quando o usuário acessa com GET)
$eventId_get = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId_get <= 0) {
    http_response_code(400);
    echo "ID do evento não fornecido.";
    exit;
}

// Buscar dados do evento para exibir (e verificar permissão de acesso à página)
try {
    $stmt = $pdo->prepare("SELECT id, nome, created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId_get]);
    $event = $stmt->fetch();
    if (!$event) {
        http_response_code(404);
        echo "Evento não encontrado.";
        exit;
    }
    
    // Verificação de permissão (igual ao POST)
    $createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
    $isDev = ($myRole === 2);
    $isCollaborator = false;
    if (!empty($event['colaboradores_ids'])) {
        $tmp = json_decode($event['colaboradores_ids'], true);
        if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
    }
    if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) {
        http_response_code(403);
        echo "Permissão negada para acessar esta página de validação.";
        exit;
    }
    
    $eventName_php = $event['nome'];
    $eventId_php = $event['id'];

} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro ao carregar dados do evento.";
    exit;
}

// Se chegou até aqui (GET), renderiza o HTML do scanner
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Validar Presença - notIFy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.7/html5-qrcode.min.js"></script>

    <style>
        body { margin: 0; font-family: 'Arial', sans-serif; background-color: #fefefe; color: #2c3e50; text-align: center; }
        header { background-color: #2e5c44; padding: 20px 0; }
        header h1 { color: white; font-size: 28px; font-weight: 600; margin: 0; }
        .brand { font-weight: bold; font-size: 24px; color: white; }
        .brand .i { color: #c0392b; }
        .container { padding: 30px 20px; }
        #reader { width: 100%; max-width: 320px; margin: 20px auto; display: none; border: 2px solid #2e5c44; border-radius: 8px; }
        .btn { background-color: #2e5c44; color: white; border: none; padding: 14px 28px; font-size: 16px; border-radius: 8px; cursor: pointer; margin: 10px auto; display: block; width: 200px; }
        .btn:hover { background-color: #244a38; }
        .btn-secondary { background-color: #6b7280; width: 140px; display: inline-block; margin: 10px 5px; }
        .btn-secondary:hover { background-color: #4b5563; }
        .switch-btn { background: none; border: none; cursor: pointer; margin-top: 10px; padding: 0; }
        .switch-btn img, .switch-btn span { width: 36px; height: 36px; filter: invert(25%) sepia(11%) saturate(1000%) hue-rotate(80deg); }
        #result { margin-top: 20px; font-weight: bold; padding: 10px; border-radius: 6px; }
        #result.success { background-color: #dff0d8; color: #3c763d; }
        #result.error { background-color: #f2dede; color: #a94442; }
        #result.warning { background-color: #fcf8e3; color: #8a6d3b; }
        #result.pending { background-color: #d9edf7; color: #31708f; }
        h2 { font-size: 22px; color: #2e5c44; }
        .btn-group { display: flex; justify-content: center; gap: 20px; align-items: center; flex-wrap: wrap; }
        .switch-btn span { font-size: 32px; display: inline-block; line-height: 1; }
    </style>
</head>

<body>
    <header>
        <div class="brand">not<span class="i">iFy</span></div>
    </header>

    <div class="container">
        <h2>Validar Presença</h2>
        <h3 style="font-weight: normal; margin-top: -10px;">Evento: <strong><?= htmlspecialchars($eventName_php) ?></strong></h3>

        <button id="start-camera-btn" class="btn">Escanear QR Code</button>

        <div class="btn-group">
            <button id="switch-btn" class="switch-btn" title="Trocar câmera" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2e5c44" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 19H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/><path d="M13 5h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5"/><path d="m18 12-3-3 3-3"/><path d="m6 12 3 3-3 3"/></svg>
            </button>

            <button id="manual-input-btn" class="switch-btn" title="Inserir manualmente">
                <span>⌨️</span>
            </button>

            <button id="back-btn" class="btn btn-secondary" title="Voltar ao calendário">Voltar</button>
        </div>

        <div id="reader"></div>
        <div id="result"></div>
    </div>

    <script>
        // Passa o ID do evento do PHP para o JS
        const EVENT_ID = <?= json_encode($eventId_php); ?>;

        const startBtn = document.getElementById("start-camera-btn");
        const switchBtn = document.getElementById("switch-btn");
        const manualBtn = document.getElementById("manual-input-btn");
        const readerElem = document.getElementById("reader");
        const resultElem = document.getElementById("result");
        const backBtn = document.getElementById("back-btn");

        const html5QrCode = new Html5Qrcode("reader");
        let cameras = [];
        let currentCameraIndex = 0;
        let scanning = false;

        // Helper para mostrar resultados
        function showResult(message, type) { // type = success, error, warning, pending
            resultElem.textContent = message;
            resultElem.className = type;
        }

        // Função para registrar a presença (via AJAX)
        async function registerPresence(cpf) {
            showResult("Registrando CPF: " + cpf + "...", "pending");
            try {
                const response = await fetch('validar_presenca.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: EVENT_ID, cpf: cpf })
                });

                const result = await response.json();

                if (response.ok) {
                    showResult(result.mensagem, (result.status === 'duplicado' ? 'warning' : 'success'));
                } else {
                    showResult(result.erro || 'Erro desconhecido.', 'error');
                }
            } catch (err) {
                console.error("Fetch error:", err);
                showResult("Erro de conexão ao registrar presença.", 'error');
            }
            
            // Recomeça o scanner após 3 segundos
            setTimeout(() => {
                if (scanning) {
                     showResult("Aponte a câmera para o QR code...", "pending");
                }
            }, 3000);
        }

        // Iniciar scanner
        startBtn.onclick = async () => {
            if (scanning) return;
            startBtn.disabled = true;
            try {
                cameras = await Html5Qrcode.getCameras();
                if (cameras.length === 0) {
                    alert("Nenhuma câmera encontrada.");
                    startBtn.disabled = false;
                    return;
                }
                startScan(cameras[currentCameraIndex]);
                switchBtn.disabled = cameras.length < 2;
            } catch (err) {
                console.error("Erro ao acessar câmeras:", err);
                alert("Erro ao acessar câmeras. Dê permissão no navegador.");
                startBtn.disabled = false;
            }
        };

        // Trocar câmera
        switchBtn.onclick = async () => {
            if (!scanning || cameras.length < 2) return;
            await html5QrCode.stop();
            currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
            startScan(cameras[currentCameraIndex]);
        };

        // Inserção manual
        manualBtn.onclick = () => {
            const input = prompt("Digite os 11 dígitos do CPF do usuário:");
            const cpf = input ? input.replace(/\D/g, '') : '';
            
            if (cpf && cpf.length === 11) {
                registerPresence(cpf);
            } else if (input) {
                alert("Insira 11 dígitos numéricos.");
            }
        };

        // Função de escaneamento
        function startScan(camera) {
            readerElem.style.display = 'block';
            showResult("Aponte a câmera para o QR code...", "pending");
            
            html5QrCode.start(
                camera.id,
                { fps: 10, qrbox: { width: 250, height: 250 } },
                qrCodeMessage => {
                    // QR Code lido com sucesso
                    html5QrCode.pause(); // Pausa o scanner
                    const cpf = String(qrCodeMessage).replace(/\D/g, '');

                    if (cpf.length !== 11) {
                        showResult(`Código lido (${qrCodeMessage}) não é um CPF válido (11 dígitos).`, "error");
                        // Tenta de novo em 2s
                        setTimeout(() => html5QrCode.resume(), 2000);
                        return;
                    }
                    
                    // Envia para o backend
                    registerPresence(cpf);
                    // Reinicia o scanner após o registro (dentro do registerPresence)
                     setTimeout(() => {
                         if (scanning) html5QrCode.resume();
                     }, 3000); // Espera 3s para o usuário ler a msg
                },
                errorMessage => {
                    // Erros de "não encontrado" são ignorados
                }
            ).then(() => {
                scanning = true;
                startBtn.textContent = "Scanner Ativo";
            }).catch(err => {
                console.error("Erro ao iniciar câmera:", err);
                startBtn.disabled = false;
            });
        }

        // Botão Voltar: vai sempre para index.php
        backBtn.addEventListener('click', () => {
            if (scanning) {
                html5QrCode.stop().catch(err => console.error("Erro ao parar scanner.", err));
            }
            window.location.href = 'index.php';
        });
    </script>
</body>
</html>