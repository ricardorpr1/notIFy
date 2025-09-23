<?php
    //parãmetros de conexão com BD
    //define('HOST', 'localhost');//define o endereço do servidor (CASA)
    define('HOST', '127.0.0.1:3306');//define  o endereço do do servidor (IFMG)
    define('USER', 'tcc_notify');; //nome do usuário
    define('PASSWORD', '108Xk:C'); //define a senha de acesso ao BD
    define('DB', 'notify_db'); //define o nome do Bando de Dados

    //criar um objeto de conexão
    $conn = new mysqli(HOST, USER, PASSWORD, DB);

    //checar a conexão
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    //echo "Conexão realizada com sucesso";
?>
