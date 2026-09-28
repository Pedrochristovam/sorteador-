<?php

$juridico = new JuridicoService();
$id = (string) ($_GET['id'] ?? '');
$sociedade = $id !== '' ? $juridico->findById($id) : null;

if (!$sociedade): ?>
  <div class="alert alert-warning">Sociedade não encontrada.</div>
  <a class="btn btn-outline" href="<?= e(page_url('juridico-sociedades')) ?>">Voltar</a>
<?php return; endif;

$hash = integrity_hash_for(json_encode($sociedade->toArray(), JSON_UNESCAPED_UNICODE));
$labelOp = match ($sociedade->statusOperacional) {
    'apto' => 'Apto a sorteio',
    'indisponivel_demanda' => 'Indisponível — Em atendimento',
    'contemplado' => 'Contemplado',
    default => $sociedade->statusOperacional,
};
?>

<?php if (!empty($_GET['saved'])): ?>
  <div class="alert alert-success">
    Sociedade salva com sucesso.
    <?php if (!$sociedade->isAtiva()): ?>
      Ela está <strong>fora</strong> dos sorteios jurídicos enquanto permanecer desativada/suspensa.
    <?php elseif ($sociedade->statusOperacional === 'indisponivel_demanda'): ?>
      Situação operacional: <strong>com demanda em andamento</strong> — fora da amostra até conclusão.
    <?php endif; ?>
  </div>
<?php endif; ?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('juridico-dashboard')) ?>">Jurídico</a>
  <span>/</span>
  <a href="<?= e(page_url('juridico-sociedades')) ?>">Sociedades</a>
  <span>/</span>
  <span>Ficha</span>
</nav>

<div class="empresas-hero<?= $sociedade->isAtiva() ? '' : ' empresas-hero-inactive' ?>">
  <div class="company-hero-identity">
    <div class="company-avatar xl <?= $sociedade->isAtiva() ? 'tone-' . e(avatar_tone($sociedade->id)) : 'tone-inactive' ?>">
      <?= e(iniciais_empresa($sociedade->razaoSocial)) ?>
    </div>
    <div>
      <div class="dash-title-row">
        <h1><?= e($sociedade->razaoSocial) ?></h1>
        <span class="status-pill <?= $sociedade->isAtiva() ? 'success' : 'danger' ?>">
          <span class="dot"></span> <?= $sociedade->isAtiva() ? 'Ativa' : e(ucfirst($sociedade->status)) ?>
        </span>
        <span class="tag"><?= e($labelOp) ?></span>
      </div>
      <p class="subtitle">
        Contrato #<?= e($sociedade->numeroContrato ?: '—') ?>
        · <?= e($sociedade->cidadeSede) ?>/<?= e($sociedade->ufSede) ?>
        <?php if ($sociedade->oabSociedade): ?> · <?= e($sociedade->oabSociedade) ?><?php endif; ?>
        · Credenciada em <?= e(format_date($sociedade->dataCredenciamento)) ?>
      </p>
    </div>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('juridico-sociedades')) ?>">
      <i data-lucide="arrow-left"></i> Voltar
    </a>
    <a class="btn btn-accent" href="<?= e(page_url('juridico-sociedade-form', ['id' => $sociedade->id])) ?>">
      <i data-lucide="pencil"></i> Editar
    </a>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Contrato</div>
    <div class="kpi-value" style="font-size:1.35rem;">#<?= e($sociedade->numeroContrato ?: '—') ?></div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Atividades</div>
    <div class="kpi-value"><?= count($sociedade->atividades) ?></div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Polos / Comarcas</div>
    <div class="kpi-value"><?= count($sociedade->polos) ?></div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Elegível agora?</div>
    <div class="kpi-value" style="font-size:1.35rem;"><?= $sociedade->isAptaSorteio() ? 'Sim' : 'Não' ?></div>
  </article>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
  <section class="panel">
    <div class="panel-header"><h3>Identificação</h3></div>
    <div class="panel-body detail-grid">
      <div class="detail-item"><div class="lbl">CNPJ</div><div class="val mono"><?= e($sociedade->cnpj ?: '—') ?></div></div>
      <div class="detail-item"><div class="lbl">OAB sociedade</div><div class="val"><?= e($sociedade->oabSociedade ?: '—') ?></div></div>
      <div class="detail-item"><div class="lbl">Responsável</div><div class="val"><?= e($sociedade->responsavel ?: '—') ?></div></div>
      <div class="detail-item"><div class="lbl">OAB responsável</div><div class="val"><?= e($sociedade->oabResponsavel ?: '—') ?></div></div>
      <div class="detail-item"><div class="lbl">E-mail</div><div class="val"><?= e($sociedade->email ?: '—') ?></div></div>
      <div class="detail-item"><div class="lbl">Telefone</div><div class="val"><?= e($sociedade->telefone ?: '—') ?></div></div>
      <div class="detail-item" style="grid-column:1/-1;"><div class="lbl">Endereço</div>
        <div class="val"><?= e($sociedade->endereco) ?><?= $sociedade->bairro ? ' — ' . e($sociedade->bairro) : '' ?></div>
      </div>
      <div class="detail-item"><div class="lbl">Processo SEI!</div><div class="val mono"><?= e($sociedade->processoSei ?: '—') ?></div></div>
    </div>
  </section>
  <section class="panel">
    <div class="panel-header"><h3>Anexo X — Opções</h3></div>
    <div class="panel-body">
      <div class="lbl" style="margin-bottom:0.4rem;">Atividades</div>
      <div class="tags-wrap" style="margin-bottom:1rem;">
        <?php foreach ($sociedade->atividades as $a): ?><span class="tag"><?= e($a) ?></span><?php endforeach; ?>
        <?php if ($sociedade->atividades === []): ?><span class="muted">Nenhuma</span><?php endif; ?>
      </div>
      <div class="lbl" style="margin-bottom:0.4rem;">Polos / Comarcas</div>
      <div class="tags-wrap">
        <?php foreach ($sociedade->polos as $p): ?><span class="tag"><?= e($p) ?></span><?php endforeach; ?>
        <?php if ($sociedade->polos === []): ?><span class="muted">Nenhum</span><?php endif; ?>
      </div>
      <?php if ($sociedade->observacoes): ?>
        <div class="lbl" style="margin-top:1rem;">Observações</div>
        <p style="margin:0.35rem 0 0;font-size:0.88rem;"><?= e($sociedade->observacoes) ?></p>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="integrity-bar" style="margin-top:1.25rem;">
  <div>
    <strong>Protocolo de Integridade — Módulo Jurídico</strong>
    <div class="mono integrity-hash"><?= e($hash) ?></div>
  </div>
  <a href="<?= e(page_url('juridico-sorteio')) ?>">Ir para sorteio jurídico →</a>
</section>

<style>
@media (max-width: 900px) {
  div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>
