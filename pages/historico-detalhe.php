<?php

$drawService = new DrawService();
$id = (string) ($_GET['id'] ?? '');
$sorteio = $id !== '' ? $drawService->findById($id) : null;

if (!$sorteio): ?>
  <div class="alert alert-warning">Sorteio não encontrado.</div>
  <a class="btn btn-outline" href="<?= e(page_url('historico')) ?>">Voltar ao histórico</a>
<?php return; endif;

$resultado = [
    'sorteioId' => $sorteio['id'],
    'empresaSelecionada' => $sorteio['empresaSelecionada'] ?? [],
    'demanda' => $sorteio['demanda'] ?? [],
    'dataHora' => $sorteio['dataHora'] ?? '',
    'participantes' => $sorteio['participantes'] ?? [],
    'criteriosUtilizados' => $sorteio['criteriosUtilizados'] ?? [],
    'hashIntegridade' => $sorteio['hashIntegridade'] ?? '',
];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <a href="<?= e(page_url('historico')) ?>">Histórico</a>
  <span>/</span>
  <span><?= e($sorteio['id']) ?></span>
</nav>

<div class="page-header">
  <div>
    <div class="dash-title-row">
      <h1>Detalhe do sorteio</h1>
      <span class="count-pill mono"><?= e($sorteio['id']) ?></span>
      <?php if (!empty($sorteio['modalidade'])): ?>
        <span class="modalidade-pill modalidade-<?= e((string) $sorteio['modalidade']) ?>"><?= e(modalidade_label((string) $sorteio['modalidade'])) ?></span>
      <?php endif; ?>
    </div>
    <p class="subtitle">Resumo do ato, participantes e resultado registrado com hash de integridade.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('historico')) ?>">
      <i data-lucide="arrow-left"></i> Voltar
    </a>
    <a class="btn btn-accent" href="<?= e(page_url('auditoria', ['sorteio' => $sorteio['id']])) ?>">
      <i data-lucide="shield-check"></i> Abrir auditoria deste ato
    </a>
    <a class="btn btn-outline" href="<?= e(page_url('resultado', ['id' => $sorteio['id']])) ?>">
      Ver resultado institucional
    </a>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h3>Resumo</h3></div>
  <div class="panel-body">
    <div class="detail-grid">
      <div class="detail-item">
        <div class="lbl">Data e horário</div>
        <div class="val"><?= e(format_datetime($sorteio['dataHora'] ?? null)) ?></div>
      </div>
      <div class="detail-item">
        <div class="lbl">Status</div>
        <div class="val"><span class="badge <?= e(status_badge_class($sorteio['status'] ?? '')) ?>"><?= e(status_sorteio_label((string) ($sorteio['status'] ?? ''))) ?></span></div>
      </div>
      <div class="detail-item">
        <div class="lbl">Operador</div>
        <div class="val"><?= e($sorteio['usuarioNome'] ?? '—') ?> <span class="mono">(<?= e($sorteio['usuarioId'] ?? '') ?>)</span></div>
      </div>
      <div class="detail-item">
        <div class="lbl">Participantes</div>
        <div class="val"><?= count($sorteio['participantes'] ?? []) ?></div>
      </div>
      <div class="detail-item">
        <div class="lbl">Analisadas / Eliminadas / Elegíveis</div>
        <div class="val">
          <?= (int) ($sorteio['totalAnalisadas'] ?? 0) ?> /
          <?= (int) ($sorteio['totalEliminadas'] ?? 0) ?> /
          <?= (int) ($sorteio['totalElegiveis'] ?? 0) ?>
        </div>
      </div>
      <div class="detail-item">
        <div class="lbl">Serviço solicitado</div>
        <div class="val"><?= e($sorteio['demanda']['servicoNecessario'] ?? '—') ?></div>
      </div>
    </div>
  </div>
</div>

<?php
$embeddedResultado = $resultado;
$embeddedSorteioId = $sorteio['id'];
require __DIR__ . '/partials/resultado-card.php';

$pageScripts = ['assets/js/sorteio.js'];
?>
