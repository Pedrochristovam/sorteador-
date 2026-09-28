<?php

$juridico = new JuridicoService();
$catalogo = $juridico->getCatalogo();

$filters = [
    'busca' => trim((string) ($_GET['busca'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'statusOperacional' => trim((string) ($_GET['statusOperacional'] ?? '')),
    'polo' => trim((string) ($_GET['polo'] ?? '')),
    'atividade' => trim((string) ($_GET['atividade'] ?? '')),
];

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = (int) ($_GET['per'] ?? 10);
if (!in_array($perPage, [5, 10, 20, 50], true)) {
    $perPage = 10;
}

$todas = $juridico->listAll();
$lista = $juridico->search(array_filter($filters));
$totalGeral = count($todas);
$totalFiltrado = count($lista);
$ativas = $juridico->countAtivas();
$aptas = $juridico->countAptas();

$totalPages = max(1, (int) ceil($totalFiltrado / $perPage));
if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}
$offset = ($pageNum - 1) * $perPage;
$pagina = array_slice($lista, $offset, $perPage);
$hasFilters = array_filter($filters) !== [];

$labelOperacional = static function (string $s): string {
    return match ($s) {
        'apto' => 'Apto a sorteio',
        'indisponivel_demanda' => 'Indisponível — Em atendimento',
        'contemplado' => 'Contemplado',
        default => $s,
    };
};
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('juridico-dashboard')) ?>">Jurídico</a>
  <span>/</span>
  <span>Sociedades de Advogados</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Sociedades Credenciadas</h1>
      <span class="count-pill"><?= (int) $totalGeral ?> cadastradas</span>
      <span class="chip-success"><i data-lucide="scale"></i> Edital 01/2026</span>
    </div>
    <p class="subtitle">Cadastro homologado — Ficha de Opção (Anexo X): atividades e polos/comarcas.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('juridico-dashboard')) ?>">Hub Jurídico</a>
    <a class="btn btn-accent" href="<?= e(page_url('juridico-sociedade-form')) ?>">
      <i data-lucide="plus"></i> Nova Sociedade
    </a>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Total</div>
    <div class="kpi-value"><?= (int) $totalGeral ?></div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Ativas</div>
    <div class="kpi-value"><?= (int) $ativas ?></div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Aptas ao sorteio</div>
    <div class="kpi-value"><?= (int) $aptas ?></div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Filtradas</div>
    <div class="kpi-value"><?= (int) $totalFiltrado ?></div>
  </article>
</div>

<section class="panel panel-filters">
  <div class="panel-header">
    <h3>Filtros</h3>
    <?php if ($hasFilters): ?>
      <a class="btn btn-ghost" href="<?= e(page_url('juridico-sociedades')) ?>">Limpar</a>
    <?php endif; ?>
  </div>
  <div class="panel-body">
    <form class="filters filters-empresas" method="get" action="index.php" style="grid-template-columns:1.4fr 0.9fr 1.1fr 1fr 1fr auto;">
      <input type="hidden" name="page" value="juridico-sociedades">
      <input type="hidden" name="per" value="<?= (int) $perPage ?>">
      <div class="form-group">
        <label for="busca">Busca</label>
        <input class="form-control" type="search" id="busca" name="busca" value="<?= e($filters['busca']) ?>"
          placeholder="Razão social, OAB, CNPJ, cidade...">
      </div>
      <div class="form-group">
        <label for="status">Status</label>
        <select class="form-control" id="status" name="status">
          <option value="">Todas</option>
          <option value="ativa" <?= $filters['status'] === 'ativa' ? 'selected' : '' ?>>Ativas</option>
          <option value="inativa" <?= $filters['status'] === 'inativa' ? 'selected' : '' ?>>Desativadas</option>
          <option value="suspensa" <?= $filters['status'] === 'suspensa' ? 'selected' : '' ?>>Suspensas</option>
        </select>
      </div>
      <div class="form-group">
        <label for="statusOperacional">Situação operacional</label>
        <select class="form-control" id="statusOperacional" name="statusOperacional">
          <option value="">Todas</option>
          <option value="apto" <?= $filters['statusOperacional'] === 'apto' ? 'selected' : '' ?>>Apto a sorteio</option>
          <option value="indisponivel_demanda" <?= $filters['statusOperacional'] === 'indisponivel_demanda' ? 'selected' : '' ?>>Indisponível — Em atendimento</option>
          <option value="contemplado" <?= $filters['statusOperacional'] === 'contemplado' ? 'selected' : '' ?>>Contemplado</option>
        </select>
      </div>
      <div class="form-group">
        <label for="polo">Polo / Comarca</label>
        <select class="form-control" id="polo" name="polo">
          <option value="">Todos</option>
          <?php foreach ($catalogo['polos'] as $code => $nome): ?>
            <option value="<?= e($code) ?>" <?= $filters['polo'] === $code ? 'selected' : '' ?>><?= e($nome) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="atividade">Atividade</label>
        <select class="form-control" id="atividade" name="atividade">
          <option value="">Todas</option>
          <?php foreach ($catalogo['atividades'] as $code => $nome): ?>
            <option value="<?= e($code) ?>" <?= $filters['atividade'] === $code ? 'selected' : '' ?>><?= e($nome) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-accent" type="submit"><i data-lucide="search"></i> Filtrar</button>
      </div>
    </form>
  </div>
</section>

<section class="panel">
  <div class="table-wrap">
    <table class="data-table data-table-premium empresas-table">
      <thead>
        <tr>
          <th>Sociedade</th>
          <th>Contrato</th>
          <th>Sede</th>
          <th>Atividades</th>
          <th>Status</th>
          <th>Operacional</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pagina === []): ?>
          <tr><td colspan="7"><div class="empty-state">Nenhuma sociedade encontrada.</div></td></tr>
        <?php else: ?>
          <?php foreach ($pagina as $soc): ?>
            <tr class="<?= $soc->isAtiva() ? '' : 'row-inactive' ?>">
              <td>
                <div class="company-cell">
                  <div class="company-avatar <?= $soc->isAtiva() ? 'tone-' . e(avatar_tone($soc->id)) : 'tone-inactive' ?>">
                    <?= e(iniciais_empresa($soc->razaoSocial)) ?>
                  </div>
                  <div>
                    <strong><?= e($soc->razaoSocial) ?></strong>
                    <?php if (!$soc->isAtiva()): ?>
                      <span class="inactive-flag">Desativada</span>
                    <?php endif; ?>
                    <?php if ($soc->oabSociedade): ?>
                      <span class="muted"><?= e($soc->oabSociedade) ?></span>
                    <?php endif; ?>
                    <?php if ($soc->cnpj): ?>
                      <span class="mono muted"><?= e($soc->cnpj) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="mono">#<?= e($soc->numeroContrato ?: '—') ?></td>
              <td><?= e($soc->cidadeSede) ?>/<?= e($soc->ufSede) ?></td>
              <td>
                <div class="tags-wrap">
                  <?php foreach (array_slice($soc->atividades, 0, 2) as $a): ?>
                    <span class="tag"><?= e($a) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($soc->atividades) > 2): ?>
                    <span class="tag tag-more">+<?= count($soc->atividades) - 2 ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <span class="status-pill <?= $soc->isAtiva() ? 'success' : 'danger' ?>">
                  <span class="dot"></span> <?= $soc->isAtiva() ? 'Ativa' : e(ucfirst($soc->status)) ?>
                </span>
              </td>
              <td>
                <span class="tag <?= $soc->statusOperacional === 'apto' ? '' : 'tag-more' ?>">
                  <?= e($labelOperacional($soc->statusOperacional)) ?>
                </span>
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-outline btn-sm" href="<?= e(page_url('juridico-sociedade-detalhe', ['id' => $soc->id])) ?>" title="Ver">
                    <i data-lucide="eye"></i>
                  </a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(page_url('juridico-sociedade-form', ['id' => $soc->id])) ?>" title="Editar">
                    <i data-lucide="pencil"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span>
      Mostrando <?= $totalFiltrado === 0 ? 0 : $offset + 1 ?>–<?= min($offset + $perPage, $totalFiltrado) ?>
      de <?= (int) $totalFiltrado ?>
    </span>
    <div class="pager">
      <?php $base = array_filter(array_merge($filters, ['per' => $perPage])); ?>
      <?php if ($pageNum > 1): ?>
        <a class="pager-btn" href="<?= e(page_url('juridico-sociedades', $base + ['p' => $pageNum - 1])) ?>">Anterior</a>
      <?php endif; ?>
      <?php for ($i = max(1, $pageNum - 1); $i <= min($totalPages, $pageNum + 2); $i++): ?>
        <a class="pager-btn <?= $i === $pageNum ? 'active' : '' ?>" href="<?= e(page_url('juridico-sociedades', $base + ['p' => $i])) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($pageNum < $totalPages): ?>
        <a class="pager-btn" href="<?= e(page_url('juridico-sociedades', $base + ['p' => $pageNum + 1])) ?>">Próxima</a>
      <?php endif; ?>
    </div>
  </div>
</section>
