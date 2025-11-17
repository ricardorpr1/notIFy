<?php
// collaborators.php
session_start();

// Requer login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
$me = intval($_SESSION['usuario_id']);
$myRole = intval($_SESSION['role'] ?? 0);

// DB config - ajuste se necessário
$host = "127.0.0.1";
$port = "3306";
$dbname = "notify_db";
$dbuser = "tcc_notify";
$dbpass = "108Xk:C";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "Erro de conexão com o banco.";
    exit;
}

// obter event_id do GET
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) {
    echo "ID de evento inválido.";
    exit;
}

// buscar evento e created_by + colaboradores_ids
try {
    $stmt = $pdo->prepare("SELECT id, nome, created_by, colaboradores_ids FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        echo "Evento não encontrado.";
        exit;
    }
} catch (PDOException $e) {
    echo "Erro ao buscar evento.";
    exit;
}

// verificar permissão: só DEV (2) ou criador podem acessar essa página
$createdBy = array_key_exists('created_by', $event) && $event['created_by'] !== null ? intval($event['created_by']) : null;
$allowed = ($myRole === 2) || ($createdBy !== null && $createdBy === $me);
if (!$allowed) {
    http_response_code(403);
    echo "Permissão negada.";
    exit;
}

// obter todos os usuários (id, nome, email)
try {
    $stmt = $pdo->query("SELECT id, nome, email FROM usuarios ORDER BY id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Erro ao listar usuários.";
    exit;
}

// parse colaboradores_ids (JSON) -> array de ints (pode ser string vazia)
$current_collabs = [];
if (!empty($event['colaboradores_ids'])) {
    $tmp = json_decode($event['colaboradores_ids'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $current_collabs = array_map('intval', $tmp);
    } else {
        // fallback: tentar CSV
        $items = array_filter(array_map('trim', explode(',', (string) $event['colaboradores_ids'])));
        $current_collabs = array_map('intval', $items);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Adicionar Colaboradores — Evento <?= htmlspecialchars($event['nome']) ?></title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f7fb;
            margin: 0;
            padding: 20px
        }

        .card {
            background: #fff;
            max-width: 900px;
            margin: 0 auto;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06)
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px
        }

        .search {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 12px;
            box-sizing: border-box
        }

        .list {
            max-height: 60vh;
            overflow: auto;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 8px
        }

        .user-row {
            display: flex;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #f1f1f1
        }

        .user-row:last-child {
            border-bottom: none
        }

        .user-name {
            flex: 1;
            padding-left: 10px
        }

        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer
        }

        .btn-add {
            background: #228b22;
            color: #fff;
            margin-top: 12px
        }

        .btn-back {
            background: #6c757d;
            color: #fff
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="top">
            <h2>Adicionar colaboradores — <?= htmlspecialchars($event['nome']) ?></h2>
            <div>
                <button class="btn btn-back" onclick="location.href='index.php'">Voltar</button>
            </div>
        </div>

        <p>Marque os usuários que devem ser colaboradores deste evento. Somente o criador do evento e DEV podem
            gerenciar colaboradores.</p>

        <input id="searchBox" class="search" placeholder="Pesquisar por nome ou e-mail..." />

        <div class="list" id="usersList">
            <?php foreach ($users as $u):
                $uid = intval($u['id']);
                // pular o próprio usuário (não deve aparecer na lista)
                if ($uid === $me)
                    continue;

                $checked = in_array($uid, $current_collabs) ? 'checked' : '';
                ?>
                <div class="user-row" data-name="<?= htmlspecialchars(strtolower($u['nome'] . ' ' . $u['email'])) ?>">
                    <input type="checkbox" class="chkUser" data-uid="<?= $uid ?>" <?= $checked ?> />
                    <div class="user-name">
                        <strong><?= htmlspecialchars($u['nome']) ?></strong><br /><small><?= htmlspecialchars($u['email']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <button id="btnAdd" class="btn btn-add">Adicionar selecionados</button>
    </div>

    <script>
        const searchBox = document.getElementById('searchBox');
        const usersList = document.getElementById('usersList');

        searchBox.addEventListener('input', () => {
            const q = searchBox.value.trim().toLowerCase();
            Array.from(usersList.querySelectorAll('.user-row')).forEach(row => {
                const name = row.getAttribute('data-name') || '';
                row.style.display = name.indexOf(q) !== -1 ? 'flex' : 'none';
            });
        });

        document.getElementById('btnAdd').addEventListener('click', async () => {
            const checked = Array.from(document.querySelectorAll('.chkUser:checked')).map(cb => parseInt(cb.getAttribute('data-uid'), 10));
            if (checked.length === 0) {
                alert('Nenhum usuário selecionado.');
                return;
            }

            const payload = { event_id: <?= json_encode($eventId) ?>, add_ids: checked };

            try {
                const res = await fetch('update_collaborators.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const text = await res.text();
                let json;
                try { json = text ? JSON.parse(text) : {}; } catch (e) { alert('Resposta inválida do servidor'); console.error(text); return; }

                if (!res.ok) {
                    alert(json.erro || 'Erro ao adicionar colaboradores.');
                    return;
                }

                alert('Colaboradores adicionados com sucesso.');
                // voltar para index ou atualizar a página
                window.location.href = 'index.php';
            } catch (err) {
                console.error(err);
                alert('Erro de rede ao adicionar colaboradores.');
            }
        });
    </script>
</body>

</html>