<?php
// login.php (robusto, com debug)
session_start();

// Config DB - ajuste se necessário
$host   = "127.0.0.1";
$port   = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

// Função auxiliar de log (arquivo no diretório do projeto)
function dbg($msg) {
    $file = __DIR__ . '/login_debug.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($file, "[$time] " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Apenas aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dbg("Requisição não-POST recebida: " . $_SERVER['REQUEST_METHOD']);
    header('Location: telalogin.html?error=empty');
    exit;
}

// Ler Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

// Primeiro tentar $_POST (form-encoded)
$email = $_POST['email'] ?? null;
$senha = $_POST['senha'] ?? null;

// Se estiver vazio, tentar ler JSON bruto (fetch)
if ((empty($email) && empty($senha)) && stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
        $email = $j['email'] ?? $j['user'] ?? null;
        $senha = $j['senha'] ?? $j['password'] ?? null;
        dbg("Recebeu JSON: " . substr($raw, 0, 1000));
    } else {
        dbg("JSON inválido recebido: " . substr($raw, 0, 1000));
    }
}

// Log do que chegou (NÃO logar senhas em produção; aqui é só para debug local)
dbg("POST/email: " . var_export($email, true) . " | senha_present? " . (empty($senha) ? 'no' : 'yes') . " | Content-Type: $contentType");

// Validação mínima
if (empty($email) || empty($senha)) {
    dbg("Campos vazios → redirect empty");
    header('Location: telalogin.html?error=empty');
    exit;
}

// Conectar ao banco
try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    dbg("Erro conexão DB: " . $e->getMessage());
    header('Location: telalogin.html?error=server');
    exit;
}

// Buscar usuário por email
try {
    $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        dbg("Usuário não encontrado para email: $email");
        header('Location: telalogin.html?error=notfound');
        exit;
    }

    $hash = $user['senha'] ?? '';
    if (!is_string($hash) || $hash === '') {
        dbg("Hash de senha inválido no DB para id {$user['id']}");
        header('Location: telalogin.html?error=server');
        exit;
    }

    if (!password_verify($senha, $hash)) {
        dbg("Senha incorreta para usuário id {$user['id']}");
        header('Location: telalogin.html?error=badpass');
        exit;
    }

    // Sucesso: criar sessão
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['usuario_nome'] = $user['nome'] ?? '';
    $_SESSION['usuario_email'] = $user['email'];

    dbg("Login OK para id {$user['id']} ({$user['email']})");
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    dbg("Erro no DB ao autenticar: " . $e->getMessage());
    header('Location: telalogin.html?error=server');
    exit;
}
