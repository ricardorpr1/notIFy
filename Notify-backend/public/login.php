<?php
// login.php — autentica e grava role (0=user,1=organizador,2=dev) na sessão
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/login_debug.log');

// Config DB - ajuste se necessário
$host   = "127.0.0.1";
$port   = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: telalogin.html?error=empty');
    exit;
}

// Ler formulário ou JSON (compatível com fetch)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input');
if (stripos($contentType, 'application/json') !== false) {
    $body = json_decode($raw, true);
    $email = $body['email'] ?? null;
    $senha = $body['senha'] ?? $body['password'] ?? null;
} else {
    $email = $_POST['email'] ?? null;
    $senha = $_POST['senha'] ?? null;
}

// debug log helper (remova em produção)
function dbg($m){ file_put_contents(__DIR__ . '/login_debug.log', date('Y-m-d H:i:s') . " - $m\n", FILE_APPEND); }

dbg("Tentativa de login: email=" . substr($email ?? '',0,200) . " | senha_presente=" . ($senha? 'yes':'no'));

// validação básica
if (!$email || !$senha) {
    dbg("Campos vazios -> redirect");
    header('Location: telalogin.html?error=empty');
    exit;
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    dbg("DB error: " . $e->getMessage());
    header('Location: telalogin.html?error=server');
    exit;
}

try {
    // Seleciona também a coluna role
    $stmt = $pdo->prepare("SELECT id, nome, email, senha, role FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        dbg("Usuário não encontrado para email: $email");
        header('Location: telalogin.html?error=notfound');
        exit;
    }

    $hash = $user['senha'] ?? '';
    if (!password_verify($senha, $hash)) {
        dbg("Senha incorreta para id {$user['id']}");
        header('Location: telalogin.html?error=badpass');
        exit;
    }

    // Autenticação OK -> gravar sessão com role
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = intval($user['id']);
    $_SESSION['usuario_nome'] = $user['nome'] ?? '';
    $_SESSION['usuario_email'] = $user['email'] ?? '';
    // Se role não existir no DB, usar 0 como padrão
    $_SESSION['role'] = isset($user['role']) ? intval($user['role']) : 0;

    dbg("Login OK id={$_SESSION['usuario_id']} role={$_SESSION['role']}");

    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    dbg("Erro DB ao autenticar: " . $e->getMessage());
    header('Location: telalogin.html?error=server');
    exit;
}
