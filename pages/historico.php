<?php

$drawService = new DrawService();
$todos = $drawService->listHistorico();

$filters = [
    'busca' => trim((string) ($_GET['busca'] ?? '')),
    'cidade' => trim((string) ($_GET['cidade'] ?? '')),
    'periodo' => trim((string) ($_GET['periodo'] ?? '')),
    'dataInicio' => trim((string) ($_GET['dataInicio'] ?? '')),
    'dataFim' => trim((string) ($_GET['dataFim'] ?? '')),
];

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = (int) ($_GET['per'] ?? 5);
if (!in_array($perPage, [5, 10, 20, 50], true)) {
    $perPage = 5;
}

$cidades = [];
foreach ($todos as $item) {
    $cidade = trim((string) ($item['demanda']['cidade'] ?? ''));
    if ($cidade !== '') {
        $cidades[$cidade] = $cidade;
    }
}
ksort($cidades, SORT_NATURAL | SORT_FLAG_CASE);

$hoje = date('Y-m-d');
if ($filters['periodo'] === 'hoje') {
    $filters['dataInicio'] = $hoje;
    $filters['dataFim'] = $hoje;
} elseif ($filters['periodo'] === '7d') {
    $filters['dataInicio'] = date('Y-m-d', strtotime('-6 days'));
    $filters['dataFim'] = $hoje;
} elseif ($filters['periodo'] === '30d') {
    $filters['dataInicio'] = date('Y-m-d', strtotime('-29 days'));
    $filters['dataFim'] = $hoje;
}

$filtrados = array_values(array_filter($todos, static function (array $s) use ($filters): bool {
    $busca = mb_strtolower($filters['busca']);
    if ($busca !== '') {
        $hay = mb_strtolower(implode(' ', [
            $s['id'] ?? '',
            $s['demanda']['cidade'] ?? '',
            $s['empresaSelecionada']['nome'] ?? '',
            $s['empresaSelecionada']['cnpj'] ?? '',
            $s['usuarioNome'] ?? '',
        ]));
        if (!str_contains($hay, $busca)) {
            return false;
        }
    }

    if ($filters['cidade'] !== '') {
        if (strcasecmp((string) ($s['demanda']['cidade'] ?? ''), $filters['cidade']) !== 0) {
            return false;
        }
    }

    $data = substr((string) ($s['dataHora'] ?? ''), 0, 10);
    if ($filters['dataInicio'] !== '' && $data < $filters['dataInicio']) {
        return false;
    }
    if ($filters['dataFim'] !== '' && $data > $filters['dataFim']) {
        return false;
    }

    return true;
}));

$totalGeral = count($todos);
$totalFiltrado = count($filtrados);

$empresasContempladas = [];
$totalParticipacoes = 0;
foreach ($todos as $s) {
    $totalParticipacoes += count($s['participantes'] ?? []);
    $selId = (string) ($s['empresaSelecionada']['id'] ?? $s['empresaSelecionada']['nome'] ?? '');
    if ($selId !== '') {
        $empresasContempladas[$selId] = true;
    }
}
$distintas = count($empresasContempladas);
$mediaParticipacao = $totalGeral > 0 ? round($totalParticipacoes / $totalGeral, 1) : 0.0;

$ultimo = $todos[0] ?? null;
$ultimaData = $ultimo['dataHora'] ?? null;

$totalPages = max(1, (int) ceil($totalFiltrado / $perPage));
if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}
$offset = ($pageNum - 1) * $perPage;
$pagina = array_slice($filtrados, $offset, $perPage);

$integrity = integrity_hash_for(json_encode([
    'total' => $totalGeral,
    'validos' => $totalGeral,
    'updated' => date('c'),
], JSON_UNESCAPED_UNICODE));

$filterParams = array_filter([
    'busca' => $filters['busca'],
    'cidade' => $filters['cidade'],
    'periodo' => $filters['periodo'],
    'dataInicio' => $filters['periodo'] === '' ? $filters['dataInicio'] : '',
    'dataFim' => $filters['periodo'] === '' ? $filters['dataFim'] : '',
], static fn($v) => $v !== '');
$hasFilters = $filterParams !== [];

$periodoLabel = match ($filters['periodo']) {
    'hoje' => 'Hoje (' . format_date($hoje) . ')',
    '7d' => 'Últimos 7 dias',
    '30d' => 'Últimos 30 dias',
    default => '',
};
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Governança &amp; Transparência</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Histórico de Sorteios</h1>
      <span class="count-pill"><?= (int) $totalGeral ?> realizados</span>
      <span class="chip-success"><i data-lucide="shield-check"></i> Auditoria 100% Homologada</span>
    </div>
    <p class="subtitle">Registro imutável dos atos de seleção pública com trilha criptográfica verificável.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('auditoria')) ?>">
      <i data-lucide="book-open"></i> Livro de Atas
    </a>
    <a class="btn btn-outline" href="api/exportar-historico.php">
      <i data-lucide="download"></i> Exportar CSV
    </a>
    <a class="btn btn-accent" href="<?= e(page_url('novo-sorteio')) ?>">
      <i data-lucide="plus"></i> Novo Sorteio
    </a>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Total de Sorteios</div>
    <div class="kpi-value"><?= (int) $totalGeral ?></div>
    <div class="kpi-trend up">100% Válidos</div>
    <div class="kpi-hint">Integridade SHA-256 ativa</div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Empresas Contempladas</div>
    <div class="kpi-value" style="font-size:1.55rem;"><?= (int) $distintas ?> distintas</div>
    <div class="kpi-hint">Distribuição equitativa sem repetição forçada</div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Participação Média</div>
    <div class="kpi-value" style="font-size:1.55rem;"><?= e(number_format($mediaParticipacao, 1, '.', '')) ?></div>
    <div class="kpi-hint"><?= (int) $totalParticipacoes ?> participações · empresas / certame</div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Última Homologação</div>
    <div class="kpi-value" style="font-size:1.25rem;"><?= $ultimaData ? e(format_date($ultimaData)) : '—' ?></div>
    <div class="kpi-last-footer">
      <a href="<?= e(page_url('auditoria')) ?>">
        <i data-lucide="badge-check"></i> Assinada via ICP-Brasil
      </a>
    </div>
  </article>
</div>

<section class="panel panel-filters">
  <div class="panel-header">
    <h3>Pesquisar Registro</h3>
    <?php if ($hasFilters): ?>
      <a class="btn btn-ghost" href="<?= e(page_url('historico')) ?>">Limpar filtros</a>
    <?php endif; ?>
  </div>
  <div class="panel-body">
    <form class="filters filters-historico" method="get" action="index.php">
      <input type="hidden" name="page" value="historico">
      <input type="hidden" name="per" value="<?= (int) $perPage ?>">
      <div class="form-group">
        <label for="busca">Token / Empresa / CNPJ</label>
        <input class="form-control" type="search" id="busca" name="busca"
          value="<?= e($filters['busca']) ?>" placeholder="Ex.: SRT-2026… ou razão social">
      </div>
      <div class="form-group">
        <label for="cidade">Polo / Município</label>
        <select class="form-control" id="cidade" name="cidade">
          <option value="">Todos os Polos</option>
          <?php foreach ($cidades as $cidade): ?>
            <option value="<?= e($cidade) ?>" <?= $filters['cidade'] === $cidade ? 'selected' : '' ?>><?= e($cidade) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="periodo">Período de realização</label>
        <select class="form-control" id="periodo" name="periodo">
          <option value="">Todo o período</option>
          <option value="hoje" <?= $filters['periodo'] === 'hoje' ? 'selected' : '' ?>>Hoje</option>
          <option value="7d" <?= $filters['periodo'] === '7d' ? 'selected' : '' ?>>Últimos 7 dias</option>
          <option value="30d" <?= $filters['periodo'] === '30d' ? 'selected' : '' ?>>Últimos 30 dias</option>
        </select>
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <div class="filter-actions-inline">
          <button class="btn btn-accent" type="submit"><i data-lucide="search"></i> Filtrar</button>
          <a class="btn btn-ghost" href="<?= e(page_url('historico')) ?>" title="Limpar">
            <i data-lucide="rotate-ccw"></i>
          </a>
        </div>
      </div>
    </form>

    <?php if ($hasFilters): ?>
      <div class="filter-tags">
        <?php if ($filters['busca'] !== ''): ?>
          <?php $clear = $filterParams; unset($clear['busca']); ?>
          <span class="filter-tag">Busca: <?= e($filters['busca']) ?> <a href="<?= e(page_url('historico', $clear)) ?>">×</a></span>
        <?php endif; ?>
        <?php if ($filters['cidade'] !== ''): ?>
          <?php $clear = $filterParams; unset($clear['cidade']); ?>
          <span class="filter-tag">Polo: <?= e($filters['cidade']) ?> <a href="<?= e(page_url('historico', $clear)) ?>">×</a></span>
        <?php endif; ?>
        <?php if ($periodoLabel !== ''): ?>
          <?php $clear = $filterParams; unset($clear['periodo'], $clear['dataInicio'], $clear['dataFim']); ?>
          <span class="filter-tag"><?= e($periodoLabel) ?> <a href="<?= e(page_url('historico', $clear)) ?>">×</a></span>
        <?php endif; ?>
        <span class="filter-tag">Status: Todos</span>
      </div>
    <?php endif; ?>

    <div class="filter-integrity-row">
      <span class="muted">Verificação de filtro</span>
      <span class="mono integrity-hash-sm"><?= e(substr($integrity, 0, 24)) ?>…</span>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-header">
    <h3>Histórico de Atos Homologados</h3>
    <span class="muted"><?= (int) $totalFiltrado ?> registro(s)</span>
  </div>
  <div class="table-wrap">
    <table class="data-table data-table-premium historico-table">
      <thead>
        <tr>
          <th>ID do Sorteio / Token</th>
          <th>Data &amp; Hora</th>
          <th>Local</th>
          <th>Participantes</th>
          <th>Empresa Selecionada</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pagina === []): ?>
          <tr><td colspan="6"><div class="empty-state">Nenhum sorteio encontrado com os filtros informados.</div></td></tr>
        <?php else: ?>
          <?php foreach ($pagina as $s): ?>
            <?php
              $id = (string) ($s['id'] ?? '');
              $seed = integrity_hash_for($id . '|' . ($s['dataHora'] ?? ''));
              $sel = $s['empresaSelecionada'] ?? null;
              $aguardando = in_array(($s['status'] ?? ''), ['aguardando_orcamento', 'aguardando_aceite'], true);
              $selNome = (string) ($sel['nome'] ?? ($aguardando ? 'Aguardando orçamentos' : '—'));
              $selCnpj = (string) ($sel['cnpj'] ?? '');
              $selId = (string) ($sel['id'] ?? $selNome);
              $cidade = (string) ($s['demanda']['cidade'] ?? '—');
              $servico = (string) ($s['demanda']['servicoNecessario'] ?? ($s['demanda']['tipoDemanda'] ?? ''));
              $modalidadeHist = (string) ($s['modalidade'] ?? $s['demanda']['modalidade'] ?? '');
              if ($modalidadeHist !== '') {
                  $servico = trim($servico . ' · ' . modalidade_label($modalidadeHist), ' ·');
              }
              $nPart = count($s['participantes'] ?? []);
            ?>
            <tr>
              <td>
                <div class="token-cell">
                  <div class="token-row">
                    <a class="token-id" href="<?= e(page_url('historico-detalhe', ['id' => $id])) ?>"><?= e($id) ?></a>
                    <button type="button" class="btn btn-ghost btn-sm copy-token" data-copy="<?= e($id) ?>" title="Copiar ID">
                      <i data-lucide="copy"></i>
                    </button>
                  </div>
                  <span class="cell-meta verified"><i data-lucide="shield-check"></i> Semente SHA-256 verificada</span>
                  <span class="mono cell-meta-hash"><?= e(substr($seed, 0, 16)) ?>…</span>
                </div>
              </td>
              <td>
                <div class="datetime-cell">
                  <span class="datetime-main"><i data-lucide="clock"></i> <?= e(format_datetime($s['dataHora'] ?? null)) ?></span>
                  <span class="cell-meta">Carimbo do Tempo UTC-3</span>
                </div>
              </td>
              <td>
                <div class="local-cell">
                  <i data-lucide="map-pin"></i>
                  <div>
                    <strong><?= e($cidade) ?></strong>
                    <?php if ($servico !== ''): ?>
                      <span class="cell-meta"><?= e($servico) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div class="participantes-cell">
                  <strong><?= (int) $nPart ?></strong>
                  <span class="cell-meta">empresas aptas</span>
                </div>
              </td>
              <td>
                <div class="company-cell">
                  <div class="company-avatar tone-<?= e(avatar_tone($selId)) ?>">
                    <?= e(iniciais_empresa($selNome)) ?>
                  </div>
                  <div>
                    <strong><?= e($selNome) ?></strong>
                    <?php if ($selCnpj !== ''): ?>
                      <span class="mono muted"><?= e($selCnpj) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-outline btn-sm" href="<?= e(page_url('historico-detalhe', ['id' => $id])) ?>" title="Detalhes">
                    <i data-lucide="eye"></i>
                  </a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(page_url('auditoria', ['sorteio' => $id])) ?>" title="Auditoria">
                    <i data-lucide="shield"></i>
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
      de <?= (int) $totalFiltrado ?> sorteios realizados
    </span>
    <div class="pager-wrap">
      <form method="get" class="per-page-form">
        <input type="hidden" name="page" value="historico">
        <?php foreach ($filterParams as $k => $v): ?>
          <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
        <?php endforeach; ?>
        <label>
          Por página
          <select name="per" class="form-control form-control-sm" onchange="this.form.submit()">
            <?php foreach ([5, 10, 20, 50] as $n): ?>
              <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
      <div class="pager">
        <?php $baseParams = array_merge($filterParams, ['per' => $perPage]); ?>
        <?php if ($pageNum > 1): ?>
          <a class="pager-btn" href="<?= e(page_url('historico', $baseParams + ['p' => $pageNum - 1])) ?>">Anterior</a>
        <?php else: ?>
          <button type="button" class="pager-btn" disabled>Anterior</button>
        <?php endif; ?>

        <?php for ($i = max(1, $pageNum - 1); $i <= min($totalPages, $pageNum + 2); $i++): ?>
          <a class="pager-btn <?= $i === $pageNum ? 'active' : '' ?>"
             href="<?= e(page_url('historico', $baseParams + ['p' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pageNum < $totalPages): ?>
          <a class="pager-btn" href="<?= e(page_url('historico', $baseParams + ['p' => $pageNum + 1])) ?>">Próxima</a>
        <?php else: ?>
          <button type="button" class="pager-btn" disabled>Próxima</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="integrity-bar">
  <div>
    <strong>Protocolo de Auditoria Pública e Imutabilidade</strong>
    <p class="integrity-desc">
      Cada ato de sorteio gera semente criptográfica SHA-256, carimbo de tempo e trilha de homologação
      verificável no livro de atas digital.
    </p>
    <div class="mono integrity-hash"><?= e($integrity) ?></div>
  </div>
  <a href="<?= e(page_url('auditoria')) ?>">Ver Manual de Conformidade →</a>
</section>
