<?php
// login.php
session_start();

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: telalogin.html');
    exit;
}

// Configurações do banco (altere se necessário)
$host   = "127.0.0.1";
$port   = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // Falha na conexão
    header('Location: telalogin.html?error=server');
    exit;
}

// Pega dados do form
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) ? $_POST['senha'] : '';

if ($email === '' || $senha === '') {
    header('Location: telalogin.html?error=empty');
    exit;
}

try {
    // Buscar usuário pelo email (campo email é UNIQUE na tabela)
    $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // não encontrou
        header('Location: telalogin.html?error=notfound');
        exit;
    }

    $hash = $user['senha'] ?? '';

    if (!is_string($hash) || $hash === '') {
        header('Location: telalogin.html?error=server');
        exit;
    }

    // Verifica senha
    if (!password_verify($senha, $hash)) {
        header('Location: telalogin.html?error=badpass');
        exit;
    }

    // Autenticação bem-sucedida: criar sessão
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['usuario_nome'] = $user['nome'] ?? '';
    $_SESSION['usuario_email'] = $user['email'];

    // Redireciona para index.html (conforme pedido)
    header('Location: index.html');
    exit;

} catch (PDOException $e) {
    header('Location: telalogin.html?error=server');
    exit;
}
