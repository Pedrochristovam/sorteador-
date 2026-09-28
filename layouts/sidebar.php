<?php
$navItems = [
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
    ['page' => 'empresas', 'label' => 'Empresas Credenciadas', 'icon' => 'building-2'],
    ['page' => 'novo-sorteio', 'label' => 'Novo Sorteio', 'icon' => 'shuffle'],
    ['page' => 'historico', 'label' => 'Histórico', 'icon' => 'history'],
    ['page' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'shield-check'],
];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-mark" aria-hidden="true">
      <i data-lucide="shuffle"></i>
    </div>
    <div class="org"><?= e(app_config('org')) ?></div>
    <h1><?= e(app_config('name')) ?></h1>
    <div class="version">Ambiente de demonstração</div>
  </div>

  <nav class="sidebar-nav" aria-label="Menu principal">
    <?php foreach ($navItems as $item): ?>
      <?php
        $active = $currentPage === $item['page']
          || ($item['page'] === 'empresas' && in_array($currentPage, ['empresa-detalhe', 'empresa-form'], true))
          || ($item['page'] === 'historico' && in_array($currentPage, ['historico-detalhe', 'resultado'], true))
          || ($item['page'] === 'novo-sorteio' && $currentPage === 'resultado' && isset($_GET['from']) && $_GET['from'] === 'novo');
      ?>
      <a class="nav-item <?= $active ? 'active' : '' ?>" href="<?= e(page_url($item['page'])) ?>">
        <i data-lucide="<?= e($item['icon']) ?>"></i>
        <span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-name"><?= e($user['nome'] ?? 'Usuário') ?></div>
    <div class="user-role"><?= e(ucfirst((string) ($user['perfil'] ?? 'operador'))) ?> · <?= e($user['id'] ?? '') ?></div>
  </div>
</aside>
