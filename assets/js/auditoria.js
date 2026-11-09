/**
 * Auditoria: troca de dossiê sem reload (mantém scroll) + glossário em popover.
 */
(function () {
  const main = () => document.getElementById('auditMain');

  // ---- Glossário popover (sem pular a página) ----
  const raw = document.getElementById('auditGlossarioData');
  let glossData = {};
  try {
    glossData = JSON.parse(raw?.textContent || '{}');
  } catch (_) {
    glossData = {};
  }

  let pop = document.getElementById('auditGlossPopover');
  if (!pop) {
    pop = document.createElement('div');
    pop.id = 'auditGlossPopover';
    pop.className = 'audit-gloss-popover';
    pop.hidden = true;
    pop.innerHTML = `
      <button type="button" class="audit-gloss-close" aria-label="Fechar">×</button>
      <h4 id="auditGlossTitle"></h4>
      <p id="auditGlossResumo" class="audit-glossary-resumo"></p>
      <p id="auditGlossDetalhe" class="audit-glossary-detalhe"></p>`;
    document.body.appendChild(pop);
  }

  const titleEl = () => document.getElementById('auditGlossTitle');
  const resumoEl = () => document.getElementById('auditGlossResumo');
  const detalheEl = () => document.getElementById('auditGlossDetalhe');

  function fecharGloss() {
    pop.hidden = true;
  }

  function abrirGloss(id, anchor) {
    const g = glossData[id];
    if (!g) return;
    titleEl().textContent = g.titulo || '';
    resumoEl().textContent = g.resumo || '';
    detalheEl().textContent = g.detalhe || '';
    pop.hidden = false;

    const rect = anchor.getBoundingClientRect();
    const pad = 12;
    let top = rect.bottom + pad + window.scrollY;
    let left = rect.left + window.scrollX;
    pop.style.top = `${top}px`;
    pop.style.left = `${left}px`;

    requestAnimationFrame(() => {
      const box = pop.getBoundingClientRect();
      if (box.right > window.innerWidth - 12) {
        left = Math.max(12, window.scrollX + window.innerWidth - box.width - 12);
      }
      if (box.bottom > window.innerHeight - 12) {
        top = Math.max(12, rect.top + window.scrollY - box.height - pad);
      }
      pop.style.left = `${left}px`;
      pop.style.top = `${top}px`;
    });
  }

  function bindGloss(root = document) {
    root.querySelectorAll('.audit-gloss-btn').forEach((el) => {
      if (el.dataset.bound === '1') return;
      el.dataset.bound = '1';
      el.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        const id = el.getAttribute('data-glossario');
        if (!pop.hidden && pop.dataset.active === id) {
          fecharGloss();
          return;
        }
        pop.dataset.active = id || '';
        abrirGloss(id, el);
      });
    });
  }

  document.getElementById('btnToggleGlossario')?.addEventListener('click', () => {
    const body = document.getElementById('glossarioBody');
    const btn = document.getElementById('btnToggleGlossario');
    if (!body || !btn) return;
    const open = body.hasAttribute('hidden');
    if (open) body.removeAttribute('hidden');
    else body.setAttribute('hidden', '');
    btn.textContent = open ? 'Recolher' : 'Expandir';
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  pop.querySelector('.audit-gloss-close')?.addEventListener('click', fecharGloss);
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') fecharGloss();
  });
  document.addEventListener('click', (ev) => {
    if (!pop.hidden && !pop.contains(ev.target) && !ev.target.closest('.audit-gloss-btn')) {
      fecharGloss();
    }
  });

  bindGloss();

  // ---- Soft navigation da lista de resultados ----
  let carregando = false;

  async function carregarDossie(url, link) {
    if (carregando) return;
    carregando = true;
    const scrollY = window.scrollY;
    const alvo = main();
    if (alvo) alvo.classList.add('is-loading');

    try {
      const u = new URL(url, window.location.origin);
      u.searchParams.set('partial', 'main');
      const res = await fetch(u.toString(), {
        headers: { Accept: 'text/html', 'X-Requested-With': 'fetch' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('Falha ao carregar dossiê');
      const html = await res.text();
      const tmp = document.createElement('div');
      tmp.innerHTML = html.trim();
      const next = tmp.querySelector('#auditMain') || tmp.firstElementChild;
      const current = main();
      if (!next || !current) throw new Error('Fragmento inválido');

      current.replaceWith(next);
      document.querySelectorAll('.audit-op-item').forEach((a) => {
        const ativo = a === link;
        a.classList.toggle('is-active', ativo);
        const badge = a.querySelector('.audit-badge-foco, .audit-badge-ok');
        if (badge) {
          badge.className = ativo ? 'audit-badge-foco' : 'audit-badge-ok';
          badge.textContent = ativo ? 'Em foco' : 'Auditado';
        }
      });

      const clean = new URL(url, window.location.origin);
      clean.searchParams.delete('partial');
      history.pushState({ softAudit: true }, '', clean.pathname + clean.search);

      bindGloss(next);
      if (window.lucide?.createIcons) {
        window.lucide.createIcons({ nodes: [next] });
      }
      window.scrollTo(0, scrollY);
    } catch (_) {
      window.location.href = url;
    } finally {
      carregando = false;
      main()?.classList.remove('is-loading');
    }
  }

  document.querySelector('.audit-op-list')?.addEventListener('click', (ev) => {
    const link = ev.target.closest('a.audit-op-item[data-soft-nav]');
    if (!link) return;
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || link.target === '_blank') return;
    ev.preventDefault();
    carregarDossie(link.href, link);
  });

  window.addEventListener('popstate', () => {
    // Voltar/avançar do browser: recarrega o fragmento da URL atual
    const link = document.querySelector(`.audit-op-item[href="${CSS.escape(location.pathname + location.search)}"]`)
      || [...document.querySelectorAll('.audit-op-item')].find((a) => {
        try {
          return new URL(a.href).searchParams.get('sorteio') === new URL(location.href).searchParams.get('sorteio');
        } catch (_) {
          return false;
        }
      });
    if (link) carregarDossie(location.href, link);
  });
})();
