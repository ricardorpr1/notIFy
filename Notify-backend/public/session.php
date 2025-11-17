<?php
// session.php

session_start();

// Inicia ou incrementa um contador simples de sessão (pode ser usado como teste)
if (!isset($_SESSION['count'])) {
    $_SESSION['count'] = 0;
} else {
    $_SESSION['count']++;
}

// Função para verificar se o usuário está logado
function verificarSessao() {
    if (!isset($_SESSION['usuario_id'])) {
        // Usuário não logado, redireciona
        header("Location: telainicio.html");
        exit();
    }
}
?>
