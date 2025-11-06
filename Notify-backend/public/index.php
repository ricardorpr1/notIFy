<?php
// index.php - Página principal (substituir completamente)
session_start();

// redireciona se não autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>notIFy — Calendário</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
  <style>
    body { margin:0; font-family:Arial, Helvetica, sans-serif; background:#f7f7f7; }
    #calendarContainer { max-width:1100px; margin:70px auto; background:#fff; padding:18px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); }
    #addEventBtn, #permissionsBtn { position:fixed; top:20px; left:20px; background:#228b22; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; z-index:1100; display:none; }
    #permissionsBtn { left:160px; background:#6f42c1; }
    #userArea { position:fixed; top:12px; right:12px; z-index:1200; display:flex; gap:10px; align-items:center; }
    #profileImg { width:44px;height:44px;border-radius:50%;object-fit:cover;box-shadow:0 2px 6px rgba(0,0,0,0.15); cursor:pointer; border:2px solid #fff; }
    #logoutMini { background:#d9534f;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer; display:none; }

    /* modal */
    #overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1200; align-items:center; justify-content:center; }
    .modal { background:#fff; width:90%; max-width:760px; border-radius:10px; padding:16px; box-shadow:0 12px 30px rgba(0,0,0,0.25); max-height:90vh; overflow:auto; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .modal-title { margin:0; font-size:20px; }
    .modal-close { background:transparent; border:none; font-size:24px; cursor:pointer; }
    .modal-body { display:grid; grid-template-columns:1fr 300px; gap:16px; margin-top:12px; }
    .modal-desc { white-space:pre-wrap; color:#333; }
    .modal-image { width:100%; height:100%; object-fit:cover; border-radius:6px; display:block; max-height:360px; }
    .modal-footer { margin-top:14px; display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
    .btn { padding:8px 12px; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
    .btn-close { background:#6c757d; color:#fff; }
    .btn-delete { background:#d9534f; color:#fff; }
    .btn-inscribe { background:#0b6bff; color:#fff; }
    .btn-export { background:#007bff; color:#fff; }
    .btn-collab { background:#6f42c1; color:#fff; }
    .btn-edit { background:#17a2b8; color:#fff; }

    /* profile card overlay */
    #profileOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:2000; align-items:center; justify-content:center; }
    #profileCard { background:#fff; width:360px; border-radius:10px; padding:18px; box-shadow:0 12px 30px rgba(0,0,0,0.25); }

    .qr-box { display:flex; flex-direction:column; align-items:center; gap:8px; margin-top:12px; }

    @media (max-width:780px) {
      .modal-body { grid-template-columns:1fr; }
      .modal-image { max-height:200px; }
      #profileCard { width:92%; }
    }
  </style>
</head>
<body>
  <!-- botões (inicialmente escondidos; serão mostrados via JS após get_user.php) -->
  <button id="addEventBtn">Adicionar Evento</button>
  <button id="permissionsBtn">Permissões</button>

  <!-- user area (profile image + logout pequeno) -->
  <div id="userArea">
    <img id="profileImg" src="default.jpg" alt="Perfil" title="Meu perfil" />
    <button id="logoutMini">Sair</button>
  </div>

  <div id="calendarContainer">
    <div id="calendar"></div>
  </div>

  <!-- Modal de visualização de evento -->
  <div id="overlay" aria-modal="true" role="dialog">
    <div id="viewModal" class="modal" role="document">
      <div class="modal-header">
        <h3 id="modalTitle" class="modal-title">Título</h3>
        <button id="modalClose" class="modal-close" aria-label="Fechar">&times;</button>
      </div>

      <div class="modal-body">
        <div>
          <p id="modalDescription" class="modal-desc">Descrição</p>
          <p style="color:#666; margin-top:10px;"><strong>Local:</strong> <span id="modalLocation">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Início:</strong> <span id="modalStart">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Fim:</strong> <span id="modalEnd">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Participantes inscritos:</strong> <span id="modalCount">0</span></p>
        </div>
        <div>
          <img id="modalImage" class="modal-image" src="" alt="Capa do evento" style="display:none" />
        </div>
      </div>

      <div class="modal-footer">
        <button id="btnValidate" class="btn" style="background:#10b981; color:#fff; display:none;">Validar presença</button>
        <button id="btnClose" class="btn btn-close">Fechar</button>
        <button id="inscribeBtn" class="btn btn-inscribe" style="display:none;">Inscrever-se</button>
        <button id="btnExport" class="btn btn-export" style="display:none;">Exportar lista de inscrições</button>
        <button id="btnAddCollaborators" class="btn btn-collab" style="display:none;">Adicionar colaboradores</button>
        <button id="btnEdit" class="btn btn-edit" style="display:none;">Editar</button>
        <button id="btnDelete" class="btn btn-delete" style="display:none;">Excluir evento</button>
      </div>
    </div>
  </div>

  <!-- profile card -->
  <div id="profileOverlay">
    <div id="profileCard">
      <div style="display:flex;gap:12px;align-items:center;">
        <img id="cardPhoto" src="default.jpg" alt="Foto" style="width:88px;height:88px;border-radius:10px;object-fit:cover"/>
        <div style="flex:1;">
          <h3 id="cardName" style="margin:0;font-size:18px;">Nome</h3>
          <div id="cardRole" style="font-size:13px;color:#666;margin-top:4px;">ROLE</div>
        </div>
      </div>

      <div style="margin-top:12px;font-size:14px;color:#333;">
        <p style="margin:6px 0"><strong>E-mail:</strong> <span id="cardEmail">—</span></p>
        <p style="margin:6px 0"><strong>CPF:</strong> <span id="cardCPF">—</span></p>
        <p style="margin:6px 0"><strong>RA:</strong> <span id="cardRA">—</span></p>
        <p style="margin:6px 0"><strong>Telefone:</strong> <span id="cardPhone">—</span></p>
        <p style="margin:6px 0"><strong>Data de nascimento:</strong> <span id="cardBirth">—</span></p>
      </div>

      <!-- QR code generator section -->
      <div class="qr-box">
        <button id="generateQRBtn" class="btn" style="background:#0b6bff;color:#fff;">Gerar QR code (CPF)</button>
        <img id="qrImage" src="" alt="QR Code" style="display:none;width:150px;height:150px;border-radius:8px;border:1px solid #e0e0e0;background:#fff;padding:6px;"/>
        <a id="downloadQR" href="#" download="cpf_qr.png" style="display:none;margin-top:4px;color:#007bff;text-decoration:none;">Baixar QR</a>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
        <button id="editProfileBtn" style="background:#17a2b8;color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;">Editar perfil</button>
        <button id="closeProfileBtn" style="background:#6c757d;color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;">Fechar</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    // Variáveis que serão preenchidas após get_user.php
    let currentUser = null; // objeto com id, nome, role, foto_url...
    let calendar = null;
    let selectedEvent = null;

    // utilitários
    function formatDateTimeForDisplay(s) {
      if (!s) return '—';
      const d = new Date(s.replace(' ', 'T'));
      if (isNaN(d)) return s;
      return d.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    }
    function toInputDatetimeLocal(s) {
      if (!s) return '';
      const t = s.replace(' ', 'T');
      return t.length >= 16 ? t.slice(0,16) : t;
    }
    function parseCollaborators(props) {
      let arr = [];
      if (!props) return arr;
      if (Array.isArray(props.colaboradores_ids)) return props.colaboradores_ids.map(String);
      if (Array.isArray(props.colaboradores)) return props.colaboradores.map(String);
      const tryField = props.colaboradores_ids ?? props.colaboradores ?? null;
      if (!tryField) return arr;
      if (typeof tryField === 'string') {
        try {
          const parsed = JSON.parse(tryField);
          if (Array.isArray(parsed)) return parsed.map(String);
        } catch(e) {
          return tryField.split(',').map(s => s.trim()).filter(Boolean).map(String);
        }
      }
      return [String(tryField)];
    }
    function getEventColor(start, end) {
      const now = new Date();
      const s = start ? new Date(start) : null;
      const e = end ? new Date(end) : null;
      if (s && now < s) return 'green';
      if (s && e && now >= s && now <= e) return 'yellow';
      if (e && now > e) return 'red';
      return 'green';
    }

    // fetch do usuário logado e inicializa app
    async function fetchUserAndInit() {
      try {
        const res = await fetch('get_user.php', { cache: 'no-store' });
        if (!res.ok) {
          // redireciona para inicio se não autenticado
          if (res.status === 401) window.location.href = 'telainicio.html';
          throw new Error('Falha ao obter dados do usuário');
        }
        const data = await res.json();
        currentUser = data;

        // atualizar UI (perfil, botões)
        const profileImg = document.getElementById('profileImg');
        profileImg.src = currentUser.foto_url || 'default.jpg';
        profileImg.onerror = () => profileImg.src = 'default.jpg';

        document.getElementById('logoutMini').style.display = 'inline-block';

        // mostrar botões conforme role
        if (currentUser.role >= 1) document.getElementById('addEventBtn').style.display = 'inline-block';
        if (currentUser.role === 2) document.getElementById('permissionsBtn').style.display = 'inline-block';

        // inicializar calendário e handlers agora que sabemos o usuário
        initCalendar();
        attachProfileHandlers();
      } catch (err) {
        console.error('Erro ao carregar usuário:', err);
        alert('Não foi possível carregar dados do usuário. Recarregue a página.');
      }
    }

    // inicializa o calendário e todos os handlers (usa currentUser)
    function initCalendar() {
      const overlay = document.getElementById('overlay');
      const viewModal = document.getElementById('viewModal');
      const modalTitle = document.getElementById('modalTitle');
      const modalDescription = document.getElementById('modalDescription');
      const modalLocation = document.getElementById('modalLocation');
      const modalImage = document.getElementById('modalImage');
      const modalCount = document.getElementById('modalCount');
      const modalStart = document.getElementById('modalStart');
      const modalEnd = document.getElementById('modalEnd');

      const modalClose = document.getElementById('modalClose');
      const btnClose = document.getElementById('btnClose');
      const inscribeBtn = document.getElementById('inscribeBtn');
      const btnDelete = document.getElementById('btnDelete');
      const btnExport = document.getElementById('btnExport');
      const btnAddCollaborators = document.getElementById('btnAddCollaborators');
      const btnEdit = document.getElementById('btnEdit');

      function openModal() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
      function closeModal() { overlay.style.display = 'none'; document.body.style.overflow = ''; selectedEvent = null; }

      modalClose.addEventListener('click', closeModal);
      btnClose.addEventListener('click', closeModal);
      overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

      calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        eventClick: async function(info) {
          selectedEvent = info.event;
          const props = selectedEvent.extendedProps || {};

          modalTitle.textContent = selectedEvent.title || props.nome || 'Sem título';
          modalDescription.textContent = props.descricao ?? props.description ?? '';
          modalLocation.textContent = props.local ?? props.location ?? 'Não informado';

          const startStr = selectedEvent.start ? selectedEvent.start.toISOString().slice(0,19).replace('T',' ') : (props.data_hora_inicio ?? props.start ?? '');
          const endStr = selectedEvent.end ? selectedEvent.end.toISOString().slice(0,19).replace('T',' ') : (props.data_hora_fim ?? props.end ?? '');

          modalStart.textContent = formatDateTimeForDisplay(startStr);
          modalEnd.textContent = formatDateTimeForDisplay(endStr);

          const inscricoes = Array.isArray(props.inscricoes) ? props.inscricoes.map(String) : (props.inscricoes ? (Array.isArray(props.inscricoes) ? props.inscricoes.map(String) : String(props.inscricoes).split(',').map(s=>s.trim()).filter(Boolean)) : []);
          modalCount.textContent = inscricoes.length;

          let createdBy = null;
          if (props.created_by !== undefined && props.created_by !== null) createdBy = String(props.created_by);
          else if (selectedEvent._def && selectedEvent._def.extendedProps && selectedEvent._def.extendedProps.created_by !== undefined) createdBy = String(selectedEvent._def.extendedProps.created_by);

          const collaboratorsArr = parseCollaborators(props);

          // Inscrição botão
          if (currentUser && currentUser.id) {
            inscribeBtn.style.display = 'inline-block';
            const isInscrito = inscricoes.includes(String(currentUser.id));
            inscribeBtn.textContent = isInscrito ? 'Remover inscrição' : 'Inscrever-se';
            inscribeBtn.disabled = false;
          } else {
            inscribeBtn.style.display = 'none';
          }

          // permissões
          const isDev = (currentUser && currentUser.role === 2);
          const isCreator = (createdBy !== null && String(createdBy) === String(currentUser.id));
          const isCollaborator = collaboratorsArr.includes(String(currentUser.id));

          if (isDev || isCreator || isCollaborator) {
            btnExport.style.display = 'inline-block';
            btnDelete.style.display = 'inline-block';
            btnEdit.style.display = 'inline-block';
          } else {
            btnExport.style.display = 'none';
            btnDelete.style.display = 'none';
            btnEdit.style.display = 'none';
          }

          if (isDev || isCreator) btnAddCollaborators.style.display = 'inline-block'; else btnAddCollaborators.style.display = 'none';

          const imageUrl = props.capa_url ?? props.capa ?? props.image ?? null;
          if (imageUrl) { modalImage.src = imageUrl; modalImage.style.display = 'block'; } else modalImage.style.display = 'none';

          openModal();
        },
        eventDidMount: function(info) {
          const start = info.event.start ? info.event.start.toISOString().slice(0,19).replace('T',' ') : (info.event.extendedProps?.data_hora_inicio ?? null);
          const end = info.event.end ? info.event.end.toISOString().slice(0,19).replace('T',' ') : (info.event.extendedProps?.data_hora_fim ?? null);
          const c = getEventColor(start, end);
          info.el.style.backgroundColor = c;
          info.el.style.borderColor = c;
        }
      });

      calendar.render();

      // carregar eventos
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
                icone_url: row.icone_url ?? null,
                inscricoes: row.inscricoes ?? [],
                colaboradores: row.colaboradores ?? [],
                colaboradores_ids: row.colaboradores_ids ?? [],
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

      // Inscrever / remover inscrição
      inscribeBtn.addEventListener('click', async () => {
        if (!selectedEvent) return;
        try {
          const res = await fetch('inscrever_evento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: selectedEvent.id })
          });
          const text = await res.text();
          let json = {};
          try { json = text ? JSON.parse(text) : {}; } catch(e) { json = { erro: 'Resposta inválida' }; }
          if (!res.ok) { alert(json.erro || 'Erro ao processar inscrição.'); return; }
          const inscricoes = Array.isArray(json.inscricoes) ? json.inscricoes.map(String) : [];
          modalCount.textContent = inscricoes.length;
          inscribeBtn.textContent = !!json.inscrito ? 'Remover inscrição' : 'Inscrever-se';
          try { selectedEvent.setExtendedProp('inscricoes', inscricoes); } catch(e) { selectedEvent.extendedProps = selectedEvent.extendedProps || {}; selectedEvent.extendedProps.inscricoes = inscricoes; }
        } catch (err) {
          console.error('Erro na inscrição:', err);
          alert('Erro ao inscrever-se.');
        }
      });

      // Excluir evento
      btnDelete.addEventListener('click', async () => {
        if (!selectedEvent) return;
        if (!confirm('Excluir este evento permanentemente?')) return;
        try {
          const res = await fetch('delete_event.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id: selectedEvent.id })
          });
          const json = await res.json();
          if (!res.ok) { alert(json.erro || 'Erro ao excluir evento.'); return; }
          const ev = calendar.getEventById(selectedEvent.id);
          if (ev) ev.remove();
          closeModal();
          alert(json.mensagem || 'Evento excluído.');
        } catch (err) {
          console.error('Erro ao excluir:', err);
          alert('Erro na exclusão.');
        }
      });

      // Exportar lista
      btnExport.addEventListener('click', () => {
        if (!selectedEvent) return;
        window.location.href = `export_inscricoes.php?id=${encodeURIComponent(selectedEvent.id)}`;
      });

      // Add collaborators
      btnAddCollaborators.addEventListener('click', () => {
        if (!selectedEvent) return;
        window.location.href = `collaborators.php?event_id=${encodeURIComponent(selectedEvent.id)}`;
      });

      // Edit event (abre modal de edição)
      btnEdit.addEventListener('click', () => {
        if (!selectedEvent) return;
        const props = selectedEvent.extendedProps || {};
        const pre = {
          id: selectedEvent.id,
          title: selectedEvent.title || '',
          start: selectedEvent.start ? selectedEvent.start.toISOString().slice(0,19).replace('T',' ') : (props.data_hora_inicio || ''),
          end: selectedEvent.end ? selectedEvent.end.toISOString().slice(0,19).replace('T',' ') : (props.data_hora_fim || ''),
          local: props.local || props.location || '',
          description: props.descricao || props.description || '',
          capa_url: props.capa_url || ''
        };
        openEditModal(pre);
      });

      // logout principal (redireciona)
      document.getElementById('logoutMini').addEventListener('click', async () => {
        try {
          const r = await fetch('logout.php', { method: 'POST' });
          if (r.ok) window.location.href = 'telainicio.html';
          else alert('Erro ao sair.');
        } catch (err) {
          console.error('Erro no logout', err);
          alert('Erro ao sair.');
        }
      });

      // abrir modal de edição (cria dinamicamente)
      function openEditModal(prefill) {
        const existing = document.getElementById('editModal');
        if (existing) existing.remove();
        const editModal = document.createElement('div');
        editModal.id = 'editModal';
        editModal.className = 'modal';
        editModal.style.zIndex = 1300;
        editModal.style.position = 'fixed';
        editModal.style.left = '50%';
        editModal.style.top = '50%';
        editModal.style.transform = 'translate(-50%, -50%)';
        editModal.style.width = '420px';
        editModal.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0">Editar evento</h3>
            <button id="closeEdit" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
          </div>
          <div style="margin-top:10px">
            <label>Título:<br/><input id="edit_title" style="width:100%;padding:6px" value="${escapeHtml(prefill.title||'')}"></label>
            <label>Início:<br/><input id="edit_start" type="datetime-local" style="width:100%;padding:6px" value="${toInputDatetimeLocal(prefill.start||'')}"></label>
            <label>Término:<br/><input id="edit_end" type="datetime-local" style="width:100%;padding:6px" value="${toInputDatetimeLocal(prefill.end||'')}"></label>
            <label>Local:<br/><input id="edit_local" style="width:100%;padding:6px" value="${escapeHtml(prefill.local||'')}"></label>
            <label>Descrição:<br/><textarea id="edit_desc" style="width:100%;padding:6px">${escapeHtml(prefill.description||'')}</textarea></label>
            <label>URL da capa:<br/><input id="edit_capa" style="width:100%;padding:6px" value="${escapeHtml(prefill.capa_url||'')}"></label>
            <label>Limite participantes:<br/><input id="edit_limit" type="number" min="0" style="width:100%;padding:6px" value=""></label>
            <div style="text-align:right;margin-top:8px">
              <button id="cancelEdit" class="btn btn-close" style="margin-right:6px">Cancelar</button>
              <button id="saveEdit" class="btn btn-edit">Salvar</button>
            </div>
          </div>
        `;
        document.body.appendChild(editModal);
        overlay.style.display = 'flex';

        document.getElementById('closeEdit').addEventListener('click', () => { editModal.remove(); overlay.style.display = 'none'; });
        document.getElementById('cancelEdit').addEventListener('click', () => { editModal.remove(); overlay.style.display = 'none'; });

        document.getElementById('saveEdit').addEventListener('click', async () => {
          const payload = {
            id: prefill.id,
            nome: document.getElementById('edit_title').value.trim(),
            data_hora_inicio: document.getElementById('edit_start').value,
            data_hora_fim: document.getElementById('edit_end').value,
            local: document.getElementById('edit_local').value.trim(),
            descricao: document.getElementById('edit_desc').value.trim(),
            capa_url: document.getElementById('edit_capa').value.trim(),
            limite_participantes: document.getElementById('edit_limit').value ? parseInt(document.getElementById('edit_limit').value,10) : null
          };

          if (!payload.nome || !payload.data_hora_inicio || !payload.data_hora_fim) {
            alert('Preencha título, início e término.');
            return;
          }

          try {
            const res = await fetch('edit_event.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });
            const text = await res.text();
            let json;
            try { json = text ? JSON.parse(text) : {}; } catch(e) { json = { erro: 'Resposta inválida' }; }

            if (!res.ok) { alert(json.erro || 'Erro ao editar evento.'); return; }

            const ev = calendar.getEventById(String(prefill.id));
            if (ev) {
              ev.setProp('title', payload.nome);
              ev.setStart(payload.data_hora_inicio);
              ev.setEnd(payload.data_hora_fim);
              ev.setExtendedProp('descricao', payload.descricao);
              ev.setExtendedProp('local', payload.local);
              ev.setExtendedProp('capa_url', payload.capa_url);
              ev.setExtendedProp('limite_participantes', payload.limite_participantes);
            }

            alert(json.mensagem || 'Evento atualizado.');
            editModal.remove();
            overlay.style.display = 'none';
            if (selectedEvent) {
              modalTitle.textContent = payload.nome;
              modalDescription.textContent = payload.descricao;
              modalLocation.textContent = payload.local;
              modalStart.textContent = formatDateTimeForDisplay(payload.data_hora_inicio);
              modalEnd.textContent = formatDateTimeForDisplay(payload.data_hora_fim);
              if (payload.capa_url) { modalImage.src = payload.capa_url; modalImage.style.display = 'block'; }
            }
          } catch (err) {
            console.error('Erro salvar edição:', err);
            alert('Erro ao salvar edição.');
          }
        });
      }

      function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    } // end initCalendar

    // perfil handlers (abre get_user.php quando clicar)
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
          document.getElementById('cardRA').textContent = u.registro_academico || '—';
          document.getElementById('cardPhone').textContent = u.telefone || '—';
          document.getElementById('cardBirth').textContent = u.data_nascimento || '—';

          // limpar qr anterior
          qrImage.style.display = 'none';
          qrImage.src = '';
          downloadQR.style.display = 'none';

          profileOverlay.style.display = 'flex';
          document.body.style.overflow = 'hidden';
        } catch (err) {
          console.error('Erro abrir cartão:', err);
          alert('Não foi possível abrir o cartão do usuário.');
        }
      });

      closeProfileBtn.addEventListener('click', () => { document.getElementById('profileOverlay').style.display = 'none'; document.body.style.overflow = ''; });
      document.getElementById('profileOverlay').addEventListener('click', (e) => { if (e.target === document.getElementById('profileOverlay')) { document.getElementById('profileOverlay').style.display = 'none'; document.body.style.overflow = ''; } });

      editProfileBtn.addEventListener('click', () => { window.location.href = 'perfil_editar.php'; });

      // Geração do QR (usa a mesma ideia do script.js do seu colega)
      generateQRBtn.addEventListener('click', async () => {
        try {
          const res = await fetch('get_user.php', { cache: 'no-store' });
          if (!res.ok) { alert('Não foi possível obter CPF.'); return; }
          const u = await res.json();
          let cpf = u.cpf || '';
          // remover tudo que não é dígito
          cpf = String(cpf).replace(/\D/g, '');
          if (!cpf) { alert('CPF não disponível.'); return; }
          if (cpf.length !== 11) {
            // aceita CPF com menos/mais dígitos? você pediu 11 dígitos: avisar o usuário.
            alert('CPF precisa ter 11 dígitos para gerar o QR code. Valor atual: ' + cpf);
            return;
          }
          // gerar QR usando API externa simples
          const size = 250;
          const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(cpf)}`;
          qrImage.src = qrUrl;
          qrImage.style.display = 'block';

          // configurar link de download direto (alguns navegadores não vão permitir salvar cross-origin images; mas linkará para a imagem)
          downloadQR.href = qrUrl;
          downloadQR.style.display = 'inline-block';
          downloadQR.textContent = 'Baixar QR';
        } catch (err) {
          console.error('Erro gerar QR:', err);
          alert('Erro ao gerar QR code.');
        }
      });
    }

    // Iniciar: buscar usuário e inicializar
    document.addEventListener('DOMContentLoaded', () => {
      fetchUserAndInit();
      // addEvent btn redirect
      document.getElementById('addEventBtn').addEventListener('click', () => { location.href = 'adicionarevento.html'; });
      document.getElementById('permissionsBtn').addEventListener('click', () => { location.href = 'permissions.php'; });
    });
  </script>
</body>
</html>
