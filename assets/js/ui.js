(() => {
  const reduzMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Cabeçalho ganha vidro/sombra ao rolar
  const header = document.querySelector('.app-header');
  if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // Entrada suave das seções conforme aparecem na tela
  const alvosReveal = document.querySelectorAll([
    '.content > .breadcrumb',
    '.content > .dash-hero',
    '.content > .empresas-hero',
    '.content > .page-header',
    '.content > .wizard-steps',
    '.kpi-grid',
    '.metric-row',
    '.panel',
    '.map-card',
    '.integrity-bar',
    '.result-card',
    '.draw-stage',
    '.elim-card',
    '.company-card',
  ].join(','));

  if (!reduzMovimento && 'IntersectionObserver' in window && alvosReveal.length) {
    document.documentElement.classList.add('js-reveal');
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.04 });

    let ordem = 0;
    alvosReveal.forEach((el) => {
      if (el.closest('.modal-backdrop')) return;
      el.classList.add('reveal');
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight) {
        el.style.transitionDelay = `${Math.min(ordem, 8) * 55}ms`;
        ordem += 1;
      }
      io.observe(el);
    });

    // Garantia: nada fica invisível se o observer falhar
    setTimeout(() => alvosReveal.forEach((el) => el.classList.add('is-visible')), 2500);
  }

  // Linhas de tabela entram em cascata
  document.querySelectorAll('table.data-table tbody').forEach((tbody) => {
    [...tbody.rows].slice(0, 25).forEach((tr, i) => tr.style.setProperty('--row-i', i));
  });

  // Números dos indicadores contam até o valor
  const easeOut = (t) => 1 - Math.pow(1 - t, 3);
  const contar = (el) => {
    const texto = el.textContent.trim();
    const m = texto.match(/^(\d{1,3}(?:\.\d{3})*|\d+)([.,]\d+)?(.*)$/s);
    if (!m) return;
    const inteiro = m[1];
    const decimal = m[2] || '';
    const sufixo = m[3] || '';
    if (/^[/:\-]/.test(sufixo.trim())) return;
    const separadorMilhar = inteiro.includes('.') && !decimal;
    const alvo = parseFloat(inteiro.replace(/\./g, '') + (decimal ? '.' + decimal.slice(1) : ''));
    if (!isFinite(alvo) || alvo === 0) return;
    const casas = decimal ? decimal.length - 1 : 0;
    const sepDecimal = decimal ? decimal[0] : ',';
    const formatar = (v) => {
      let s = v.toFixed(casas);
      if (casas) s = s.replace('.', sepDecimal);
      if (separadorMilhar) s = Math.round(v).toLocaleString('pt-BR');
      return s + sufixo;
    };
    const duracao = 900 + Math.min(alvo, 400);
    const inicio = performance.now();
    const passo = (agora) => {
      const t = Math.min(1, (agora - inicio) / duracao);
      el.textContent = formatar(alvo * easeOut(t));
      if (t < 1) requestAnimationFrame(passo);
      else el.textContent = texto;
    };
    requestAnimationFrame(passo);
  };

  if (!reduzMovimento) {
    const numeros = document.querySelectorAll('.kpi-value, .metric-box .num, .stat-card .value');
    if ('IntersectionObserver' in window) {
      const ioNum = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            contar(entry.target);
            ioNum.unobserve(entry.target);
          }
        });
      }, { threshold: 0.4 });
      numeros.forEach((n) => {
        if (n.children.length === 0) ioNum.observe(n);
      });
    }
  }

  // Confete nas cores da marca (canvas-confetti)
  window.celebrar = (intensidade = 1) => {
    if (reduzMovimento || typeof window.confetti !== 'function') return;
    const cores = ['#e11d48', '#fb7185', '#f43f5e', '#fda4af', '#1e293b', '#fbbf24'];
    const base = { spread: 70, ticks: 220, gravity: 0.9, scalar: 1, colors: cores, disableForReducedMotion: true };
    window.confetti({ ...base, particleCount: Math.round(90 * intensidade), origin: { y: 0.55 } });
    setTimeout(() => window.confetti({ ...base, particleCount: Math.round(50 * intensidade), angle: 60, origin: { x: 0, y: 0.65 } }), 180);
    setTimeout(() => window.confetti({ ...base, particleCount: Math.round(50 * intensidade), angle: 120, origin: { x: 1, y: 0.65 } }), 320);
  };

  // Efeito de brilho que segue o mouse nos botões principais
  document.querySelectorAll('.btn-accent, .btn-primary, .nav-cta').forEach((btn) => {
    btn.addEventListener('pointermove', (ev) => {
      const r = btn.getBoundingClientRect();
      btn.style.setProperty('--mx', `${ev.clientX - r.left}px`);
      btn.style.setProperty('--my', `${ev.clientY - r.top}px`);
    });
  });
})();
