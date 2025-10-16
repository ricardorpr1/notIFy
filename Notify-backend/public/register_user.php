<?php
// register_user.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- Configuração do banco (ajuste se necessário) ---
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$user = "tcc_notify";
$password = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro de conexão com o banco: " . $e->getMessage()]);
    exit;
}

// Ler entrada: aceitar JSON ou form-urlencoded (POST)
$raw = file_get_contents("php://input");
$data = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["erro" => "JSON inválido: " . json_last_error_msg()]);
        exit;
    }
} else {
    // fallback para form-data / application/x-www-form-urlencoded
    $data = $_POST;
}

// validações mínimas
$required = ['nome', 'email', 'senha', 'cpf', 'nascimento', 'ra']; // campos obrigatórios
$missing = [];
foreach ($required as $f) {
    if (!isset($data[$f]) || trim($data[$f]) === '') $missing[] = $f;
}
if (count($missing) > 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Campos obrigatórios ausentes: " . implode(', ', $missing)]);
    exit;
}

$nome = trim($data['nome']);
$email = trim($data['email']);
$senha_raw = $data['senha']; // texto cru
$telefone = isset($data['telefone']) ? trim($data['telefone']) : null;
$cpf = trim($data['cpf']);
$data_nascimento = trim($data['nascimento']); // espera YYYY-MM-DD
$ra = trim($data['ra']);
$foto_url = isset($data['foto_url']) ? trim($data['foto_url']) : null;

// validação simples de email e cpf (apenas formato mínimo)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["erro" => "E-mail inválido."]);
    exit;
}

// opcional: normalizar CPF (manter o que usuário enviou — DB tem CHAR(14))
if (strlen($cpf) < 11) {
    http_response_code(400);
    echo json_encode(["erro" => "CPF inválido."]);
    exit;
}

// hash da senha (bcrypt por padrão)
$senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);

// Inserir no banco
try {
    $sql = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url)
            VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :ra, :foto_url)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':senha', $senha_hash, PDO::PARAM_STR);
    $stmt->bindValue(':telefone', $telefone ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->bindValue(':data_nascimento', $data_nascimento ?: null, PDO::PARAM_STR); // espera 'YYYY-MM-DD'
    $stmt->bindValue(':ra', $ra, PDO::PARAM_STR);
    $stmt->bindValue(':foto_url', $foto_url ?: null, PDO::PARAM_STR);

    $stmt->execute();
    $newId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode(["mensagem" => "Usuário criado com sucesso.", "id" => $newId]);
    exit;
} catch (PDOException $e) {
    // tratar chave única duplicada (MySQL error code 1062)
    $msg = $e->getMessage();
    if ($e->getCode() == 23000 || stripos($msg, '1062 Duplicate entry') !== false) {
        // identificar campo duplicado pela mensagem (email, cpf, registro_academico)
        $field = "valor já existente";
        if (stripos($msg, 'email') !== false) $field = "email já cadastrado";
        elseif (stripos($msg, 'cpf') !== false) $field = "CPF já cadastrado";
        elseif (stripos($msg, 'registro_academico') !== false || stripos($msg, 'registro_academico') !== false) $field = "RA já cadastrado";

        http_response_code(409);
        echo json_encode(["erro" => "Conflito: " . $field]);
        exit;
    }

    http_response_code(500);
    echo json_encode(["erro" => "Erro ao inserir usuário: " . $e->getMessage()]);
    exit;
}
?>