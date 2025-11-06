<?php
// gerar_certificado.php
session_start();

// 1. Verificar Login
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado. Você precisa estar logado.");
}
$userId = intval($_SESSION['usuario_id']);

// 2. Verificar Parâmetros
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId <= 0) {
    die("ID do evento inválido.");
}

// 3. Conectar ao DB
$host = "127.0.0.1"; $port = "3306"; $dbname = "notify_db";
$dbuser = "tcc_notify"; $dbpass = "108Xk:C";
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco.");
}

// 4. Buscar Dados (Usuário e Evento)
try {
    $stmt_user = $pdo->prepare("SELECT nome, cpf FROM usuarios WHERE id = :id LIMIT 1");
    $stmt_user->execute([':id' => $userId]);
    $user = $stmt_user->fetch();
    if (!$user) die("Usuário não encontrado.");

    $stmt_event = $pdo->prepare("SELECT * FROM eventos WHERE id = :id LIMIT 1");
    $stmt_event->execute([':id' => $eventId]);
    $event = $stmt_event->fetch();
    if (!$event) die("Evento não encontrado.");

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// 5. Validar Datas do Evento
if (empty($event['data_hora_inicio']) || empty($event['data_hora_fim'])) {
    die("Dados de data inválidos. O evento precisa ter data de início e fim registradas para gerar o certificado.");
}

// 6. Verificações de Segurança e Lógica
date_default_timezone_set('America/Sao_Paulo'); 
$agora = new DateTime();
$fim_evento = new DateTime($event['data_hora_fim']); 

if ($agora <= $fim_evento) {
    die("O certificado só pode ser emitido após o término do evento.");
}

$presencas = json_decode($event['presencas'] ?? '[]', true);
if (!is_array($presencas) || !in_array($userId, $presencas)) {
    die("Certificado não disponível. Sua presença não foi registrada neste evento.");
}

// 7. Determinar Função (Lógica de Prioridade Corrigida)
$funcao = 'participante'; // Default

$palestrantes = json_decode($event['palestrantes_ids'] ?? '[]', true);
$colabs = json_decode($event['colaboradores_ids'] ?? '[]', true);
$inscritos = json_decode($event['inscricoes'] ?? '[]', true); 

// Checar na ordem de prioridade (do maior para o menor)
if (is_array($palestrantes) && in_array($userId, $palestrantes)) {
    $funcao = 'palestrante'; // 1. Prioridade Máxima
} elseif ($event['created_by'] == $userId) {
    $funcao = 'organizador'; // 2. Próxima
} elseif (is_array($colabs) && in_array($userId, $colabs)) {
    $funcao = 'colaborador'; // 3. Próxima
} elseif (is_array($inscritos) && in_array($userId, $inscritos)) {
    $funcao = 'participante'; // 4. Próxima
}

// 8. Gerar e Salvar Hash
$salt = "notify_ifmg_salt_secreto_2025"; 
$hash_seed = $userId . $eventId . $funcao . $salt;
$hash_final = substr(md5($hash_seed), 0, 12); 

try {
    $sql_insert_hash = "INSERT INTO certificados (usuario_id, evento_id, hash, funcao) 
                        VALUES (:uid, :eid, :hash, :funcao) 
                        ON DUPLICATE KEY UPDATE hash = VALUES(hash), funcao = VALUES(funcao)";
    $stmt_insert = $pdo->prepare($sql_insert_hash);
    $stmt_insert->execute([
        ':uid' => $userId,
        ':eid' => $eventId,
        ':hash' => $hash_final,
        ':funcao' => $funcao
    ]);
} catch (PDOException $e) {
    die("Erro ao salvar o hash do certificado: " . $e->getMessage());
}

// 9. Preparar dados para o JavaScript
$nome_user_php = $user['nome'];
$cpf_user_php = $user['cpf'];
$nome_evento_php = $event['nome'];

$data_inicio_obj = new DateTime($event['data_hora_inicio']);
$data_fim_obj = new DateTime($event['data_hora_fim']);
$hora_inicio_php = $data_inicio_obj->format('H:i');
$hora_fim_php = $data_fim_obj->format('H:i');

$data_inicio_formatada = $data_inicio_obj->format('d/m/Y');
$data_fim_formatada = $data_fim_obj->format('d/m/Y');

$data_texto_php = "";
if ($data_inicio_formatada == $data_fim_formatada) {
    $data_texto_php = "no dia " . $data_inicio_formatada;
} else {
    $data_texto_php = "dos dias " . $data_inicio_formatada . " a " . $data_fim_formatada;
}

$funcao_texto_php = ''; // Default para participante
if ($funcao == 'organizador') {
    $funcao_texto_php = 'como organizador';
} elseif ($funcao == 'colaborador') {
    $funcao_texto_php = 'como colaborador';
} elseif ($funcao == 'palestrante') {
    $funcao_texto_php = 'como palestrante';
}

$dados_json = json_encode([
    'nome' => $nome_user_php,
    'cpf' => $cpf_user_php,
    'evento' => $nome_evento_php,
    'horaInicio' => $hora_inicio_php,
    'horaFim' => $hora_fim_php,
    'dataTexto' => $data_texto_php,
    'funcaoTexto' => $funcao_texto_php,
    'hash' => $hash_final
], JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Gerando Certificado...</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; color: #333; }
    h2 { font-weight: 300; }
  </style>
</head>
<body>
<h2>Gerando seu certificado...</h2>
<p>O download deve iniciar automaticamente.</p>
<script>
// Pega os dados do PHP
const dados = <?php echo $dados_json; ?>;

function formatarCPF(cpf) {
  cpf = String(cpf).replace(/\D/g, "");
  return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
}

function gerarCertificado(nome, cpf_raw, evento, horaInicio, horaFim, dataTexto, funcaoTexto, hash) {
  const cpf = formatarCPF(cpf_raw);
  const inicio = new Date(`1970-01-01T${horaInicio}:00`);
  const fim = new Date(`1970-01-01T${horaFim}:00`);
  let diff = (fim - inicio) / (1000 * 60 * 60);
  if (diff < 0) diff += 24; 
  const totalHoras = diff.toFixed(1).replace('.0', '');
  let funcaoTextoFormatado = funcaoTexto ? ` ${funcaoTexto}` : ''; 
  let verbo = 'participou';
  if (funcaoTexto.includes('palestrante')) {
      verbo = 'palestrou';
      funcaoTextoFormatado = ''; 
  }
  let texto = `Certificamos que ${nome}, CPF ${cpf}, ${verbo} do(a) ${evento}${funcaoTextoFormatado}, ${dataTexto}, promovido pelo Instituto Federal de Educação, Ciência e Tecnologia de Minas Gerais - campus OURO BRANCO, perfazendo um total de ${totalHoras}h.`;

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });
  const img = new Image();
  img.src = "certificado-base.jpg"; 
  img.onload = () => {
    doc.addImage(img, "PNG", 0, 0, 297, 210);
    const pageWidth = doc.internal.pageSize.getWidth();
    doc.setFont("helvetica", "bold");
    doc.setFontSize(22);
    doc.text("INSTITUTO FEDERAL Minas Gerais", pageWidth / 2, 60, { align: "center" });
    doc.setFontSize(18);
    doc.text("CERTIFICADO", pageWidth / 2, 75, { align: "center" });
    doc.setFont("helvetica", "normal");
    doc.setFontSize(14);
    const textoFormatado = doc.splitTextToSize(texto, 240);
    const yInicial = 110; 
    const margemEsquerda = (pageWidth - 240) / 2; 
    doc.text(textoFormatado, margemEsquerda, yInicial, { align: "left" });
    doc.setFontSize(10);
    doc.text("Código de Verificação: " + hash, pageWidth / 2, 200, { align: "center" });
    doc.save("certificado-ifmg.pdf");
    setTimeout(() => { window.close(); }, 1000);
  };
   img.onerror = () => {
        alert("Erro ao carregar a imagem base do certificado. Verifique se o arquivo 'certificado-base.jpg' está na pasta correta.");
   }
}
window.onload = () => {
    gerarCertificado(
        dados.nome,
        dados.cpf,
        dados.evento,
        dados.horaInicio,
        dados.horaFim,
        dados.dataTexto,
        dados.funcaoTexto,
        dados.hash 
    );
};
</script>
</body>
</html>