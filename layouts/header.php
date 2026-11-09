<?php
$isJuridico = $isJuridico ?? str_starts_with($currentPage ?? '', 'juridico-');

if ($isJuridico) {
    $navItems = [
        ['page' => 'juridico-dashboard', 'label' => 'Visão geral', 'icon' => 'scale'],
        ['page' => 'juridico-sociedades', 'label' => 'Sociedades', 'icon' => 'building-2'],
        ['page' => 'juridico-historico', 'label' => 'Histórico', 'icon' => 'history'],
        ['page' => 'juridico-sorteio', 'label' => 'Novo Sorteio Jurídico', 'icon' => 'shuffle', 'hidden_nav' => true],
    ];
    $ctaNav = ['page' => 'juridico-sorteio', 'label' => 'Novo sorteio', 'active' => ($currentPage ?? '') === 'juridico-sorteio'];
} else {
    $navItems = [
        ['page' => 'dashboard', 'label' => 'Visão geral', 'icon' => 'layout-dashboard'],
        ['page' => 'empresas', 'label' => 'Empresas', 'icon' => 'building-2'],
        ['page' => 'historico', 'label' => 'Histórico', 'icon' => 'history'],
        ['page' => 'standby', 'label' => 'Standby', 'icon' => 'hourglass'],
        ['page' => 'escolha-manual', 'label' => 'Escolha manual', 'icon' => 'hand'],
        ['page' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'shield-check'],
        ['page' => 'novo-sorteio', 'label' => 'Novo Sorteio', 'icon' => 'shuffle', 'hidden_nav' => true],
    ];
    $ctaNav = ['page' => 'novo-sorteio', 'label' => 'Novo sorteio', 'active' => ($currentPage ?? '') === 'novo-sorteio'];
}

if (!function_exists('nav_is_active')) {
    function nav_is_active(string $itemPage, string $currentPage): bool
    {
        if ($itemPage === 'dashboard') {
            return $currentPage === 'dashboard';
        }
        if ($itemPage === 'empresas') {
            return in_array($currentPage, ['empresas', 'empresa-detalhe', 'empresa-form'], true);
        }
        if ($itemPage === 'historico') {
            return in_array($currentPage, ['historico', 'historico-detalhe', 'resultado'], true);
        }
        if ($itemPage === 'auditoria') {
            return $currentPage === 'auditoria';
        }
        if ($itemPage === 'standby') {
            return $currentPage === 'standby';
        }
        if ($itemPage === 'escolha-manual') {
            return $currentPage === 'escolha-manual';
        }
        if ($itemPage === 'juridico-dashboard') {
            return $currentPage === 'juridico-dashboard';
        }
        if ($itemPage === 'juridico-sociedades') {
            return in_array($currentPage, ['juridico-sociedades', 'juridico-sociedade-form', 'juridico-sociedade-detalhe'], true);
        }
        if ($itemPage === 'juridico-historico') {
            return $currentPage === 'juridico-historico';
        }
        return $itemPage === $currentPage;
    }
}

$iniciaisUser = 'CL';
$nomeExibicao = 'Comissão de Licitação';

$standbyPendentes = 0;
$standbyCriticos = 0;
if (empty($isJuridico) && class_exists('DrawService')) {
    try {
        $standbyLista = (new DrawService())->listStandby();
        $standbyPendentes = count($standbyLista);
        foreach ($standbyLista as $sbItem) {
            if (standby_urgencia($sbItem['dataHora'] ?? null) === 'critico') {
                $standbyCriticos++;
            }
        }
    } catch (Throwable) {
        $standbyPendentes = 0;
        $standbyCriticos = 0;
    }
}
?>
<header class="app-header">
  <div class="header-top">
    <div class="brand-block">
      <div class="brand-mark" aria-hidden="true">
        <i data-lucide="scale"></i>
      </div>
      <div class="brand-text">
        <div class="brand-title-row">
          <strong><?= e(app_config('org')) ?></strong>
          <span class="brand-version">v<?= e(app_config('version')) ?></span>
        </div>
        <div class="brand-sub">
          <span class="live-dot" aria-hidden="true"></span>
          Auditoria Criptográfica Ativa
          <span class="brand-sep">·</span>
          <?= $isJuridico ? 'Módulo Jurídico · Edital 01/2026' : 'Módulo Engenharia · Edital 02/2026' ?>
        </div>
      </div>
    </div>

    <div class="module-switch" role="navigation" aria-label="Credenciamento">
      <a class="module-chip <?= !$isJuridico ? 'is-active' : '' ?>" href="<?= e(page_url('dashboard')) ?>">
        <i data-lucide="hard-hat"></i> Engenharia
      </a>
      <a class="module-chip <?= $isJuridico ? 'is-active' : '' ?>" href="<?= e(page_url('juridico-dashboard')) ?>">
        <i data-lucide="scale"></i> Jurídico
      </a>
    </div>

    <form class="header-search" action="index.php" method="get" role="search">
      <input type="hidden" name="page" value="<?= $isJuridico ? 'juridico-sociedades' : 'empresas' ?>">
      <i data-lucide="search"></i>
      <input type="search" name="busca" placeholder="<?= $isJuridico ? 'Buscar sociedade, OAB ou CNPJ...' : 'Buscar empresa, token ou ata...' ?>" aria-label="Buscar">
    </form>

    <div class="header-actions">
      <a class="icon-btn<?= $standbyPendentes > 0 ? ' has-notif' : '' ?>"
         href="<?= e(page_url('standby')) ?>"
         aria-label="<?= $standbyPendentes > 0
           ? $standbyPendentes . ' aceite(s) em standby'
           : 'Standby — nenhuma pendência' ?>"
         title="<?= $standbyPendentes > 0
           ? ($standbyCriticos > 0
             ? $standbyCriticos . ' crítico(s) · ' . $standbyPendentes . ' pendente(s)'
             : $standbyPendentes . ' pendente(s) no Standby')
           : 'Abrir Standby' ?>">
        <i data-lucide="bell"></i>
        <?php if ($standbyPendentes > 0): ?>
          <span class="notif-dot<?= $standbyCriticos > 0 ? ' is-critico' : '' ?>"></span>
          <span class="notif-count"><?= $standbyPendentes > 9 ? '9+' : $standbyPendentes ?></span>
        <?php endif; ?>
      </a>
      <div class="user-chip">
        <div class="user-chip-text">
          <strong><?= e($nomeExibicao) ?></strong>
          <span><?= e(date('d/m/Y')) ?></span>
        </div>
        <div class="user-avatar"><?= e($iniciaisUser) ?></div>
      </div>
      <button type="button" class="mobile-toggle" id="navToggle" aria-label="Abrir menu">
        <i data-lucide="menu"></i>
      </button>
    </div>
  </div>

  <nav class="header-nav" id="headerNav" aria-label="Menu principal">
    <div class="nav-tabs">
      <?php foreach ($navItems as $item): ?>
        <?php if (!empty($item['hidden_nav'])) continue; ?>
        <a class="nav-tab <?= nav_is_active($item['page'], $currentPage) ? 'active' : '' ?>"
           href="<?= e(page_url($item['page'])) ?>">
          <i data-lucide="<?= e($item['icon']) ?>"></i>
          <?= e($item['label']) ?>
          <?php if (($item['page'] ?? '') === 'standby' && $standbyPendentes > 0): ?>
            <span class="nav-badge<?= $standbyCriticos > 0 ? ' is-critico' : '' ?>"><?= $standbyPendentes ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <a class="nav-cta <?= $ctaNav['active'] ? 'is-active' : '' ?>" href="<?= e(page_url($ctaNav['page'])) ?>">
      <span class="nav-cta-icon"><i data-lucide="sparkles"></i></span>
      <?= e($ctaNav['label']) ?>
      <i data-lucide="arrow-right" class="nav-cta-arrow"></i>
    </a>
  </nav>
</header>
