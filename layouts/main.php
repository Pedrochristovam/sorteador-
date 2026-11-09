<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#f7f8fc">
  <title><?= e($pageTitle) ?> · <?= e(app_config('name')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Outfit:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php
    $cssApp = @filemtime(__DIR__ . '/../assets/css/app.css') ?: 1;
    $cssTheme = @filemtime(__DIR__ . '/../assets/css/theme.css') ?: 1;
  ?>
  <link rel="stylesheet" href="assets/css/app.css?v=<?= (int) $cssApp ?>">
  <link rel="stylesheet" href="assets/css/theme.css?v=<?= (int) $cssTheme ?>">
  <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" defer></script>
  <?php if (in_array($currentPage, ['novo-sorteio', 'resultado'], true)): ?>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js" defer></script>
  <?php endif; ?>
</head>
<body class="page-<?= e($currentPage) ?><?= !empty($isJuridico) ? ' modulo-juridico' : ' modulo-engenharia' ?>">
  <div class="aurora<?= $currentPage === 'auditoria' ? ' aurora-lite' : '' ?>" aria-hidden="true">
    <span class="aurora-blob aurora-a"></span>
    <span class="aurora-blob aurora-b"></span>
    <?php if ($currentPage !== 'auditoria'): ?>
      <span class="aurora-blob aurora-c"></span>
      <span class="aurora-grid"></span>
    <?php endif; ?>
  </div>

  <div class="app-frame">
    <?php require __DIR__ . '/header.php'; ?>

    <main class="content">
      <?php require $pageFile; ?>
    </main>

    <footer class="app-footer">
      <div class="footer-brand">
        <span class="footer-mark"><i data-lucide="scale"></i></span>
        <div>
          <strong><?= e(app_config('org')) ?></strong>
          <span>© <?= date('Y') ?> · Integridade e transparência pública</span>
        </div>
      </div>
      <div class="footer-links">
        <span class="footer-cert"><i data-lucide="badge-check"></i> Ambiente certificado ICP-Brasil</span>
        <a href="<?= e(page_url('auditoria')) ?>">Verificador de hash SHA-256</a>
        <a href="#">Termos de uso</a>
        <a href="#">Suporte técnico</a>
      </div>
    </footer>
  </div>

  <script src="assets/js/app.js" defer></script>
  <script src="assets/js/ui.js" defer></script>
  <?php if (!empty($pageScripts)): ?>
    <?php foreach ((array) $pageScripts as $script): ?>
      <script src="<?= e($script) ?>" defer></script>
    <?php endforeach; ?>
  <?php endif; ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (window.lucide?.createIcons) window.lucide.createIcons();
    });
  </script>
</body>
</html>
