<?php
// register_user.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// Só POST permitido (OPTIONS para preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método inválido. Use POST.']);
    exit;
}

// DB config — ajuste se necessário
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com o banco: ' . $e->getMessage()]);
    exit;
}

// Ler corpo (aceita application/json ou form)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input');
$data = null;

if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['erro' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
} else {
    $data = $_POST;
}

// campos esperados
$required = ['nome','email','senha','cpf','nascimento','ra'];
$missing = [];
foreach ($required as $f) {
    if (!isset($data[$f]) || trim((string)$data[$f]) === '') $missing[] = $f;
}
if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Campos obrigatórios ausentes: ' . implode(', ', $missing)]);
    exit;
}

// normalizar / extrair
$nome = trim($data['nome']);
$email = trim($data['email']);
$senha_raw = $data['senha'];
$telefone = isset($data['telefone']) ? trim($data['telefone']) : null;
$cpf = trim($data['cpf']);
$data_nascimento = trim($data['nascimento']); // espera YYYY-MM-DD
$ra = trim($data['ra']);
$foto_url = isset($data['foto_url']) ? trim($data['foto_url']) : null;

// validações básicas
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['erro' => 'E-mail inválido.']);
    exit;
}

// Garantir formato CPF básico: 11 dígitos (remover não-dígitos)
$cpf_digits = preg_replace('/\D/', '', $cpf);
if (strlen($cpf_digits) !== 11) {
    http_response_code(400);
    echo json_encode(['erro' => 'CPF inválido. Deve conter 11 dígitos.']);
    exit;
}
// opcional: formatar o CPF antes de gravar (xxx.xxx.xxx-xx)
$cpf_formatted = substr($cpf_digits,0,3) . '.' . substr($cpf_digits,3,3) . '.' . substr($cpf_digits,6,3) . '-' . substr($cpf_digits,9,2);

// validar data_nascimento (YYYY-MM-DD)
$date_ok = false;
try {
    $d = new DateTime($data_nascimento);
    $date_ok = true;
    $data_nascimento = $d->format('Y-m-d');
} catch (Exception $e) {
    $date_ok = false;
}
if (!$date_ok) {
    http_response_code(400);
    echo json_encode(['erro' => 'Data de nascimento inválida. Use YYYY-MM-DD.']);
    exit;
}

// hash da senha
$senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);

// Insert
try {
    $sql = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, data_nascimento, registro_academico, foto_url)
            VALUES (:nome, :email, :senha, :telefone, :cpf, :data_nascimento, :ra, :foto_url)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':senha', $senha_hash, PDO::PARAM_STR);
    $stmt->bindValue(':telefone', $telefone ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':cpf', $cpf_formatted, PDO::PARAM_STR);
    $stmt->bindValue(':data_nascimento', $data_nascimento, PDO::PARAM_STR);
    $stmt->bindValue(':ra', $ra, PDO::PARAM_STR);
    $stmt->bindValue(':foto_url', $foto_url ?: null, PDO::PARAM_STR);

    $stmt->execute();
    $newId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode(['mensagem' => 'Usuário criado com sucesso.', 'id' => $newId]);
    exit;

} catch (PDOException $e) {
    // detectar duplicidade (SQLSTATE 23000)
    $msg = $e->getMessage();
    if ($e->getCode() == 23000 || stripos($msg, 'Duplicate entry') !== false) {
        // tentar detectar qual campo duplicou: email / cpf / registro_academico
        $field = 'valor já existente';
        if (stripos($msg, 'email') !== false) $field = 'E-mail já cadastrado';
        elseif (stripos($msg, 'cpf') !== false) $field = 'CPF já cadastrado';
        elseif (stripos($msg, 'registro_academico') !== false || stripos($msg, 'registro_academico') !== false) $field = 'RA já cadastrado';

        http_response_code(409);
        echo json_encode(['erro' => 'Conflito: ' . $field]);
        exit;
    }

    // log para debug e resposta genérica
    error_log("Erro insert usuarios: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao criar usuário.']);
    exit;
}
