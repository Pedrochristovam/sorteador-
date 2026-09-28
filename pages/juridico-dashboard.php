<?php

$juridico = new JuridicoService();
$todas = $juridico->listAll();
$total = count($todas);
$ativas = $juridico->countAtivas();
$aptas = $juridico->countAptas();
$indisponiveis = count(array_filter($todas, static fn($s) => $s->statusOperacional === 'indisponivel_demanda'));
$desativadas = $total - $ativas;
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Credenciamento Jurídico · Edital 01/2026</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Módulo Jurídico</h1>
      <span class="count-pill">Edital 01/2026</span>
      <span class="chip-success"><i data-lucide="scale"></i> Sociedades de Advogados</span>
    </div>
    <p class="subtitle">
      Sorteio eletrônico impessoal conforme Seção VII — cruzamento atividade × polo/comarca (Anexo X),
      com exclusão de credenciados com demanda em andamento.
    </p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('juridico-sociedades')) ?>">
      <i data-lucide="building-2"></i> Sociedades
    </a>
    <a class="btn btn-accent" href="<?= e(page_url('juridico-sorteio')) ?>">
      <i data-lucide="shuffle"></i> Novo Sorteio Jurídico
    </a>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Sociedades credenciadas</div>
    <div class="kpi-value"><?= (int) $total ?></div>
    <div class="kpi-hint"><?= (int) $ativas ?> ativas no credenciamento</div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Aptas ao sorteio</div>
    <div class="kpi-value"><?= (int) $aptas ?></div>
    <div class="kpi-hint">Ativas e sem demanda aberta</div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Indisponíveis</div>
    <div class="kpi-value"><?= (int) $indisponiveis ?></div>
    <div class="kpi-hint">Com demanda / OS em andamento</div>
  </article>
  <a class="kpi-card kpi-last kpi-card-link" href="<?= e(page_url('juridico-sociedades', ['status' => 'inativa'])) ?>">
    <div class="kpi-label">Desativadas</div>
    <div class="kpi-value"><?= (int) $desativadas ?></div>
    <div class="kpi-last-footer"><span><i data-lucide="filter"></i> Filtrar desativadas</span></div>
  </a>
</div>

<div class="hub-layout">
  <section class="panel">
    <div class="panel-header"><h3>Atalhos do módulo</h3></div>
    <div class="panel-body action-tiles">
      <a class="action-tile action-tile-primary" href="<?= e(page_url('juridico-sorteio')) ?>">
        <span class="action-tile-icon"><i data-lucide="shuffle"></i></span>
        <strong>Abrir sorteio jurídico</strong>
        <span>Cruza atividade × polo e sorteia entre as aptas.</span>
        <i data-lucide="arrow-up-right" class="action-tile-arrow"></i>
      </a>
      <a class="action-tile" href="<?= e(page_url('juridico-sociedades')) ?>">
        <span class="action-tile-icon"><i data-lucide="users"></i></span>
        <strong>Sociedades credenciadas</strong>
        <span>Cadastro, Ficha de Opção e status (Anexo X).</span>
        <i data-lucide="arrow-up-right" class="action-tile-arrow"></i>
      </a>
      <a class="action-tile" href="<?= e(page_url('juridico-historico')) ?>">
        <span class="action-tile-icon"><i data-lucide="history"></i></span>
        <strong>Histórico jurídico</strong>
        <span>Atas, amostras e contemplados de cada rodada.</span>
        <i data-lucide="arrow-up-right" class="action-tile-arrow"></i>
      </a>
      <a class="action-tile" href="<?= e(page_url('dashboard')) ?>">
        <span class="action-tile-icon"><i data-lucide="hard-hat"></i></span>
        <strong>Ir para Engenharia</strong>
        <span>Empresas credenciadas e sorteios de engenharia.</span>
        <i data-lucide="arrow-up-right" class="action-tile-arrow"></i>
      </a>
    </div>
  </section>
  <section class="panel">
    <div class="panel-header"><h3>Regras do Edital 01/2026</h3></div>
    <div class="panel-body">
      <ol class="rule-list">
        <li><span class="rule-num">7.1</span><div><strong>Sorteio eletrônico</strong> aleatório e impessoal</div></li>
        <li><span class="rule-num">7.2</span><div><strong>Cruzamento</strong> serviço × polos da Ficha de Opção</div></li>
        <li><span class="rule-num">7.3</span><div><strong>Aceite</strong> em até 5 dias úteis (SEI!/e-mail)</div></li>
        <li><span class="rule-num">7.4</span><div><strong>Recusa</strong> gera novo sorteio na mesma amostra</div></li>
        <li><span class="rule-num">7.5</span><div><strong>Registro</strong> de data/hora, amostra e contemplado</div></li>
        <li><span class="rule-num"><i data-lucide="ban"></i></span><div>Credenciado com demanda aberta <strong>não</strong> entra na rodada</div></li>
      </ol>
    </div>
  </section>
</div>
