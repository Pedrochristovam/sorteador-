<?php

$companyService = new CompanyService();
$drawService = new DrawService();

$totalEmpresas = $companyService->countAll();
$empresasAtivas = $companyService->countAtivas();
$historico = $drawService->listHistorico();
$totalSorteios = count($historico);
$ultimoSorteio = $historico[0] ?? null;
$ultimos = array_slice($historico, 0, 5);
$aptidao = $totalEmpresas > 0 ? round(($empresasAtivas / $totalEmpresas) * 100, 1) : 0;
?>

<div class="dash-hero">
  <div>
    <div class="dash-title-row">
      <h1>Dashboard — Engenharia</h1>
      <span class="chip-success"><i data-lucide="shield-check"></i> Edital 02/2026</span>
    </div>
    <p class="subtitle">
      Visão consolidada dos processos de seleção pública de empresas credenciadas (Engenharia / Arquitetura / Agronomia).
      Para sociedades de advogados, use o módulo Jurídico.
    </p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('juridico-dashboard')) ?>">
      <i data-lucide="scale"></i> Ir para Jurídico
    </a>
    <a class="btn btn-accent btn-lg" href="<?= e(page_url('novo-sorteio')) ?>">
      <i data-lucide="plus"></i>
      Novo Sorteio
    </a>
  </div>
</div>

<div class="kpi-grid">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Empresas Credenciadas</div>
    <div class="kpi-value"><?= (int) $totalEmpresas ?></div>
    <div class="kpi-trend up">+4 este mês</div>
    <div class="kpi-hint">Cadastro total homologado</div>
  </article>

  <article class="kpi-card kpi-green">
    <div class="kpi-label">Empresas Ativas</div>
    <div class="kpi-value"><?= (int) $empresasAtivas ?></div>
    <div class="kpi-trend up"><?= e((string) $aptidao) ?>% aptidão</div>
    <div class="kpi-hint">Elegíveis a participar dos editais</div>
  </article>

  <article class="kpi-card kpi-red">
    <div class="kpi-label">Sorteios Realizados</div>
    <div class="kpi-value"><?= (int) $totalSorteios ?></div>
    <div class="kpi-trend up">100% auditados</div>
    <div class="kpi-hint">Histórico consolidado público</div>
  </article>

  <article class="kpi-card kpi-last">
    <div class="kpi-label">Último Sorteio</div>
    <?php if ($ultimoSorteio): ?>
      <div class="kpi-token mono"><?= e($ultimoSorteio['id']) ?></div>
      <div class="kpi-meta"><?= e(format_datetime($ultimoSorteio['dataHora'])) ?></div>
      <div class="kpi-company"><?= e($ultimoSorteio['empresaSelecionada']['nome'] ?? '—') ?></div>
      <div class="kpi-last-footer">
        <span class="chip-success"><i data-lucide="check"></i> Homologado</span>
        <a href="<?= e(page_url('historico-detalhe', ['id' => $ultimoSorteio['id']])) ?>">Ver ata →</a>
      </div>
    <?php else: ?>
      <div class="kpi-value" style="font-size:1.2rem;">—</div>
      <div class="kpi-hint">Nenhum sorteio registrado</div>
    <?php endif; ?>
  </article>
</div>

<section class="panel panel-dash">
  <div class="panel-header panel-header-dash">
    <div class="panel-title-wrap">
      <h3>Últimos Sorteios</h3>
      <span class="count-pill"><?= (int) $totalSorteios ?> Registros</span>
    </div>
    <div class="panel-tools">
      <a class="btn btn-outline btn-sm" href="<?= e(page_url('historico')) ?>">
        <i data-lucide="filter"></i> Filtrar no histórico
      </a>
      <a class="btn btn-ghost btn-sm" href="<?= e(page_url('historico')) ?>">
        Ver histórico completo <i data-lucide="arrow-right"></i>
      </a>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table data-table-premium">
      <thead>
        <tr>
          <th>ID Token</th>
          <th>Data &amp; Hora</th>
          <th>Localidade</th>
          <th>Empresa selecionada</th>
          <th>Participantes</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($ultimos === []): ?>
          <tr><td colspan="7"><div class="empty-state">Nenhum sorteio registrado.</div></td></tr>
        <?php else: ?>
          <?php foreach ($ultimos as $s): ?>
            <?php
              $statusSel = (string) ($s['status'] ?? 'concluido');
              $nomeSel = $s['empresaSelecionada']['nome'] ?? ($statusSel === 'aguardando_orcamento' ? 'Aguardando orçamentos (3 sorteadas)' : '—');
              if ($statusSel === 'aguardando_aceite' && !empty($s['empresaSelecionada']['nome'])) {
                  $nomeSel = $s['empresaSelecionada']['nome'] . ' (standby)';
              }
              $cnpjSel = $s['empresaSelecionada']['cnpj'] ?? '';
              $dt = $s['dataHora'] ?? '';
              $dataPart = $dt !== '' ? format_date($dt, 'd/m/Y') : '—';
              $horaPart = $dt !== '' ? format_date($dt, 'H:i') : '';
            ?>
            <tr>
              <td>
                <div class="token-cell">
                  <span class="mono"><?= e($s['id']) ?></span>
                  <button type="button" class="icon-btn-xs copy-token" data-copy="<?= e($s['id']) ?>" title="Copiar ID" aria-label="Copiar ID">
                    <i data-lucide="copy"></i>
                  </button>
                </div>
              </td>
              <td>
                <div class="datetime-cell">
                  <strong><?= e($dataPart) ?></strong>
                  <span><?= e($horaPart) ?></span>
                </div>
              </td>
              <td>
                <div class="local-cell">
                  <i data-lucide="map-pin"></i>
                  <?= e($s['demanda']['cidade'] ?? '—') ?>
                </div>
              </td>
              <td>
                <div class="company-cell">
                  <div class="company-avatar"><?= e(iniciais_empresa($nomeSel)) ?></div>
                  <div>
                    <strong><?= e($nomeSel) ?></strong>
                    <?php if ($cnpjSel !== ''): ?>
                      <span class="mono muted"><?= e($cnpjSel) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div class="participants-cell">
                  <i data-lucide="users"></i>
                  <?= count($s['participantes'] ?? []) ?> empresas
                </div>
              </td>
              <td>
                <span class="status-pill <?= in_array($statusSel, ['aguardando_orcamento', 'aguardando_aceite'], true) ? 'warning' : ($statusSel === 'recusado' ? 'danger' : 'success') ?>">
                  <span class="dot"></span> <?= e(status_sorteio_label($statusSel)) ?>
                </span>
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-outline btn-sm" href="<?= e(page_url('historico-detalhe', ['id' => $s['id']])) ?>" title="Detalhes">
                    <i data-lucide="eye"></i>
                  </a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(page_url('auditoria', ['sorteio' => $s['id']])) ?>" title="Auditoria">
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

  <?php if ($totalSorteios > 0): ?>
    <div class="table-footer">
      <span>Mostrando 1–<?= min(5, $totalSorteios) ?> de <?= (int) $totalSorteios ?> sorteios homologados</span>
      <div class="pager">
        <button type="button" class="pager-btn" disabled>Anterior</button>
        <button type="button" class="pager-btn active">1</button>
        <?php if ($totalSorteios > 5): ?>
          <button type="button" class="pager-btn" disabled>2</button>
        <?php endif; ?>
        <a class="pager-btn" href="<?= e(page_url('historico')) ?>">Próxima</a>
      </div>
    </div>
  <?php endif; ?>
</section>
