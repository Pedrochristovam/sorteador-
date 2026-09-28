<?php

$juridico = new JuridicoService();
$catalogo = $juridico->getCatalogo();
$aptas = $juridico->countAptas();
$total = count($juridico->listAll());
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('juridico-dashboard')) ?>">Jurídico</a>
  <span>/</span>
  <span>Novo Sorteio</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Sorteio Jurídico</h1>
      <span class="count-pill">Edital 01/2026</span>
      <span class="chip-success"><i data-lucide="shield-check"></i> Impessoalidade · art. 7.1</span>
    </div>
    <p class="subtitle">
      Universo isolado das sociedades de advogados. Engenharia não participa desta amostra.
    </p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('juridico-sociedades')) ?>">Ver sociedades</a>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Base jurídica</div>
    <div class="kpi-value"><?= (int) $total ?></div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Aptas agora</div>
    <div class="kpi-value"><?= (int) $aptas ?></div>
    <div class="kpi-hint">Ativas + sem demanda aberta</div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Critérios do edital</div>
    <div class="kpi-value"><?= count($catalogo['criterios']) ?></div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Prazo de aceite</div>
    <div class="kpi-value" style="font-size:1.45rem;">5 DU</div>
  </article>
</div>

<section class="panel">
  <div class="panel-header"><h3>Fluxo previsto (Seção VII + diretrizes operacionais)</h3></div>
  <div class="panel-body">
    <ol class="wizard-steps" style="margin:0;box-shadow:none;border:none;padding:0;display:grid;grid-template-columns:repeat(4,1fr);gap:0.75rem;">
      <li class="wizard-step is-active">
        <span class="wizard-num">1</span>
        <span class="wizard-text"><strong>Etapa 1</strong><span>Demanda CPL</span><small>Objeto, atividade, polo</small></span>
      </li>
      <li class="wizard-step is-pending">
        <span class="wizard-num">2</span>
        <span class="wizard-text"><strong>Etapa 2</strong><span>Amostra elegível</span><small>Anexo X − demanda aberta</small></span>
      </li>
      <li class="wizard-step is-pending">
        <span class="wizard-num">3</span>
        <span class="wizard-text"><strong>Etapa 3</strong><span>Sorteio CSPRNG</span><small>Seed + hash SHA-256</small></span>
      </li>
      <li class="wizard-step is-pending">
        <span class="wizard-num">4</span>
        <span class="wizard-text"><strong>Etapa 4</strong><span>Aceite 5 DU</span><small>SEI! / e-mail</small></span>
      </li>
    </ol>

    <div class="alert alert-info" style="margin-top:1.25rem;margin-bottom:0;">
      O motor de execução do sorteio jurídico (cálculo de elegíveis + animação + ata) será ligado em seguida
      sobre esta base. Por enquanto o cadastro, o banco e a navegação do módulo já estão isolados do Edital 02/2026 (Engenharia).
    </div>

    <div class="actions-row" style="margin-top:1.25rem;">
      <a class="btn btn-outline" href="<?= e(page_url('juridico-dashboard')) ?>">Voltar ao hub</a>
      <a class="btn btn-accent" href="<?= e(page_url('juridico-sociedades')) ?>">
        Preparar base de sociedades <i data-lucide="arrow-right"></i>
      </a>
    </div>
  </div>
</section>

<section class="panel" style="margin-top:1.25rem;">
  <div class="panel-header"><h3>Critérios jurídicos ativos</h3></div>
  <div class="panel-body">
    <div class="checkbox-grid criteria-grid">
      <?php foreach ($catalogo['criterios'] as $c): ?>
        <div class="checkbox-item criteria-card" style="cursor:default;">
          <span>
            <div class="cb-title"><?= e($c['nome']) ?> <?php if (!empty($c['obrigatorio'])): ?><em class="req">obrigatório</em><?php endif; ?></div>
            <div class="cb-desc"><?= e($c['descricao']) ?></div>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
