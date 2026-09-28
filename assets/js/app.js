document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('navToggle');
  const headerNav = document.getElementById('headerNav');
  if (navToggle && headerNav) {
    navToggle.addEventListener('click', () => headerNav.classList.toggle('open'));
  }

  // legacy sidebar toggle (se existir em páginas antigas)
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-close-modal');
      const modal = document.getElementById(id);
      if (modal) modal.classList.remove('open');
    });
  });

  document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-open-modal');
      const modal = document.getElementById(id);
      if (modal) modal.classList.add('open');
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (ev) => {
      if (!confirm(form.getAttribute('data-confirm') || 'Confirmar?')) {
        ev.preventDefault();
      }
    });
  });

  document.querySelectorAll('.copy-token').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const value = btn.getAttribute('data-copy') || '';
      try {
        await navigator.clipboard.writeText(value);
        btn.title = 'Copiado!';
        setTimeout(() => { btn.title = 'Copiar ID'; }, 1200);
      } catch (_) {
        /* ignore */
      }
    });
  });
});

async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.message || 'Falha na requisição');
  }
  return data;
}
