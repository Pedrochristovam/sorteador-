<?php
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('juridico-dashboard')) ?>">Jurídico</a>
  <span>/</span>
  <span>Histórico</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Histórico de Sorteios Jurídicos</h1>
      <span class="count-pill">Edital 01/2026</span>
    </div>
    <p class="subtitle">Atos do módulo de sociedades de advogados — separado do histórico de engenharia.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-accent" href="<?= e(page_url('juridico-sorteio')) ?>">
      <i data-lucide="plus"></i> Novo Sorteio Jurídico
    </a>
  </div>
</div>

<section class="panel">
  <div class="panel-body">
    <div class="empty-state">
      Nenhum sorteio jurídico registrado ainda.
      <div style="margin-top:1rem;">
        <a class="btn btn-outline" href="<?= e(page_url('juridico-sorteio')) ?>">Abrir fluxo de sorteio</a>
      </div>
    </div>
  </div>
</section>
