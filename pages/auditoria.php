<?php

$auditService = new AuditService();
$drawService = new DrawService();
$companyService = new CompanyService();

$sorteioId = trim((string) ($_GET['sorteio'] ?? ''));
$auditId = trim((string) ($_GET['id'] ?? ''));
$buscaOp = trim((string) ($_GET['q'] ?? ''));
$filtroMod = trim((string) ($_GET['mod'] ?? ''));
$verify = isset($_GET['verify']);

/** Resumo leve para a lateral — sem montar dossiê completo de cada ato. */
$enriquecerLeve = static function (array $log, ?array $sorteio): array {
    if ($sorteio === null) {
        return $log;
    }
    $log['modalidade'] = $sorteio['modalidade'] ?? ($log['modalidade'] ?? ($log['demanda']['modalidade'] ?? null));
    $log['status'] = $sorteio['status'] ?? ($log['status'] ?? null);
    $log['aceiteStatus'] = $sorteio['aceiteStatus'] ?? ($log['aceiteStatus'] ?? null);
    if (empty($log['resultadoFinal']) && !empty($sorteio['empresaSelecionada'])) {
        $log['resultadoFinal'] = [
            'id' => $sorteio['empresaSelecionada']['id'] ?? '',
            'nome' => $sorteio['empresaSelecionada']['nome'] ?? '',
        ];
    }
    if (empty($log['demanda']) && !empty($sorteio['demanda'])) {
        $log['demanda'] = [
            'cidade' => $sorteio['demanda']['cidade'] ?? '',
            'endereco' => $sorteio['demanda']['endereco'] ?? '',
        ];
    }
    if (!isset($log['empresasEliminadas']) && isset($sorteio['totalEliminadas'])) {
        $log['empresasEliminadas'] = array_fill(0, (int) $sorteio['totalEliminadas'], []);
    }
    return $log;
};

$logs = $auditService->listAll();
$sorteios = $drawService->listHistorico();
$sorteioById = [];
foreach ($sorteios as $s) {
    $sorteioById[(string) ($s['id'] ?? '')] = $s;
}

$auditBySorteio = [];
foreach ($logs as $i => $log) {
    $sid = (string) ($log['sorteioId'] ?? '');
    $auditBySorteio[$sid] = true;
    $logs[$i] = $enriquecerLeve($log, $sorteioById[$sid] ?? null);
}
foreach ($sorteios as $s) {
    $sid = (string) ($s['id'] ?? '');
    if ($sid === '' || isset($auditBySorteio[$sid])) {
        continue;
    }
    $dataHora = (string) ($s['dataHora'] ?? '');
    $logs[] = $enriquecerLeve([
        'id' => 'AUD-' . substr($sid, 4),
        'sorteioId' => $sid,
        'dataHora' => $dataHora,
        'usuarioNome' => $s['usuarioNome'] ?? '',
        'demanda' => [
            'cidade' => $s['demanda']['cidade'] ?? '',
            'endereco' => $s['demanda']['endereco'] ?? '',
        ],
        'resultadoFinal' => [
            'id' => $s['empresaSelecionada']['id'] ?? '',
            'nome' => $s['empresaSelecionada']['nome'] ?? '',
        ],
        'empresasEliminadas' => array_fill(0, (int) ($s['totalEliminadas'] ?? 0), []),
        'hashOperacao' => $s['hashIntegridade'] ?? '',
        'sintetico' => true,
    ], $s);
    $auditBySorteio[$sid] = true;
}

usort($logs, static fn(array $a, array $b): int => strcmp((string) ($b['dataHora'] ?? ''), (string) ($a['dataHora'] ?? '')));

if ($filtroMod !== '') {
    $logs = array_values(array_filter(
        $logs,
        static fn(array $log): bool => (string) ($log['modalidade'] ?? $log['demanda']['modalidade'] ?? '') === $filtroMod
    ));
}

if ($buscaOp !== '') {
    $q = mb_strtolower($buscaOp);
    $logs = array_values(array_filter($logs, static function (array $log) use ($q): bool {
        $hay = mb_strtolower(implode(' ', [
            $log['id'] ?? '',
            $log['sorteioId'] ?? '',
            $log['resultadoFinal']['nome'] ?? '',
            $log['demanda']['cidade'] ?? '',
            $log['demanda']['endereco'] ?? '',
            $log['usuarioNome'] ?? '',
            modalidade_label((string) ($log['modalidade'] ?? '')),
        ]));
        return str_contains($hay, $q);
    }));
}

$selecionado = null;
if ($auditId !== '') {
    foreach ($logs as $log) {
        if (($log['id'] ?? '') === $auditId) {
            $selecionado = $log;
            break;
        }
    }
    if (!$selecionado) {
        $found = $auditService->findById($auditId);
        if ($found) {
            $sid = (string) ($found['sorteioId'] ?? '');
            $selecionado = $auditService->montarDossie($found, $sorteioById[$sid] ?? null);
        }
    }
} elseif ($sorteioId !== '') {
    foreach ($logs as $log) {
        if (($log['sorteioId'] ?? '') === $sorteioId) {
            $selecionado = $log;
            break;
        }
    }
    if (!$selecionado && isset($sorteioById[$sorteioId])) {
        $selecionado = $auditService->fromSorteio($sorteioById[$sorteioId]);
    }
}

if (!$selecionado && $logs !== []) {
    $selecionado = $logs[0];
}

// Dossiê completo só do ato em foco
if ($selecionado) {
    $sidSel = (string) ($selecionado['sorteioId'] ?? '');
    if (!empty($selecionado['sintetico']) && isset($sorteioById[$sidSel])) {
        $selecionado = $auditService->fromSorteio($sorteioById[$sidSel]);
    } else {
        $full = $auditService->findBySorteioId($sidSel) ?? $selecionado;
        $selecionado = $auditService->montarDossie(
            $auditService->prepararParaExibicao($full),
            $sorteioById[$sidSel] ?? null
        );
    }
}

$totalOps = count($logs);
$glossario = AuditService::glossario();
$integrityChain = integrity_hash_for(json_encode(array_column($logs, 'hashOperacao'), JSON_UNESCAPED_UNICODE));

$nAnalisadas = count($selecionado['empresasAnalisadas'] ?? []);
$nEliminadas = count($selecionado['empresasEliminadas'] ?? []);
$nElegiveis = count($selecionado['empresasElegiveis'] ?? []);
$nParticipantes = count($selecionado['empresasParticipantes'] ?? []);
$modalidadeSel = (string) ($selecionado['modalidade'] ?? $selecionado['demanda']['modalidade'] ?? '');
$demanda = is_array($selecionado['demanda'] ?? null) ? $selecionado['demanda'] : [];
$verificacao = $selecionado ? $auditService->verificarIntegridade($selecionado) : null;
$hashOk = (bool) ($verificacao['ok'] ?? false);
$isPartial = ($_GET['partial'] ?? '') === 'main';

if ($isPartial) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
}

$statusAto = (string) ($selecionado['status'] ?? '');
$aceiteStatus = (string) ($selecionado['aceiteStatus'] ?? '');
$statusLabel = match (true) {
    $statusAto === 'concluido', $aceiteStatus === 'aceito' => 'Concluído / aceito',
    $statusAto === 'recusado', $aceiteStatus === 'recusado' => 'Recusado',
    $statusAto === 'aguardando_aceite', $aceiteStatus === 'pendente' => 'Aguardando aceite',
    $statusAto === 'aguardando_orcamento' => 'Aguardando orçamentos',
    default => $statusAto !== '' ? status_sorteio_label($statusAto) : 'Registrado',
};

$contAceitos = 0;
$contRecusas = 0;
$contPend = 0;
foreach ($logs as $l) {
    $st = (string) ($l['aceiteStatus'] ?? $l['status'] ?? '');
    if ($st === 'aceito' || ($l['status'] ?? '') === 'concluido') {
        $contAceitos++;
    } elseif ($st === 'recusado' || ($l['status'] ?? '') === 'recusado') {
        $contRecusas++;
    } elseif ($st === 'pendente' || ($l['status'] ?? '') === 'aguardando_aceite') {
        $contPend++;
    }
}

if ($isPartial) {
    goto audit_main_fragment;
}
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Governança &amp; Conformidade</span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1>Auditoria Criptográfica</h1>
      <span class="chip-success"><i data-lucide="shield-check"></i> Dossiê completo</span>
    </div>
    <p class="subtitle">
      Trilha imutável de cada sorteio: critérios, eliminações, aleatoriedade, hash SHA-256, aceite/recusa e fundamento legal —
      tudo explicado componente a componente.
    </p>
  </div>
  <div class="hero-actions">
    <?php if ($selecionado): ?>
      <a class="btn btn-outline" href="<?= e(page_url('auditoria', array_filter(['sorteio' => $selecionado['sorteioId'] ?? '', 'q' => $buscaOp, 'mod' => $filtroMod, 'verify' => 1]))) ?>">
        <i data-lucide="scan-search"></i> Verificar integridade
      </a>
      <button type="button" class="btn btn-outline" onclick="window.print()">
        <i data-lucide="printer"></i> Imprimir dossiê
      </button>
      <a class="btn btn-accent" href="<?= e(page_url('historico-detalhe', ['id' => $selecionado['sorteioId'] ?? ''])) ?>">
        <i data-lucide="file-text"></i> Abrir ato no histórico
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($verify && $selecionado && $verificacao): ?>
  <div class="alert <?= $hashOk ? 'alert-success' : 'alert-warning' ?>">
    <?php if ($hashOk): ?>
      <strong>Integridade confirmada.</strong>
      O hash persistido confere com o recálculo canônico desta operação
      (<span class="mono"><?= e($selecionado['id'] ?? '') ?></span>).
    <?php else: ?>
      <strong>Atenção:</strong> o hash persistido não bate exatamente com o recálculo padrão
      (comum em registros sintéticos/migrados). Persistido:
      <span class="mono"><?= e($verificacao['persistido']) ?></span>
      · Esperado: <span class="mono"><?= e($verificacao['esperado']) ?></span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Operações na trilha</div>
    <div class="kpi-value"><?= (int) $totalOps ?></div>
    <div class="kpi-hint">Logs + sorteios reconstruídos</div>
  </article>
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Aceites registrados</div>
    <div class="kpi-value"><?= (int) $contAceitos ?></div>
    <div class="kpi-hint"><?= (int) $contPend ?> em standby · <?= (int) $contRecusas ?> recusas</div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Algoritmo</div>
    <div class="kpi-value" style="font-size:1.2rem;">CSPRNG</div>
    <div class="kpi-hint">Fisher-Yates · SHA-256</div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Cadeia da sessão</div>
    <div class="kpi-token mono" style="font-size:0.78rem;word-break:break-all;"><?= e(substr($integrityChain, 0, 28)) ?>…</div>
    <div class="kpi-hint">Hash agregado dos atos listados</div>
  </article>
</div>

<section class="panel audit-glossary-panel">
  <div class="panel-header">
    <div>
      <h3>Glossário — o que é cada componente</h3>
      <p class="panel-sub muted">Toque em “O que é…” em cada seção do dossiê, ou expanda o glossário completo aqui.</p>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" id="btnToggleGlossario" aria-expanded="false">
      Expandir
    </button>
  </div>
  <div class="panel-body" id="glossarioBody" hidden>
    <div class="audit-glossary-grid">
      <?php foreach ($glossario as $g): ?>
        <article class="audit-glossary-card" id="glossario-<?= e($g['id']) ?>">
          <div class="audit-glossary-icon"><i data-lucide="<?= e($g['icone']) ?>"></i></div>
          <div>
            <h4><?= e($g['titulo']) ?></h4>
            <p class="audit-glossary-resumo"><?= e($g['resumo']) ?></p>
            <p class="audit-glossary-detalhe"><?= e($g['detalhe']) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="audit-layout">
  <aside class="panel audit-sidebar">
    <div class="panel-header">
      <h3>Operações auditadas</h3>
      <span class="count-pill"><?= (int) $totalOps ?></span>
    </div>
    <div class="panel-body audit-sidebar-body">
      <form method="get" action="index.php" class="audit-search-form">
        <input type="hidden" name="page" value="auditoria">
        <input class="form-control" type="search" name="q" value="<?= e($buscaOp) ?>" placeholder="ID, empresa, cidade, operador…">
        <select class="form-control" name="mod" onchange="this.form.submit()">
          <option value="">Todas as modalidades</option>
          <?php foreach (['sorteio' => 'Sorteio', 'orcamento' => 'Orçamento', 'manual' => 'Manual', 'proxima' => 'Mais próxima'] as $cod => $lab): ?>
            <option value="<?= e($cod) ?>" <?= $filtroMod === $cod ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-accent btn-sm" type="submit"><i data-lucide="search"></i> Buscar</button>
      </form>

      <?php if ($logs === []): ?>
        <div class="empty-state">Nenhuma operação encontrada.</div>
      <?php else: ?>
        <div class="audit-op-list">
          <?php foreach ($logs as $log): ?>
            <?php
              $active = ($selecionado['id'] ?? '') === ($log['id'] ?? '');
              $params = array_filter([
                  'sorteio' => $log['sorteioId'] ?? '',
                  'q' => $buscaOp,
                  'mod' => $filtroMod,
              ]);
              $mod = (string) ($log['modalidade'] ?? $log['demanda']['modalidade'] ?? '');
            ?>
            <a class="audit-op-item<?= $active ? ' is-active' : '' ?>" href="<?= e(page_url('auditoria', $params)) ?>" data-soft-nav>
              <div class="audit-op-top">
                <span class="mono audit-op-id"><?= e($log['sorteioId'] ?? '') ?></span>
                <?php if ($active): ?>
                  <span class="audit-badge-foco">Em foco</span>
                <?php else: ?>
                  <span class="audit-badge-ok">Auditado</span>
                <?php endif; ?>
              </div>
              <div class="audit-op-date"><?= e(format_datetime($log['dataHora'] ?? null)) ?></div>
              <div class="audit-op-winner"><?= e($log['resultadoFinal']['nome'] ?? '—') ?></div>
              <div class="audit-op-meta">
                <?= e($log['demanda']['cidade'] ?? '—') ?>
                <?php if ($mod !== ''): ?> · <?= e(modalidade_label($mod)) ?><?php endif; ?>
                · <?= count($log['empresasEliminadas'] ?? []) ?> elim.
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>

<?php
audit_main_fragment:
?>
  <div class="audit-main" id="auditMain">
    <?php if (!$selecionado): ?>
      <div class="alert alert-info">Selecione uma operação à esquerda para abrir o dossiê completo.</div>
    <?php else: ?>
      <?php
        $selId = (string) ($selecionado['sorteioId'] ?? '');
        $vencedor = $selecionado['resultadoFinal'] ?? null;
        $vencedorId = (string) ($vencedor['id'] ?? '');
        $eventos = $selecionado['eventos'] ?? [];
      ?>

      <!-- 1. Sumário do ato -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="file-badge"></i> 1. Sumário do ato</h3>
            <p class="panel-sub muted">
              Identificação do sorteio, demanda e responsável.
              <button type="button" class="audit-gloss-btn" data-glossario="modalidade">O que é modalidade?</button>
            </p>
          </div>
          <span class="status-pill <?= in_array($aceiteStatus, ['aceito'], true) || $statusAto === 'concluido' ? 'success' : (in_array($aceiteStatus, ['recusado'], true) || $statusAto === 'recusado' ? 'danger' : 'warning') ?>">
            <span class="dot"></span> <?= e($statusLabel) ?>
          </span>
        </div>
        <div class="panel-body">
          <div class="audit-summary-grid">
            <div class="audit-summary-item">
              <span class="lbl">ID do sorteio</span>
              <strong class="mono"><a href="<?= e(page_url('historico-detalhe', ['id' => $selId])) ?>"><?= e($selId) ?></a></strong>
            </div>
            <div class="audit-summary-item">
              <span class="lbl">ID da auditoria</span>
              <strong class="mono"><?= e($selecionado['id'] ?? '—') ?></strong>
            </div>
            <div class="audit-summary-item">
              <span class="lbl">Data / hora</span>
              <strong><?= e(format_datetime($selecionado['dataHora'] ?? null)) ?></strong>
            </div>
            <div class="audit-summary-item">
              <span class="lbl">Operador</span>
              <strong><?= e($selecionado['usuarioNome'] ?? '—') ?></strong>
            </div>
            <div class="audit-summary-item">
              <span class="lbl">Modalidade</span>
              <strong><?= e(modalidade_label($modalidadeSel)) ?></strong>
              <?php if (!empty($selecionado['modalidadeMotivo'])): ?>
                <small class="muted"><?= e($selecionado['modalidadeMotivo']) ?></small>
              <?php endif; ?>
            </div>
            <div class="audit-summary-item">
              <span class="lbl">Versão do algoritmo</span>
              <strong class="mono"><?= e($selecionado['versaoAlgoritmo'] ?? '—') ?></strong>
            </div>
          </div>

          <div class="audit-demand-box">
            <h4>Demanda congelada no ato</h4>
            <p>
              <strong><?= e(($demanda['cidade'] ?? '—') . '/' . ($demanda['uf'] ?? '')) ?></strong>
              — <?= e($demanda['endereco'] ?? '—') ?>
            </p>
            <div class="audit-demand-meta">
              <?php if (!empty($demanda['regiaoCodigo']) || !empty($demanda['regiaoNome'])): ?>
                <span><i data-lucide="map"></i> Região <?= e(trim(($demanda['regiaoCodigo'] ?? '') . ' — ' . ($demanda['regiaoNome'] ?? ''))) ?></span>
              <?php endif; ?>
              <?php if (!empty($demanda['servicoNecessario'])): ?>
                <span><i data-lucide="wrench"></i> <?= e($demanda['servicoNecessario']) ?></span>
              <?php elseif (!empty($demanda['atividadesCodigos'])): ?>
                <span><i data-lucide="wrench"></i> Atividades: <?= e(implode(', ', (array) $demanda['atividadesCodigos'])) ?></span>
              <?php endif; ?>
              <?php if (!empty($demanda['precoEdital'])): ?>
                <span><i data-lucide="badge-dollar-sign"></i> Preço de edital</span>
              <?php endif; ?>
              <?php if (!empty($demanda['areaConstruidaM2'])): ?>
                <span>Área construída: <?= e((string) $demanda['areaConstruidaM2']) ?> m²</span>
              <?php endif; ?>
              <?php if (!empty($demanda['areaTerrenoM2'])): ?>
                <span>Terreno: <?= e((string) $demanda['areaTerrenoM2']) ?> m²</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Certificação -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="fingerprint"></i> 2. Certificação criptográfica</h3>
            <p class="panel-sub muted">
              <button type="button" class="audit-gloss-btn" data-glossario="hash">Hash</button> ·
              <button type="button" class="audit-gloss-btn" data-glossario="seed">Semente</button> ·
              <button type="button" class="audit-gloss-btn" data-glossario="cadeia">Cadeia</button>
            </p>
          </div>
          <span class="chip-success"><i data-lucide="<?= $hashOk ? 'shield-check' : 'shield-alert' ?>"></i> <?= $hashOk ? 'Íntegro' : 'Verificar' ?></span>
        </div>
        <div class="panel-body">
          <div class="audit-explain">
            <strong>Para que serve:</strong>
            o hash prova que o registro deste sorteio não foi alterado depois de consolidado.
            A semente documenta a fonte de aleatoriedade do embaralhamento.
          </div>

          <div class="audit-hash-box">
            <div class="audit-hash-label">
              <span>Hash criptográfico SHA-256 da operação</span>
              <span class="chip-success"><i data-lucide="lock"></i> Imutável</span>
            </div>
            <code class="audit-hash-value"><?= e($selecionado['hashOperacao'] ?? '—') ?></code>
            <div class="audit-hash-actions">
              <button type="button" class="btn btn-ghost btn-sm copy-token" data-copy="<?= e($selecionado['hashOperacao'] ?? '') ?>">
                <i data-lucide="copy"></i> Copiar hash
              </button>
              <span class="muted mono">seed <?= e($selecionado['seedReferencia'] ?? 'n/d') ?></span>
            </div>
          </div>

          <?php if ($verificacao): ?>
            <div class="audit-verify-grid">
              <div>
                <span class="lbl">Recálculo canônico</span>
                <code class="mono audit-mini-hash"><?= e($verificacao['esperado']) ?></code>
              </div>
              <div>
                <span class="lbl">Checksum do algoritmo</span>
                <code class="mono audit-mini-hash"><?= e($selecionado['checksumAlgoritmo'] ?? '—') ?></code>
              </div>
            </div>
          <?php endif; ?>

          <div class="audit-criteria">
            <?php
              $criterios = $selecionado['criteriosUtilizados'] ?? [];
              if ($criterios === []) {
                  $criterios = ['Atividade habilitada', 'Credenciado ativo', 'Atende a região da demanda', 'Limite 200 km ida e volta'];
              }
              foreach ($criterios as $c):
            ?>
              <span class="audit-criterion ok"><i data-lucide="check"></i> <?= e(str_ireplace('Convênio ativo', 'Credenciado ativo', (string) $c)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- 3. Funil -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="filter"></i> 3. Funil de elegibilidade</h3>
            <p class="panel-sub muted"><button type="button" class="audit-gloss-btn" data-glossario="funil">O que é o funil?</button></p>
          </div>
        </div>
        <div class="panel-body">
          <div class="audit-explain">
            <strong>Para que serve:</strong>
            mostra quantas empresas entraram na análise, quantas foram eliminadas por critério e quantas
            permaneceram aptas / participaram do sorteio propriamente dito.
          </div>
          <div class="metric-row audit-metrics">
            <div class="metric-box">
              <div class="num"><?= (int) $nAnalisadas ?></div>
              <div class="lbl">Analisadas</div>
            </div>
            <div class="metric-box bad">
              <div class="num"><?= (int) $nEliminadas ?></div>
              <div class="lbl">Eliminadas</div>
            </div>
            <div class="metric-box ok">
              <div class="num"><?= (int) $nElegiveis ?></div>
              <div class="lbl">Elegíveis</div>
            </div>
            <div class="metric-box">
              <div class="num"><?= (int) $nParticipantes ?></div>
              <div class="lbl">No sorteio</div>
            </div>
          </div>

          <?php if ($vencedor): ?>
            <div class="audit-winner">
              <div class="company-avatar tone-<?= e(avatar_tone($vencedorId ?: ($vencedor['nome'] ?? ''))) ?>">
                <?= e(iniciais_empresa($vencedor['nome'] ?? '')) ?>
              </div>
              <div>
                <div class="lbl">Empresa contemplada / selecionada</div>
                <strong>
                  <?php if ($vencedorId !== ''): ?>
                    <a href="<?= e(page_url('empresa-detalhe', ['id' => $vencedorId])) ?>"><?= e($vencedor['nome'] ?? '—') ?></a>
                  <?php else: ?>
                    <?= e($vencedor['nome'] ?? '—') ?>
                  <?php endif; ?>
                </strong>
                <?php if (isset($vencedor['distanciaKm'])): ?>
                  <span class="muted"><?= e(format_km((float) $vencedor['distanciaKm'])) ?> de carro</span>
                <?php endif; ?>
              </div>
              <div class="audit-winner-actions">
                <a class="btn btn-outline btn-sm" href="<?= e(page_url('historico-detalhe', ['id' => $selId])) ?>">Histórico</a>
                <a class="btn btn-ghost btn-sm" href="<?= e(page_url('resultado', ['id' => $selId])) ?>">Resultado</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 4. Timeline -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="list-ordered"></i> 4. Linha do tempo do algoritmo</h3>
            <p class="panel-sub muted"><button type="button" class="audit-gloss-btn" data-glossario="timeline">Como ler os eventos?</button></p>
          </div>
        </div>
        <div class="panel-body">
          <div class="audit-explain">
            <strong>Para que serve:</strong>
            reconstitui a ordem exata em que o sistema executou o ato — da demanda ao hash e ao aceite.
          </div>
          <?php if ($eventos === []): ?>
            <div class="empty-state">Nenhum evento estruturado; veja o terminal técnico abaixo.</div>
          <?php else: ?>
            <ol class="audit-timeline-rich">
              <?php foreach ($eventos as $ev): ?>
                <?php
                  $cod = (string) ($ev['evento'] ?? '');
                  $info = AuditService::explicarEvento($cod);
                  $detalhe = $ev['detalhe'] ?? null;
                ?>
                <li>
                  <div class="audit-tl-badge"><?= e($info['nivel']) ?></div>
                  <div class="audit-tl-body">
                    <div class="audit-tl-top">
                      <strong><?= e($info['titulo']) ?></strong>
                      <time><?= e(format_datetime($ev['timestamp'] ?? null)) ?></time>
                    </div>
                    <p><?= e($info['explicacao']) ?></p>
                    <?php if (is_string($detalhe) && $detalhe !== ''): ?>
                      <div class="audit-tl-detalhe"><i data-lucide="corner-down-right"></i> <?= e($detalhe) ?></div>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>

          <div class="audit-terminal" style="margin-top:1.1rem;">
            <?php foreach ($selecionado['execucaoLog'] ?? [] as $line): ?>
              <div class="audit-log-line level-<?= e(strtolower($line['level'] ?? '')) ?>">
                <span class="ts">[<?= e($line['ts'] ?? '') ?>]</span>
                <span class="lvl"><?= e($line['level'] ?? '') ?>:</span>
                <span class="msg"><?= e($line['message'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
            <div class="audit-terminal-footer">
              <span>Log técnico espelhado · checksum <span class="mono"><?= e(substr((string) ($selecionado['checksumAlgoritmo'] ?? ''), 0, 24)) ?>…</span></span>
            </div>
          </div>
        </div>
      </section>

      <!-- 5. Participantes -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="users"></i> 5. Participantes do sorteio</h3>
            <p class="panel-sub muted">Empresas que efetivamente entraram no pool sorteado / escolhido</p>
          </div>
        </div>
        <div class="panel-body">
          <?php $parts = $selecionado['empresasParticipantes'] ?? []; ?>
          <?php if ($parts === []): ?>
            <div class="empty-state">Nenhum participante listado neste registro.</div>
          <?php else: ?>
            <div class="audit-part-grid">
              <?php foreach ($parts as $i => $p): ?>
                <?php
                  $pid = (string) ($p['id'] ?? $p['empresaId'] ?? '');
                  $isWin = $pid !== '' && $pid === $vencedorId;
                ?>
                <article class="audit-part-card<?= $isWin ? ' is-winner' : '' ?>">
                  <span class="audit-part-rank"><?= $i + 1 ?></span>
                  <div>
                    <strong>
                      <?php if ($pid !== ''): ?>
                        <a href="<?= e(page_url('empresa-detalhe', ['id' => $pid])) ?>"><?= e($p['nome'] ?? '—') ?></a>
                      <?php else: ?>
                        <?= e($p['nome'] ?? '—') ?>
                      <?php endif; ?>
                    </strong>
                    <div class="muted">
                      <?= e($p['cidade'] ?? '') ?>
                      <?php if (isset($p['distanciaKm'])): ?> · <?= e(format_km((float) $p['distanciaKm'])) ?><?php endif; ?>
                      <?php if (!empty($p['emRodizio'])): ?> · já no rodízio<?php endif; ?>
                    </div>
                  </div>
                  <?php if ($isWin): ?><span class="badge badge-success">Selecionada</span><?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($selecionado['sorteadas']) && count($selecionado['sorteadas']) > 1): ?>
            <h4 class="audit-subhead">Sorteadas para orçamento</h4>
            <ul class="audit-simple-list">
              <?php foreach ($selecionado['sorteadas'] as $s): ?>
                <li><?= e($s['nome'] ?? '—') ?><?php if (isset($s['distanciaKm'])): ?> · <?= e(format_km((float) $s['distanciaKm'])) ?><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($selecionado['orcamentos'])): ?>
            <h4 class="audit-subhead">Orçamentos registrados</h4>
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Empresa</th><th>Valor</th><th>Enviou?</th></tr></thead>
                <tbody>
                  <?php foreach ($selecionado['orcamentos'] as $o): ?>
                    <tr class="<?= (($o['id'] ?? '') === $vencedorId) ? 'is-winner' : '' ?>">
                      <td><?= e($o['nome'] ?? '—') ?></td>
                      <td><?= isset($o['valor']) && $o['valor'] !== null ? e(format_moeda((float) $o['valor'])) : '—' ?></td>
                      <td><?= !empty($o['enviou']) ? 'Sim' : 'Não' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <?php if (!empty($selecionado['rodizioNota'])): ?>
            <div class="alert alert-info" style="margin-top:1rem;margin-bottom:0;">
              <strong>Nota de rodízio:</strong> <?= e($selecionado['rodizioNota']) ?>
              <?php if (!empty($selecionado['rodizioIds'])): ?>
                · IDs no rodízio: <span class="mono"><?= e(implode(', ', (array) $selecionado['rodizioIds'])) ?></span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 6. Aceite -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="hourglass"></i> 6. Aceite / recusa (Standby)</h3>
            <p class="panel-sub muted"><button type="button" class="audit-gloss-btn" data-glossario="aceite">Entenda o Standby</button></p>
          </div>
        </div>
        <div class="panel-body">
          <div class="audit-explain">
            <strong>Para que serve:</strong>
            documenta a decisão humana após o sorteio. Só o aceite coloca a empresa no rodízio.
          </div>
          <?php if ($aceiteStatus === '' && $statusAto !== 'aguardando_aceite'): ?>
            <div class="empty-state">Este ato ainda não passou pelo fluxo de aceite (ou é anterior ao Standby).</div>
          <?php else: ?>
            <div class="audit-aceite-box tone-<?= $aceiteStatus === 'aceito' || $statusAto === 'concluido' ? 'success' : ($aceiteStatus === 'recusado' || $statusAto === 'recusado' ? 'danger' : 'warning') ?>">
              <div><strong>Situação:</strong> <?= e($statusLabel) ?></div>
              <?php if (!empty($selecionado['aceiteEm'])): ?>
                <div><strong>Registrado em:</strong> <?= e(format_datetime($selecionado['aceiteEm'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($selecionado['aceiteUsuario'])): ?>
                <div><strong>Por:</strong> <?= e($selecionado['aceiteUsuario']) ?></div>
              <?php endif; ?>
              <?php if (trim((string) ($selecionado['aceiteObservacao'] ?? '')) !== ''): ?>
                <div class="audit-aceite-obs">
                  <strong><?= $aceiteStatus === 'recusado' ? 'Motivo da recusa' : 'Observação' ?>:</strong>
                  <?= e($selecionado['aceiteObservacao']) ?>
                </div>
              <?php elseif ($aceiteStatus === 'recusado'): ?>
                <div class="muted">Recusa sem motivo informado.</div>
              <?php elseif ($aceiteStatus === 'aceito'): ?>
                <div class="muted">Aceite sem observação adicional.</div>
              <?php elseif ($statusAto === 'aguardando_aceite'): ?>
                <a class="btn btn-accent btn-sm" href="<?= e(page_url('standby')) ?>">Abrir Standby</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 7. Eliminações -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="file-warning"></i> 7. Eliminações e fundamentação</h3>
            <p class="panel-sub muted"><button type="button" class="audit-gloss-btn" data-glossario="eliminacao">Por que eliminar?</button></p>
          </div>
          <a class="btn btn-ghost btn-sm" href="<?= e(page_url('empresas', ['status' => 'inativa'])) ?>">Ver desativadas</a>
        </div>
        <div class="panel-body">
          <div class="audit-explain">
            <strong>Para que serve:</strong>
            cada exclusão fica motivada (fatos) e enquadrada (edital/regulamento), para defesa do ato.
          </div>
          <?php if ($nEliminadas === 0): ?>
            <div class="empty-state">Nenhuma eliminação registrada neste ato (ou visão sintética sem lista completa).</div>
          <?php else: ?>
            <div class="elim-grid">
              <?php foreach ($selecionado['empresasEliminadas'] as $emp): ?>
                <?php
                  $emp = enriquecer_eliminacao($emp);
                  $eid = (string) ($emp['id'] ?? $emp['empresaId'] ?? '');
                  $tone = $emp['tone'] ?? 'danger';
                ?>
                <article class="elim-card tone-<?= e($tone) ?>">
                  <div class="elim-card-head">
                    <div>
                      <h4>
                        <?php if ($eid !== ''): ?>
                          <a href="<?= e(page_url('empresa-detalhe', ['id' => $eid])) ?>"><?= e($emp['nome'] ?? '—') ?></a>
                        <?php else: ?>
                          <?= e($emp['nome'] ?? '—') ?>
                        <?php endif; ?>
                      </h4>
                    </div>
                    <span class="elim-badge"><?= e($emp['categoriaLabel'] ?? 'Não elegível') ?></span>
                  </div>
                  <div class="elim-block">
                    <div class="lbl">Motivo fático</div>
                    <ul>
                      <?php foreach ($emp['motivos'] ?? [] as $motivo): ?>
                        <li><?= e($motivo) ?></li>
                      <?php endforeach; ?>
                      <?php if (isset($emp['distanciaKm'])): ?>
                        <li>Rota de carro: <?= e(format_km((float) $emp['distanciaKm'])) ?> (ida e volta <?= e(format_km((float) $emp['distanciaKm'] * 2)) ?>)</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  <div class="elim-block">
                    <div class="lbl">Enquadramento</div>
                    <p><?= e($emp['fundamentacao'] ?? '') ?></p>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 8. Base legal -->
      <section class="panel audit-section">
        <div class="panel-header">
          <div>
            <h3><i data-lucide="scale"></i> 8. Base legal e conformidade</h3>
            <p class="panel-sub muted"><button type="button" class="audit-gloss-btn" data-glossario="legal">Princípios aplicáveis</button></p>
          </div>
        </div>
        <div class="panel-body">
          <div class="audit-legal-grid">
            <article>
              <h4>Art. 37, CF/88</h4>
              <p>Legalidade, impessoalidade, moralidade, publicidade e eficiência — o sorteio objetivo com registro público materializa esses princípios.</p>
            </article>
            <article>
              <h4>Lei 14.133/2021</h4>
              <p>Contratações públicas: isonomia entre licitantes/credenciados e transparência dos critérios de seleção.</p>
            </article>
            <article>
              <h4>Edital 02/2026 — Engenharia</h4>
              <p>Credenciamento, rodízio, margem de 200 km ida/volta, orçamento entre as 3 mais próximas e escolha manual quando não houver aptas na cidade.</p>
            </article>
            <article>
              <h4>Integridade digital</h4>
              <p>Hash SHA-256, semente CSPRNG e trilha de eventos permitem auditoria independente e reconstituição do ato.</p>
            </article>
          </div>
          <?php if (!empty($selecionado['sintetico'])): ?>
            <div class="alert alert-info" style="margin-top:1rem;margin-bottom:0;">
              Este dossiê foi <strong>reconstruído</strong> a partir do sorteio persistido (log sintético).
              Novos atos geram log completo automaticamente na execução.
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="integrity-bar">
        <div>
          <strong>Cadeia de custódia desta sessão de consulta</strong>
          <div class="mono integrity-hash"><?= e($integrityChain) ?></div>
          <small class="muted">Agrega os hashes das <?= (int) $totalOps ?> operações listadas à esquerda.</small>
        </div>
        <a href="<?= e(page_url('historico')) ?>">Voltar ao histórico →</a>
      </section>
    <?php endif; ?>
  </div>
<?php if ($isPartial) {
    return;
} ?>
</div>

<script type="application/json" id="auditGlossarioData"><?= json_encode(
  array_column($glossario, null, 'id'),
  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
) ?></script>
<div id="auditGlossPopover" class="audit-gloss-popover" hidden role="dialog" aria-modal="false">
  <button type="button" class="audit-gloss-close" aria-label="Fechar">×</button>
  <h4 id="auditGlossTitle"></h4>
  <p id="auditGlossResumo" class="audit-glossary-resumo"></p>
  <p id="auditGlossDetalhe" class="audit-glossary-detalhe"></p>
</div>
<script src="assets/js/auditoria.js" defer></script>
