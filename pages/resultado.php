<?php

$drawService = new DrawService();
$id = (string) ($_GET['id'] ?? '');
$sorteio = $id !== '' ? $drawService->findById($id) : null;

if (!$sorteio): ?>
  <div class="alert alert-warning">Resultado não encontrado.</div>
  <a class="btn btn-outline" href="<?= e(page_url('historico')) ?>">Voltar</a>
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

$embeddedResultado = $resultado;
$embeddedSorteioId = $sorteio['id'];
$pageScripts = ['assets/js/sorteio.js'];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <a href="<?= e(page_url('historico')) ?>">Histórico</a>
  <span>/</span>
  <span>Resultado</span>
</nav>

<div class="page-header">
  <div>
    <div class="dash-title-row">
      <h1>Resultado</h1>
      <span class="count-pill mono"><?= e($sorteio['id']) ?></span>
    </div>
    <p class="subtitle">Card institucional preparado para publicação ou download.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('historico-detalhe', ['id' => $sorteio['id']])) ?>">
      <i data-lucide="arrow-left"></i> Voltar
    </a>
  </div>
</div>

<?php require __DIR__ . '/partials/resultado-card.php'; ?>
