<?php

$companyService = new CompanyService();
$catalogo = $companyService->getCatalogo();
$atividades = $catalogo['atividades'] ?? [];
$regioesMg = $catalogo['regioesMunicipios'] ?? [];
$estados = $catalogo['estadosAtuacao'] ?? [];
$criterios = $companyService->listCriterios();
$cidades = $companyService->listCidades();
$totalEmpresas = count($companyService->listAll());
$ativas = $companyService->countAtivas();
$desativadasBase = $totalEmpresas - $ativas;
$sugestaoRegioes = require dirname(__DIR__) . '/mocks/regioes_sugestao.php';
$provedorDistancia = (new DistanceService())->provider();

$fluxo = sorteador_get_fluxo() ?? [];
$etapa = (string) ($_GET['etapa'] ?? ($fluxo['etapa'] ?? 'demanda'));
$etapasValidas = ['demanda', 'elegiveis', 'preparacao', 'execucao', 'resultado'];
if (!in_array($etapa, $etapasValidas, true)) {
    $etapa = 'demanda';
}
// Fluxos antigos (antes das modalidades) voltam para a demanda
if ($etapa !== 'demanda' && $etapa !== 'resultado' && empty($fluxo['demanda']['modalidade'])) {
    $etapa = 'demanda';
}
if (in_array($etapa, ['elegiveis', 'preparacao', 'execucao'], true) && empty($fluxo['elegiveis']) && empty($fluxo['eliminadas'])) {
    $etapa = 'demanda';
}
if ($etapa === 'resultado' && empty($fluxo['ultimoResultado']) && empty($_SESSION['ultimo_resultado_id'])) {
    $etapa = 'demanda';
}

$visualStep = match ($etapa) {
    'demanda' => 1,
    'elegiveis' => 2,
    'preparacao', 'execucao' => 3,
    'resultado' => 4,
    default => 1,
};

$processoId = 'SRT-' . date('Y') . '/' . str_pad((string) ((int) date('z') + 1), 3, '0', STR_PAD_LEFT);
$d = $fluxo['demanda'] ?? [];
$modalidade = (string) ($d['modalidade'] ?? 'sorteio');
$modalidadeIcone = $modalidade === 'proxima' ? 'locate-fixed' : ($modalidade === 'orcamento' ? 'receipt' : 'shuffle');

$elegiveis = $fluxo['elegiveis'] ?? [];
$livres = array_values(array_filter($elegiveis, static fn(array $e): bool => empty($e['emRodizio'])));
$aguardandoRodizio = array_values(array_filter($elegiveis, static fn(array $e): bool => !empty($e['emRodizio'])));
$pool = match ($modalidade) {
    'orcamento' => count($livres) >= DrawService::QTD_ORCAMENTO ? $livres : $elegiveis,
    'proxima' => $elegiveis,
    default => $livres !== [] ? $livres : $elegiveis,
};
$rankingProxima = array_values(array_filter($elegiveis, static fn(array $e): bool => ($e['distanciaKm'] ?? null) !== null));

$pageScripts = ['assets/js/sorteio.js'];

// Wizard: etapas concluídas viram links de volta
$wizard = [
    1 => ['label' => 'Parâmetros', 'hint' => 'Local, atividade e regra', 'icon' => 'sliders-horizontal', 'href' => page_url('novo-sorteio', ['etapa' => 'demanda'])],
    2 => ['label' => 'Elegibilidade', 'hint' => 'Região, rota e rodízio', 'icon' => 'filter', 'href' => page_url('novo-sorteio', ['etapa' => 'elegiveis'])],
    3 => ['label' => 'Escolha', 'hint' => modalidade_label($modalidade), 'icon' => $modalidadeIcone, 'href' => page_url('novo-sorteio', ['etapa' => 'preparacao'])],
    4 => ['label' => 'Resultado', 'hint' => 'Registro e auditoria', 'icon' => 'file-check-2', 'href' => page_url('novo-sorteio', ['etapa' => 'resultado'])],
];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Novo Sorteio — Engenharia</span>
</nav>

<div class="empresas-hero sorteio-hero<?= $etapa === 'demanda' ? ' is-compact' : '' ?>">
  <div>
    <div class="dash-title-row">
      <h1>Novo Sorteio</h1>
      <span class="count-pill mono"><?= e($processoId) ?></span>
      <span class="chip-success"><i data-lucide="route"></i> Rota de carro · <?= $provedorDistancia === 'google' ? 'Google Maps' : 'OpenStreetMap' ?></span>
    </div>
    <?php if ($etapa !== 'demanda'): ?>
      <p class="subtitle">
        A demanda define a regra: sorteio com rodízio (também com preço de edital) ou orçamento entre as 3 mais próximas.
      </p>
    <?php endif; ?>
  </div>
  <div class="hero-actions">
    <?php if ($etapa !== 'demanda'): ?>
      <button type="button" class="btn btn-outline" id="btnReiniciarFluxo">
        <i data-lucide="rotate-ccw"></i> Reiniciar fluxo
      </button>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(page_url('historico')) ?>">
      <i data-lucide="history"></i> Histórico
    </a>
  </div>
</div>

<nav class="wizard-bar" aria-label="Etapas do sorteio">
  <ol>
    <?php foreach ($wizard as $n => $meta):
      $cls = $n === $visualStep ? 'is-active' : ($n < $visualStep ? 'is-done' : 'is-pending');
      $clicavel = $n < $visualStep;
      $tag = $clicavel ? 'a' : 'div';
    ?>
      <li class="wizard-item <?= $cls ?>">
        <<?= $tag ?> class="wizard-step-link"<?= $clicavel ? ' href="' . e($meta['href']) . '" title="Voltar para ' . e($meta['label']) . '"' : '' ?><?= $n === $visualStep ? ' aria-current="step"' : '' ?>>
          <span class="wizard-node">
            <?php if ($n < $visualStep): ?>
              <i data-lucide="check"></i>
            <?php else: ?>
              <i data-lucide="<?= e($meta['icon']) ?>"></i>
            <?php endif; ?>
          </span>
          <span class="wizard-copy">
            <small>Etapa <?= $n ?></small>
            <strong><?= e($meta['label']) ?></strong>
            <em><?= e($meta['hint']) ?></em>
          </span>
        </<?= $tag ?>>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>

<?php if ($etapa === 'demanda'): ?>
  <?php
    $atividadesSel = $d['atividadesCodigos'] ?? [];
    $ufAtual = $d['uf'] ?? 'MG';
  ?>
  <script type="application/json" id="regioesSugestao"><?= json_encode($sugestaoRegioes, JSON_UNESCAPED_UNICODE) ?></script>

  <div class="demanda-stage">
    <header class="demanda-stage-intro">
      <div>
        <p class="demanda-kicker"><i data-lucide="sliders-horizontal"></i> Etapa 1 · Parâmetros</p>
        <h2>O que precisa ser feito, e onde?</h2>
        <p>Informe o local e as atividades. O sistema escolhe a regra certa e mostra o impacto em tempo real.</p>
      </div>
      <ul class="demanda-pillars" aria-label="Como funciona">
        <li><i data-lucide="route"></i> Distância por rota de carro</li>
        <li><i data-lucide="repeat"></i> Rodízio automático</li>
        <li><i data-lucide="shield-check"></i> Trilha de auditoria</li>
      </ul>
    </header>

    <div class="sorteio-layout demanda-layout">
      <div class="sorteio-main">
        <form id="formDemanda" class="sorteio-form demanda-form" novalidate>

          <section class="demanda-block">
            <div class="demanda-block-head">
              <span class="demanda-step-index">01</span>
              <div>
                <h3>Localização</h3>
                <p>Cidade, endereço e região atendida pela demanda.</p>
              </div>
            </div>

            <div class="demanda-loc-grid">
              <div class="form-group">
                <label for="cidade">Cidade da demanda *</label>
                <input class="form-control form-control-lg" type="text" id="cidade" name="cidade" required
                  list="listaCidades" placeholder="Digite a cidade"
                  value="<?= e($d['cidade'] ?? '') ?>" autocomplete="address-level2">
                <datalist id="listaCidades">
                  <?php foreach ($cidades as $cid): ?>
                    <option value="<?= e($cid) ?>"></option>
                  <?php endforeach; ?>
                </datalist>
              </div>
              <div class="form-group">
                <label for="uf">UF</label>
                <select class="form-control form-control-lg" id="uf" name="uf">
                  <?php foreach (['MG','AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                    <option value="<?= e($uf) ?>" <?= $ufAtual === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <?php if ($cidades !== []): ?>
              <div class="city-chips" id="cityChips">
                <?php foreach (array_slice($cidades, 0, 6) as $cid): ?>
                  <button type="button" class="city-chip" data-city="<?= e($cid) ?>"><?= e($cid) ?></button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="form-group demanda-field-gap">
              <div class="address-field-wrap" id="addressFieldWrap">
                <label for="endereco">
                  Endereço da demanda (rua, número, bairro) *
                  <span class="geo-badge" id="geoBadge" hidden><i data-lucide="check-circle-2"></i> No mapa</span>
                </label>
                <input class="form-control form-control-lg" type="text" id="endereco" name="endereco" required
                  placeholder="Ex.: Av. Afonso Pena, 1500 — Centro"
                  value="<?= e($d['endereco'] ?? '') ?>" autocomplete="street-address">
                <span class="address-live-dot" aria-hidden="true"></span>
                <div class="demanda-callout soft">
                  <i data-lucide="navigation"></i>
                  <span>A distância de cada credenciada é a rota de carro até este endereço, não linha reta.</span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="regiaoCodigo">
                Região da demanda *
                <span class="regiao-sugestao" id="regiaoSugestao" hidden></span>
              </label>
              <select class="form-control form-control-lg" id="regiaoCodigo" name="regiaoCodigo" required>
                <option value="">Selecione a região onde fica a demanda…</option>
                <optgroup label="Regiões de municípios (MG)">
                  <?php foreach ($regioesMg as $cod => $nome): ?>
                    <option value="<?= e($cod) ?>" <?= ($d['regiaoCodigo'] ?? '') === $cod ? 'selected' : '' ?>><?= e($cod . ' — ' . $nome) ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="Estados">
                  <?php foreach ($estados as $cod => $nome): ?>
                    <option value="<?= e($cod) ?>" <?= ($d['regiaoCodigo'] ?? '') === $cod ? 'selected' : '' ?>><?= e($cod . ' — ' . $nome) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
              <div class="demanda-callout soft">
                <i data-lucide="map"></i>
                <span>Participa quem tem esta região no cadastro, mesmo que esteja longe. Não há raio máximo.</span>
              </div>
            </div>
          </section>

          <section class="demanda-block">
            <div class="demanda-block-head">
              <span class="demanda-step-index">02</span>
              <div>
                <h3>Atividades habilitadas</h3>
                <p>A credenciada precisa ser habilitada em <strong>ao menos uma</strong> das marcadas.</p>
              </div>
              <div class="servicos-selected-count muted" id="servicosCountHint">Nenhuma atividade selecionada</div>
            </div>

            <div class="demanda-atividades" id="atividadesGrid">
              <?php foreach ($atividades as $cod => $nome): ?>
                <label class="activity-pick">
                  <input type="checkbox" name="atividadesCodigos[]" value="<?= e($cod) ?>"
                    <?= in_array($cod, $atividadesSel, true) ? 'checked' : '' ?>>
                  <span class="activity-pick-code"><?= e($cod) ?></span>
                  <span class="activity-pick-body">
                    <strong><?= e($nome) ?></strong>
                    <?php if (in_array($cod, Demanda::ATIVIDADES_ORCAMENTO, true)): ?>
                      <em>Exige orçamento entre 3</em>
                    <?php endif; ?>
                  </span>
                  <span class="activity-pick-check" aria-hidden="true"><i data-lucide="check"></i></span>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="demanda-block">
            <div class="demanda-block-head">
              <span class="demanda-step-index">03</span>
              <div>
                <h3>Regra de escolha</h3>
                <p>Áreas, preço de edital e restrição à cidade definem a modalidade.</p>
              </div>
            </div>

            <div class="demanda-areas">
              <div class="form-group">
                <label for="areaConstruidaM2">Área construída (m²)</label>
                <input class="form-control" type="number" min="0" step="0.01" id="areaConstruidaM2" name="areaConstruidaM2"
                  placeholder="Sala, loja, prédio, galpão…" value="<?= e(isset($d['areaConstruidaM2']) ? (string) $d['areaConstruidaM2'] : '') ?>">
                <span class="demanda-threshold">Acima de 1.000 m² → orçamento entre 3</span>
              </div>
              <div class="form-group">
                <label for="areaTerrenoM2">Área do terreno (m²)</label>
                <input class="form-control" type="number" min="0" step="0.01" id="areaTerrenoM2" name="areaTerrenoM2"
                  placeholder="Terreno" value="<?= e(isset($d['areaTerrenoM2']) ? (string) $d['areaTerrenoM2'] : '') ?>">
                <span class="demanda-threshold">Acima de 10.000 m² → orçamento entre 3</span>
              </div>
            </div>

            <div class="demanda-toggles">
              <label class="switch-row">
                <input type="checkbox" id="precoEdital" name="precoEdital" value="1" <?= !empty($d['precoEdital']) ? 'checked' : '' ?>>
                <span>
                  <strong>Laudo com preço definido em edital</strong>
                  <small>Sorteio com rodízio entre as aptas — não escolhe só a mais próxima.</small>
                </span>
              </label>
              <label class="switch-row" id="somenteCidadeRow">
                <input type="checkbox" id="somenteCidade" name="somenteCidade" value="1" <?= !empty($d['somenteCidade']) ? 'checked' : '' ?>>
                <span>
                  <strong>Sortear só entre as credenciadas sediadas na cidade da demanda</strong>
                  <small id="somenteCidadeHint">Disponível quando houver 2 ou mais aptas com endereço na cidade.</small>
                </span>
              </label>
            </div>

            <div class="modalidade-card" id="modalidadeCard" data-modalidade="sorteio">
              <div class="modalidade-card-icon"><i data-lucide="shuffle"></i></div>
              <div class="modalidade-card-body">
                <div class="modalidade-card-eyebrow">Regra aplicada a esta demanda</div>
                <div class="modalidade-card-title" id="modalidadeTitulo">Sorteio com rodízio</div>
                <div class="modalidade-card-desc" id="modalidadeMotivo">Sem preço de edital nem exigência de orçamento: sorteio com rodízio.</div>
              </div>
              <div class="modalidade-card-override">
                <label for="modalidadeSolicitada">Alterar</label>
                <select class="form-control form-control-sm" id="modalidadeSolicitada" name="modalidadeSolicitada">
                  <?php $ms = $d['modalidadeSolicitada'] ?? 'auto'; ?>
                  <option value="auto" <?= $ms === 'auto' ? 'selected' : '' ?>>Automática (pela demanda)</option>
                  <option value="sorteio" <?= $ms === 'sorteio' ? 'selected' : '' ?>>Sorteio com rodízio</option>
                  <option value="proxima" <?= $ms === 'proxima' ? 'selected' : '' ?>>Mais próxima</option>
                  <option value="orcamento" <?= $ms === 'orcamento' ? 'selected' : '' ?>>Orçamento entre 3</option>
                </select>
              </div>
            </div>
          </section>

          <details class="demanda-criterios">
            <summary>
              <i data-lucide="list-checks"></i>
              Critérios aplicados a todas as credenciadas
              <i data-lucide="chevron-down" class="demanda-criterios-chevron"></i>
            </summary>
            <ul class="criterios-fixos">
              <?php foreach ($criterios as $criterio): ?>
                <?php if (!$criterio->obrigatorio) continue; ?>
                <li>
                  <i data-lucide="check-circle-2"></i>
                  <span><strong><?= e($criterio->nome) ?></strong> — <?= e($criterio->descricao) ?></span>
                </li>
              <?php endforeach; ?>
              <li>
                <i data-lucide="repeat"></i>
                <span><strong>Controle das chamadas (rodízio)</strong> — quem foi chamada só volta depois que todas as ativas forem chamadas, salvo se for a única apta. Preço de edital também entra no sorteio com rodízio.</span>
              </li>
            </ul>
          </details>

          <div class="sorteio-actions demanda-actions">
            <button type="button" class="btn btn-outline" id="btnSalvarRascunho" title="Mantém os campos preenchidos neste navegador">
              <i data-lucide="save"></i> Salvar rascunho
            </button>
            <button type="submit" class="btn btn-accent btn-lg" id="btnCalcular">
              Calcular credenciadas aptas
              <i data-lucide="arrow-right"></i>
            </button>
          </div>
          <p id="demandaErro" class="alert alert-warning" style="display:none;margin-top:1rem;"></p>
          <p class="sorteio-footnote">
            <i data-lucide="info"></i>
            A primeira consulta de um endereço novo pode levar alguns segundos (cálculo das rotas de carro).
          </p>
        </form>
      </div>

      <aside class="sorteio-aside">
        <div class="map-card" id="mapCard" aria-label="Mapa da demanda">
          <div class="map-card-header">
            <div class="map-title"><i data-lucide="map"></i> Local da demanda</div>
            <span class="map-status" id="mapStatus">Aguardando</span>
          </div>
          <div class="map-frame-wrap">
            <div class="map-placeholder" id="mapPlaceholder">
              <div class="map-icon"><i data-lucide="map-pinned"></i></div>
              <h4>Mapa da demanda</h4>
              <p>Informe cidade e endereço para ver o local.</p>
            </div>
            <div class="map-loading" id="mapLoading"><span class="map-spinner"></span> Atualizando mapa…</div>
            <iframe id="mapFrame" title="Google Maps — local da demanda" loading="lazy"
              referrerpolicy="no-referrer-when-downgrade" allowfullscreen src="about:blank"></iframe>
          </div>
          <div class="map-card-footer">
            <i data-lucide="navigation"></i>
            <div>
              <div class="map-address" id="mapAddressLabel">Nenhum endereço informado</div>
              <div id="mapRaioHint" style="margin-top:0.2rem;">Margem: 200 km ida e volta</div>
            </div>
          </div>
        </div>

        <div class="panel impact-panel demanda-impact">
          <div class="panel-header">
            <h3>Impacto em tempo real</h3>
            <span class="muted" id="impactStatus">Aguardando parâmetros</span>
          </div>
          <div class="panel-body">
            <div class="impact-funnel">
              <div class="impact-row"><span>Total na base</span><strong id="impTotal"><?= (int) $totalEmpresas ?></strong></div>
              <div class="impact-row muted-row"><span>Desativadas</span><strong id="impOff"><?= (int) $desativadasBase ?></strong></div>
              <div class="impact-row warn"><span>Sem a atividade</span><strong id="impAtividade">—</strong></div>
              <div class="impact-row warn"><span>Não atendem a região</span><strong id="impRegiao">—</strong></div>
              <div class="impact-row warn"><span>Passam de 200 km</span><strong id="impLimite">—</strong></div>
              <div class="impact-row warn" id="impCidadeRow" hidden><span>Fora da cidade</span><strong id="impCidade">—</strong></div>
              <div class="impact-row highlight"><span>Aptas no sorteio</span><strong id="impOk">—</strong></div>
              <div class="impact-row muted-row"><span>Aguardando rodízio</span><strong id="impRodizio">—</strong></div>
            </div>
            <div class="impact-meta">
              <span><i data-lucide="route"></i> Mais próxima: <strong id="impProxima">—</strong></span>
              <span><i data-lucide="building-2"></i> Aptas na cidade: <strong id="impNaCidade">—</strong></span>
              <span><i data-lucide="satellite"></i> <span id="impFonte"><?= $provedorDistancia === 'google' ? 'Google Maps (rota de carro)' : 'OpenStreetMap (rota de carro)' ?></span></span>
            </div>
            <div class="alert alert-warning" id="impManualBanner" hidden style="margin-top:0.85rem;">
              <strong id="impManualTitulo">Sem empresas para o sorteio automático</strong>
              <p id="impManualMsg" style="margin:0.35rem 0 0.75rem;"></p>
              <a class="btn btn-accent btn-sm" href="<?= e(page_url('escolha-manual')) ?>">
                <i data-lucide="hand"></i> Escolher manualmente
              </a>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>

<?php elseif ($etapa === 'elegiveis'): ?>
  <?php
    $eliminadas = $fluxo['eliminadas'] ?? [];
    $cidadeInfo = $fluxo['cidade'] ?? [];
    $rodizioInfo = $fluxo['rodizio'] ?? [];
  ?>
  <div class="flow-stage">
    <header class="flow-intro">
      <div>
        <p class="flow-kicker"><i data-lucide="filter"></i> Etapa 2 · Elegibilidade</p>
        <h2>Quem pode atender esta demanda</h2>
        <p>As credenciadas aptas aparecem em ordem de proximidade pela rota de carro.</p>
      </div>
    </header>

    <div class="flow-metrics metric-row">
      <div class="metric-box">
        <div class="num"><?= (int) ($fluxo['totalAnalisadas'] ?? 0) ?></div>
        <div class="lbl">Analisadas</div>
      </div>
      <div class="metric-box bad">
        <div class="num"><?= (int) ($fluxo['totalEliminadas'] ?? 0) ?></div>
        <div class="lbl">Eliminadas</div>
      </div>
      <div class="metric-box ok">
        <div class="num"><?= (int) ($fluxo['totalElegiveis'] ?? 0) ?></div>
        <div class="lbl">Aptas</div>
      </div>
      <div class="metric-box">
        <div class="num"><?= count($aguardandoRodizio) ?></div>
        <div class="lbl">Aguardando rodízio</div>
      </div>
    </div>

    <?php if (!empty($cidadeInfo['restricaoSolicitada']) && empty($cidadeInfo['restricaoAplicada'])): ?>
      <div class="flow-banner is-warn">
        <i data-lucide="alert-triangle"></i>
        <span>Restrição à cidade não aplicada: só <strong><?= (int) ($cidadeInfo['aptasNaCidade'] ?? 0) ?></strong> apta(s) sediada(s) em <?= e($d['cidade'] ?? '') ?>.</span>
      </div>
    <?php elseif (!empty($cidadeInfo['restricaoAplicada'])): ?>
      <div class="flow-banner is-info">
        <i data-lucide="building-2"></i>
        <span>Restrito às credenciadas sediadas em <strong><?= e($d['cidade'] ?? '') ?></strong>.</span>
      </div>
    <?php endif; ?>

    <div class="flow-resumo">
      <div class="modalidade-card compact" data-modalidade="<?= e($modalidade) ?>">
        <div class="modalidade-card-icon"><i data-lucide="<?= e($modalidadeIcone) ?>"></i></div>
        <div class="modalidade-card-body">
          <div class="modalidade-card-eyebrow">Regra desta demanda</div>
          <div class="modalidade-card-title"><?= e(modalidade_label($modalidade)) ?></div>
          <div class="modalidade-card-desc"><?= e($d['modalidadeMotivo'] ?? '') ?></div>
        </div>
      </div>
      <div class="flow-demanda-chip">
        <div class="flow-demanda-line">
          <i data-lucide="map-pin"></i>
          <div>
            <strong><?= e($d['cidade'] ?? '') ?>/<?= e($d['uf'] ?? '') ?></strong>
            <span><?= e($d['endereco'] ?? '') ?></span>
          </div>
        </div>
        <div class="flow-demanda-tags">
          <span class="tag">Região <?= e(($d['regiaoCodigo'] ?? '') . ' - ' . ($d['regiaoNome'] ?? '')) ?></span>
          <span class="tag"><?= e($d['servicoNecessario'] ?? '') ?></span>
          <span class="tag"><i data-lucide="route"></i> <?= e($fluxo['distancia']['rotulo'] ?? '') ?></span>
        </div>
      </div>
    </div>

    <section class="flow-section">
      <div class="flow-section-head">
        <h3><i data-lucide="check-circle-2"></i> Credenciadas aptas</h3>
        <span class="muted"><?= count($elegiveis) ?> apta(s) · ordenadas pela rota de carro</span>
      </div>

      <?php if ($elegiveis === []): ?>
        <div class="flow-empty">
          <i data-lucide="search-x"></i>
          <p><?= !empty($cidadeInfo['mensagem'])
            ? e($cidadeInfo['mensagem'])
            : 'Nenhuma credenciada atendeu aos critérios. Revise a região e as atividades, ou use a escolha manual.' ?></p>
          <a class="btn btn-accent" href="<?= e(page_url('escolha-manual')) ?>">
            <i data-lucide="hand"></i> Escolher manualmente
          </a>
        </div>
      <?php else: ?>
        <ol class="apta-list">
          <?php foreach ($elegiveis as $i => $emp): ?>
            <li class="apta-card<?= !empty($emp['emRodizio']) ? ' is-rodizio' : '' ?><?= $i === 0 ? ' is-first' : '' ?>">
              <span class="apta-rank"><?= $i + 1 ?></span>
              <div class="apta-main">
                <div class="apta-title">
                  <a href="<?= e(page_url('empresa-detalhe', ['id' => $emp['empresaId'] ?? ''])) ?>"><?= e($emp['nome']) ?></a>
                  <?php if (!empty($emp['emRodizio'])): ?>
                    <span class="tag tag-rodizio" title="<?= !empty($emp['rodizioPorMarcacao'])
                      ? e('Marcação no cadastro: ' . ($emp['servicoRealizadoObs'] ?? 'serviço já realizado'))
                      : 'Já chamada no ciclo atual do rodízio' ?>">
                      <?= !empty($emp['rodizioPorMarcacao']) ? 'Rodízio (cadastro)' : 'Aguardando rodízio' ?>
                    </span>
                  <?php else: ?>
                    <span class="tag tag-livre">Na vez</span>
                  <?php endif; ?>
                </div>
                <div class="apta-meta">
                  <span><i data-lucide="map-pin"></i> <?= e($emp['cidade']) ?></span>
                  <?php if (!empty($emp['mesmaCidade'])): ?><span class="tag tag-cidade">mesma cidade</span><?php endif; ?>
                  <?php if (!empty($emp['servicosCompativeis'])): ?>
                    <span class="apta-services">
                      <?php foreach ($emp['servicosCompativeis'] as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="apta-dist">
                <strong><?= e(format_km($emp['distanciaKm'] ?? null)) ?></strong>
                <small>
                  <?php if (($emp['distanciaKm'] ?? null) === null): ?>
                    sem rota de carro
                  <?php else: ?>
                    <?php if (!empty($emp['duracaoMin'])): ?>~<?= (int) $emp['duracaoMin'] ?> min · <?php endif; ?>
                    <?php if (($emp['distanciaPrecisao'] ?? '') === 'cidade'): ?><span title="Endereço não localizado com precisão; rota calculada a partir do centro da cidade">centro da cidade</span><?php elseif (in_array($emp['distanciaFonte'] ?? '', ['google', 'osm'], true)): ?>carro · <?= ($emp['distanciaFonte'] ?? '') === 'google' ? 'Google' : 'OSM' ?><?php else: ?>carro<?php endif; ?>
                  <?php endif; ?>
                </small>
                <small class="apta-ida-volta">
                  Ida e volta <?= e(format_km($emp['idaVoltaKm'] ?? null)) ?>
                  <?= empty($emp['atendeAcima100km']) ? '<span class="tag tag-km-limite">≤ 200 km</span>' : '' ?>
                </small>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <details class="flow-eliminadas">
      <summary>
        <i data-lucide="x-circle"></i>
        <span>Credenciadas eliminadas</span>
        <span class="count-pill"><?= count($eliminadas) ?></span>
        <i data-lucide="chevron-down" class="flow-chevron"></i>
      </summary>
      <div class="flow-eliminadas-body">
        <?php if ($eliminadas === []): ?>
          <div class="flow-empty"><p>Nenhuma credenciada eliminada nesta análise.</p></div>
        <?php else: ?>
          <?php foreach ($eliminadas as $emp): ?>
            <?php $empClass = enriquecer_eliminacao([
              'id' => $emp['empresaId'] ?? '',
              'nome' => $emp['nome'] ?? '',
              'motivos' => $emp['motivosEliminacao'] ?? [],
              'status' => $emp['status'] ?? '',
            ]); ?>
            <div class="company-card eliminada tone-<?= e($empClass['tone'] ?? 'danger') ?>">
              <div class="flow-elim-head">
                <h4><a href="<?= e(page_url('empresa-detalhe', ['id' => $emp['empresaId'] ?? ''])) ?>"><?= e($emp['nome']) ?></a></h4>
                <span class="elim-badge"><?= e($empClass['categoriaLabel'] ?? 'Eliminada') ?></span>
              </div>
              <div class="company-meta">
                <span><?= e($emp['cidade']) ?></span>
                <?php if (($emp['distanciaKm'] ?? null) !== null): ?><span><?= e(format_km($emp['distanciaKm'])) ?> de carro</span><?php endif; ?>
              </div>
              <ul class="criterio-list">
                <?php foreach ($emp['motivosEliminacao'] ?? [] as $motivo): ?>
                  <li class="fail">✕ <?= e($motivo) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(page_url('auditoria')) ?>">Ver fundamentação no painel</a>
        <?php endif; ?>
      </div>
    </details>

    <?php $redirecionarManual = !empty($cidadeInfo['redirecionarManual']) || $elegiveis === []; ?>
    <?php if (!empty($cidadeInfo['redirecionarManual']) || (!empty($cidadeInfo['mensagem']) && $elegiveis === [])): ?>
      <div class="flow-banner is-warn">
        <i data-lucide="hand"></i>
        <span>
          <strong>Não há empresas para o sorteio automático.</strong>
          <?= e($cidadeInfo['mensagem'] ?? 'Use a escolha manual.') ?>
        </span>
        <a class="btn btn-accent btn-sm" href="<?= e(page_url('escolha-manual')) ?>">Escolher manualmente</a>
      </div>
    <?php elseif (!empty($cidadeInfo['priorizacaoAplicada'])): ?>
      <div class="flow-banner is-info">
        <i data-lucide="building-2"></i>
        <span>
          Prioridade para sediadas em <strong><?= e($d['cidade'] ?? '') ?></strong> dentro de
          <strong>200 km ida e volta</strong>.
          <?= (int) ($cidadeInfo['aptasNaCidade'] ?? 0) ?> na cidade · <?= count($elegiveis) ?> no pool.
        </span>
      </div>
    <?php endif; ?>

    <div class="flow-actions actions-row">
      <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio', ['etapa' => 'demanda'])) ?>">
        <i data-lucide="arrow-left"></i> Voltar
      </a>
      <?php if ($redirecionarManual): ?>
        <a class="btn btn-accent btn-lg" href="<?= e(page_url('escolha-manual')) ?>">
          <i data-lucide="hand"></i> Escolher manualmente
        </a>
      <?php elseif (count($elegiveis) >= 1): ?>
        <a class="btn btn-accent btn-lg" href="<?= e(page_url('novo-sorteio', ['etapa' => 'preparacao'])) ?>">
          Avançar para a escolha <i data-lucide="arrow-right"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($etapa === 'preparacao'): ?>
  <div class="flow-stage">
    <header class="flow-intro">
      <div>
        <p class="flow-kicker"><i data-lucide="<?= e($modalidadeIcone) ?>"></i> Etapa 3 · Escolha</p>
        <h2><?= e(modalidade_label($modalidade)) ?></h2>
        <p><?= e($d['modalidadeMotivo'] ?? 'Revise os participantes antes de confirmar.') ?></p>
      </div>
      <span class="chip-success"><i data-lucide="lock"></i> Critérios fixados</span>
    </header>

    <?php if ($elegiveis === []): ?>
      <div class="flow-empty">
        <i data-lucide="search-x"></i>
        <p>Não há credenciadas aptas.</p>
        <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio', ['etapa' => 'demanda'])) ?>">Voltar à demanda</a>
      </div>
    <?php else: ?>

      <?php if ($modalidade === 'proxima'): ?>
        <section class="flow-section">
          <div class="flow-section-head">
            <h3><i data-lucide="route"></i> Comparação pela rota de carro até a demanda</h3>
          </div>
          <?php if ($rankingProxima === []): ?>
            <div class="flow-banner is-warn">
              <i data-lucide="alert-triangle"></i>
              <span>Não foi possível apurar a rota de nenhuma apta. Revise o endereço da demanda.</span>
            </div>
          <?php else: ?>
            <ol class="ranking-list">
              <?php foreach (array_slice($rankingProxima, 0, 8) as $i => $emp): ?>
                <li class="<?= $i === 0 ? 'is-winner' : '' ?>">
                  <span><?= e($emp['nome']) ?> <small class="muted"><?= e($emp['cidade']) ?></small></span>
                  <strong><?= e(format_km($emp['distanciaKm'])) ?><?php if (!empty($emp['duracaoMin'])): ?> <small class="muted">~<?= (int) $emp['duracaoMin'] ?> min</small><?php endif; ?></strong>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <div class="prep-stats">
          <div class="prep-stat">
            <span class="prep-stat-icon"><i data-lucide="<?= $modalidade === 'orcamento' ? 'receipt' : 'trophy' ?>"></i></span>
            <div>
              <small><?= $modalidade === 'orcamento' ? 'Serão sorteadas' : 'Será sorteada' ?></small>
              <strong><?= $modalidade === 'orcamento' ? min(DrawService::QTD_ORCAMENTO, count($elegiveis)) . ' para orçamento' : '1 credenciada' ?></strong>
            </div>
          </div>
          <div class="prep-stat">
            <span class="prep-stat-icon"><i data-lucide="users"></i></span>
            <div>
              <small>Participam do sorteio</small>
              <strong><?= count($pool) ?> de <?= count($elegiveis) ?> apta(s)</strong>
            </div>
          </div>
          <div class="prep-stat">
            <span class="prep-stat-icon"><i data-lucide="repeat"></i></span>
            <div>
              <small>Aguardando rodízio</small>
              <strong><?= count($aguardandoRodizio) ?></strong>
            </div>
          </div>
        </div>

        <?php if ($livres === []): ?>
          <div class="flow-banner is-warn">
            <i data-lucide="alert-triangle"></i>
            <span>Exceção ao rodízio: todas as aptas já foram chamadas no ciclo<?= count($elegiveis) === 1 ? ' (é a única que realiza a demanda)' : '' ?>. O sorteio considera todas.</span>
          </div>
        <?php elseif ($aguardandoRodizio !== [] && count($pool) < count($elegiveis)): ?>
          <div class="flow-banner is-info">
            <i data-lucide="info"></i>
            <span><?= count($aguardandoRodizio) ?> apta(s) aguardando o rodízio ficam fora: <?= e(implode(', ', array_column($aguardandoRodizio, 'nome'))) ?>.</span>
          </div>
        <?php endif; ?>

        <section class="flow-section">
          <div class="flow-section-head">
            <h3><i data-lucide="users"></i> Participantes</h3>
            <span class="muted"><?= count($pool) ?> no sorteio</span>
          </div>
          <div class="prep-grid">
            <?php foreach ($pool as $emp): ?>
              <article class="prep-card">
                <span class="prep-avatar"><?= e(mb_strtoupper(mb_substr((string) ($emp['nome'] ?? '?'), 0, 1))) ?></span>
                <div class="prep-card-body">
                  <h4><?= e($emp['nome']) ?></h4>
                  <div class="prep-card-meta">
                    <span><i data-lucide="map-pin"></i> <?= e($emp['cidade']) ?></span>
                    <span><i data-lucide="route"></i> <?= e(format_km($emp['distanciaKm'] ?? null)) ?></span>
                  </div>
                  <?php if (!empty($emp['emRodizio'])): ?><span class="tag tag-rodizio">já chamada no ciclo</span><?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <div class="flow-actions actions-row">
        <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio', ['etapa' => 'elegiveis'])) ?>">
          <i data-lucide="arrow-left"></i> Voltar
        </a>
        <button type="button" class="btn btn-accent btn-lg" data-open-modal="modalConfirmarSorteio" <?= $modalidade === 'proxima' && $rankingProxima === [] ? 'disabled' : '' ?>>
          <i data-lucide="<?= $modalidade === 'proxima' ? 'locate-fixed' : 'shuffle' ?>"></i>
          <?= $modalidade === 'proxima' ? 'Confirmar a mais próxima' : ($modalidade === 'orcamento' ? 'Sortear 3 para orçamento' : 'Realizar sorteio') ?>
        </button>
      </div>
    <?php endif; ?>
  </div>

  <div class="modal-backdrop" id="modalConfirmarSorteio">
    <div class="modal">
      <div class="modal-header">
        <h3>Confirmar</h3>
        <button type="button" class="btn btn-ghost" data-close-modal="modalConfirmarSorteio" aria-label="Fechar"><i data-lucide="x"></i></button>
      </div>
      <div class="modal-body">
        <p>
          <?php if ($modalidade === 'proxima'): ?>
            A credenciada mais próxima (<strong><?= e($rankingProxima[0]['nome'] ?? '') ?></strong>) será registrada para esta demanda.
          <?php elseif ($modalidade === 'orcamento'): ?>
            Serão sorteadas até <strong>3</strong> credenciadas entre <strong><?= count($pool) ?></strong> participante(s) para pedido de orçamento.
          <?php else: ?>
            Você está prestes a sortear 1 credenciada entre <strong><?= count($pool) ?></strong> participante(s).
          <?php endif; ?>
        </p>
        <p style="color:var(--muted);font-size:0.9rem;">Esta ação será registrada na auditoria e não poderá ser desfeita.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalConfirmarSorteio">Cancelar</button>
        <a class="btn btn-accent" href="<?= e(page_url('novo-sorteio', ['etapa' => 'execucao'])) ?>">Confirmar e continuar</a>
      </div>
    </div>
  </div>

<?php elseif ($etapa === 'execucao'): ?>
  <div class="flow-stage">
    <header class="flow-intro">
      <div>
        <p class="flow-kicker"><i data-lucide="<?= e($modalidadeIcone) ?>"></i> Etapa 3 · Execução</p>
        <h2><?= $modalidade === 'proxima' ? 'Registrar a mais próxima' : ($modalidade === 'orcamento' ? 'Sorteio para orçamento' : 'Hora do sorteio') ?></h2>
        <p>A escolha é feita no servidor (CSPRNG + Fisher-Yates) e gera trilha no painel de auditoria.</p>
      </div>
    </header>

    <div class="draw-stage" id="drawStage" data-modalidade="<?= e($modalidade) ?>">
      <div class="draw-stage-copy">
        <div class="draw-stage-eyebrow"><?= e(modalidade_label($modalidade)) ?></div>
        <h3 class="draw-stage-title" id="drawStatus">
          <?= $modalidade === 'proxima' ? 'Pronto para registrar a mais próxima' : (count($pool) === 1 ? 'Confirmando seleção única' : 'Participantes prontos') ?>
        </h3>
      </div>
      <div class="draw-participants" id="drawParticipants">
        <?php foreach (($modalidade === 'proxima' ? array_slice($rankingProxima, 0, 8) : $pool) as $i => $emp): ?>
          <div class="draw-chip" data-index="<?= $i ?>" data-id="<?= e($emp['empresaId']) ?>">
            <?= e($emp['nome']) ?>
            <?php if ($modalidade === 'proxima'): ?><small> · <?= e(format_km($emp['distanciaKm'])) ?></small><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="draw-winner" id="drawWinner" style="display:none;"></div>
    </div>

    <div class="flow-cta">
      <button type="button" class="btn btn-accent btn-lg" id="btnExecutarSorteio" data-modalidade="<?= e($modalidade) ?>">
        <i data-lucide="<?= $modalidade === 'proxima' ? 'locate-fixed' : 'shuffle' ?>"></i>
        <?= $modalidade === 'proxima' ? 'Registrar a mais próxima' : ($modalidade === 'orcamento' ? 'Sortear 3 para orçamento' : (count($pool) === 1 ? 'Confirmar seleção' : 'Realizar sorteio')) ?>
      </button>
      <p id="execErro" class="alert alert-warning" style="display:none;margin-top:1rem;text-align:left;"></p>
    </div>
  </div>

<?php elseif ($etapa === 'resultado'): ?>
  <?php
    $resultado = $fluxo['ultimoResultado']['resultado'] ?? null;
    $sorteio = $fluxo['ultimoResultado']['sorteio'] ?? null;
    if (!$resultado && !empty($_SESSION['ultimo_resultado_id'])) {
      $sorteio = (new DrawService())->findById((string) $_SESSION['ultimo_resultado_id']);
      if ($sorteio) {
        $resultado = [
          'sorteioId' => $sorteio['id'],
          'empresaSelecionada' => $sorteio['empresaSelecionada'],
          'demanda' => $sorteio['demanda'],
          'dataHora' => $sorteio['dataHora'],
          'participantes' => $sorteio['participantes'],
          'criteriosUtilizados' => $sorteio['criteriosUtilizados'] ?? [],
          'hashIntegridade' => $sorteio['hashIntegridade'] ?? '',
        ];
      }
    }
  ?>
  <div class="flow-stage">
    <header class="flow-intro">
      <div>
        <p class="flow-kicker"><i data-lucide="file-check-2"></i> Etapa 4 · Resultado</p>
        <h2>Sorteio registrado</h2>
        <p>Ata, participantes e hash de integridade ficam salvos no histórico e na auditoria.</p>
      </div>
    </header>

    <?php if (!$resultado): ?>
      <div class="flow-empty">
        <i data-lucide="search-x"></i>
        <p>Resultado não disponível.</p>
        <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio')) ?>">Novo sorteio</a>
      </div>
    <?php else: ?>
      <?php
        $embeddedResultado = $resultado;
        $embeddedSorteioId = $resultado['sorteioId'] ?? ($sorteio['id'] ?? '');
        require __DIR__ . '/partials/resultado-card.php';
      ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
