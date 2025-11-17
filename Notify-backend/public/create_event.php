<?php
// create_event.php - cria evento com upload de arquivos e IDs de turmas
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

session_start();

$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db";
$DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

// Config e Funções Helper de Imagem (sem alterações)
$UPLOAD_DIR = __DIR__ . '/uploads'; $UPLOAD_WEBPATH = 'uploads';
$MAX_FILE_BYTES = 10 * 1024 * 1024;
$ALLOWED_MIMES = [ 'image/jpeg' => '.jpg', 'image/jpg'  => '.jpg', 'image/png'  => '.png', 'image/webp' => '.webp' ];
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);
function respond($code, $payload) { http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function gen_filename($ext = '.jpg') { return 'evt_' . bin2hex(random_bytes(10)) . $ext; }
function create_image_from_file($path) { $data = @file_get_contents($path); if ($data === false) return false; return @imagecreatefromstring($data); }
function save_image_to_file($img, $path) { $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); if ($ext === 'jpg' || $ext === 'jpeg') return imagejpeg($img, $path, 90); elseif ($ext === 'png') return imagepng($img, $path, 3); elseif ($ext === 'webp' && function_exists('imagewebp')) return imagewebp($img, $path, 90); else return imagejpeg($img, $path, 90); }
function create_aspect_crop_3_1($srcPath, $destPath) { $srcImg = create_image_from_file($srcPath); if (!$srcImg) return false; $w = imagesx($srcImg); $h = imagesy($srcImg); if ($w <= 0 || $h <= 0) { imagedestroy($srcImg); return false; } $targetRatio = 3.0 / 1.0; $srcRatio = $w / $h; if ($srcRatio > $targetRatio) { $srcH = $h; $srcW = intval($h * $targetRatio); $srcX = intval(($w - $srcW) / 2); $srcY = 0; } else { $srcW = $w; $srcH = intval($w / $targetRatio); $srcX = 0; $srcY = intval(($h - $srcH) / 2); } $outW = 1200; $outH = 400; $thumb = imagecreatetruecolor($outW, $outH); imagealphablending($thumb, false); imagesavealpha($thumb, true); $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127); imagefill($thumb, 0, 0, $transparent); $ok = imagecopyresampled($thumb, $srcImg, 0, 0, $srcX, $srcY, $outW, $outH, $srcW, $srcH); if (!$ok) { imagedestroy($srcImg); imagedestroy($thumb); return false; } $res = save_image_to_file($thumb, $destPath); imagedestroy($srcImg); imagedestroy($thumb); return $res; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ["erro" => "Método não permitido. Use POST."]);
}

$data = $_POST;
if (empty($data['nome']) || empty($data['data_hora_inicio']) || empty($data['data_hora_fim'])) {
    respond(400, ["erro" => "Campos obrigatórios: nome, data_hora_inicio, data_hora_fim."]);
}

function normalize_datetime($s) { if (!$s) return null; $s2 = str_replace('T', ' ', $s); try { $d = new DateTime($s2); return $d->format('Y-m-d H:i:s'); } catch (Exception $e) { return null; } }

// Normalizar dados do $_POST
$nome = trim($data['nome']);
$descricao = isset($data['descricao']) ? trim($data['descricao']) : null;
$local = isset($data['local']) ? trim($data['local']) : null;
$data_hora_inicio = normalize_datetime($data['data_hora_inicio']);
$data_hora_fim = normalize_datetime($data['data_hora_fim']);
$limite_participantes = isset($data['limite_participantes']) && $data['limite_participantes'] !== '' ? (int)$data['limite_participantes'] : null;
$created_by = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : null;

// PROCESSAMENTO DE TURMAS
$turmas_ids = isset($data['turmas_permitidas']) && is_array($data['turmas_permitidas']) 
    ? array_map('intval', $data['turmas_permitidas']) 
    : [];
if (isset($data['publico_externo']) && $data['publico_externo'] == '1') {
    $turmas_ids[] = 0;
}
$turmas_json = json_encode(array_values(array_unique($turmas_ids)));

// (Processamento de Uploads - sem alterações)
$capa_url_db = null;
$imagem_completa_url_db = null;
try {
    if (isset($_FILES['capa_upload']) && $_FILES['capa_upload']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['capa_upload']; $fMime = mime_content_type($f['tmp_name']) ?: $f['type'];
        if ($f['size'] > $MAX_FILE_BYTES) throw new Exception('Arquivo de capa muito grande.');
        if (!array_key_exists($fMime, $ALLOWED_MIMES)) throw new Exception('Tipo de arquivo de capa não permitido.');
        $ext = $ALLOWED_MIMES[$fMime]; $fileName = gen_filename('_capa' . $ext);
        $destPath = $UPLOAD_DIR . '/' . $fileName;
        if (!create_aspect_crop_3_1($f['tmp_name'], $destPath)) throw new Exception('Falha ao processar imagem de capa.');
        $capa_url_db = $UPLOAD_WEBPATH . '/' . $fileName;
    }
    if (isset($_FILES['imagem_completa_upload']) && $_FILES['imagem_completa_upload']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['imagem_completa_upload']; $fMime = mime_content_type($f['tmp_name']) ?: $f['type'];
        if ($f['size'] > $MAX_FILE_BYTES) throw new Exception('Arquivo de imagem completa muito grande.');
        if (!array_key_exists($fMime, $ALLOWED_MIMES)) throw new Exception('Tipo de arquivo de imagem completa não permitido.');
        $ext = $ALLOWED_MIMES[$fMime]; $fileName = gen_filename('_full' . $ext);
        $destPath = $UPLOAD_DIR . '/' . $fileName;
        if (!move_uploaded_file($f['tmp_name'], $destPath)) throw new Exception('Falha ao salvar imagem completa.');
        $imagem_completa_url_db = $UPLOAD_WEBPATH . '/' . $fileName;
    }
} catch (Exception $e) { respond(400, ["erro" => $e->getMessage()]); }

if (!$data_hora_inicio || !$data_hora_fim) {
    respond(400, ["erro" => "Formato de data/hora inválido."]);
}

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) { respond(500, ["erro" => "Erro de conexão com o banco."]); }

// Detectar colunas
try {
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'eventos'");
    $colsStmt->execute([':db' => $DB_NAME]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
} catch (PDOException $e) { $cols = []; }

$insertCols = []; $placeholders = []; $params = [];

$map = [
    'nome' => $nome,
    'descricao' => $descricao,
    'local' => $local,
    'data_hora_inicio' => $data_hora_inicio,
    'data_hora_fim' => $data_hora_fim,
    'imagem_completa_url' => $imagem_completa_url_db,
    'capa_url' => $capa_url_db,
    'limite_participantes' => $limite_participantes,
    'turmas_permitidas' => $turmas_json,
    'colaboradores' => json_encode([]), 
    'inscricoes' => json_encode([]),
    'colaboradores_ids' => json_encode([]),
    'palestrantes_ids' => json_encode([]),
    'created_by' => $created_by
];

foreach ($map as $col => $val) {
    if (in_array($col, $cols)) {
        $insertCols[] = "`$col`";
        $placeholders[] = ":$col";
        $params[":$col"] = $val;
    }
}
if (empty($insertCols)) { respond(500, ["erro" => "A tabela 'eventos' não possui colunas esperadas."]); }

$sql = "INSERT INTO eventos (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        if ($v === null) $stmt->bindValue($k, null, PDO::PARAM_NULL);
        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $newId = $pdo->lastInsertId();
    respond(201, ["mensagem" => "Evento criado com sucesso.", "id" => $newId]);
} catch (PDOException $e) {
    // --- CORREÇÃO AQUI ---
    // Removida a palavra "aluno" que estava causando o erro de sintaxe
    error_log("create_event.php insert error: " . $e->getMessage());
    // --- FIM DA CORREÇÃO ---
    respond(500, ["erro" => "Erro ao inserir evento: " . $e->getMessage()]);
}
?>