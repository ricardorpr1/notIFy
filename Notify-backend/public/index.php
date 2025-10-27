<?php
// index.php — protegido por sessão e com controle de UI por role
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: telainicio.html');
    exit;
}

// garantir que role existe (0 = USER, 1 = ORGANIZADOR, 2 = DEV)
$currentUserId = intval($_SESSION['usuario_id']);
$currentUserName = $_SESSION['usuario_nome'] ?? 'Usuário';
$currentRole = intval($_SESSION['role'] ?? 0);
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
    #calendarContainer { max-width:900px; margin:60px auto; background:#fff; padding:18px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); }
    #addEventBtn { position:fixed; top:20px; left:20px; background:#228b22; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; z-index:1100; }
    #addEventBtn:hover { background:#1e7a1e; }
    #userInfo { position:fixed; top:18px; right:20px; z-index:1100; display:flex; gap:12px; align-items:center; }
    #userName { color:#045c3f; font-weight:600; }
    #logoutBtn { background:#d9534f; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; }

    #overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1200; align-items:center; justify-content:center; }
    #modal { background:#fff; width:90%; max-width:760px; border-radius:10px; padding:16px; box-shadow:0 12px 30px rgba(0,0,0,0.25); max-height:90vh; overflow:auto; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .modal-title { margin:0; font-size:20px; }
    .modal-close { background:transparent; border:none; font-size:24px; cursor:pointer; }
    .modal-body { display:grid; grid-template-columns:1fr 320px; gap:16px; margin-top:12px; }
    .modal-desc { white-space:pre-wrap; color:#333; }
    .modal-image { width:100%; height:100%; object-fit:cover; border-radius:6px; display:block; max-height:360px; }
    .modal-footer { margin-top:14px; display:flex; justify-content:flex-end; gap:8px; }
    .btn { padding:8px 12px; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
    .btn-close { background:#6c757d; color:#fff; }
    .btn-delete { background:#d9534f; color:#fff; }
    .btn-inscribe { background:#0b6bff; color:#fff; }
    .btn-export { background:#007bff; color:#fff; }

    @media (max-width:780px) {
      .modal-body { grid-template-columns:1fr; }
      .modal-image { max-height:200px; }
    }
  </style>
</head>
<body>
  <?php if ($currentRole >= 1): ?>
    <button id="addEventBtn" onclick="location.href='adicionarevento.html'">Adicionar Evento</button>
  <?php endif; ?>

  <div id="userInfo">
    <div id="userName"><?= htmlspecialchars($currentUserName) ?></div>
    <button id="logoutBtn">Sair</button>
  </div>

  <div id="calendarContainer">
    <div id="calendar"></div>
  </div>

  <!-- Modal / Overlay -->
  <div id="overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div id="modal">
      <div class="modal-header">
        <h3 id="modalTitle" class="modal-title">Título</h3>
        <button class="modal-close" id="modalClose" aria-label="Fechar">&times;</button>
      </div>

      <div class="modal-body">
        <div>
          <p id="modalDescription" class="modal-desc">Descrição</p>
          <p style="color:#666; margin-top:10px;"><strong>Local:</strong> <span id="modalLocation">—</span></p>
          <p style="color:#666; margin-top:6px;"><strong>Participantes inscritos:</strong> <span id="modalCount">0</span></p>
        </div>
        <div>
          <img id="modalImage" class="modal-image" src="" alt="Capa do evento" style="display:none" />
        </div>
      </div>

      <div class="modal-footer">
        <button id="btnClose" class="btn btn-close">Fechar</button>
        <button id="inscribeBtn" class="btn btn-inscribe" style="display:none;">Inscrever-se</button>
        <button id="btnExport" class="btn btn-export" style="display:none;">Exportar lista de inscrições</button>
        <button id="btnDelete" class="btn btn-delete" style="display:none;">Excluir evento</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    // dados do servidor injetados para controle de UI e ações
    const currentUserId = <?= json_encode($currentUserId) ?>;
    const currentRole = <?= json_encode($currentRole) ?>; // 0=user,1=organizador,2=dev

    let calendar;
    let selectedEvent = null;

    function toIsoString(mysqlDateTime) { if (!mysqlDateTime) return null; return mysqlDateTime.replace(' ', 'T'); }

    function normalizeEvent(row) {
      const id = row.id ?? null;
      const title = row.nome ?? row.title ?? 'Sem título';
      const start = toIsoString(row.data_hora_inicio ?? row.start);
      const end = toIsoString(row.data_hora_fim ?? row.end);
      const description = row.descricao ?? row.description ?? '';
      const location = row.local ?? row.location ?? '';
      const image = row.capa_url ?? row.image ?? null;
      const inscricoes = row.inscricoes ?? (row.extendedProps && row.extendedProps.inscricoes ? row.extendedProps.inscricoes : []);
      return { id, title, start, end, extendedProps: { description, location, image, inscricoes } };
    }

    document.addEventListener('DOMContentLoaded', function () {
      const calendarEl = document.getElementById('calendar');
      const overlay = document.getElementById('overlay');
      const modalClose = document.getElementById('modalClose');
      const btnClose = document.getElementById('btnClose');
      const btnDelete = document.getElementById('btnDelete');
      const btnExport = document.getElementById('btnExport');
      const inscribeBtn = document.getElementById('inscribeBtn');
      const modalTitle = document.getElementById('modalTitle');
      const modalDescription = document.getElementById('modalDescription');
      const modalLocation = document.getElementById('modalLocation');
      const modalImage = document.getElementById('modalImage');
      const modalCount = document.getElementById('modalCount');
      const logoutBtn = document.getElementById('logoutBtn');

      function openModal() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
      function closeModal() { overlay.style.display = 'none'; document.body.style.overflow = ''; selectedEvent = null; }

      modalClose.addEventListener('click', closeModal);
      btnClose.addEventListener('click', closeModal);
      overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

      calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        eventClick: function (info) {
          selectedEvent = info.event;
          const props = selectedEvent.extendedProps || {};
          modalTitle.textContent = selectedEvent.title || 'Sem título';
          modalDescription.textContent = props.description || 'Sem descrição.';
          modalLocation.textContent = props.location || 'Não informado';

          const inscricoes = Array.isArray(props.inscricoes) ? props.inscricoes.map(x => String(x)) : [];
          modalCount.textContent = inscricoes.length;

          // regra de exibição dos botões por role
          // se role == 0 (USER) => mostrar apenas inscrever + fechar
          // se role >= 1 (ORGANIZADOR ou DEV) => mostrar inscrever, exportar, excluir, fechar
          if (currentUserId > 0) {
            inscribeBtn.style.display = 'inline-block';
            const isInscrito = inscricoes.includes(String(currentUserId));
            inscribeBtn.textContent = isInscrito ? 'Inscrito' : 'Inscrever-se';
            inscribeBtn.disabled = false;
          } else {
            inscribeBtn.style.display = 'none';
          }

          if (currentRole >= 1) {
            // organizador/dev: pode exportar e excluir (server deve validar permissões)
            btnExport.style.display = 'inline-block';
            btnDelete.style.display = 'inline-block';
          } else {
            btnExport.style.display = 'none';
            btnDelete.style.display = 'none';
          }

          if (props.image) {
            modalImage.src = props.image;
            modalImage.style.display = 'block';
          } else {
            modalImage.style.display = 'none';
          }

          openModal();
        }
      });

      calendar.render();

      // carregar eventos
      (async () => {
        try {
          const resp = await fetch('list_events.php', { cache: 'no-store' });
          const text = await resp.text();
          const data = JSON.parse(text);
          data.forEach(row => {
            const ev = normalizeEvent(row);
            if (ev.start) calendar.addEvent(ev);
          });
        } catch (err) {
          console.error('Erro ao carregar eventos:', err);
        }
      })();

      // inscrever / remover inscrição (igual antes)
      inscribeBtn.addEventListener('click', async () => {
        if (!selectedEvent) return;
        const eventId = selectedEvent.id;
        try {
          const res = await fetch('inscrever_evento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: eventId })
          });
          const text = await res.text();
          const json = text ? JSON.parse(text) : null;
          if (!res.ok) {
            alert(json && json.erro ? json.erro : 'Erro ao inscrever-se.');
            return;
          }

          const inscricoes = Array.isArray(json.inscricoes) ? json.inscricoes.map(x => String(x)) : [];
          const isInscrito = !!json.inscrito;

          modalCount.textContent = inscricoes.length;
          inscribeBtn.textContent = isInscrito ? 'Inscrito' : 'Inscrever-se';

          try {
            selectedEvent.setExtendedProp('inscricoes', inscricoes);
          } catch (e) {
            selectedEvent.extendedProps = selectedEvent.extendedProps || {};
            selectedEvent.extendedProps.inscricoes = inscricoes;
          }

        } catch (err) {
          console.error('Erro na inscrição:', err);
          alert('Erro de rede ao inscrever-se.');
        }
      });

      // excluir evento (apenas UI; server deve validar role)
      btnDelete.addEventListener('click', async () => {
        if (!selectedEvent) return alert('Evento inválido.');
        if (!confirm('Excluir este evento permanentemente?')) return;
        try {
          const res = await fetch('delete_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: selectedEvent.id })
          });
          const json = await res.json();
          if (res.ok) {
            const ev = calendar.getEventById(selectedEvent.id);
            if (ev) ev.remove();
            closeModal();
            alert('Evento excluído.');
          } else alert(json.erro || 'Erro ao excluir.');
        } catch (err) {
          console.error(err);
          alert('Erro de comunicação com o servidor.');
        }
      });

      // exportar lista (apenas link para download; server deve verificar permissão)
      btnExport.addEventListener('click', () => {
        if (!selectedEvent) return alert('Evento inválido.');
        const eventId = selectedEvent.id;
        // abrir o PHP que gera CSV; o PHP deve validar a sessão/role
        window.location.href = `export_inscricoes.php?id=${encodeURIComponent(eventId)}`;
      });

      // logout
      logoutBtn.addEventListener('click', async () => {
        try {
          const res = await fetch('logout.php', { method: 'POST' });
          if (res.ok) {
            window.location.href = 'telainicio.html';
          } else {
            alert('Falha ao sair. Tente novamente.');
          }
        } catch (err) {
          console.error('Erro no logout:', err);
          alert('Erro ao sair. Veja console.');
        }
      });

    });
  </script>
</body>
</html>
