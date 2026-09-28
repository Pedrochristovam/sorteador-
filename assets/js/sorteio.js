/**
 * Fluxo de novo sorteio (Engenharia) — demanda, prévia de impacto, execução por modalidade e orçamentos.
 */
(function () {
  const MODALIDADES = {
    sorteio: { titulo: 'Sorteio com rodízio', icone: 'shuffle' },
    proxima: { titulo: 'Mais próxima (preço de edital)', icone: 'locate-fixed' },
    orcamento: { titulo: 'Orçamento entre 3 sorteadas', icone: 'receipt' },
  };
  const ATIVIDADES_ORCAMENTO = ['C', 'D', 'E'];

  initDemandMapPreview();
  initDemandForm();
  initExecucao();
  initOrcamentos();
  initCompartilhar();

  const btnReiniciar = document.getElementById('btnReiniciarFluxo');
  btnReiniciar?.addEventListener('click', async () => {
    if (!confirm('Reiniciar o fluxo de sorteio?')) return;
    await postJson('api/fluxo.php', { reset: true });
    window.location.href = 'index.php?page=novo-sorteio&etapa=demanda';
  });

  // ------------------------------------------------------------------ demanda

  function buildDemandaPayload(form) {
    const atividades = [...form.querySelectorAll('input[name="atividadesCodigos[]"]:checked')].map((el) => el.value);
    return {
      cidade: form.cidade.value.trim(),
      uf: form.uf.value,
      endereco: form.endereco.value.trim(),
      regiaoCodigo: form.regiaoCodigo.value,
      atividadesCodigos: [...new Set(atividades)],
      areaConstruidaM2: form.areaConstruidaM2.value,
      areaTerrenoM2: form.areaTerrenoM2.value,
      precoEdital: form.precoEdital.checked,
      modalidadeSolicitada: form.modalidadeSolicitada.value,
      somenteCidade: form.somenteCidade.checked,
    };
  }

  function faltando(p) {
    const f = [];
    if (!p.cidade) f.push('cidade');
    if (!p.endereco) f.push('endereço');
    if (!p.regiaoCodigo) f.push('região da demanda');
    if (!p.atividadesCodigos.length) f.push('ao menos uma atividade');
    return f;
  }

  /** Espelha Demanda::modalidadeSugerida() no PHP. */
  function modalidadeSugerida(p) {
    const orc = p.atividadesCodigos.filter((c) => ATIVIDADES_ORCAMENTO.includes(c));
    if (orc.length) return { codigo: 'orcamento', motivo: `Atividade ${orc.join(', ')} exige orçamento entre 3 credenciadas.` };
    if (Number(p.areaConstruidaM2 || 0) > 1000) return { codigo: 'orcamento', motivo: 'Área construída acima de 1.000 m² exige orçamento entre 3 credenciadas.' };
    if (Number(p.areaTerrenoM2 || 0) > 10000) return { codigo: 'orcamento', motivo: 'Terreno acima de 10.000 m² exige orçamento entre 3 credenciadas.' };
    if (p.precoEdital) return { codigo: 'proxima', motivo: 'Laudo com preço definido em edital: escolhe a credenciada mais próxima.' };
    return { codigo: 'sorteio', motivo: 'Sem preço de edital nem exigência de orçamento: sorteio com rodízio.' };
  }

  function initDemandForm() {
    const form = document.getElementById('formDemanda');
    if (!form) return;

    const sugestoes = JSON.parse(document.getElementById('regioesSugestao')?.textContent || '{}');
    const regiaoHint = document.getElementById('regiaoSugestao');
    let regiaoManual = Boolean(form.regiaoCodigo.value);

    const normalizar = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    function sugerirRegiao() {
      const uf = form.uf.value;
      const cidade = normalizar(form.cidade.value);
      let codigo = null;
      if (uf !== 'MG') {
        codigo = sugestoes.uf?.[uf] || null;
      } else if (cidade) {
        for (const [cod, lista] of Object.entries(sugestoes.mg || {})) {
          if (lista.some((c) => normalizar(c) === cidade)) { codigo = cod; break; }
        }
      }
      if (!regiaoHint) return;
      if (!codigo) {
        regiaoHint.hidden = !(uf === 'MG' && cidade);
        regiaoHint.textContent = 'Cidade sem sugestão — selecione a região';
        return;
      }
      regiaoHint.hidden = false;
      regiaoHint.textContent = `Sugerida pela cidade: ${codigo} — confira`;
      if (!regiaoManual || !form.regiaoCodigo.value) {
        form.regiaoCodigo.value = codigo;
      }
    }

    form.regiaoCodigo.addEventListener('change', () => { regiaoManual = true; agendarPrevia(); });
    ['input', 'change'].forEach((ev) => {
      form.cidade.addEventListener(ev, () => { sugerirRegiao(); agendarPrevia(); });
      form.endereco.addEventListener(ev, agendarPrevia);
    });
    form.uf.addEventListener('change', () => { regiaoManual = false; sugerirRegiao(); agendarPrevia(); });

    document.querySelectorAll('.city-chip').forEach((chip) => {
      chip.addEventListener('click', () => {
        form.cidade.value = chip.getAttribute('data-city') || '';
        form.cidade.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });

    form.querySelectorAll('input[name="atividadesCodigos[]"]').forEach((el) => {
      el.addEventListener('change', () => { syncAtividades(); atualizarModalidade(); agendarPrevia(); });
    });
    [form.areaConstruidaM2, form.areaTerrenoM2].forEach((el) => el.addEventListener('input', () => { atualizarModalidade(); agendarPrevia(); }));
    form.precoEdital.addEventListener('change', () => { atualizarModalidade(); agendarPrevia(); });
    form.modalidadeSolicitada.addEventListener('change', () => { atualizarModalidade(); agendarPrevia(); });
    form.somenteCidade.addEventListener('change', agendarPrevia);

    function syncAtividades() {
      const n = form.querySelectorAll('input[name="atividadesCodigos[]"]:checked').length;
      const hint = document.getElementById('servicosCountHint');
      if (hint) hint.textContent = n === 0 ? 'Nenhuma atividade selecionada' : (n === 1 ? '1 atividade selecionada' : `${n} atividades selecionadas`);
    }

    function atualizarModalidade() {
      const p = buildDemandaPayload(form);
      const sug = modalidadeSugerida(p);
      const manual = p.modalidadeSolicitada !== 'auto' && p.modalidadeSolicitada !== sug.codigo;
      const codigo = manual ? p.modalidadeSolicitada : sug.codigo;
      const card = document.getElementById('modalidadeCard');
      if (!card) return;
      card.dataset.modalidade = codigo;
      document.getElementById('modalidadeTitulo').textContent = MODALIDADES[codigo].titulo + (manual ? ' (manual)' : '');
      document.getElementById('modalidadeMotivo').textContent = manual
        ? `Definida manualmente. Pela demanda seria: ${MODALIDADES[sug.codigo].titulo}.`
        : sug.motivo;
      const icon = card.querySelector('.modalidade-card-icon');
      if (icon) {
        icon.innerHTML = `<i data-lucide="${MODALIDADES[codigo].icone}"></i>`;
        if (window.lucide) lucide.createIcons();
      }
    }

    // Rascunho
    const btnDraft = document.getElementById('btnSalvarRascunho');
    btnDraft?.addEventListener('click', () => {
      try {
        localStorage.setItem('sorteador_rascunho_demanda_v2', JSON.stringify(buildDemandaPayload(form)));
        btnDraft.innerHTML = '<i data-lucide="check"></i> Rascunho salvo';
        if (window.lucide) lucide.createIcons();
        setTimeout(() => {
          btnDraft.innerHTML = '<i data-lucide="save"></i> Salvar rascunho';
          if (window.lucide) lucide.createIcons();
        }, 1600);
      } catch (_) {
        alert('Não foi possível salvar o rascunho neste navegador.');
      }
    });
    try {
      const raw = localStorage.getItem('sorteador_rascunho_demanda_v2');
      if (raw && !form.cidade.value) {
        const d = JSON.parse(raw);
        form.cidade.value = d.cidade || '';
        form.uf.value = d.uf || 'MG';
        form.endereco.value = d.endereco || '';
        if (d.regiaoCodigo) { form.regiaoCodigo.value = d.regiaoCodigo; regiaoManual = true; }
        form.areaConstruidaM2.value = d.areaConstruidaM2 || '';
        form.areaTerrenoM2.value = d.areaTerrenoM2 || '';
        form.precoEdital.checked = Boolean(d.precoEdital);
        form.modalidadeSolicitada.value = d.modalidadeSolicitada || 'auto';
        form.somenteCidade.checked = Boolean(d.somenteCidade);
        form.querySelectorAll('input[name="atividadesCodigos[]"]').forEach((el) => {
          el.checked = (d.atividadesCodigos || []).includes(el.value);
        });
        form.endereco.dispatchEvent(new Event('change', { bubbles: true }));
      }
    } catch (_) {}

    // Prévia de impacto
    let timer = null;
    let seq = 0;
    function agendarPrevia() {
      clearTimeout(timer);
      timer = setTimeout(rodarPrevia, 900);
    }

    async function rodarPrevia() {
      const p = buildDemandaPayload(form);
      const status = document.getElementById('impactStatus');
      if (faltando(p).length || p.endereco.length < 5) {
        if (status) status.textContent = 'Aguardando parâmetros';
        return;
      }
      const atual = ++seq;
      if (status) status.textContent = 'Calculando rotas…';
      try {
        const data = await postJson('api/calcular-elegiveis.php', { ...p, preview: true });
        if (atual !== seq) return;
        const imp = data.impacto || {};
        setText('impTotal', imp.total);
        setText('impOff', imp.desativadas);
        setText('impAtividade', imp.semAtividade);
        setText('impRegiao', imp.foraRegiao);
        setText('impLimite', imp.limite100);
        setText('impOk', imp.elegiveis);
        setText('impRodizio', imp.emRodizio);
        setText('impNaCidade', imp.aptasNaCidade);
        setText('impFonte', imp.fonteDistancia);
        setText('impProxima', imp.maisProxima ? `${imp.maisProxima.nome} (${formatKm(imp.maisProxima.km)})` : '—');
        const cidadeRow = document.getElementById('impCidadeRow');
        if (cidadeRow) cidadeRow.hidden = !imp.restricaoCidade;
        setText('impCidade', imp.foraCidade);
        atualizarRestricaoCidade(imp.aptasNaCidade || 0);
        if (status) status.textContent = 'Atualizado';
      } catch (err) {
        if (atual === seq && status) status.textContent = 'Prévia indisponível';
      }
    }

    function atualizarRestricaoCidade(n) {
      const hint = document.getElementById('somenteCidadeHint');
      const row = document.getElementById('somenteCidadeRow');
      const cidade = form.cidade.value.trim() || 'cidade da demanda';
      const disponivel = n >= 2;
      form.somenteCidade.disabled = !disponivel;
      if (!disponivel) form.somenteCidade.checked = false;
      row?.classList.toggle('is-disabled', !disponivel);
      if (hint) {
        hint.textContent = disponivel
          ? `${n} credenciadas aptas têm endereço em ${cidade}. Marque para sortear só entre elas.`
          : `Só ${n} apta(s) com endereço em ${cidade} — é preciso 2 ou mais para restringir.`;
      }
    }

    function setText(id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = value == null || value === '' ? '—' : String(value);
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const erro = document.getElementById('demandaErro');
      const btn = document.getElementById('btnCalcular');
      erro.style.display = 'none';

      const p = buildDemandaPayload(form);
      const f = faltando(p);
      if (f.length) {
        erro.textContent = `Preencha: ${f.join(', ')}.`;
        erro.style.display = 'block';
        return;
      }

      btn.disabled = true;
      const original = btn.innerHTML;
      btn.innerHTML = '<span class="map-spinner" style="width:16px;height:16px;border-width:2px;"></span> Calculando rotas de carro…';
      try {
        await postJson('api/calcular-elegiveis.php', p);
        window.location.href = 'index.php?page=novo-sorteio&etapa=elegiveis';
      } catch (err) {
        erro.textContent = err.message || 'Erro ao calcular as credenciadas aptas.';
        erro.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = original;
        if (window.lucide) lucide.createIcons();
      }
    });

    sugerirRegiao();
    syncAtividades();
    atualizarModalidade();
    if (!faltando(buildDemandaPayload(form)).length) agendarPrevia();
  }

  function initDemandMapPreview() {
    const endereco = document.getElementById('endereco');
    const cidade = document.getElementById('cidade');
    const uf = document.getElementById('uf');
    const frame = document.getElementById('mapFrame');
    const placeholder = document.getElementById('mapPlaceholder');
    const loading = document.getElementById('mapLoading');
    const status = document.getElementById('mapStatus');
    const addressLabel = document.getElementById('mapAddressLabel');
    const wrap = document.getElementById('addressFieldWrap');
    const geoBadge = document.getElementById('geoBadge');
    if (!endereco || !frame) return;

    let timer = null;
    let lastQuery = '';

    const update = () => {
      const street = (endereco.value || '').trim();
      const city = (cidade?.value || '').trim();
      const query = [street, city && uf ? `${city} - ${uf.value}` : city, city ? 'Brasil' : ''].filter(Boolean).join(', ');

      wrap?.classList.toggle('has-value', street.length >= 3);
      if (geoBadge) geoBadge.hidden = street.length < 5;

      if (street.length < 3 && !city) {
        placeholder?.classList.remove('hidden');
        if (status) { status.textContent = 'Aguardando'; status.classList.remove('live'); }
        if (addressLabel) addressLabel.textContent = 'Nenhum endereço informado';
        frame.src = 'about:blank';
        lastQuery = '';
        return;
      }
      if (query === lastQuery) return;
      lastQuery = query;

      loading?.classList.add('visible');
      if (status) { status.textContent = 'Atualizando'; status.classList.add('live'); }
      if (addressLabel) addressLabel.textContent = query;

      const zoom = street.length >= 5 ? 16 : 12;
      frame.onload = () => {
        placeholder?.classList.add('hidden');
        loading?.classList.remove('visible');
        if (status) status.textContent = 'Ao vivo';
      };
      setTimeout(() => {
        placeholder?.classList.add('hidden');
        loading?.classList.remove('visible');
        if (status && status.textContent === 'Atualizando') status.textContent = 'Ao vivo';
      }, 1200);
      frame.src = `https://maps.google.com/maps?q=${encodeURIComponent(query)}&z=${zoom}&hl=pt-BR&output=embed`;
    };

    const schedule = () => { clearTimeout(timer); timer = setTimeout(update, 450); };
    [endereco, cidade].forEach((el) => {
      el?.addEventListener('input', schedule);
      el?.addEventListener('change', schedule);
    });
    uf?.addEventListener('change', schedule);
    if ((endereco.value || '').trim().length >= 3 || (cidade?.value || '').trim()) update();
  }

  // ----------------------------------------------------------------- execução

  function initExecucao() {
    const btn = document.getElementById('btnExecutarSorteio');
    if (!btn) return;
    btn.addEventListener('click', () => executar(btn));
  }

  async function executar(btn) {
    const modalidade = btn.dataset.modalidade || 'sorteio';
    const erro = document.getElementById('execErro');
    const statusEl = document.getElementById('drawStatus');
    const chips = [...document.querySelectorAll('.draw-chip')];
    const winnerBox = document.getElementById('drawWinner');

    erro.style.display = 'none';
    btn.disabled = true;

    try {
      if (modalidade === 'proxima') {
        statusEl.textContent = 'Registrando a credenciada mais próxima…';
        await wait(500);
      } else if (chips.length > 1) {
        statusEl.textContent = 'Percorrendo participantes…';
        await animarPassagem(chips, statusEl);
      } else {
        statusEl.textContent = 'Registrando seleção…';
        await wait(800);
      }

      const data = await postJson('api/realizar-sorteio.php', { confirmado: true });
      const sorteio = data.data.sorteio;
      const idsSorteadas = (sorteio.sorteadas || []).map((s) => s.id);

      if (modalidade !== 'proxima' && chips.length > 1) {
        const alvo = Math.max(0, chips.findIndex((c) => c.dataset.id === idsSorteadas[idsSorteadas.length - 1]));
        await animarAte(chips, alvo, statusEl);
      }
      chips.forEach((c) => c.classList.toggle('highlight', idsSorteadas.includes(c.dataset.id)));

      winnerBox.style.display = 'block';
      if (modalidade === 'orcamento') {
        statusEl.textContent = 'Credenciadas sorteadas para orçamento';
        winnerBox.innerHTML = `
          <h3>Peça o orçamento a:</h3>
          <ol class="draw-winner-list">${sorteio.sorteadas.map((s) => `<li>${escapeHtml(s.nome)} <small>${escapeHtml(s.cidade || '')}</small></li>`).join('')}</ol>
          <div class="meta">ID: <span class="mono">${escapeHtml(sorteio.id)}</span></div>`;
      } else {
        const sel = sorteio.empresaSelecionada;
        statusEl.textContent = modalidade === 'proxima' ? 'Credenciada mais próxima' : 'Credenciada sorteada';
        winnerBox.innerHTML = `
          <h3>${escapeHtml(sel.nome)}</h3>
          <div class="meta">
            ${escapeHtml(sel.cidade || '')}${sel.distanciaKm != null ? ` · ${formatKm(sel.distanciaKm)} de carro` : ''}<br>
            <span class="mono">${escapeHtml(sorteio.dataHora)}</span> · ID <span class="mono">${escapeHtml(sorteio.id)}</span>
          </div>`;
      }

      winnerBox.classList.add('is-revealed');
      if (typeof window.celebrar === 'function') window.celebrar(modalidade === 'orcamento' ? 0.7 : 1);

      btn.style.display = 'none';
      setTimeout(() => { window.location.href = 'index.php?page=novo-sorteio&etapa=resultado'; }, 3200);
    } catch (err) {
      chips.forEach((c) => c.classList.remove('highlight'));
      statusEl.textContent = 'Falha na execução';
      erro.textContent = err.message || 'Erro ao realizar o sorteio.';
      erro.style.display = 'block';
      btn.disabled = false;
    }
  }

  async function animarPassagem(chips, statusEl) {
    const total = chips.length;
    const passos = total * 2 + Math.ceil(total / 2);
    for (let step = 0; step < passos; step += 1) {
      const idx = step % total;
      chips.forEach((c) => c.classList.remove('highlight'));
      chips[idx].classList.add('highlight');
      chips[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
      statusEl.textContent = step < total ? `Passando pelos participantes (${step + 1}/${total})…` : 'Sorteando…';
      await wait(90 + (step / passos) * 110);
    }
  }

  async function animarAte(chips, alvo, statusEl) {
    const total = chips.length;
    statusEl.textContent = 'Finalizando…';
    const passos = total + alvo + 1;
    for (let step = 0; step < passos; step += 1) {
      const idx = step % total;
      chips.forEach((c) => c.classList.remove('highlight'));
      chips[idx].classList.add('highlight');
      const restante = passos - step;
      await wait(restante <= 6 ? 180 + (6 - restante) * 70 : 130);
    }
  }

  // --------------------------------------------------------------- orçamentos

  function initOrcamentos() {
    const form = document.getElementById('formOrcamentos');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const erro = document.getElementById('orcamentoErro');
      const btn = form.querySelector('button[type="submit"]');
      erro.style.display = 'none';

      const valores = {};
      form.querySelectorAll('input[data-empresa-id]').forEach((el) => { valores[el.dataset.empresaId] = el.value.trim(); });
      if (!Object.values(valores).some((v) => v !== '')) {
        erro.textContent = 'Informe o valor de ao menos um orçamento.';
        erro.style.display = 'block';
        return;
      }
      if (!confirm('Registrar os orçamentos? A credenciada com o menor valor leva o serviço e isso fica na auditoria.')) return;

      btn.disabled = true;
      try {
        await postJson('api/registrar-orcamentos.php', {
          sorteioId: form.dataset.sorteioId,
          valores,
          observacao: document.getElementById('orcamentoObs')?.value || '',
        });
        window.location.reload();
      } catch (err) {
        erro.textContent = err.message || 'Falha ao registrar os orçamentos.';
        erro.style.display = 'block';
        btn.disabled = false;
      }
    });
  }

  function initCompartilhar() {
    const btn = document.getElementById('btnCompartilhar');
    btn?.addEventListener('click', async () => {
      const title = btn.dataset.shareTitle || 'Resultado do Sorteio';
      const text = btn.dataset.shareText || '';
      const url = window.location.href;
      try {
        if (navigator.share) {
          await navigator.share({ title, text, url });
        } else {
          await navigator.clipboard.writeText(`${title}\n${text}\n${url}`);
          alert('Link do resultado copiado para a área de transferência.');
        }
      } catch (_) {}
    });
  }

  // -------------------------------------------------------------------- util

  function formatKm(km) {
    return km == null ? '—' : `${Number(km).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} km`;
  }

  function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
})();
