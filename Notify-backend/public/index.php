<?php
// index.php — proteção de sessão
session_start();
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
    #calendarContainer { max-width:900px; margin:60px auto; background:#fff; padding:18px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); }
    #addEventBtn { position:fixed; top:20px; left:20px; background:#228b22; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; z-index:1100; }
    #addEventBtn:hover { background:#1e7a1e; }

    /* logout / user info */
    #userInfo { position:fixed; top:18px; right:20px; z-index:1100; display:flex; gap:12px; align-items:center; }
    #userName { color:#045c3f; font-weight:600; }
    #logoutBtn { background:#d9534f; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; }

    /* overlay + modal */
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

    @media (max-width:780px) {
      .modal-body { grid-template-columns:1fr; }
      .modal-image { max-height:200px; }
    }
  </style>
</head>
<body>
  <button id="addEventBtn" onclick="location.href='adicionarevento.html'">Adicionar Evento</button>

  <div id="userInfo">
    <div id="userName"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></div>
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
        </div>
        <div>
          <img id="modalImage" class="modal-image" src="" alt="Capa do evento" style="display:none" />
        </div>
      </div>

      <div class="modal-footer">
        <button id="btnClose" class="btn btn-close">Fechar</button>
        <button id="btnDelete" class="btn btn-delete">Excluir evento</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    let calendar;
    let selectedEventId = null;

    function toIsoString(mysqlDateTime) { if (!mysqlDateTime) return null; return mysqlDateTime.replace(' ', 'T'); }

    function normalizeEvent(row) {
      const id = row.id ?? null;
      const title = row.nome ?? row.title ?? 'Sem título';
      const start = toIsoString(row.data_hora_inicio ?? row.start);
      const end = toIsoString(row.data_hora_fim ?? row.end);
      const description = row.descricao ?? row.description ?? '';
      const location = row.local ?? row.location ?? '';
      const image = row.capa_url ?? row.image ?? null;
      return { id, title, start, end, extendedProps: { description, location, image } };
    }

    document.addEventListener('DOMContentLoaded', function () {
      const calendarEl = document.getElementById('calendar');
      const overlay = document.getElementById('overlay');
      const modalClose = document.getElementById('modalClose');
      const btnClose = document.getElementById('btnClose');
      const btnDelete = document.getElementById('btnDelete');
      const modalTitle = document.getElementById('modalTitle');
      const modalDescription = document.getElementById('modalDescription');
      const modalLocation = document.getElementById('modalLocation');
      const modalImage = document.getElementById('modalImage');
      const logoutBtn = document.getElementById('logoutBtn');

      function openModal() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
      function closeModal() { overlay.style.display = 'none'; document.body.style.overflow = ''; selectedEventId = null; }

      modalClose.addEventListener('click', closeModal);
      btnClose.addEventListener('click', closeModal);
      overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

      calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        eventClick: function (info) {
          const ev = info.event;
          const props = ev.extendedProps || {};
          selectedEventId = String(ev.id || '');

          modalTitle.textContent = ev.title || 'Sem título';
          modalDescription.textContent = props.description || 'Sem descrição.';
          modalLocation.textContent = props.location || 'Não informado';

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
          const resp = await fetch('list_events.php');
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

      // exclusão
      btnDelete.addEventListener('click', async () => {
        if (!selectedEventId) return alert('Evento inválido.');
        if (!confirm('Excluir este evento permanentemente?')) return;

        try {
          const res = await fetch('delete_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: selectedEventId })
          });
          const json = await res.json();
          if (res.ok) {
            const ev = calendar.getEventById(selectedEventId);
            if (ev) ev.remove();
            closeModal();
            alert('Evento excluído.');
          } else alert(json.erro || 'Erro ao excluir.');
        } catch (err) {
          console.error(err);
          alert('Erro de comunicação com o servidor.');
        }
      });

      // logout (faz POST para logout.php e redireciona)
      logoutBtn.addEventListener('click', async () => {
        try {
          const res = await fetch('logout.php', { method: 'POST' });
          if (res.ok) {
            // opcional: ler json para mensagem
            // const j = await res.json();
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
