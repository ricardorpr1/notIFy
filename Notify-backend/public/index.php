<?php
// index.php - Página principal (CORRIGIDA: Cores, Permissões e Z-Index)
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: telainicio.html');
  exit;
}
$DB_HOST = "127.0.0.1";
$DB_PORT = "3306";
$DB_NAME = "notify_db";
$DB_USER = "tcc_notify";
$DB_PASS = "108Xk:C";
$cursos_map_json = "[]";
$turmas_json = "[]";
try {
  $pdo_idx = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  $cursos = $pdo_idx->query("SELECT id, nome, sigla FROM cursos ORDER BY nome")->fetchAll();
  $turmas = $pdo_idx->query("SELECT id, curso_id, nome_exibicao FROM turmas ORDER BY ano, nome_exibicao")->fetchAll();
  $cursos_map = [];
  foreach ($cursos as $curso) {
    $cursos_map[$curso['id']] = $curso;
    $cursos_map[$curso['id']]['turmas'] = [];
  }
  foreach ($turmas as $turma) {
    if (isset($cursos_map[$turma['curso_id']])) {
      $cursos_map[$turma['curso_id']]['turmas'][] = $turma;
    }
  }
  $cursos_map_json = json_encode(array_values($cursos_map));
  $turmas_json = json_encode($turmas);
} catch (PDOException $e) { /* Falha silenciosa */
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>notIFy — Calendário</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      font-family: 'Inter', Arial, Helvetica, sans-serif;
      background: #f0f2f5;
      color: #333;
    }

    /* ===== HEADER ===== */
    header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background-color: #045c3f;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      z-index: 3000;
    }

    header h1 {
      font-size: 32px;
      font-weight: 800;
      margin: 0;
      letter-spacing: -1px;
    }

    header span {
      color: #c00000;
      font-weight: 900;
    }

    /* ===== CALENDÁRIO GERAL ===== */
    #calendarContainer {
      padding: 90px 20px 20px 290px; /* Espaçamento ajustado */
      max-width: 1400px;
      margin: 0 auto;
    }

    #calendar {
      padding: 18px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      margin: 0;
    }

    .fc-toolbar-title {
        font-weight: 700 !important;
        font-size: 24px !important;
        color: #045c3f;
    }
    .fc-button-primary {
        background-color: #045c3f !important;
        border-color: #045c3f !important;
        color: #fff !important;
        transition: 0.2s;
    }
    .fc-button-primary:hover {
        background-color: #05774f !important;
        border-color: #05774f !important;
    }

    /* ===== SIDEBAR (Navegação) ===== */
    #sidebar {
      position: fixed;
      top: 72px;
      left: 0;
      width: 250px;
      height: calc(100vh - 72px);
      background: #ffffff;
      padding: 20px 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      border-right: 1px solid #e0e0e0;
      box-shadow: 4px 0 16px rgba(0, 0, 0, 0.08);
      z-index: 2000;
    }

    /* ===== BOTÕES DA SIDEBAR ===== */
    .sidebar-btn {
      background: #045c3f;
      color: #fff;
      border: none;
      padding: 14px 20px;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      display: flex; /* Importante: flex */
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: background 0.3s, transform 0.3s, box-shadow 0.3s;
      width: 100%;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      box-sizing: border-box;
    }

    /* Correção de Permissões: Ocultar por padrão com !important para sobrescrever o display:flex acima */
    #addEventBtn, #permissionsBtn, #gerenciarCursosBtn {
        display: none !important; 
    }

    .sidebar-btn:hover {
      background: #05774f;
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }

    /* ===== ÁREA DO USUÁRIO ===== */
    #userArea {
      position: fixed;
      top: 10px;
      right: 20px;
      z-index: 3100;
      display: flex;
      gap: 10px;
      align-items: center;
    }

    #profileImg {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      cursor: pointer;
      border: 3px solid #fff;
      transition: 0.2s;
    }

    #profileImg:hover {
        opacity: 0.8;
    }

    #logoutMini {
      background: #d9534f;
      color: #fff;
      border: none;
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.2s;
      display: none;
    }

    /* ===== OVERLAY E MODAL ===== */
    #overlay, #profileOverlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 4000; /* Overlay alto */
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px);
    }

    .modal {
      background: #fff;
      width: 90%;
      max-width: 600px;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35);
      max-height: 95vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 15px;
      margin-bottom: 15px;
    }

    .modal-title {
      margin: 0;
      font-size: 24px;
      font-weight: 700;
      color: #045c3f;
    }

    .modal-close {
      background: #f0f2f5;
      border: none;
      font-size: 20px;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      cursor: pointer;
      line-height: 1;
    }

    .modal-body { display: block; }

    .modal-image-full {
      width: 100%;
      max-width: 100%;
      height: auto;
      object-fit: contain;
      border-radius: 8px;
      margin-top: 15px;
      border: 1px solid #e0e0e0;
    }

    .modal-desc { white-space: pre-wrap; color: #555; line-height: 1.6; }
    .modal-body p strong { color: #045c3f; }

    .modal-footer {
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #e0e0e0;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      padding: 10px 16px;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      transition: 0.2s;
    }

    .btn:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }

    /* CORES DOS BOTÕES */
    .btn-close { background: #6c757d; color: #fff; }
    .btn-delete { background: #d9534f; color: #fff; }
    .btn-inscribe { background: #0b6bff; color: #fff; }
    .btn-inscribe:disabled { background: #aaa; cursor: not-allowed; }
    .btn-export { background: #007bff; color: #fff; }
    .btn-collab { background: #6f42c1; color: #fff; }
    .btn-edit { background: #17a2b8; color: #fff; }
    .btn-palestrante { background: #fd7e14; color: #fff; }
    .btn-avaliar { background: #ffc107; color: #212529; }
    .btn-ver-avaliacoes { background: #343a40; color: #fff; }
    #btnValidate { background: #10b981 !important; }
    
    /* Perfil Card */
    #profileCard {
      background: #fff;
      width: 360px;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35);
      border: 1px solid #e0e0e0;
    }
    #cardPhoto { width: 90px !important; height: 90px !important; border-radius: 50% !important; object-fit: cover; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
    #cardName { font-size: 22px !important; font-weight: 700 !important; color: #045c3f; }
    #cardRole { font-size: 14px !important; color: #c00000 !important; font-weight: 600; }
    #profileCard p { border-bottom: 1px dotted #e0e0e0; padding-bottom: 5px; margin: 8px 0 !important; }
    .qr-box { border-top: 1px solid #e0e0e0; padding-top: 15px; margin-top: 15px !important; }
    #qrImage { border: 4px solid #fff !important; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); }
    
    /* Eventos no Calendário */
    .fc-event-title-custom {
      font-weight: 700;
      color: #fff;
      text-shadow: 0 0 3px rgba(0, 0, 0, 0.8);
      padding: 4px 6px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 13px;
    }
    .fc-event-cover-img {
      width: 100%;
      height: auto;
      aspect-ratio: 3 / 1;
      object-fit: cover;
      border-radius: 4px; 
      margin-top: 2px;
    }
    .fc-daygrid-event {
      padding: 0 !important;
      border-width: 3px !important; /* Borda Grossa para mostrar a cor */
      border-style: solid !important;
      border-radius: 8px;
      overflow: hidden;
      /* A cor de fundo e borda serão setadas via JS */
    }

    /* Responsividade */
    @media (max-width: 1024px) {
        #sidebar { width: 220px; }
        #calendarContainer { padding-left: 240px; }
    }
    @media (max-width: 780px) {
        header h1 { font-size: 24px; }
        #sidebar { position: relative; top: auto; width: 100%; height: auto; border-right: none; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); padding: 10px 16px; gap: 8px; }
        #calendarContainer { padding: 10px; padding-top: 70px; max-width: 100%; }
        #calendar { padding: 10px; }
        #userArea { top: 8px; right: 10px; }
        .modal { max-width: 95%; padding: 15px; }
        .modal-footer { justify-content: center; }
        .btn { width: 100%; text-align: center; }
    }
    /* Estilos de Turma no Modal de Edição */
    .turmas-container { border: 1px solid #ddd; border-radius: 6px; padding: 10px; margin-top: 5px; max-height: 150px; overflow-y: auto; background: #fdfdfd; }
    .turma-curso-grupo { margin-bottom: 8px; }
    .turma-curso-grupo strong { font-size: 14px; color: #0056b3; }
    .turma-checkbox { margin-right: 15px; }
    .turma-checkbox input { width: auto; margin-right: 5px; }

  </style>
</head>
<header>
  <h1>Not<span>IF</span>y</h1>
</header>
<body>

  <div id="sidebar">
    <button class="sidebar-btn" id="meusEventosBtn">📅 Meus Eventos</button>
    <button class="sidebar-btn" id="addEventBtn">➕ Adicionar Evento</button>
    <button class="sidebar-btn" id="permissionsBtn">🔐 Permissões</button>
    <button class="sidebar-btn" id="gerenciarCursosBtn">🏫 Gerenciar Cursos</button>
  </div>

  <div id="userArea">
    <img id="profileImg" src="default.jpg" alt="Perfil" title="Meu perfil" />
    <button id="logoutMini">Sair</button>
  </div>

  <div id="calendarContainer">
    <div id="calendar"></div>
  </div>

  <div id="overlay" aria-modal="true" role="dialog">
    <div id="viewModal" class="modal" role="document">
      <div class="modal-header">
        <h3 id="modalTitle" class="modal-title">Título</h3>
        <button id="modalClose" class="modal-close" aria-label="Fechar">&times;</button>
      </div>
      <div class="modal-body">
        <div>
          <img id="modalImageFull" class="modal-image-full" src="" alt="Imagem do evento" style="display:none" />
          <p id="modalDescription" class="modal-desc">Descrição</p>
          <p style="color:#666; margin-top:12px;"><strong>Local:</strong> <span id="modalLocation">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Início:</strong> <span id="modalStart">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Fim:</strong> <span id="modalEnd">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Participantes inscritos:</strong> <span
              id="modalCount">0</span></p>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btnVerAvaliacoes" class="btn btn-ver-avaliacoes" style="display:none; margin-right: 8px;" title="Ver Avaliações">👁️ Avaliações</button>
        <button id="btnAvaliar" class="btn btn-avaliar" style="display:none;" title="Avaliar Evento">⭐ Avaliar</button>
        
        <button id="inscribeBtn" class="btn btn-inscribe" style="display:none; margin-left: auto;" title="Inscrever-se">✍️ Inscrever-se</button>
        <button id="btnManageInscricoes" class="btn btn-export" style="display:none;" title="Gerenciar Inscrições">📦 Gerenciar</button>
        <button id="btnAddPalestrantes" class="btn btn-palestrante" style="display:none;" title="Adicionar palestrantes">🗣️ Palestrantes</button>
        <button id="btnAddCollaborators" class="btn btn-collab" style="display:none;" title="Adicionar colaboradores">🤝 Colaboradores</button>
        <button id="btnValidate" class="btn" style="background:#10b981; color:#fff; display:none;" title="Validar presença">✅ Validar</button>

        <button id="btnEdit" class="btn btn-edit" style="display:none;" title="Editar">📝 Editar</button>
        <button id="btnDelete" class="btn btn-delete" style="display:none;" title="Excluir evento">🗑️ Excluir</button>
        
        <button id="btnClose" class="btn btn-close" title="Fechar">❌ Fechar</button>
      </div>
    </div>
  </div>

  <div id="profileOverlay">
    <div id="profileCard">
      <div style="display:flex;gap:18px;align-items:center;">
        <img id="cardPhoto" src="default.jpg" alt="Foto"
          style="width:88px;height:88px;border-radius:10px;object-fit:cover" />
        <div style="flex:1;">
          <h3 id="cardName" style="margin:0;font-size:18px;">Nome</h3>
          <div id="cardRole" style="font-size:13px;color:#666;margin-top:4px;">ROLE</div>
        </div>
      </div>
      <div style="margin-top:20px;font-size:15px;color:#333;">
        <p style="margin:6px 0"><strong>E-mail:</strong> <span id="cardEmail">—</span></p>
        <p style="margin:6px 0"><strong>CPF:</strong> <span id="cardCPF">—</span></p>
        <div id="cardAlunoInfo" style="display:none;">
          <p style="margin:6px 0"><strong>RA:</strong> <span id="cardRA">—</span></p>
          <p style="margin:6px 0"><strong>Curso:</strong> <span id="cardCurso">—</span></p>
          <p style="margin:6px 0"><strong>Turma:</strong> <span id="cardTurma">—</span></p>
        </div>
        <p style="margin:6px 0"><strong>Telefone:</strong> <span id="cardPhone">—</span></p>
        <p style="margin:6px 0"><strong>Data de nascimento:</strong> <span id="cardBirth">—</span></p>
      </div>
      <div class="qr-box">
        <button id="generateQRBtn" class="btn" style="background:#0b6bff;color:#fff; width:100%;">Gerar QR code (CPF)</button>
        <img id="qrImage" src="" alt="QR Code"
          style="display:none;width:150px;height:150px;border-radius:8px;border:1px solid #e0e0e0;background:#fff;padding:6px;" />
        <a id="downloadQR" href="#" download="cpf_qr.png"
          style="display:none;margin-top:4px;color:#007bff;text-decoration:none;font-weight:600;">Baixar QR</a>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
        <button id="editProfileBtn"
          style="background:#17a2b8;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">Editar
          perfil</button>
        <button id="closeProfileBtn"
          style="background:#6c757d;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">Fechar</button>
      </div>
    </div>
  </div>
  
  <footer>
    Not<span>IF</span>y © 2025
  </footer>

</body>

</html>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    const CursosTurmasData = <?php echo $cursos_map_json; ?>;

    let currentUser = null;
    let calendar = null;
    let selectedEvent = null;

    function formatDateTimeForDisplay(s) { if (!s) return '—'; const d = new Date(s.replace(' ', 'T')); if (isNaN(d)) return s; return d.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }); }
    function toInputDatetimeLocal(s) { if (!s) return ''; const t = s.replace(' ', 'T'); return t.length >= 16 ? t.slice(0, 16) : t; }
    function parseJsonArrayProp(props, key) {
      let arr = []; const tryField = props[key] ?? null; if (!tryField) return arr;
      if (Array.isArray(tryField)) return tryField.map(String);
      if (typeof tryField === 'string') {
        try { const parsed = JSON.parse(tryField); if (Array.isArray(parsed)) return parsed.map(String); } catch (e) { return tryField.split(',').map(s => s.trim()).filter(Boolean).map(String); }
      }
      return [String(tryField)];
    }

    // --- LÓGICA DE CORES CORRIGIDA (SEM CONVERSÃO DE STRING) ---
    // Usamos os objetos Date do FullCalendar diretamente
    function getEventColor(start, end) {
      const now = new Date(); 
      const s = start; // Já é um objeto Date (com fuso local do navegador)
      const e = end;   // Já é um objeto Date

      if (!s) return 'blue'; // Padrão se não tiver data

      // 1. Terminado: Vermelho
      if (e && now > e) { return 'red'; }
      
      // 2. Acontecendo: Verde
      if (s && e && now >= s && now <= e) { return 'green'; }
      
      // 3. Futuro
      if (now < s) { 
          const diffMs = s.getTime() - now.getTime(); 
          const diffHours = diffMs / (1000 * 60 * 60); 
          
          if (diffHours <= 24) { return 'goldenrod'; } // Menos de 24h
          else { return 'blue'; } // Mais de 24h
      }
      return 'blue'; // Fallback
    }
    // --- FIM DA CORREÇÃO ---

    function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    async function fetchUserAndInit() {
      try {
        const res = await fetch('get_user.php', { cache: 'no-store' });
        if (!res.ok) { if (res.status === 401) window.location.href = 'telainicio.html'; throw new Error('Falha ao obter dados do usuário'); }
        const data = await res.json();
        currentUser = data;
        const profileImg = document.getElementById('profileImg');
        profileImg.src = currentUser.foto_url || 'default.jpg';
        profileImg.onerror = () => profileImg.src = 'default.jpg';
        document.getElementById('logoutMini').style.display = 'inline-block';
        
        // --- CORREÇÃO DE PERMISSÕES ---
        // Usa style.setProperty para forçar a visibilidade sobrescrevendo o CSS
        if (currentUser.role >= 1) {
            document.getElementById('addEventBtn').style.setProperty('display', 'flex', 'important');
        }
        if (currentUser.role === 2) {
          document.getElementById('permissionsBtn').style.setProperty('display', 'flex', 'important');
          document.getElementById('gerenciarCursosBtn').style.setProperty('display', 'flex', 'important');
        }
        // --- FIM DA CORREÇÃO ---
        
        initCalendar();
        attachProfileHandlers();
      } catch (err) { console.error('Erro ao carregar usuário:', err); alert('Não foi possível carregar dados do usuário. Recarregue a página.'); }
    }

    function initCalendar() {
      const overlay = document.getElementById('overlay');
      const modalTitle = document.getElementById('modalTitle');
      const modalDescription = document.getElementById('modalDescription');
      const modalLocation = document.getElementById('modalLocation');
      const modalImageFull = document.getElementById('modalImageFull');
      const modalCount = document.getElementById('modalCount');
      const modalStart = document.getElementById('modalStart');
      const modalEnd = document.getElementById('modalEnd');
      const modalClose = document.getElementById('modalClose');
      const btnClose = document.getElementById('btnClose');
      const inscribeBtn = document.getElementById('inscribeBtn');
      const btnDelete = document.getElementById('btnDelete');
      const btnManageInscricoes = document.getElementById('btnManageInscricoes'); 
      const btnAddCollaborators = document.getElementById('btnAddCollaborators');
      const btnAddPalestrantes = document.getElementById('btnAddPalestrantes');
      const btnEdit = document.getElementById('btnEdit');
      const btnValidate = document.getElementById('btnValidate');
      const btnAvaliar = document.getElementById('btnAvaliar');
      const btnVerAvaliacoes = document.getElementById('btnVerAvaliacoes');

      function openModal() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
      function closeModal() { overlay.style.display = 'none'; document.body.style.overflow = ''; selectedEvent = null; }
      modalClose.addEventListener('click', closeModal);
      btnClose.addEventListener('click', closeModal);
      overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

      calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        eventDisplay: 'block',
        eventContent: function (arg) {
          let html = '<div class="fc-event-title-custom">' + escapeHtml(arg.event.title) + '</div>';
          const capa = arg.event.extendedProps.capa_url;
          if (capa) {
            html += '<img class="fc-event-cover-img" src="' + escapeHtml(capa) + '" alt="Capa">';
          }
          return { html: html };
        },
        eventClick: async function (info) {
          selectedEvent = info.event;
          const props = selectedEvent.extendedProps || {};
          modalTitle.textContent = selectedEvent.title || props.nome || 'Sem título';
          modalDescription.textContent = props.descricao ?? props.description ?? '';
          modalLocation.textContent = props.local ?? props.location ?? 'Não informado';
          const startStr = selectedEvent.start ? selectedEvent.start.toISOString().slice(0, 19).replace('T', ' ') : (props.data_hora_inicio ?? props.start ?? '');
          const endStr = selectedEvent.end ? selectedEvent.end.toISOString().slice(0, 19).replace('T', ' ') : (props.data_hora_fim ?? props.end ?? '');
          modalStart.textContent = formatDateTimeForDisplay(startStr);
          modalEnd.textContent = formatDateTimeForDisplay(endStr);
          const inscricoes = parseJsonArrayProp(props, 'inscricoes');
          const collaboratorsArr = parseJsonArrayProp(props, 'colaboradores_ids');
          const palestrantesArr = parseJsonArrayProp(props, 'palestrantes_ids');
          modalCount.textContent = inscricoes.length;
          let createdBy = null;
          if (props.created_by !== undefined && props.created_by !== null) createdBy = String(props.created_by);
          else if (selectedEvent._def && selectedEvent._def.extendedProps && selectedEvent._def.extendedProps.created_by !== undefined) createdBy = String(selectedEvent._def.extendedProps.created_by);

          let isHappeningNow = false;
          let eventoTerminou = false;
          if (startStr && endStr) {
            try {
              const now = new Date();
              const startTime = new Date(startStr.replace(' ', 'T'));
              const endTime = new Date(endStr.replace(' ', 'T'));
              if (now >= startTime && now <= endTime) isHappeningNow = true;
              if (now > endTime) eventoTerminou = true;
            } catch (e) { }
          }

          if (currentUser && currentUser.id) {
            inscribeBtn.style.display = 'inline-block';
            if (eventoTerminou) {
              inscribeBtn.textContent = 'Inscrições encerradas';
              inscribeBtn.disabled = true;
            } else {
              const isInscrito = inscricoes.includes(String(currentUser.id));
              const limite = props.limite_participantes;
              const inscritosCount = inscricoes.length;
              if (limite && limite > 0 && inscritosCount >= limite && !isInscrito) {
                inscribeBtn.textContent = `Lotado (${inscritosCount}/${limite})`;
                inscribeBtn.disabled = true;
              } else {
                inscribeBtn.textContent = isInscrito ? 'Remover inscrição' : 'Inscrever-se';
                inscribeBtn.disabled = false;
              }
            }
          } else {
            inscribeBtn.style.display = 'none';
          }

          const isDev = (currentUser && currentUser.role === 2);
          const isCreator = (createdBy !== null && String(createdBy) === String(currentUser.id));
          const isCollaborator = collaboratorsArr.includes(String(currentUser.id));
          const canManage = isDev || isCreator || isCollaborator;

          btnManageInscricoes.style.display = canManage ? 'inline-block' : 'none'; 
          btnDelete.style.display = canManage ? 'inline-block' : 'none';
          btnEdit.style.display = canManage ? 'inline-block' : 'none';
          btnAddPalestrantes.style.display = canManage ? 'inline-block' : 'none';
          btnAddCollaborators.style.display = (isDev || isCreator) ? 'inline-block' : 'none';

          if (canManage && isHappeningNow) {
            btnValidate.style.display = 'inline-block';
          } else {
            btnValidate.style.display = 'none';
          }
          if (isDev || isCreator) {
            btnVerAvaliacoes.style.display = 'inline-block';
          } else {
            btnVerAvaliacoes.style.display = 'none';
          }
          if (eventoTerminou) {
            btnAvaliar.style.display = 'inline-block';
          } else {
            btnAvaliar.style.display = 'none';
          }

          const imageUrlFull = props.imagem_completa_url ?? null;
          if (imageUrlFull) { modalImageFull.src = imageUrlFull; modalImageFull.style.display = 'block'; }
          else { modalImageFull.style.display = 'none'; }
          openModal();
        },
        eventDidMount: function (info) {
            // Passa os objetos Date diretos, sem converter para string
            const start = info.event.start; 
            const end = info.event.end;
            
            const c = getEventColor(start, end);
            info.el.style.borderColor = c;
            
            if (info.event.extendedProps.capa_url) {
                info.el.style.backgroundColor = '#333';
            } else {
                info.el.style.backgroundColor = c;
            }
        }
      });
      calendar.render();
      (async function loadEvents() {
        try {
          const res = await fetch('list_events.php', { cache: 'no-store' });
          const raw = await res.text();
          if (!raw) return;
          let data;
          try { data = JSON.parse(raw); } catch (e) { console.error('Resposta inválida list_events.php', raw); return; }
          if (!Array.isArray(data)) return;
          data.forEach(row => {
            const ev = {
              id: String(row.id),
              title: row.nome || row.title || '',
              start: row.data_hora_inicio ?? row.start ?? null,
              end: row.data_hora_fim ?? row.end ?? null,
              extendedProps: row.extendedProps ?? {
                descricao: row.descricao ?? row.description ?? '',
                local: row.local ?? row.location ?? '',
                capa_url: row.capa_url ?? null,
                imagem_completa_url: row.imagem_completa_url ?? null,
                inscricoes: row.inscricoes ?? [],
                colaboradores: row.colaboradores ?? [],
                colaboradores_ids: row.colaboradores_ids ?? [],
                palestrantes_ids: row.palestrantes_ids ?? [],
                created_by: row.created_by ?? null,
                limite_participantes: row.limite_participantes ?? null,
                turmas_permitidas: row.turmas_permitidas ?? []
              }
            };
            calendar.addEvent(ev);
          });
        } catch (err) {
          console.error('Erro ao carregar eventos:', err);
        }
      })();
      inscribeBtn.addEventListener('click', async () => {
        if (!selectedEvent) return;
        try {
          const res = await fetch('inscrever_evento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: selectedEvent.id }) });
          const text = await res.text();
          let json = {};
          try { json = text ? JSON.parse(text) : {}; } catch(e) { json = { erro: 'Resposta inválida' }; }
          if (!res.ok) { alert(json.erro || 'Erro ao processar inscrição.'); return; }
          const inscricoes = Array.isArray(json.inscricoes) ? json.inscricoes.map(String) : [];
          modalCount.textContent = inscricoes.length;
          inscribeBtn.textContent = !!json.inscrito ? 'Remover inscrição' : 'Inscrever-se';
          try { selectedEvent.setExtendedProp('inscricoes', inscricoes); } catch(e) { selectedEvent.extendedProps = selectedEvent.extendedProps || {}; selectedEvent.extendedProps.inscricoes = inscricoes; }
          const props = selectedEvent.extendedProps || {};
          const limite = props.limite_participantes;
          const inscritosCount = inscricoes.length;
          const isInscrito = inscricoes.includes(String(currentUser.id));
          if (limite && limite > 0 && inscritosCount >= limite && !isInscrito) {
            inscribeBtn.textContent = `Lotado (${inscritosCount}/${limite})`;
            inscribeBtn.disabled = true;
          }
        } catch (err) { console.error('Erro na inscrição:', err); alert('Erro ao inscrever-se.'); }
      });
      btnDelete.addEventListener('click', async () => {
        if (!selectedEvent) return;
        if (!confirm('Excluir este evento permanentemente?')) return;
        try {
          const res = await fetch('delete_event.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: selectedEvent.id }) });
          const json = await res.json();
          if (!res.ok) { alert(json.erro || 'Erro ao excluir evento.'); return; }
          const ev = calendar.getEventById(selectedEvent.id);
          if (ev) ev.remove();
          closeModal();
          alert(json.mensagem || 'Evento excluído.');
        } catch (err) { console.error('Erro ao excluir:', err); alert('Erro na exclusão.'); }
      });
      btnEdit.addEventListener('click', () => {
        if (!selectedEvent) return;
        const props = selectedEvent.extendedProps || {};
        const prefill = {
          id: selectedEvent.id,
          title: selectedEvent.title || '',
          start: selectedEvent.start ? selectedEvent.start.toISOString().slice(0, 19).replace('T', ' ') : (props.data_hora_inicio || ''),
          end: selectedEvent.end ? selectedEvent.end.toISOString().slice(0, 19).replace('T', ' ') : (props.data_hora_fim || ''),
          local: props.local || props.location || '',
          description: props.descricao || props.description || '',
          capa_url: props.capa_url || '',
          imagem_completa_url: props.imagem_completa_url || '',
          limite_participantes: props.limite_participantes || '',
          turmas_permitidas: parseJsonArrayProp(props, 'turmas_permitidas')
        };
        openEditModal(prefill);
      });
      document.getElementById('logoutMini').addEventListener('click', async () => {
        try {
          const r = await fetch('logout.php', { method: 'POST' });
          if (r.ok) window.location.href = 'telainicio.html';
          else alert('Erro ao sair.');
        } catch (err) { console.error('Erro no logout', err); alert('Erro ao sair.'); }
      });
      function openEditModal(prefill) {
        const existing = document.getElementById('editModal');
        if (existing) existing.remove();
        const editModal = document.createElement('div');
        editModal.id = 'editModal';
        editModal.className = 'modal';
        editModal.style.zIndex = 5000; /* CORREÇÃO: Z-Index mais alto que o Overlay */
        editModal.style.position = 'fixed';
        editModal.style.left = '50%';
        editModal.style.top = '50%';
        editModal.style.transform = 'translate(-50%, -50%)';
        editModal.style.width = '420px';
        let turmasHtml = '';
        CursosTurmasData.forEach(curso => {
          turmasHtml += `<div class="turma-curso-grupo"><strong>${escapeHtml(curso.nome)}</strong><br>`;
          if (curso.turmas.length > 0) {
            curso.turmas.forEach(turma => {
              const isChecked = prefill.turmas_permitidas.includes(String(turma.id));
              turmasHtml += `
                        <label class="turma-checkbox">
                            <input type="checkbox" name="turmas_permitidas[]" value="${turma.id}" ${isChecked ? 'checked' : ''}>
                            ${escapeHtml(turma.nome_exibicao)}
                        </label>`;
            });
          } else {
            turmasHtml += `<small>Nenhuma turma cadastrada</small>`;
          }
          turmasHtml += `</div>`;
        });
        const isExternoChecked = prefill.turmas_permitidas.includes("0");
        turmasHtml += `
            <hr style="border:0; border-top:1px dashed #ccc; margin: 8px 0;">
            <label class="turma-checkbox">
                <input type="checkbox" name="publico_externo" value="1" ${isExternoChecked ? 'checked' : ''}>
                <strong>Público Externo (Não-alunos)</strong>
            </label>`;
        editModal.innerHTML = `
          <form id="editForm" style="max-height: 80vh; overflow-y: auto;">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <h3 style="margin:0">Editar evento</h3>
              <button type="button" id="closeEdit" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
            </div>
            <div style="margin-top:10px">
              <label>Título:<br/><input id="edit_title" name="nome" style="width:100%;padding:6px" value="${escapeHtml(prefill.title || '')}"></label>
              <label>Início:<br/><input id="edit_start" name="data_hora_inicio" type="datetime-local" style="width:100%;padding:6px" value="${toInputDatetimeLocal(prefill.start || '')}"></label>
              <label>Término:<br/><input id="edit_end" name="data_hora_fim" type="datetime-local" style="width:100%;padding:6px" value="${toInputDatetimeLocal(prefill.end || '')}"></label>
              <label>Local:<br/><input id="edit_local" name="local" style="width:100%;padding:6px" value="${escapeHtml(prefill.local || '')}"></label>
              <label>Descrição:<br/><textarea id="edit_desc" name="descricao" style="width:100%;padding:6px">${escapeHtml(prefill.description || '')}</textarea></label>
              <label>Nova Imagem de Capa (3:1):<br/>
                <span style="font-size:11px;color:#666">Atual: ${prefill.capa_url ? escapeHtml(prefill.capa_url.split('/').pop()) : 'Nenhuma'}</span>
                <input id="edit_capa_upload" name="capa_upload" type="file" style="width:100%;padding:6px" accept="image/*"></label>
              <label>Nova Imagem Completa (Modal):<br/>
                <span style="font-size:11px;color:#666">Atual: ${prefill.imagem_completa_url ? escapeHtml(prefill.imagem_completa_url.split('/').pop()) : 'Nenhuma'}</span>
                <input id="edit_img_full_upload" name="imagem_completa_upload" type="file" style="width:100%;padding:6px" accept="image/*"></label>
              <label>Limite participantes:<br/><input id="edit_limit" name="limite_participantes" type="number" min="0" style="width:100%;padding:6px" value="${prefill.limite_participantes || ''}"></label>
              <label>Turmas Permitidas</label>
              <div class="turmas-container">${turmasHtml}</div>
              <div style="text-align:right;margin-top:8px">
                <button type="button" id="cancelEdit" class="btn btn-close" style="margin-right:6px">Cancelar</button>
                <button type="submit" id="saveEdit" class="btn btn-edit">Salvar</button>
              </div>
            </div>
          </form>
        `;
        document.body.appendChild(editModal);
        // Aqui o overlay continua, mas o editModal tem zIndex maior
        overlay.style.display = 'flex'; 
        
        document.getElementById('closeEdit').addEventListener('click', () => { editModal.remove(); overlay.style.display = 'none'; });
        document.getElementById('cancelEdit').addEventListener('click', () => { editModal.remove(); overlay.style.display = 'none'; });
        document.getElementById('editForm').addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = e.target;
          const formData = new FormData();
          formData.append('id', prefill.id);
          formData.append('nome', document.getElementById('edit_title').value.trim());
          formData.append('data_hora_inicio', document.getElementById('edit_start').value);
          formData.append('data_hora_fim', document.getElementById('edit_end').value);
          formData.append('local', document.getElementById('edit_local').value.trim());
          formData.append('descricao', document.getElementById('edit_desc').value.trim());
          formData.append('limite_participantes', document.getElementById('edit_limit').value);
          const capaFile = document.getElementById("edit_capa_upload").files[0];
          if (capaFile) formData.append('capa_upload', capaFile);
          const imgFullFile = document.getElementById("edit_img_full_upload").files[0];
          if (imgFullFile) formData.append('imagem_completa_upload', imgFullFile);
          const turmasCheckboxes = form.querySelectorAll('input[name="turmas_permitidas[]"]:checked');
          turmasCheckboxes.forEach(chk => {
            formData.append('turmas_permitidas[]', chk.value);
          });
          if (form.querySelector('input[name="publico_externo"]:checked')) {
            formData.append('publico_externo', '1');
          }
          if (!formData.get('nome') || !formData.get('data_hora_inicio') || !formData.get('data_hora_fim')) {
            alert('Preencha título, início e término.');
            return;
          }
          const saveBtn = document.getElementById('saveEdit');
          saveBtn.disabled = true;
          saveBtn.textContent = 'Salvando...';
          try {
            const res = await fetch('edit_event.php', { method: 'POST', body: formData });
            const text = await res.text();
            let json;
            try { json = text ? JSON.parse(text) : {}; } catch(e) { json = { erro: 'Resposta inválida' }; }
            if (!res.ok) { alert(json.erro || 'Erro ao editar evento.'); return; }
            alert(json.mensagem || 'Evento atualizado. Recarregando calendário...');
            window.location.reload();
          } catch (err) {
            console.error('Erro salvar edição:', err);
            alert('Erro ao salvar edição.');
          } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salvar';
          }
        });
      }
      btnValidate.addEventListener('click', () => { if (selectedEvent) window.location.href = `validar_presenca.php?event_id=${encodeURIComponent(selectedEvent.id)}`; });
      btnExport.addEventListener('click', () => { if (selectedEvent) window.location.href = `export_inscricoes.php?id=${encodeURIComponent(selectedEvent.id)}`; });
      btnAddCollaborators.addEventListener('click', () => { if (selectedEvent) window.location.href = `collaborators.php?event_id=${encodeURIComponent(selectedEvent.id)}`; });
      btnAddPalestrantes.addEventListener('click', () => { if (selectedEvent) window.location.href = `palestrantes.php?event_id=${encodeURIComponent(selectedEvent.id)}`; });
      btnAvaliar.addEventListener('click', () => { if (selectedEvent) window.location.href = `avaliar_evento.php?event_id=${encodeURIComponent(selectedEvent.id)}`; });
      btnVerAvaliacoes.addEventListener('click', () => { if (selectedEvent) window.location.href = `ver_avaliacoes.php?event_id=${encodeURIComponent(selectedEvent.id)}`; });
      // Adicionando o listener que faltava para o botão renomeado de gerenciar inscrições
      btnManageInscricoes.addEventListener('click', () => { if (selectedEvent) window.location.href = `gerenciar_inscricoes.php?event_id=${encodeURIComponent(selectedEvent.id)}`; });
    } // end initCalendar

    function attachProfileHandlers() {
      const profileBtn = document.getElementById('profileImg');
      const profileOverlay = document.getElementById('profileOverlay');
      const closeProfileBtn = document.getElementById('closeProfileBtn');
      const editProfileBtn = document.getElementById('editProfileBtn');
      const generateQRBtn = document.getElementById('generateQRBtn');
      const qrImage = document.getElementById('qrImage');
      const downloadQR = document.getElementById('downloadQR');

      profileBtn.addEventListener('click', async () => {
        try {
          const res = await fetch('get_user.php', { cache: 'no-store' });
          if (!res.ok) { if (res.status === 401) window.location.href = 'telainicio.html'; throw new Error('Erro get_user'); }
          const u = await res.json();
          document.getElementById('cardPhoto').src = u.foto_url || 'default.jpg';
          document.getElementById('cardPhoto').onerror = () => document.getElementById('cardPhoto').src = 'default.jpg';
          document.getElementById('cardName').textContent = u.nome || '';
          document.getElementById('cardRole').textContent = u.role === 2 ? 'DEV' : (u.role === 1 ? 'ORGANIZADOR' : 'USER');
          document.getElementById('cardEmail').textContent = u.email || '—';
          document.getElementById('cardCPF').textContent = u.cpf || '—';
          document.getElementById('cardPhone').textContent = u.telefone || '—';
          document.getElementById('cardBirth').textContent = u.data_nascimento || '—';

          const alunoInfoDiv = document.getElementById('cardAlunoInfo');
          if (u.turma_id) {
            document.getElementById('cardRA').textContent = u.registro_academico || '—';
            document.getElementById('cardCurso').textContent = u.curso_nome || '—';
            document.getElementById('cardTurma').textContent = u.turma_nome || '—';
            alunoInfoDiv.style.display = 'block';
          } else {
            alunoInfoDiv.style.display = 'none';
          }

          qrImage.style.display = 'none'; qrImage.src = ''; downloadQR.style.display = 'none';
          profileOverlay.style.display = 'flex';
          document.body.style.overflow = 'hidden';
        } catch (err) { console.error('Erro abrir cartão:', err); alert('Não foi possível abrir o cartão do usuário.'); }
      });

      closeProfileBtn.addEventListener('click', () => { document.getElementById('profileOverlay').style.display = 'none'; document.body.style.overflow = ''; });
      document.getElementById('profileOverlay').addEventListener('click', (e) => { if (e.target === document.getElementById('profileOverlay')) { document.getElementById('profileOverlay').style.display = 'none'; document.body.style.overflow = ''; } });
      editProfileBtn.addEventListener('click', () => { window.location.href = 'perfil_editar.php'; });

      generateQRBtn.addEventListener('click', async () => {
        try {
          const res = await fetch('get_user.php', { cache: 'no-store' });
          if (!res.ok) { alert('Não foi possível obter CPF.'); return; }
          const u = await res.json();
          let cpf = u.cpf || ''; cpf = String(cpf).replace(/\D/g, '');
          if (!cpf) { alert('CPF não disponível.'); return; }
          if (cpf.length !== 11) { alert('CPF precisa ter 11 dígitos para gerar o QR code. Valor atual: ' + cpf); return; }
          const size = 250;
          const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(cpf)}`;
          qrImage.src = qrUrl; qrImage.style.display = 'block';
          downloadQR.href = qrUrl; downloadQR.style.display = 'inline-block'; downloadQR.textContent = 'Baixar QR';
        } catch (err) { console.error('Erro gerar QR:', err); alert('Erro ao gerar QR code.'); }
      });
    }

    // Iniciar
    document.addEventListener('DOMContentLoaded', () => {
      fetchUserAndInit();
      document.getElementById('meusEventosBtn').addEventListener('click', () => { location.href = 'meus_eventos.php'; });
      document.getElementById('addEventBtn').addEventListener('click', () => { location.href = 'adicionarevento.php'; });
      document.getElementById('permissionsBtn').addEventListener('click', () => { location.href = 'permissions.php'; });
      document.getElementById('gerenciarCursosBtn').addEventListener('click', () => { location.href = 'gerenciar_cursos.php'; });
    });
  </script>
</body>

</html>