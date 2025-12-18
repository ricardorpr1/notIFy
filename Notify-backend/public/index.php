<?php
// index.php - Versão Final: Correção de Cache de Imagem
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}

// Configurações do Banco
$DB_HOST = "127.0.0.1"; $DB_PORT = "3306"; $DB_NAME = "notify_db"; $DB_USER = "tcc_notify"; $DB_PASS = "108Xk:C";

$cursos_map_json = "[]"; $turmas_json = "[]";

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
} catch (PDOException $e) {
    error_log("Erro ao carregar turmas no index.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>notIFy — Calendário</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
  <style>
    body { margin:0; font-family: 'Inter', Arial, Helvetica, sans-serif; background:#f0f2f5; color:#333; overflow-x: hidden; }
    
    /* HEADER */
    header { position: fixed; top: 0; left: 0; width: 100%; background-color: #045c3f; color: white; display: flex; align-items: center; justify-content: center; height: 60px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 3000; }
    header h1 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -1px; }
    header span { color: #c00000; font-weight: 900; }
    
    /* Menu Hamburger (Mobile) */
    #mobileMenuBtn {
        display: none; /* Escondido no Desktop */
        position: absolute; left: 15px; background: none; border: none; color: white; font-size: 24px; cursor: pointer;
    }

    /* LAYOUT PRINCIPAL */
    #calendarContainer { padding: 80px 20px 20px 290px; max-width: 1400px; margin: 0 auto; transition: padding 0.3s; }
    #calendar { padding: 18px; background: #fff; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin: 0; }

    /* SIDEBAR */
    #sidebar { position: fixed; top: 60px; left: 0; width: 250px; height: calc(100vh - 60px); background: #ffffff; padding: 20px; display: flex; flex-direction: column; gap: 12px; border-right: 1px solid #e0e0e0; box-shadow: 4px 0 16px rgba(0, 0, 0, 0.08); z-index: 2000; transition: transform 0.3s ease; }
    .sidebar-btn { background: #045c3f; color: #fff; border: none; padding: 14px 20px; border-radius: 10px; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.3s; width: 100%; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); box-sizing: border-box; }
    .sidebar-btn:hover { background: #05774f; transform: translateY(-2px); }
    
    /* Permissões (Oculto por padrão) */
    #addEventBtn, #permissionsBtn, #gerenciarCursosBtn { display: none !important; }

    /* USER AREA */
    #userArea { position: fixed; top: 8px; right: 15px; z-index: 3100; display: flex; gap: 10px; align-items: center; }
    #profileImg { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; cursor: pointer; border: 2px solid #fff; transition: 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
    #profileImg:hover { opacity: 0.8; }
    #logoutMini { background: #d9534f; color: #fff; border: none; padding: 6px 10px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.2s; display: none; font-size: 13px; }

    /* MODAIS */
    #overlay, #profileOverlay, #sidebarBackdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    #sidebarBackdrop { z-index: 1900; } 
    
    .modal { background: #fff; width: 90%; max-width: 600px; border-radius: 12px; padding: 24px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35); max-height: 90vh; overflow-y: auto; position: relative; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px; margin-bottom: 15px; }
    .modal-title { margin: 0; font-size: 20px; font-weight: 700; color: #045c3f; }
    .modal-close { background: transparent; border: none; font-size: 24px; cursor: pointer; padding: 0; }
    
    .modal-body { display: block; margin-top:12px; }
    .modal-image-full { width: 100%; max-width: 100%; height: auto; border-radius: 8px; margin-top: 15px; border: 1px solid #e0e0e0; object-fit: contain; }
    .modal-desc { white-space: pre-wrap; color: #555; line-height: 1.6; margin-top: 10px; font-size: 15px; }
    .modal-body p strong { color: #045c3f; }

    .modal-footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
    .btn { padding: 10px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; transition: 0.2s; white-space: nowrap; }
    .btn:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
    
    /* Cores dos Botões */
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
    #profileCard { background: #fff; width: 360px; max-width: 90%; border-radius: 12px; padding: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.35); border: 1px solid #e0e0e0; }
    #cardPhoto { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; }
    #cardName { font-size: 20px; font-weight: 700; color: #045c3f; }
    #cardRole { font-size: 13px; color: #c00000; font-weight: 600; }
    .qr-box { border-top: 1px solid #e0e0e0; padding-top: 15px; margin-top: 15px !important; text-align: center; }

    /* Eventos no Calendário (Desktop) */
    .fc-event-title-custom { font-weight: 700; color: #fff; text-shadow: 0 0 3px rgba(0,0,0,0.8); padding: 4px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; }
    .fc-event-cover-img { width: 100%; height: auto; aspect-ratio: 3 / 1; object-fit: cover; border-radius: 4px; margin-top: 2px; display: block; }
    
    /* Estilos Específicos para Grade */
    .fc-daygrid-event { padding: 0 !important; border-width: 3px !important; border-style: solid !important; border-radius: 8px; overflow: hidden; }

    /* Estilos Específicos para Lista (Mobile) */
    .fc-list-event-title .fc-event-cover-img {
        margin-top: 8px;
        max-width: 100%;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* CSS para edição de turmas */
    .turmas-container { border: 1px solid #ddd; border-radius: 6px; padding: 10px; margin-top: 5px; max-height: 150px; overflow-y: auto; background: #fdfdfd; }
    .turma-curso-grupo { margin-bottom: 8px; }
    .turma-curso-grupo strong { font-size: 14px; color: #0056b3; }
    .turma-checkbox { margin-right: 15px; display: inline-block; }
    .turma-checkbox input { width: auto; margin-right: 5px; }
    
    /* ================= MOBILE ================= */
    @media (max-width: 768px) {
        #mobileMenuBtn { display: block; }
        #sidebar { transform: translateX(-100%); width: 260px; box-shadow: none; }
        #sidebar.active { transform: translateX(0); box-shadow: 4px 0 16px rgba(0, 0, 0, 0.2); }
        #calendarContainer { padding: 80px 15px 20px 15px; width: 100%; box-sizing: border-box; }
        #calendar { padding: 10px; }
        .fc-toolbar { flex-direction: column; gap: 10px; }
        .fc-toolbar-title { font-size: 20px !important; }
        .modal { width: 90%; maxWidth: 450px; padding: 15px; margin: 10px; max-height: 85vh; }
        .modal-footer { justify-content: center; }
        .btn { width: 100%; margin-bottom: 5px; text-align: center; }
        #profileCard { width: 95%; }
    }
  </style>
</head>

<header>
  <button id="mobileMenuBtn">☰</button>
  <h1>Not<span>IF</span>y</h1>
</header>

<body>
  <div id="sidebarBackdrop"></div>

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
          <p style="color:#666; margin-top:6px;"><strong>Participantes inscritos:</strong> <span id="modalCount">0</span></p>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btnVerAvaliacoes" class="btn btn-ver-avaliacoes" style="display:none; margin-right: auto;">👁️ Avaliações</button>
        <button id="btnAvaliar" class="btn btn-avaliar" style="display:none;">⭐ Avaliar</button>
        
        <button id="inscribeBtn" class="btn btn-inscribe" style="display:none; margin-left: auto;">✍️ Inscrever-se</button>
        <button id="btnManageInscricoes" class="btn btn-export" style="display:none;">📦 Gerenciar</button>
        <button id="btnAddPalestrantes" class="btn btn-palestrante" style="display:none;">🗣️ Palestrantes</button>
        <button id="btnAddCollaborators" class="btn btn-collab" style="display:none;">🤝 Colaboradores</button>
        <button id="btnValidate" class="btn" style="background:#10b981; color:#fff; display:none;">✅ Validar</button>

        <button id="btnEdit" class="btn btn-edit" style="display:none;">📝 Editar</button>
        <button id="btnDelete" class="btn btn-delete" style="display:none;">🗑️ Excluir</button>
        
        <button id="btnClose" class="btn btn-close">❌ Fechar</button>
      </div>
    </div>
  </div>
  
  <div id="profileOverlay">
    <div id="profileCard">
      <div style="display:flex;gap:18px;align-items:center;">
        <img id="cardPhoto" src="default.jpg" alt="Foto" />
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
        <img id="qrImage" src="" alt="QR Code" style="display:none;width:150px;height:150px; margin: 10px auto;" />
        <a id="downloadQR" href="#" download="cpf_qr.png" style="display:none;margin-top:4px;color:#007bff;text-decoration:none;font-weight:600;">Baixar QR</a>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
        <button id="editProfileBtn" style="background:#17a2b8;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">Editar perfil</button>
        <button id="closeProfileBtn" style="background:#6c757d;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">Fechar</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    const CursosTurmasData = <?php echo $cursos_map_json; ?>;
    let currentUser = null;
    let calendar = null;
    let selectedEvent = null;

    // Detecta se é mobile
    const isMobile = window.innerWidth <= 768;

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
    
    function getEventColor(start, end) {
      const now = new Date(); 
      const s = start; 
      const e = end;
      if (!s) return 'blue';
      if (e && now > e) return 'red'; // Terminado
      if (s && e && now >= s && now <= e) return 'green'; // Acontecendo
      if (now < s) { 
          const diffMs = s.getTime() - now.getTime(); 
          const diffHours = diffMs / (1000 * 60 * 60); 
          if (diffHours <= 24) return 'goldenrod'; 
          return 'blue'; 
      }
      return 'blue';
    }
    function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    async function fetchUserAndInit() {
      try {
        const res = await fetch('get_user.php', { cache: 'no-store' });
        if (!res.ok) { 
            if (res.status === 401) { window.location.href = 'telainicio.html'; return; }
            throw new Error('Falha na comunicação com o servidor.'); 
        }
        const data = await res.json();
        if (data.erro) throw new Error(data.erro);
        currentUser = data;
        
        const profileImg = document.getElementById('profileImg');
        // --- CORREÇÃO DE CACHE DE IMAGEM ---
        const imgSrc = currentUser.foto_url || 'default.jpg';
        // Adiciona timestamp para forçar atualização
        profileImg.src = imgSrc + '?t=' + new Date().getTime(); 
        profileImg.onerror = () => profileImg.src = 'default.jpg';
        
        document.getElementById('logoutMini').style.display = 'inline-block';
        
        if (currentUser.role >= 1) document.getElementById('addEventBtn').style.setProperty('display', 'flex', 'important');
        if (currentUser.role === 2) {
            document.getElementById('permissionsBtn').style.setProperty('display', 'flex', 'important');
            document.getElementById('gerenciarCursosBtn').style.setProperty('display', 'flex', 'important');
        }
        initCalendar();
        attachProfileHandlers();
        initMobileMenu();
      } catch (err) { 
          console.error('Erro ao carregar usuário:', err); 
          alert('Não foi possível carregar dados do usuário. Verifique o console para mais detalhes.'); 
      }
    }
    
    function initMobileMenu() {
        const menuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        
        function toggleMenu() {
            sidebar.classList.toggle('active');
            backdrop.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }
        
        menuBtn.addEventListener('click', toggleMenu);
        backdrop.addEventListener('click', toggleMenu); 
    }

    function initCalendar() {
      const overlay = document.getElementById('overlay');
      const modalImageFull = document.getElementById('modalImageFull');
      const modalCount = document.getElementById('modalCount');
      const inscribeBtn = document.getElementById('inscribeBtn');
      const btnDelete = document.getElementById('btnDelete');
      const btnManageInscricoes = document.getElementById('btnManageInscricoes');
      const btnEdit = document.getElementById('btnEdit');
      const btnValidate = document.getElementById('btnValidate');
      const btnAvaliar = document.getElementById('btnAvaliar');
      const btnVerAvaliacoes = document.getElementById('btnVerAvaliacoes');
      const btnAddCollaborators = document.getElementById('btnAddCollaborators');
      const btnAddPalestrantes = document.getElementById('btnAddPalestrantes');
      const btnExport = document.getElementById('btnExport'); 

      function openModal() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
      function closeModal() { overlay.style.display = 'none'; document.body.style.overflow = ''; selectedEvent = null; }
      
      document.getElementById('modalClose').addEventListener('click', closeModal);
      document.getElementById('btnClose').addEventListener('click', closeModal);
      overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

      calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: isMobile ? 'listMonth' : 'dayGridMonth', 
        locale: 'pt-br',
        headerToolbar: { 
            left: isMobile ? 'prev,next' : 'prev,next today', 
            center: 'title', 
            right: isMobile ? 'dayGridMonth,listMonth' : 'dayGridMonth,timeGridWeek,timeGridDay' 
        },
        eventDisplay: 'block',
        eventContent: function (arg) {
          let html = '<div class="fc-event-title-custom">' + escapeHtml(arg.event.title) + '</div>';
          const capa = arg.event.extendedProps.capa_url;
          // --- CORREÇÃO: Removemos a checagem !isMobile ---
          if (capa) {
            html += '<img class="fc-event-cover-img" src="' + escapeHtml(capa) + '" alt="Capa">';
          }
          // --- FIM CORREÇÃO ---
          return { html: html };
        },
        eventClick: async function (info) {
          selectedEvent = info.event;
          const props = selectedEvent.extendedProps || {};
          
          document.getElementById('modalTitle').textContent = selectedEvent.title || 'Sem título';
          document.getElementById('modalDescription').textContent = props.descricao || '';
          document.getElementById('modalLocation').textContent = props.local || 'Não informado';
          
          const startStr = selectedEvent.start ? selectedEvent.start.toISOString().slice(0, 19).replace('T', ' ') : '';
          const endStr = selectedEvent.end ? selectedEvent.end.toISOString().slice(0, 19).replace('T', ' ') : '';
          document.getElementById('modalStart').textContent = formatDateTimeForDisplay(startStr);
          document.getElementById('modalEnd').textContent = formatDateTimeForDisplay(endStr);

          const inscricoes = parseJsonArrayProp(props, 'inscricoes');
          const collaboratorsArr = parseJsonArrayProp(props, 'colaboradores_ids');
          modalCount.textContent = inscricoes.length;

          let createdBy = null;
          if (props.created_by !== undefined && props.created_by !== null) createdBy = String(props.created_by);

          let isHappeningNow = false;
          let eventoTerminou = false;
          if (selectedEvent.start && selectedEvent.end) {
             const now = new Date();
             if (now >= selectedEvent.start && now <= selectedEvent.end) isHappeningNow = true;
             if (now > selectedEvent.end) eventoTerminou = true;
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

          const displayStyle = canManage ? 'inline-block' : 'none';
          btnManageInscricoes.style.display = displayStyle;
          btnDelete.style.display = displayStyle;
          btnEdit.style.display = displayStyle;
          btnAddPalestrantes.style.display = displayStyle;
          btnAddCollaborators.style.display = (isDev || isCreator) ? 'inline-block' : 'none';

          if (canManage && isHappeningNow) btnValidate.style.display = 'inline-block';
          else btnValidate.style.display = 'none';

          if (isDev || isCreator) btnVerAvaliacoes.style.display = 'inline-block';
          else btnVerAvaliacoes.style.display = 'none';

          if (eventoTerminou) btnAvaliar.style.display = 'inline-block';
          else btnAvaliar.style.display = 'none';

          const imageUrlFull = props.imagem_completa_url;
          if (imageUrlFull) { 
              modalImageFull.src = imageUrlFull; 
              modalImageFull.style.display = 'block'; 
          } else { 
              modalImageFull.style.display = 'none'; 
          }
          
          openModal();
        },
        eventDidMount: function (info) {
          const c = getEventColor(info.event.start, info.event.end);
          info.el.style.borderColor = c;
          if (info.event.extendedProps.capa_url) info.el.style.backgroundColor = '#333';
          else info.el.style.backgroundColor = c;
        }
      });
      
      calendar.render();
      loadEvents();

      inscribeBtn.onclick = async () => {
          if (!selectedEvent) return;
          try {
             const res = await fetch('inscrever_evento.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: selectedEvent.id }) });
             const json = await res.json();
             if(!res.ok) { alert(json.erro || 'Erro.'); return; }
             loadEvents(); closeModal(); alert(json.mensagem);
          } catch(e) { alert('Erro de rede.'); }
      };

      btnDelete.onclick = async () => {
          if(!confirm('Excluir?')) return;
          await fetch('delete_event.php', { method: 'POST', body: JSON.stringify({id: selectedEvent.id}) });
          loadEvents(); closeModal();
      };
      
      btnEdit.onclick = () => {
        if (!selectedEvent) return;
        const props = selectedEvent.extendedProps || {};
        const prefill = {
          id: selectedEvent.id,
          title: selectedEvent.title || '',
          start: selectedEvent.start ? selectedEvent.start.toISOString().slice(0, 19).replace('T', ' ') : '',
          end: selectedEvent.end ? selectedEvent.end.toISOString().slice(0, 19).replace('T', ' ') : '',
          local: props.local || '',
          description: props.descricao || '',
          capa_url: props.capa_url || '',
          imagem_completa_url: props.imagem_completa_url || '',
          limite_participantes: props.limite_participantes || '',
          turmas_permitidas: parseJsonArrayProp(props, 'turmas_permitidas')
        };
        openEditModal(prefill);
      };
      
      btnManageInscricoes.onclick = () => location.href = `gerenciar_inscricoes.php?event_id=${selectedEvent.id}`;
      btnAddCollaborators.onclick = () => location.href = `collaborators.php?event_id=${selectedEvent.id}`;
      btnAddPalestrantes.onclick = () => location.href = `palestrantes.php?event_id=${selectedEvent.id}`;
      btnValidate.onclick = () => location.href = `validar_presenca.php?event_id=${selectedEvent.id}`;
      btnAvaliar.onclick = () => location.href = `avaliar_evento.php?event_id=${selectedEvent.id}`;
      btnVerAvaliacoes.onclick = () => location.href = `ver_avaliacoes.php?event_id=${selectedEvent.id}`;
    }

    async function loadEvents() {
        try {
          const res = await fetch('list_events.php', { cache: 'no-store' });
          const data = await res.json();
          calendar.removeAllEvents();
          if (Array.isArray(data)) { data.forEach(ev => { calendar.addEvent(ev); }); }
        } catch (err) { console.error('Erro ao carregar eventos'); }
    }

    function attachProfileHandlers() {
      const overlay = document.getElementById('profileOverlay');
      document.getElementById('profileImg').onclick = async () => {
         if(currentUser) {
             document.getElementById('cardName').textContent = currentUser.nome;
             document.getElementById('cardRole').textContent = (currentUser.role==2?'DEV':(currentUser.role==1?'ORGANIZADOR':'USER'));
             document.getElementById('cardEmail').textContent = currentUser.email;
             document.getElementById('cardCPF').textContent = currentUser.cpf || '-';
             document.getElementById('cardPhone').textContent = currentUser.telefone || '-';
             document.getElementById('cardBirth').textContent = currentUser.data_nascimento || '-';
             
             // --- CORREÇÃO DE CACHE TAMBÉM NO CARD ---
             const imgSrc = currentUser.foto_url || 'default.jpg';
             document.getElementById('cardPhoto').src = imgSrc + '?t=' + new Date().getTime();
             
             const alunoInfo = document.getElementById('cardAlunoInfo');
             if (currentUser.turma_id) {
                 document.getElementById('cardRA').textContent = currentUser.registro_academico || '-';
                 document.getElementById('cardCurso').textContent = currentUser.curso_nome || '-';
                 document.getElementById('cardTurma').textContent = currentUser.turma_nome || '-';
                 alunoInfo.style.display = 'block';
             } else { alunoInfo.style.display = 'none'; }
         }
         overlay.style.display = 'flex';
      };
      document.getElementById('closeProfileBtn').onclick = () => overlay.style.display = 'none';
      overlay.onclick = (e) => { if(e.target === overlay) overlay.style.display = 'none'; };
      document.getElementById('editProfileBtn').onclick = () => location.href = 'perfil_editar.php';
      document.getElementById('generateQRBtn').onclick = () => {
          const cpf = currentUser.cpf.replace(/\D/g,'');
          if(cpf.length !== 11) { alert('CPF inválido para QR.'); return; }
          const url = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(cpf)}`;
          document.getElementById('qrImage').src = url; 
          document.getElementById('qrImage').style.display = 'block';
          document.getElementById('downloadQR').href = url; 
          document.getElementById('downloadQR').style.display = 'inline-block';
      };
      document.getElementById('logoutMini').onclick = async () => {
          await fetch('logout.php', { method: 'POST' });
          location.href = 'telainicio.html';
      };
    }

    function openEditModal(prefill) {
        const existing = document.getElementById('editModal');
        if (existing) existing.remove();
        const editModal = document.createElement('div');
        editModal.id = 'editModal';
        editModal.className = 'modal';
        editModal.style.zIndex = 5000; 
        editModal.style.position = 'fixed'; editModal.style.left = '50%'; editModal.style.top = '50%';
        editModal.style.transform = 'translate(-50%, -50%)'; 
        // Responsividade do modal de edição
        editModal.style.width = '90%';
        editModal.style.maxWidth = '450px';

        let turmasHtml = '';
        CursosTurmasData.forEach(curso => {
            turmasHtml += `<div class="turma-curso-grupo"><strong>${escapeHtml(curso.nome)}</strong><br>`;
            if (curso.turmas.length > 0) {
                curso.turmas.forEach(turma => {
                    const isChecked = prefill.turmas_permitidas.includes(String(turma.id));
                    turmasHtml += `<label class="turma-checkbox"><input type="checkbox" name="turmas_permitidas[]" value="${turma.id}" ${isChecked ? 'checked' : ''}> ${escapeHtml(turma.nome_exibicao)}</label>`;
                });
            } else { turmasHtml += `<small>Nenhuma turma cadastrada</small>`; }
            turmasHtml += `</div>`;
        });
        const isExternoChecked = prefill.turmas_permitidas.includes("0");
        turmasHtml += `<hr style="border:0; border-top:1px dashed #ccc; margin: 8px 0;"><label class="turma-checkbox"><input type="checkbox" name="publico_externo" value="1" ${isExternoChecked ? 'checked' : ''}> <strong>Público Externo (Não-alunos)</strong></label>`;
        
        editModal.innerHTML = `
          <form id="editForm" style="max-height: 85vh; overflow-y: auto; padding-right: 5px;">
            <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">Editar evento</h3><button type="button" id="closeEdit" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button></div>
            <div style="margin-top:10px">
              <label>Título:<br/><input id="edit_title" name="nome" style="width:100%;padding:6px;margin-bottom:5px;" value="${escapeHtml(prefill.title||'')}"></label>
              <label>Início:<br/><input id="edit_start" name="data_hora_inicio" type="datetime-local" style="width:100%;padding:6px;margin-bottom:5px;" value="${toInputDatetimeLocal(prefill.start||'')}"></label>
              <label>Término:<br/><input id="edit_end" name="data_hora_fim" type="datetime-local" style="width:100%;padding:6px;margin-bottom:5px;" value="${toInputDatetimeLocal(prefill.end||'')}"></label>
              <label>Local:<br/><input id="edit_local" name="local" style="width:100%;padding:6px;margin-bottom:5px;" value="${escapeHtml(prefill.local||'')}"></label>
              <label>Descrição:<br/><textarea id="edit_desc" name="descricao" style="width:100%;padding:6px;margin-bottom:5px;">${escapeHtml(prefill.description||'')}</textarea></label>
              <label>Nova Capa (3:1):<br/><span style="font-size:11px;color:#666">Atual: ${prefill.capa_url ? 'Definida' : 'Nenhuma'}</span><input id="edit_capa_upload" name="capa_upload" type="file" style="width:100%;padding:6px;margin-bottom:5px;" accept="image/*"></label>
              <label>Nova Imagem Completa:<br/><span style="font-size:11px;color:#666">Atual: ${prefill.imagem_completa_url ? 'Definida' : 'Nenhuma'}</span><input id="edit_img_full_upload" name="imagem_completa_upload" type="file" style="width:100%;padding:6px;margin-bottom:5px;" accept="image/*"></label>
              <label>Limite participantes:<br/><input id="edit_limit" name="limite_participantes" type="number" min="0" style="width:100%;padding:6px;margin-bottom:5px;" value="${prefill.limite_participantes || ''}"></label>
              <label style="display:block; margin-top:10px; margin-bottom:5px;">Turmas Permitidas</label>
              <div class="turmas-container">${turmasHtml}</div>
              <div style="text-align:right;margin-top:15px"><button type="button" id="cancelEdit" class="btn btn-close" style="margin-right:6px">Cancelar</button><button type="submit" id="saveEdit" class="btn btn-edit">Salvar</button></div>
            </div>
          </form>
        `;
        document.body.appendChild(editModal);
        document.getElementById('overlay').style.display = 'flex';

        const closeFn = () => { editModal.remove(); };
        document.getElementById('closeEdit').onclick = closeFn;
        document.getElementById('cancelEdit').onclick = closeFn;
        document.getElementById('editForm').onsubmit = async (e) => {
          e.preventDefault();
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
          const form = e.target;
          const turmasCheckboxes = form.querySelectorAll('input[name="turmas_permitidas[]"]:checked');
          turmasCheckboxes.forEach(chk => { formData.append('turmas_permitidas[]', chk.value); });
          if (form.querySelector('input[name="publico_externo"]:checked')) { formData.append('publico_externo', '1'); }
          if (!formData.get('nome') || !formData.get('data_hora_inicio') || !formData.get('data_hora_fim')) { alert('Preencha título, início e término.'); return; }
          const saveBtn = document.getElementById('saveEdit');
          saveBtn.disabled = true; saveBtn.textContent = 'Salvando...';
          try {
            const res = await fetch('edit_event.php', { method: 'POST', body: formData });
            const text = await res.text();
            let json; try { json = text ? JSON.parse(text) : {}; } catch(e) { json = { erro: 'Resposta inválida' }; }
            if (!res.ok) { alert(json.erro || 'Erro ao editar evento.'); return; }
            alert(json.mensagem || 'Evento atualizado. Recarregando calendário...');
            window.location.reload(); 
          } catch (err) { console.error('Erro salvar edição:', err); alert('Erro ao salvar edição.'); } finally { saveBtn.disabled = false; saveBtn.textContent = 'Salvar'; }
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
      fetchUserAndInit();
      document.getElementById('meusEventosBtn').onclick = () => location.href = 'meus_eventos.php';
      document.getElementById('addEventBtn').onclick = () => location.href = 'adicionarevento.php';
      document.getElementById('permissionsBtn').onclick = () => location.href = 'permissions.php';
      document.getElementById('gerenciarCursosBtn').onclick = () => location.href = 'gerenciar_cursos.php';
    });
  </script>
</body>
</html>