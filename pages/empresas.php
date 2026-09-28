<?php

$companyService = new CompanyService();

$filters = [
    'busca' => trim((string) ($_GET['busca'] ?? '')),
    'cidade' => trim((string) ($_GET['cidade'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'servico' => trim((string) ($_GET['servico'] ?? '')),
];

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = (int) ($_GET['per'] ?? 10);
if (!in_array($perPage, [5, 10, 20, 50], true)) {
    $perPage = 10;
}

$todas = $companyService->listAll();
$empresas = $companyService->search(array_filter($filters));
$servicos = $companyService->listServicos();
$totalFiltrado = count($empresas);
$totalGeral = count($todas);
$ativas = count(array_filter($todas, static fn($e) => $e->isAtiva()));
$desativadas = $totalGeral - $ativas;
$cidades = $companyService->listCidades();
$totalCidades = count($cidades);
$contratos = array_values(array_filter(array_map(
    static fn($e) => $e->numeroContrato,
    $todas
)));
sort($contratos, SORT_NATURAL);
$contratoMin = $contratos[0] ?? '—';
$contratoMax = $contratos !== [] ? $contratos[count($contratos) - 1] : '—';

$totalPages = max(1, (int) ceil($totalFiltrado / $perPage));
if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}
$offset = ($pageNum - 1) * $perPage;
$pagina = array_slice($empresas, $offset, $perPage);

$integrity = integrity_hash_for(json_encode([
    'total' => $totalGeral,
    'ativas' => $ativas,
    'updated' => date('c'),
], JSON_UNESCAPED_UNICODE));

$hasFilters = array_filter($filters) !== [];
$excluida = trim((string) ($_GET['excluida'] ?? ''));
$erro = trim((string) ($_GET['erro'] ?? ''));
$lote = (string) ($_GET['lote'] ?? '');
$loteQtd = (int) ($_GET['qtd'] ?? 0);
$loteSel = (int) ($_GET['sel'] ?? 0);
$loteVerbos = ['desativar' => 'desativada(s)', 'reativar' => 'reativada(s)', 'excluir' => 'excluída(s)'];
?>

<?php if (isset($loteVerbos[$lote])): ?>
  <div class="alert alert-success">
    <strong><?= $loteQtd ?></strong> empresa(s) <?= $loteVerbos[$lote] ?> em lote.
    <?php if ($loteSel > $loteQtd): ?>
      <?= $loteSel - $loteQtd ?> já estava(m) nessa situação e não foi(ram) alterada(s).
    <?php endif; ?>
    <?php if ($lote === 'desativar'): ?>
      Elas serão eliminadas automaticamente nos próximos sorteios.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($excluida !== ''): ?>
  <div class="alert alert-success">Empresa <strong><?= e($excluida) ?></strong> excluída do credenciamento.</div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
  <div class="alert alert-warning"><?= e($erro) ?></div>
<?php endif; ?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Governança &amp; Credenciamento</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Empresas Credenciadas</h1>
      <span class="count-pill"><?= (int) $totalGeral ?> cadastradas</span>
      <span class="chip-success"><i data-lucide="refresh-cw"></i> Base Sincronizada</span>
    </div>
    <p class="subtitle">Cadastro homologado de empresas habilitadas para participação em sorteios públicos.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="api/exportar-empresas.php">
      <i data-lucide="download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= e(page_url('empresa-form')) ?>" title="Cadastro manual">
      <i data-lucide="file-up"></i> Importar Planilha
    </a>
    <a class="btn btn-accent" href="<?= e(page_url('empresa-form')) ?>">
      <i data-lucide="plus"></i> Nova Empresa
    </a>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Total Habilitadas</div>
    <div class="kpi-value"><?= (int) $totalGeral ?></div>
    <div class="kpi-trend up"><?= (int) $ativas ?> ativas no credenciamento</div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Cidades Atendidas</div>
    <div class="kpi-value"><?= (int) $totalCidades ?></div>
    <div class="kpi-hint"><?= e(implode(', ', array_slice($cidades, 0, 3))) ?><?= $totalCidades > 3 ? '…' : '' ?></div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Contratos Ativos</div>
    <div class="kpi-value"><?= (int) $ativas ?></div>
    <div class="kpi-hint">#<?= e((string) $contratoMin) ?> a #<?= e((string) $contratoMax) ?></div>
  </article>
  <a class="kpi-card kpi-last kpi-card-link<?= $filters['status'] === 'inativa' ? ' is-active' : '' ?>" href="<?= e(page_url('empresas', ['status' => 'inativa'])) ?>">
    <div class="kpi-label">Desativadas</div>
    <div class="kpi-value"><?= (int) $desativadas ?></div>
    <div class="kpi-last-footer">
      <span><i data-lucide="filter"></i> Filtrar desativadas</span>
    </div>
  </a>
</div>

<section class="panel panel-filters">
  <div class="panel-header">
    <h3>Filtros de Pesquisa &amp; Segmentação</h3>
    <?php if ($hasFilters): ?>
      <a class="btn btn-ghost" href="<?= e(page_url('empresas')) ?>">Limpar filtros</a>
    <?php endif; ?>
  </div>
  <div class="panel-body">
    <form class="filters filters-empresas" method="get" action="index.php">
      <input type="hidden" name="page" value="empresas">
      <input type="hidden" name="per" value="<?= (int) $perPage ?>">
      <div class="form-group">
        <label for="busca">Busca Geral</label>
        <input class="form-control" type="search" id="busca" name="busca"
          value="<?= e($filters['busca']) ?>" placeholder="Nome, contrato, e-mail, cidade...">
      </div>
      <div class="form-group">
        <label for="cidade">Cidade / Polo</label>
        <input class="form-control" type="text" id="cidade" name="cidade"
          placeholder="Digite a cidade" value="<?= e($filters['cidade']) ?>">
      </div>
      <div class="form-group">
        <label for="status">Status</label>
        <select class="form-control" id="status" name="status">
          <option value="">Todas</option>
          <option value="ativa" <?= $filters['status'] === 'ativa' ? 'selected' : '' ?>>Ativas</option>
          <option value="inativa" <?= $filters['status'] === 'inativa' ? 'selected' : '' ?>>Desativadas</option>
        </select>
      </div>
      <div class="form-group">
        <label for="servico">Atividade</label>
        <select class="form-control" id="servico" name="servico">
          <option value="">Todas</option>
          <?php foreach ($servicos as $servico): ?>
            <option value="<?= e($servico) ?>" <?= $filters['servico'] === $servico ? 'selected' : '' ?>><?= e($servico) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <div class="filter-buttons">
          <button class="btn btn-accent" type="submit"><i data-lucide="search"></i> Filtrar</button>
          <button class="btn btn-outline" type="button" id="btnSelectAllFiltered" <?= $totalFiltrado === 0 ? 'disabled' : '' ?>
            title="Seleciona todas as empresas do resultado, em todas as páginas">
            <i data-lucide="check-square"></i> Selecionar todas (<?= (int) $totalFiltrado ?>)
          </button>
        </div>
      </div>
    </form>

    <?php if ($hasFilters): ?>
      <div class="filter-tags">
        <?php foreach ($filters as $key => $val): ?>
          <?php if ($val === '') continue; ?>
          <span class="filter-tag">
            <?= e(ucfirst($key)) ?>: <?= e($val) ?>
            <?php
              $clear = $filters;
              $clear[$key] = '';
              $clear = array_filter($clear);
            ?>
            <a href="<?= e(page_url('empresas', $clear)) ?>" aria-label="Remover filtro">×</a>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<form id="bulkForm" method="post" action="api/acoes-empresas.php">
  <input type="hidden" name="acao" id="bulkAcao" value="">
  <input type="hidden" name="todas_filtradas" id="bulkTodas" value="0">
  <input type="hidden" name="per" value="<?= (int) $perPage ?>">
  <?php foreach ($filters as $k => $v): ?>
    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
  <?php endforeach; ?>
</form>

<div class="bulk-bar" id="bulkBar" hidden
  data-total-filtrado="<?= (int) $totalFiltrado ?>" data-total-pagina="<?= count($pagina) ?>">
  <div class="bulk-bar-info">
    <i data-lucide="check-square"></i>
    <span id="bulkCount">0 selecionadas</span>
    <?php if ($totalFiltrado > count($pagina)): ?>
      <button type="button" class="bulk-link" id="bulkSelectAllLink">
        Selecionar todas as <?= (int) $totalFiltrado ?> empresas do filtro
      </button>
    <?php endif; ?>
    <button type="button" class="bulk-link" id="bulkClear">Limpar seleção</button>
  </div>
  <div class="bulk-bar-actions">
    <button type="button" class="btn btn-outline btn-sm" data-bulk-action="reativar">
      <i data-lucide="check-circle"></i> Reativar
    </button>
    <button type="button" class="btn btn-danger-soft btn-sm" data-bulk-action="desativar">
      <i data-lucide="ban"></i> Desativar
    </button>
    <button type="button" class="btn btn-danger btn-sm" data-bulk-action="excluir">
      <i data-lucide="trash-2"></i> Excluir
    </button>
  </div>
</div>

<section class="panel">
  <div class="table-wrap">
    <table class="data-table data-table-premium empresas-table">
      <thead>
        <tr>
          <th class="col-select">
            <input type="checkbox" id="selectAllPage" title="Selecionar todas desta página" <?= $pagina === [] ? 'disabled' : '' ?>>
          </th>
          <th>Empresa &amp; Contato</th>
          <th>Contrato</th>
          <th>Cidade</th>
          <th>Endereço</th>
          <th>Atividades Habilitadas</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pagina === []): ?>
          <tr><td colspan="8"><div class="empty-state">Nenhuma empresa encontrada com os filtros informados.</div></td></tr>
        <?php else: ?>
          <?php foreach ($pagina as $empresa): ?>
            <tr class="<?= $empresa->isAtiva() ? '' : 'row-inactive' ?>">
              <td class="col-select">
                <input type="checkbox" class="row-select" name="ids[]" value="<?= e($empresa->id) ?>" form="bulkForm"
                  data-nome="<?= e($empresa->nome) ?>" aria-label="Selecionar <?= e($empresa->nome) ?>">
              </td>
              <td>
                <div class="company-cell">
                  <div class="company-avatar <?= $empresa->isAtiva() ? 'tone-' . e(avatar_tone($empresa->id)) : 'tone-inactive' ?>">
                    <?= e(iniciais_empresa($empresa->nome)) ?>
                  </div>
                  <div>
                    <strong><?= e($empresa->nome) ?></strong>
                    <?php if (!$empresa->isAtiva()): ?>
                      <span class="inactive-flag">Desativada</span>
                    <?php endif; ?>
                    <?php if ($empresa->email): ?>
                      <span class="contact-line"><i data-lucide="mail"></i> <?= e($empresa->email) ?></span>
                    <?php endif; ?>
                    <?php if ($empresa->cnpj): ?>
                      <span class="mono muted"><?= e($empresa->cnpj) ?></span>
                    <?php elseif ($empresa->telefone): ?>
                      <span class="muted"><?= e($empresa->telefone) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="mono">#<?= e($empresa->numeroContrato ?: '—') ?></td>
              <td>
                <div class="local-cell">
                  <i data-lucide="map-pin"></i>
                  <?= e($empresa->cidade) ?>/<?= e($empresa->uf) ?>
                </div>
              </td>
              <td>
                <div class="address-cell">
                  <span><?= e($empresa->endereco) ?></span>
                  <?php if ($empresa->bairro): ?><span class="muted"><?= e($empresa->bairro) ?></span><?php endif; ?>
                  <?php if ($empresa->cep): ?><span class="muted">CEP <?= e($empresa->cep) ?></span><?php endif; ?>
                </div>
              </td>
              <td>
                <div class="tags-wrap">
                  <?php foreach (array_slice($empresa->servicos, 0, 3) as $s): ?>
                    <span class="tag"><?= e($s) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($empresa->servicos) > 3): ?>
                    <span class="tag tag-more">+<?= count($empresa->servicos) - 3 ?></span>
                  <?php endif; ?>
                  <?php if (!$empresa->atendeAcima100km): ?>
                    <span class="tag tag-km-limite" title="Atende somente até 100 km ida e volta">≤ 100 km</span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <span class="status-pill <?= $empresa->isAtiva() ? 'success' : 'danger' ?>">
                  <span class="dot"></span> <?= $empresa->isAtiva() ? 'Ativa' : 'Desativada' ?>
                </span>
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-outline btn-sm" href="<?= e(page_url('empresa-detalhe', ['id' => $empresa->id])) ?>" title="Ver">
                    <i data-lucide="eye"></i>
                  </a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(page_url('empresa-form', ['id' => $empresa->id])) ?>" title="Editar / Desativar">
                    <i data-lucide="pencil"></i>
                  </a>
                  <form method="post" action="api/excluir-empresa.php" class="inline-form"
                    data-confirm="Excluir definitivamente &quot;<?= e($empresa->nome) ?>&quot;? Esta ação não pode ser desfeita. O histórico de sorteios já realizados é preservado.">
                    <input type="hidden" name="id" value="<?= e($empresa->id) ?>">
                    <button type="submit" class="btn btn-danger-soft btn-sm" title="Excluir empresa">
                      <i data-lucide="trash-2"></i>
                    </button>
                  </form>
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
      de <?= (int) $totalFiltrado ?> empresas
    </span>
    <div class="pager-wrap">
      <form method="get" class="per-page-form">
        <input type="hidden" name="page" value="empresas">
        <?php foreach ($filters as $k => $v): ?>
          <?php if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
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
        <?php
          $baseParams = array_filter(array_merge($filters, ['per' => $perPage]));
        ?>
        <?php if ($pageNum > 1): ?>
          <a class="pager-btn" href="<?= e(page_url('empresas', $baseParams + ['p' => $pageNum - 1])) ?>">Anterior</a>
        <?php else: ?>
          <button type="button" class="pager-btn" disabled>Anterior</button>
        <?php endif; ?>

        <?php for ($i = max(1, $pageNum - 1); $i <= min($totalPages, $pageNum + 2); $i++): ?>
          <a class="pager-btn <?= $i === $pageNum ? 'active' : '' ?>"
             href="<?= e(page_url('empresas', $baseParams + ['p' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pageNum < $totalPages): ?>
          <a class="pager-btn" href="<?= e(page_url('empresas', $baseParams + ['p' => $pageNum + 1])) ?>">Próxima</a>
        <?php else: ?>
          <button type="button" class="pager-btn" disabled>Próxima</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="integrity-bar">
  <div>
    <strong>Protocolo de Integridade Registrado</strong>
    <div class="mono integrity-hash"><?= e($integrity) ?></div>
  </div>
  <a href="<?= e(page_url('auditoria')) ?>">Validar Assinatura Digital ICP-Brasil →</a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const bar = document.getElementById('bulkBar');
  const form = document.getElementById('bulkForm');
  if (!bar || !form) return;

  const rows = Array.from(document.querySelectorAll('.row-select'));
  const selectAllPage = document.getElementById('selectAllPage');
  const countEl = document.getElementById('bulkCount');
  const allLink = document.getElementById('bulkSelectAllLink');
  const totalFiltrado = Number(bar.dataset.totalFiltrado || 0);
  let todasFiltradas = false;

  const marcadas = () => rows.filter((cb) => cb.checked);
  const quantidade = () => (todasFiltradas ? totalFiltrado : marcadas().length);

  function atualizar() {
    const n = marcadas().length;
    const qtd = quantidade();
    bar.hidden = qtd === 0;
    countEl.textContent = todasFiltradas
      ? `Todas as ${totalFiltrado} empresas do filtro selecionadas`
      : `${n} selecionada${n === 1 ? '' : 's'} nesta página`;
    if (allLink) allLink.hidden = todasFiltradas;
    if (selectAllPage) {
      selectAllPage.checked = rows.length > 0 && n === rows.length;
      selectAllPage.indeterminate = n > 0 && n < rows.length;
    }
    rows.forEach((cb) => cb.closest('tr')?.classList.toggle('row-selected', cb.checked));
  }

  function marcarPagina(valor) {
    rows.forEach((cb) => { cb.checked = valor; });
  }

  rows.forEach((cb) => cb.addEventListener('change', () => {
    if (!cb.checked) todasFiltradas = false;
    atualizar();
  }));

  selectAllPage?.addEventListener('change', () => {
    todasFiltradas = false;
    marcarPagina(selectAllPage.checked);
    atualizar();
  });

  document.getElementById('btnSelectAllFiltered')?.addEventListener('click', () => {
    marcarPagina(true);
    todasFiltradas = true;
    atualizar();
    bar.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  allLink?.addEventListener('click', () => {
    marcarPagina(true);
    todasFiltradas = true;
    atualizar();
  });

  document.getElementById('bulkClear')?.addEventListener('click', () => {
    todasFiltradas = false;
    marcarPagina(false);
    atualizar();
  });

  const mensagens = {
    excluir: (q) => `Excluir definitivamente ${q} empresa(s)? Esta ação não pode ser desfeita. O histórico de sorteios já realizados é preservado.`,
    desativar: (q) => `Desativar ${q} empresa(s)? Elas não participarão de novos sorteios.`,
    reativar: (q) => `Reativar ${q} empresa(s)? Elas voltarão a ficar elegíveis nos sorteios.`,
  };

  bar.querySelectorAll('[data-bulk-action]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const acao = btn.dataset.bulkAction;
      const qtd = quantidade();
      if (qtd === 0) return;

      let msg = mensagens[acao](qtd);
      if (!todasFiltradas) {
        const nomes = marcadas().map((cb) => '• ' + cb.dataset.nome);
        msg += '\n\n' + nomes.slice(0, 8).join('\n') + (nomes.length > 8 ? `\n… e mais ${nomes.length - 8}` : '');
      }
      if (!confirm(msg)) return;

      document.getElementById('bulkAcao').value = acao;
      document.getElementById('bulkTodas').value = todasFiltradas ? '1' : '0';
      form.submit();
    });
  });

  atualizar();
});
</script>
