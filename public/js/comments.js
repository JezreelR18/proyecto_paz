(function () {
  let currentUserId = null;

  const BAD_WORDS = [
    'puta','puto','pendejo','pendeja','idiota','imbecil','inutil','estupido','estupida',
    'mierda','zorra','marica','culero','cabron','perra','tarado','tarada'
  ];

  document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('chatMessages')) return;
    boot();

    const input = document.getElementById('messageInput');
    if (input) {
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          commentsSend();
        }
      });
    }
  });

  async function boot() {
    await readSession();
    await loadComments();
  }

  async function readSession() {
    try {
      const r = await fetch('api/auth.php?action=session', { credentials: 'same-origin' });
      const j = await r.json();
      const sess = j && j.data ? j.data : { loggedIn: false, user: null };
      currentUserId = sess.user ? sess.user.id : null;
    } catch {
      currentUserId = null;
    }
  }

  async function loadComments() {
    try {
      const res = await fetch('api/comments.php?action=list&limit=200', { credentials: 'same-origin' });
      const raw = await res.text();
      let data;
      try { data = raw ? JSON.parse(raw) : null; }
      catch { console.error('[comments] respuesta no JSON:', raw); throw new Error('JSON inválido'); }

      if (!res.ok || !data || data.ok === false) {
        console.error('[comments] list error', res.status, data);
        alert((data && (data.error || data.message)) || 'No se pudieron cargar los comentarios.');
        return;
      }

      const rows = (data.data && data.data.comments) ? data.data.comments : [];
      render(rows);
    } catch (e) {
      console.error('[comments] list error', e);
      alert('No se pudieron cargar los comentarios.');
    }
  }


  function render(items) {
    const box = document.getElementById('chatMessages');
    if (!box) return;

    box.innerHTML = `
      <div class="message bot-message">
        <p>Bienvenido al chat moderado. Este es un espacio seguro para compartir ideas positivas y buenas prácticas de convivencia.</p>
      </div>
    `;

    for (const it of items) {
      const isSystem = (it.author_id === 1) || (it.level_monitoring === 'suggestion');
      const isMine = currentUserId && it.author_id === currentUserId;

      const author = isSystem
        ? 'Sistema'
        : (it.author_fullname && it.author_fullname.trim()
            ? it.author_fullname
            : (it.author_username || 'Anónimo'));

      const when = new Date((it.date_of_register || '').replace(' ', 'T'));
      const time = isNaN(when.getTime()) ? '' : when.toLocaleString();

      const div = document.createElement('div');
      div.className = 'message ' + (isSystem ? 'bot-message' : (isMine ? 'user-message' : 'other-message'));
      div.innerHTML = `
        <div class="message-meta" style="font-size:.8rem;opacity:.8;margin-bottom:.25rem;">
          <strong>${esc(author)}</strong>
          ${time ? `<span style="margin-left:.5rem;">${esc(time)}</span>` : ''}
          ${it.level_monitoring ? `<span style="margin-left:.5rem;">[${esc(it.level_monitoring)}]</span>` : ''}
        </div>
        <p>${esc(it.comment)}</p>
      `;
      box.appendChild(div);
    }

    box.scrollTop = box.scrollHeight;
  }

  window.sendMessage = commentsSend;
  window.commentsSend = commentsSend;

  async function commentsSend() {
    const input = document.getElementById('messageInput');
    if (!input) return;

    const original = input.value;
    const text = original.trim();
    if (!text) return;

    if (!currentUserId) {
      alert('Inicia sesión para participar.');
      window.location.href = 'index.php?page=login';
      return;
    }

    if (containsBadWords(text)) {
      alert('Tu mensaje contiene palabras no permitidas. Por favor, reformúlalo.');
      return;
    }

    try {
      const res = await fetch('api/comments.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment: original, level_monitoring: 'normal' })
      });

      const raw = await res.text();
      let data = null; try { data = raw ? JSON.parse(raw) : null; } catch {}
      if (!res.ok) {
        console.error('[comments] create error', res.status, raw);
        if (res.status === 401) {
          alert('Tu sesión expiró. Inicia sesión nuevamente.');
          window.location.href = 'index.php?page=login';
          return;
        }
        alert((data && (data.message || data.error)) || 'No se pudo enviar el comentario.');
        return;
      }

      input.value = '';
      await loadComments();
    } catch (e) {
      console.error(e);
      alert('Error de red al enviar el comentario.');
    }
  }

  function normalizeText(s) {
    return String(s)
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[@]/g, 'a')
      .replace(/[$]/g, 's')
      .replace(/0/g, 'o').replace(/1/g, 'i').replace(/3/g, 'e')
      .replace(/4/g, 'a').replace(/5/g, 's').replace(/7/g, 't')
      .replace(/[^\p{L}\p{N}\s]/gu, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }
  function containsBadWords(input) {
    const norm = normalizeText(input);
    if (!norm) return false;
    const tokens = new Set(norm.split(' '));
    for (const bad of BAD_WORDS) {
      const b = normalizeText(bad);
      if (tokens.has(b)) return true;
      if (norm.includes(b) && b.length >= 4) return true;
    }
    return false;
  }

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
}

)();

const chatForm = document.querySelector('#chat-form') || document.querySelector('form');
const chatInput = document.querySelector('#chat-input') || chatForm?.querySelector('input, textarea');
const sendBtn = chatForm?.querySelector('button[type="submit"], #send-button');

async function createComment(text) {
  const res = await fetch('api/comments.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({
      comment: text,
      level_monitoring: 'normal'
    })
  });

  const data = await res.json().catch(() => null);

  if (!res.ok || !data?.ok) {
    const msg = data?.error || 'No se pudo enviar el mensaje';
    throw new Error(msg);
  }
}

if (chatForm && chatInput) {
  chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;

    try {
      if (sendBtn) sendBtn.disabled = true;

      await createComment(text);

      chatInput.value = '';

      await loadComments();
    } catch (err) {
      console.error('[comments] Error al enviar:', err);
      alert(err.message || 'Error enviando el mensaje');
    } finally {
      if (sendBtn) sendBtn.disabled = false;
    }
  });

  chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      chatForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }
  });
}

