<?php
// logout.php
session_start();

// Se for requisição POST (usada pelo fetch), retornamos JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpa sessão
    $_SESSION = [];

    // Remove cookie de sessão, se existir
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroi sessão
    session_destroy();

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['mensagem' => 'Logout realizado com sucesso.']);
    exit;
}

// Se acessado via GET (ex.: usuário clicou link), redireciona para tela inicial
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header('Location: telainicio.html');
exit;
