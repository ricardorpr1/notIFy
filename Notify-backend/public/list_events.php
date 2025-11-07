<?php
// list_events.php - retorna APENAS eventos permitidos para o usuário logado
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // (Mantido, mas agora o acesso é filtrado)

// --- ALTERAÇÃO: INICIAR SESSÃO ---
session_start();

// Pega os dados do usuário logado (ou 0/null se for convidado)
$user_id = intval($_SESSION['usuario_id'] ?? 0);
$user_role = intval($_SESSION['role'] ?? 0);
$user_turma_id = isset($_SESSION['turma_id']) ? intval($_SESSION['turma_id']) : null;
$is_aluno = ($user_turma_id !== null);
// --- FIM DA ALTERAÇÃO ---

$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";

function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log("list_events.php DB connect error: " . $e->getMessage());
    respond(500, ["erro" => "Erro de conexão com o banco."]);
}

// Helper para decodificar JSON (robusto, mantém inteiros)
function decodeJsonArray($jsonString) {
    if (empty($jsonString)) return [];
    if (is_array($jsonString)) return array_values($jsonString); 
    $decoded = json_decode($jsonString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Garante que os valores sejam inteiros
        return array_map('intval', array_values($decoded));
    }
    // Fallback para CSV (menos provável com a nova estrutura)
    $arr = array_filter(array_map('trim', explode(',', (string)$jsonString)));
    return array_map('intval', $arr);
}

try {
    // 1. Busca TODOS os eventos
    $stmt = $pdo->query("SELECT * FROM eventos ORDER BY data_hora_inicio ASC, id ASC");
    $rows = $stmt->fetchAll();
    $out = []; // Array de saída (eventos permitidos)

    // --- ALTERAÇÃO: LÓGICA DE FILTRAGEM ---
    foreach ($rows as $r) {
        
        $turmas_permitidas = decodeJsonArray($r['turmas_permitidas'] ?? null);
        $created_by = array_key_exists('created_by', $r) && $r['created_by'] !== null ? intval($r['created_by']) : null;
        $colabs_ids = decodeJsonArray($r['colaboradores_ids'] ?? null);
        $palestrantes_ids = decodeJsonArray($r['palestrantes_ids'] ?? null);

        $podeVer = false;

        // Regra 1: DEV (Role 2) pode ver tudo
        if ($user_role === 2) {
            $podeVer = true;
        }
        
        // Regra 2: Evento é público (array de turmas vazio)
        elseif (empty($turmas_permitidas)) {
            $podeVer = true;
        }
        
        // Regra 3: Usuário é o Criador, Colaborador ou Palestrante
        elseif (
            $user_id > 0 && 
            (
                ($created_by !== null && $created_by === $user_id) ||
                in_array($user_id, $colabs_ids, true) ||
                in_array($user_id, $palestrantes_ids, true)
            )
        ) {
            $podeVer = true;
        }
        
        // Regra 4: Usuário é Aluno (tem turma_id) e sua turma é permitida
        elseif ($is_aluno && $user_turma_id !== null && in_array($user_turma_id, $turmas_permitidas, true)) {
            $podeVer = true;
        }
        
        // Regra 5: Usuário NÃO é Aluno (externo) e "Público Externo" (ID 0) é permitido
        elseif (!$is_aluno && in_array(0, $turmas_permitidas, true)) {
            $podeVer = true;
        }

        // Se não passou em nenhuma regra, pular este evento
        if (!$podeVer) {
            continue; // Pula para o próximo evento no loop
        }

        // --- FIM DA LÓGICA DE FILTRAGEM ---

        // (Se chegou aqui, o usuário pode ver o evento)
        
        // Normalização dos dados (igual a antes)
        $id = isset($r['id']) ? (string)$r['id'] : null;
        $nome = $r['nome'] ?? ($r['title'] ?? '');
        $start = $r['data_hora_inicio'] ?? ($r['start'] ?? null) ;
        $end   = $r['data_hora_fim'] ?? ($r['end'] ?? null) ;
        $descricao = $r['descricao'] ?? ($r['description'] ?? '');
        $local = $r['local'] ?? ($r['location'] ?? '');
        $capa = $r['capa_url'] ?? ($r['capa'] ?? ($r['image'] ?? null));
        $imagem_completa = $r['imagem_completa_url'] ?? ($r['icone'] ?? null);
        $limite = array_key_exists('limite_participantes', $r) ? $r['limite_participantes'] : ($r['limit'] ?? null);
        $inscricoes = decodeJsonArray($r['inscricoes'] ?? null);
        $colabs_nomes = decodeJsonArray($r['colaboradores'] ?? null);

        // Montar objeto de evento para o FullCalendar
        $event = [
            "id" => $id, "nome" => $nome, "title" => $nome, "start" => $start, "end" => $end,
            "descricao" => $descricao, "description" => $descricao, "local" => $local, "location" => $local,
            "capa_url" => $capa, "imagem_completa_url" => $imagem_completa,
            "limite_participantes" => $limite !== null ? (int)$limite : null,
            "turmas_permitidas" => $turmas_permitidas, // Envia as turmas (embora o filtro já foi feito)
            "colaboradores" => $colabs_nomes,
            "colaboradores_ids" => $colabs_ids,
            "palestrantes_ids" => $palestrantes_ids, 
            "inscricoes" => $inscricoes,
            "created_by" => $created_by,
            
            "extendedProps" => [
                "descricao" => $descricao, "local" => $local, "capa_url" => $capa,
                "imagem_completa_url" => $imagem_completa,
                "limite_participantes" => $limite !== null ? (int)$limite : null,
                "turmas_permitidas" => $turmas_permitidas,
                "colaboradores" => $colabs_nomes,
                "colaboradores_ids" => $colabs_ids,
                "palestrantes_ids" => $palestrantes_ids, 
                "inscricoes" => $inscricoes,
                "created_by" => $created_by
            ]
        ];

        $out[] = $event;
    }
    // --- FIM DO LOOP FOREACH ---

    respond(200, $out); // Envia apenas os eventos filtrados

} catch (PDOException $e) {
    error_log("list_events.php query error: " . $e->getMessage());
    respond(500, ["erro" => "Erro ao buscar eventos."]);
}