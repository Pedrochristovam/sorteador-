<?php

declare(strict_types=1);

$drawService = new DrawService();
$pendentes = $drawService->listStandby();
usort($pendentes, static function (array $a, array $b): int {
    $ha = standby_horas_pendente($a['dataHora'] ?? null) ?? 0;
    $hb = standby_horas_pendente($b['dataHora'] ?? null) ?? 0;
    return $hb <=> $ha; // mais antigos primeiro
});

$nCritico = 0;
$nAtencao = 0;
foreach ($pendentes as $s) {
    $u = standby_urgencia($s['dataHora'] ?? null);
    if ($u === 'critico') {
        $nCritico++;
    } elseif ($u === 'atencao') {
        $nAtencao++;
    }
}
?>

<section class="page-hero reveal">
  <div>
    <p class="eyebrow">Controle de aceite</p>
    <h1>Standby</h1>
    <p class="lede">
      Empresas sorteadas aguardando confirmação. Só entram no rodízio (ficam de fora dos próximos sorteios)
      as que <strong>aceitarem</strong> a demanda. Se recusarem, continuam aptas normalmente.
    </p>
  </div>
  <div class="page-hero-meta">
    <div class="kpi-mini">
      <span>Pendentes</span>
      <strong class="count-up" data-count="<?= count($pendentes) ?>"><?= count($pendentes) ?></strong>
    </div>
    <?php if ($nAtencao > 0): ?>
      <div class="kpi-mini tone-warn">
        <span>≥ 24 h</span>
        <strong><?= $nAtencao ?></strong>
      </div>
    <?php endif; ?>
    <?php if ($nCritico > 0): ?>
      <div class="kpi-mini tone-danger">
        <span>≥ 48 h</span>
        <strong><?= $nCritico ?></strong>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($nCritico > 0 || $nAtencao > 0): ?>
  <div class="standby-alert <?= $nCritico > 0 ? 'is-critico' : 'is-atencao' ?> reveal" role="status">
    <i data-lucide="<?= $nCritico > 0 ? 'alert-triangle' : 'clock' ?>"></i>
    <div>
      <strong>
        <?php if ($nCritico > 0): ?>
          <?= $nCritico ?> aceite(s) com mais de 48 horas sem resposta
        <?php else: ?>
          <?= $nAtencao ?> aceite(s) com mais de 24 horas pendente(s)
        <?php endif; ?>
      </strong>
      <p>Priorize a confirmação — enquanto estiver em standby, a demanda não fecha e o rodízio não atualiza.</p>
    </div>
  </div>
<?php endif; ?>

<?php if ($pendentes === []): ?>
  <section class="empty-state reveal">
    <i data-lucide="inbox"></i>
    <h2>Nenhuma empresa em standby</h2>
    <p>Quando um sorteio escolher uma credenciada, ela aparece aqui para você marcar se aceitou ou recusou o serviço.</p>
    <a class="btn btn-primary" href="<?= e(page_url('novo-sorteio')) ?>">Novo sorteio</a>
  </section>
<?php else: ?>
  <section class="standby-list reveal">
    <?php foreach ($pendentes as $s): ?>
      <?php
        $empresa = $s['empresaSelecionada'] ?? [];
        $demanda = $s['demanda'] ?? [];
        $sid = (string) ($s['id'] ?? '');
        $modalidade = (string) ($s['modalidade'] ?? 'sorteio');
        $urgencia = standby_urgencia($s['dataHora'] ?? null);
        $tempoLabel = standby_tempo_label($s['dataHora'] ?? null);
      ?>
      <article class="standby-card urgency-<?= e($urgencia) ?>" data-sorteio-id="<?= e($sid) ?>">
        <header class="standby-card-head">
          <div>
            <span class="modalidade-pill modalidade-<?= e($modalidade) ?>"><?= e(modalidade_label($modalidade)) ?></span>
            <h2><?= e($empresa['nome'] ?? '—') ?></h2>
            <p class="muted">
              Sorteio <?= e($sid) ?>
              · <?= e(format_datetime($s['dataHora'] ?? null)) ?>
              <?php if (!empty($empresa['cidade'])): ?> · <?= e($empresa['cidade']) ?><?php endif; ?>
            </p>
          </div>
          <div class="standby-badges">
            <span class="standby-age age-<?= e($urgencia) ?>" title="Tempo desde o sorteio">
              <i data-lucide="timer"></i> <?= e($tempoLabel) ?>
            </span>
            <span class="badge badge-warning">Aguardando aceite</span>
          </div>
        </header>

        <dl class="standby-meta">
          <div>
            <dt>Demanda</dt>
            <dd><?= e(($demanda['cidade'] ?? '—') . ' / ' . ($demanda['servicoNecessario'] ?? ($demanda['endereco'] ?? '—'))) ?></dd>
          </div>
          <div>
            <dt>Endereço</dt>
            <dd><?= e($demanda['endereco'] ?? '—') ?></dd>
          </div>
          <?php if (isset($empresa['distanciaKm'])): ?>
            <div>
              <dt>Rota</dt>
              <dd><?= e(format_km((float) $empresa['distanciaKm'])) ?></dd>
            </div>
          <?php endif; ?>
          <?php if (isset($empresa['valor'])): ?>
            <div>
              <dt>Orçamento</dt>
              <dd><?= e(format_moeda((float) $empresa['valor'])) ?></dd>
            </div>
          <?php endif; ?>
        </dl>

        <form class="standby-form" data-aceite-form>
          <fieldset>
            <legend>A empresa aceitou realizar o serviço?</legend>
            <label class="standby-option">
              <input type="radio" name="decisao-<?= e($sid) ?>" value="aceito" required>
              <span>
                <strong>Aceitou</strong>
                <small>Confirma a demanda. A empresa entra no rodízio e não participa do próximo ciclo até todas serem chamadas.</small>
              </span>
            </label>
            <label class="standby-option">
              <input type="radio" name="decisao-<?= e($sid) ?>" value="recusado" required>
              <span>
                <strong>Recusou</strong>
                <small>Libera a empresa. Ela continua apta a ser sorteada normalmente.</small>
              </span>
            </label>
          </fieldset>
          <label class="standby-obs">
            Observação (opcional)
            <textarea name="observacao" rows="2" placeholder="Ex.: recusou por agenda, aceitou verbalmente em …"></textarea>
          </label>
          <div class="standby-actions">
            <a class="btn btn-ghost" href="<?= e(page_url('historico-detalhe', ['id' => $sid])) ?>">Ver detalhe</a>
            <button type="submit" class="btn btn-primary">Confirmar</button>
          </div>
          <p class="standby-msg" hidden></p>
        </form>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<style>
.page-hero-meta { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: stretch; }
.kpi-mini.tone-warn { background: #fffbeb; border: 1px solid #fde68a; }
.kpi-mini.tone-warn strong { color: #b45309; }
.kpi-mini.tone-danger { background: #fff1f2; border: 1px solid #fecdd3; }
.kpi-mini.tone-danger strong { color: #be123c; }
.standby-alert {
  display: flex; gap: 0.85rem; align-items: flex-start;
  margin: 0 0 1.25rem; padding: 1rem 1.15rem;
  border-radius: 16px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e;
}
.standby-alert.is-critico { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }
.standby-alert svg { width: 22px; height: 22px; flex-shrink: 0; margin-top: 0.1rem; }
.standby-alert strong { display: block; margin-bottom: 0.2rem; }
.standby-alert p { margin: 0; font-size: 0.9rem; line-height: 1.45; opacity: 0.92; }
.standby-list { display: grid; gap: 1.5rem; margin-top: 1.5rem; }
.standby-card {
  border-top: 1px solid rgba(15, 23, 42, 0.08);
  padding: 1.5rem 0 2rem;
}
.standby-card.urgency-atencao { border-top-color: #f59e0b; }
.standby-card.urgency-critico {
  border-top-color: #e11d48;
  background: linear-gradient(180deg, rgba(255, 241, 242, 0.55), transparent 48%);
  padding-left: 0.85rem; padding-right: 0.85rem; margin-left: -0.85rem; margin-right: -0.85rem;
  border-radius: 0 0 16px 16px;
}
.standby-card-head {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start;
  margin-bottom: 1rem;
}
.standby-card-head h2 { margin: 0.35rem 0 0.2rem; font-size: 1.35rem; }
.standby-badges { display: flex; flex-wrap: wrap; gap: 0.45rem; justify-content: flex-end; align-items: center; }
.standby-age {
  display: inline-flex; align-items: center; gap: 0.3rem;
  padding: 0.28rem 0.65rem; border-radius: 999px;
  font-size: 0.78rem; font-weight: 700; background: #f1f5f9; color: #475569;
}
.standby-age svg { width: 14px; height: 14px; }
.standby-age.age-atencao { background: #fffbeb; color: #b45309; }
.standby-age.age-critico { background: #fff1f2; color: #be123c; }
.standby-meta {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.85rem 1.25rem;
  margin: 0 0 1.25rem;
}
.standby-meta dt { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
.standby-meta dd { margin: 0.15rem 0 0; }
.standby-form fieldset { border: 0; margin: 0; padding: 0; }
.standby-form legend { font-weight: 600; margin-bottom: 0.75rem; }
.standby-option {
  display: flex; gap: 0.75rem; align-items: flex-start;
  padding: 0.85rem 1rem; margin-bottom: 0.5rem;
  border-radius: 14px; background: rgba(15, 23, 42, 0.03); cursor: pointer;
}
.standby-option:has(input:checked) { background: rgba(225, 29, 72, 0.08); outline: 1px solid rgba(225, 29, 72, 0.25); }
.standby-option strong { display: block; }
.standby-option small { color: #64748b; }
.standby-obs { display: grid; gap: 0.35rem; margin: 1rem 0; font-size: 0.9rem; color: #475569; }
.standby-obs textarea {
  width: 100%; border: 1px solid rgba(15, 23, 42, 0.12); border-radius: 12px;
  padding: 0.75rem 0.9rem; font: inherit; resize: vertical;
}
.standby-actions { display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }
.standby-msg { margin: 0.75rem 0 0; font-size: 0.95rem; }
.standby-msg.is-ok { color: #047857; }
.standby-msg.is-err { color: #be123c; }
.empty-state { text-align: center; padding: 3rem 1rem; }
.empty-state i { width: 40px; height: 40px; margin-bottom: 0.75rem; color: #94a3b8; }
</style>

<script>
document.querySelectorAll('[data-aceite-form]').forEach((form) => {
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const card = form.closest('.standby-card');
    const sorteioId = card?.dataset.sorteioId;
    const escolhida = form.querySelector('input[type="radio"]:checked');
    const msg = form.querySelector('.standby-msg');
    const btn = form.querySelector('button[type="submit"]');
    if (!sorteioId || !escolhida) return;

    btn.disabled = true;
    msg.hidden = true;
    try {
      const res = await fetch('api/registrar-aceite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          sorteioId,
          decisao: escolhida.value,
          observacao: form.querySelector('textarea')?.value || '',
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Falha ao registrar');
      msg.textContent = data.message || 'Registrado.';
      msg.className = 'standby-msg is-ok';
      msg.hidden = false;
      setTimeout(() => { card.style.opacity = '0.45'; location.reload(); }, 900);
    } catch (err) {
      msg.textContent = err.message || 'Erro ao confirmar';
      msg.className = 'standby-msg is-err';
      msg.hidden = false;
      btn.disabled = false;
    }
  });
});
</script>
