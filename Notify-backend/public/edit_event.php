<?php
// edit_event.php - atualiza evento (com uploads de arquivo e IDs de turma)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

session_start();

// DB config
$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db";
$DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

// Upload config e Funções Helper (sem alterações)
$UPLOAD_DIR = __DIR__ . '/uploads'; $UPLOAD_WEBPATH = 'uploads';
$MAX_FILE_BYTES = 10 * 1024 * 1024;
$ALLOWED_MIMES = [ 'image/jpeg' => '.jpg', 'image/jpg'  => '.jpg', 'image/png'  => '.png', 'image/webp' => '.webp' ];
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);
function respond($code, $payload) { http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function gen_filename($ext = '.jpg') { return 'evt_' . bin2hex(random_bytes(10)) . $ext; }
function create_image_from_file($path) { $data = @file_get_contents($path); if ($data === false) return false; return @imagecreatefromstring($data); }
function save_image_to_file($img, $path) { $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); if ($ext === 'jpg' || $ext === 'jpeg') return imagejpeg($img, $path, 90); elseif ($ext === 'png') return imagepng($img, $path, 3); elseif ($ext === 'webp' && function_exists('imagewebp')) return imagewebp($img, $path, 90); else return imagejpeg($img, $path, 90); }
function create_aspect_crop_3_1($srcPath, $destPath) { $srcImg = create_image_from_file($srcPath); if (!$srcImg) return false; $w = imagesx($srcImg); $h = imagesy($srcImg); if ($w <= 0 || $h <= 0) { imagedestroy($srcImg); return false; } $targetRatio = 3.0 / 1.0; $srcRatio = $w / $h; if ($srcRatio > $targetRatio) { $srcH = $h; $srcW = intval($h * $targetRatio); $srcX = intval(($w - $srcW) / 2); $srcY = 0; } else { $srcW = $w; $srcH = intval($w / $targetRatio); $srcX = 0; $srcY = intval(($h - $srcH) / 2); } $outW = 1200; $outH = 400; $thumb = imagecreatetruecolor($outW, $outH); imagealphablending($thumb, false); imagesavealpha($thumb, true); $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127); imagefill($thumb, 0, 0, $transparent); $ok = imagecopyresampled($thumb, $srcImg, 0, 0, $srcX, $srcY, $outW, $outH, $srcW, $srcH); if (!$ok) { imagedestroy($srcImg); imagedestroy($thumb); return false; } $res = save_image_to_file($thumb, $destPath); imagedestroy($srcImg); imagedestroy($thumb); return $res; }
// --- FIM DOS HELPERS ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { respond(405, ["erro" => "Método não permitido. Use POST."]); }
if (!isset($_SESSION['usuario_id'])) { respond(401, ["erro" => "Usuário não autenticado."]); }
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);

$data = $_POST;
$eventoId = isset($data['id']) ? intval($data['id']) : 0;
if ($eventoId <= 0) respond(400, ["erro" => "ID do evento inválido."]);

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) { respond(500, ["erro" => "Erro de conexão com o banco."]); }

try {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventoId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) respond(404, ["erro" => "Evento não encontrado."]);
} catch (PDOException $e) { respond(500, ["erro" => "Erro ao acessar evento."]); }

$createdBy = $event['created_by'] !== null ? intval($event['created_by']) : null;
$isDev = ($myRole === 2);
$isCollaborator = false;
if (!empty($event['colaboradores_ids'])) {
    $tmp = json_decode($event['colaboradores_ids'], true);
    if (is_array($tmp)) $isCollaborator = in_array($me, array_map('intval', $tmp), true);
}
if (!($isDev || ($createdBy !== null && $createdBy === $me) || $isCollaborator)) {
    respond(403, ["erro" => "Permissão negada."]);
}

// (Processamento de Uploads - sem alterações)
$capa_url_db = null;
$imagem_completa_url_db = null;
$filesToSet = [];
try {
    if (isset($_FILES['capa_upload']) && $_FILES['capa_upload']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['capa_upload']; $fMime = mime_content_type($f['tmp_name']) ?: $f['type'];
        if ($f['size'] > $MAX_FILE_BYTES) throw new Exception('Arquivo de capa muito grande.');
        if (!array_key_exists($fMime, $ALLOWED_MIMES)) throw new Exception('Tipo de arquivo de capa não permitido.');
        $ext = $ALLOWED_MIMES[$fMime]; $fileName = gen_filename('_capa' . $ext);
        $destPath = $UPLOAD_DIR . '/' . $fileName;
        if (!create_aspect_crop_3_1($f['tmp_name'], $destPath)) throw new Exception('Falha ao processar imagem de capa.');
        $capa_url_db = $UPLOAD_WEBPATH . '/' . $fileName;
        $filesToSet['capa_url'] = $capa_url_db;
    }
    if (isset($_FILES['imagem_completa_upload']) && $_FILES['imagem_completa_upload']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['imagem_completa_upload']; $fMime = mime_content_type($f['tmp_name']) ?: $f['type'];
        if ($f['size'] > $MAX_FILE_BYTES) throw new Exception('Arquivo de imagem completa muito grande.');
        if (!array_key_exists($fMime, $ALLOWED_MIMES)) throw new Exception('Tipo de arquivo de imagem completa não permitido.');
        $ext = $ALLOWED_MIMES[$fMime]; $fileName = gen_filename('_full' . $ext);
        $destPath = $UPLOAD_DIR . '/' . $fileName;
        if (!move_uploaded_file($f['tmp_name'], $destPath)) throw new Exception('Falha ao salvar imagem completa.');
        $imagem_completa_url_db = $UPLOAD_WEBPATH . '/' . $fileName;
        $filesToSet['imagem_completa_url'] = $imagem_completa_url_db;
    }
} catch (Exception $e) { respond(400, ["erro" => $e->getMessage()]); }

// Campos de texto que podem ser atualizados
$updatableText = [
    'nome', 'descricao', 'local', 'data_hora_inicio', 'data_hora_fim', 
    'limite_participantes', 'colaboradores', // 'colaboradores' (nomes) é legado
    'colaboradores_ids', 'palestrantes_ids'
    // 'turmas_permitidas' é tratado separadamente
];

$fieldsToSet = [];
$params = [':id' => $eventoId];
$availableCols = array_map('strtolower', array_keys($event));

function parse_dt_or_null($s) { if ($s === null || $s === '') return null; $s2 = str_replace('T',' ',$s); try { $d = new DateTime($s2); return $d->format('Y-m-d H:i:s'); } catch (Exception $e) { return null; } }
function to_json_str($v) { if (is_array($v)) return json_encode(array_values($v), JSON_UNESCAPED_UNICODE); $tmp = json_decode($v, true); if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) return json_encode(array_values($tmp), JSON_UNESCAPED_UNICODE); $parts = array_filter(array_map('trim', explode(',', (string)$v))); return json_encode(array_values($parts), JSON_UNESCAPED_UNICODE); }

// 1. Adicionar campos de ARQUIVO (se foram processados)
foreach ($filesToSet as $colName => $val) {
    if (in_array($colName, $availableCols)) {
        $fieldsToSet[] = "`$colName` = :$colName";
        $params[":$colName"] = $val;
    }
}

// 2. Adicionar campos de TEXTO (enviados em $_POST)
foreach ($updatableText as $key) {
    if (!array_key_exists($key, $data)) continue;
    if (!in_array($key, $availableCols)) continue;
    $val = $data[$key]; $colName = $key;
    if ($key === 'data_hora_inicio' || $key === 'data_hora_fim') {
        $val = parse_dt_or_null($val);
        if ($val === null && !empty($data[$key])) respond(400, ["erro" => "Formato de data inválido para $key."]);
    } elseif ($key === 'colaboradores' || $key === 'colaboradores_ids' || $key === 'palestrantes_ids') {
        $val = to_json_str($val);
    } elseif ($key === 'limite_participantes') {
        $val = ($val === null || $val === '') ? null : intval($val);
    } else { $val = trim((string)$val); }
    $fieldsToSet[] = "`$colName` = :$colName";
    $params[":$colName"] = $val;
}

// --- 3. PROCESSAMENTO DE TURMAS ATUALIZADO ---
// Verifica se o campo 'turmas_permitidas' foi enviado (mesmo se for um array vazio)
if (isset($data['turmas_permitidas'])) {
    $turmas_ids = is_array($data['turmas_permitidas']) ? array_map('intval', $data['turmas_permitidas']) : [];
    
    // Adiciona "0" se o público externo foi marcado
    if (isset($data['publico_externo']) && $data['publico_externo'] == '1') {
        $turmas_ids[] = 0;
    }
    $turmas_json = json_encode(array_values(array_unique($turmas_ids)));
    
    if (in_array('turmas_permitidas', $availableCols)) {
        $fieldsToSet[] = "`turmas_permitidas` = :turmas_permitidas";
        $params[":turmas_permitidas"] = $turmas_json;
    }
}
// --- FIM ---

if (empty($fieldsToSet)) {
    respond(400, ["erro" => "Nenhum campo para atualizar."]);
}

$sql = "UPDATE eventos SET " . implode(', ', $fieldsToSet) . " WHERE id = :id";
try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        if ($v === null) $stmt->bindValue($k, null, PDO::PARAM_NULL);
        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    respond(200, ["mensagem" => "Evento atualizado com sucesso.", "id" => $eventoId]);
} catch (PDOException $e) {
    error_log("edit_event.php update error: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao atualizar evento."]);
}
?>