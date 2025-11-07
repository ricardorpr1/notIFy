<?php
// meus_eventos.php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
$userId = intval($_SESSION['usuario_id']);
$userName = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário');

// DB config
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db";
$dbuser = "tcc_notify"; $dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco: " . $e->getMessage());
}

// Buscar eventos
$eventos = [];
try {
    $sql = "SELECT id, nome, data_hora_inicio, data_hora_fim, local, created_by, colaboradores_ids, palestrantes_ids, inscricoes, presencas 
            FROM eventos 
            WHERE 
                created_by = :user_id 
                OR JSON_CONTAINS(colaboradores_ids, CAST(:user_id AS JSON), '$') 
                OR JSON_CONTAINS(palestrantes_ids, CAST(:user_id AS JSON), '$') 
                OR JSON_CONTAINS(inscricoes, CAST(:user_id AS JSON), '$')
            ORDER BY data_hora_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]); 
    $eventos = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() === '42000' || strpos($e->getMessage(), 'JSON_CONTAINS') !== false) { 
         try {
            $sql_fallback = "SELECT id, nome, data_hora_inicio, data_hora_fim, local, created_by, colaboradores_ids, palestrantes_ids, inscricoes, presencas
                             FROM eventos 
                             WHERE 
                                 created_by = :user_id 
                                 OR (colaboradores_ids LIKE :id_exact OR colaboradores_ids LIKE :id_start OR colaboradores_ids LIKE :id_middle OR colaboradores_ids LIKE :id_end)
                                 OR (palestrantes_ids LIKE :id_exact OR palestrantes_ids LIKE :id_start OR palestrantes_ids LIKE :id_middle OR palestrantes_ids LIKE :id_end)
                                 OR (inscricoes LIKE :id_exact OR inscricoes LIKE :id_start OR inscricoes LIKE :id_middle OR inscricoes LIKE :id_end)
                             ORDER BY data_hora_inicio DESC";
            $stmt_fallback = $pdo->prepare($sql_fallback);
            $stmt_fallback->execute([
                ':user_id'  => $userId, ':id_exact' => '[' . $userId . ']', ':id_start' => '[' . $userId . ',%',
                ':id_middle'=> '%,' . $userId . ',%', ':id_end'   => '%,' . $userId . ']'
            ]);
            $eventos = $stmt_fallback->fetchAll();
        } catch (PDOException $e2) { die("Erro ao buscar eventos (fallback): " . $e2->getMessage()); }
    } else { die("Erro ao buscar eventos: " . $e->getMessage()); }
}

function formatarData($s) { if (!$s) return '—'; try { $d = new DateTime($s); return $d->format('d/m/Y \à\s H:i'); } catch (Exception $e) { return 'Data inválida'; } }

date_default_timezone_set('America/Sao_Paulo'); 
$agora = new DateTime();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Meus Eventos — notIFy</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f6f7fb; margin: 0; padding: 20px; }
        .card { background: #fff; max-width: 900px; margin: 0 auto; padding: 18px; border-radius: 10px; box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06); }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .top h2 { margin: 0; color: #333; }
        .btn-back { background: #6c757d; color: #fff; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .event-list { list-style: none; padding: 0; }
        .event-item { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 10px; border-bottom: 1px solid #f1f1f1; }
        .event-info h3 { margin: 0 0 5px 0; color: #0056b3; }
        .event-info p { margin: 2px 0; font-size: 14px; color: #555; }
        /* --- BOTÕES DE AÇÃO --- */
        .event-actions { display: flex; gap: 10px; }
        .btn-action { 
            color: #fff; padding: 8px 12px; border: none; border-radius: 6px; 
            cursor: pointer; text-decoration: none; font-weight: bold;
            display: inline-block; font-size: 14px;
        }
        .btn-cert { background: #17a2b8; }
        .btn-cert:hover { background: #138496; }
        .btn-cert:disabled { background: #ccc; cursor: not-allowed; opacity: 0.7; }
        .btn-avaliar { background: #ffc107; color: #212529; }
        .btn-avaliar:hover { background: #e0a800; }
        /* --- FIM DOS BOTÕES --- */
        .no-events { text-align: center; color: #777; padding: 30px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="top">
            <h2>Meus Eventos (<?= $userName ?>)</h2>
            <a href="index.php" class="btn-back">Voltar ao Calendário</a>
        </div>
        <ul class="event-list">
            <?php if (empty($eventos)): ?>
                <li class="no-events">Você não está envolvido em nenhum evento.</li>
            <?php else: ?>
                <?php foreach ($eventos as $evento): ?>
                    <?php
                    $evento_terminou = false;
                    $esteve_presente = false;
                    if (!empty($evento['data_hora_fim'])) {
                        try { $fim_evento = new DateTime($evento['data_hora_fim']); if ($agora > $fim_evento) $evento_terminou = true; } catch (Exception $e) {}
                    }
                    $presencas = json_decode($evento['presencas'] ?? '[]', true);
                    if (is_array($presencas) && in_array($userId, $presencas)) $esteve_presente = true;
                    $papeis = [];
                    $palestrantes = json_decode($evento['palestrantes_ids'] ?? '[]', true);
                    if (is_array($palestrantes) && in_array($userId, $palestrantes)) $papeis[] = 'Palestrante';
                    if ($evento['created_by'] == $userId) $papeis[] = 'Criador';
                    $colabs = json_decode($evento['colaboradores_ids'] ?? '[]', true);
                    if (is_array($colabs) && in_array($userId, $colabs)) $papeis[] = 'Colaborador';
                    $inscritos = json_decode($evento['inscricoes'] ?? '[]', true);
                    if (is_array($inscritos) && in_array($userId, $inscritos)) $papeis[] = 'Inscrito';
                    $papeis_str = implode(', ', array_unique($papeis));
                    if (empty($papeis_str)) $papeis_str = 'N/A';
                    ?>
                    <li class="event-item">
                        <div class="event-info">
                            <h3><?= htmlspecialchars($evento['nome']) ?></h3>
                            <p><strong>Início:</strong> <?= formatarData($evento['data_hora_inicio']) ?></p>
                            <p><strong>Fim:</strong> <?= formatarData($evento['data_hora_fim']) ?></p>
                            <p><strong>Meu Papel:</strong> <?= htmlspecialchars($papeis_str) ?></p>
                        </div>
                        <div class="event-actions">
                            <?php
                            // Lógica do botão (Certificado E Avaliação)
                            if ($evento_terminou && $esteve_presente) {
                                echo '<a href="avaliar_evento.php?event_id=' . $evento['id'] . '" class="btn-action btn-avaliar">Avaliar</a>';
                                echo '<a href="gerar_certificado.php?event_id=' . $evento['id'] . '" class="btn-action btn-cert" target="_blank">Certificado</a>';
                            } elseif ($evento_terminou && !$esteve_presente) {
                                echo '<button class="btn-action btn-cert" disabled title="Presença não registrada neste evento.">Certificado</button>';
                            }
                            ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>