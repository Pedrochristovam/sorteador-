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
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Novo Sorteio — Engenharia</span>
</nav>

<div class="empresas-hero sorteio-hero">
  <div>
    <div class="dash-title-row">
      <h1>Novo Sorteio</h1>
      <span class="count-pill mono"><?= e($processoId) ?></span>
      <span class="chip-success"><i data-lucide="route"></i> Rota de carro · <?= $provedorDistancia === 'google' ? 'Google Maps' : 'OpenStreetMap' ?></span>
    </div>
    <p class="subtitle">
      A demanda define a regra: sorteio com rodízio, a mais próxima (preço de edital) ou orçamento entre 3.
    </p>
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

<ol class="wizard-steps" aria-label="Etapas do sorteio">
  <?php
  $wizard = [
    1 => ['label' => 'Parâmetros da Demanda', 'hint' => 'Local, atividade e regra'],
    2 => ['label' => 'Filtro & Elegibilidade', 'hint' => 'Região, rota e rodízio'],
    3 => ['label' => 'Escolha', 'hint' => modalidade_label($modalidade)],
    4 => ['label' => 'Resultado & Ata', 'hint' => 'Registro e auditoria'],
  ];
  foreach ($wizard as $n => $meta):
    $cls = $n === $visualStep ? 'is-active' : ($n < $visualStep ? 'is-done' : 'is-pending');
  ?>
    <li class="wizard-step <?= $cls ?>">
      <span class="wizard-num"><?= $n < $visualStep ? '✓' : $n ?></span>
      <span class="wizard-text">
        <strong>Etapa <?= $n ?></strong>
        <span><?= e($meta['label']) ?></span>
        <small><?= e($meta['hint']) ?></small>
      </span>
    </li>
  <?php endforeach; ?>
</ol>

<?php if ($etapa === 'demanda'): ?>
  <?php
    $atividadesSel = $d['atividadesCodigos'] ?? [];
    $ufAtual = $d['uf'] ?? 'MG';
  ?>
  <script type="application/json" id="regioesSugestao"><?= json_encode($sugestaoRegioes, JSON_UNESCAPED_UNICODE) ?></script>
  <div class="sorteio-layout">
    <div class="sorteio-main">
      <form id="formDemanda" class="sorteio-form" novalidate>
        <section class="panel sorteio-section">
          <div class="panel-header">
            <h3><i data-lucide="map-pin"></i> 1. Localização da demanda</h3>
          </div>
          <div class="panel-body">
            <div class="detail-grid">
              <div class="form-group">
                <label for="cidade">Cidade da demanda *</label>
                <input class="form-control" type="text" id="cidade" name="cidade" required
                  list="listaCidades" placeholder="Digite a cidade"
                  value="<?= e($d['cidade'] ?? '') ?>" autocomplete="address-level2">
                <datalist id="listaCidades">
                  <?php foreach ($cidades as $cid): ?>
                    <option value="<?= e($cid) ?>"></option>
                  <?php endforeach; ?>
                </datalist>
                <?php if ($cidades !== []): ?>
                  <div class="city-chips" id="cityChips">
                    <?php foreach (array_slice($cidades, 0, 6) as $cid): ?>
                      <button type="button" class="city-chip" data-city="<?= e($cid) ?>"><?= e($cid) ?></button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="form-group">
                <label for="uf">UF</label>
                <select class="form-control" id="uf" name="uf">
                  <?php foreach (['MG','AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                    <option value="<?= e($uf) ?>" <?= $ufAtual === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="grid-column:1 / -1;">
                <div class="address-field-wrap" id="addressFieldWrap">
                  <label for="endereco">
                    Endereço da demanda (rua, número, bairro) *
                    <span class="geo-badge" id="geoBadge" hidden><i data-lucide="check-circle-2"></i> No mapa</span>
                  </label>
                  <input class="form-control" type="text" id="endereco" name="endereco" required
                    placeholder="Ex.: Av. Afonso Pena, 1500 — Centro"
                    value="<?= e($d['endereco'] ?? '') ?>" autocomplete="street-address">
                  <span class="address-live-dot" aria-hidden="true"></span>
                  <div class="field-hint">
                    <i data-lucide="navigation"></i>
                    A distância de cada credenciada é a rota de carro até este endereço, não linha reta.
                  </div>
                </div>
              </div>
              <div class="form-group" style="grid-column:1 / -1;">
                <label for="regiaoCodigo">
                  Região da demanda *
                  <span class="regiao-sugestao" id="regiaoSugestao" hidden></span>
                </label>
                <select class="form-control" id="regiaoCodigo" name="regiaoCodigo" required>
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
                <div class="field-hint">
                  <i data-lucide="map"></i>
                  Participa quem tem esta região no cadastro, mesmo que esteja longe. Não há raio máximo.
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="panel sorteio-section">
          <div class="panel-header">
            <h3><i data-lucide="briefcase"></i> 2. Atividades habilitadas</h3>
          </div>
          <div class="panel-body">
            <p class="field-hint" style="margin:0 0 0.7rem;">
              <i data-lucide="layers"></i>
              Marque uma ou mais. A credenciada precisa ser habilitada em <strong>ao menos uma</strong> delas.
            </p>
            <div class="checkbox-grid servicos-grid" id="atividadesGrid">
              <?php foreach ($atividades as $cod => $nome): ?>
                <label class="checkbox-item criteria-card activity-card">
                  <input type="checkbox" name="atividadesCodigos[]" value="<?= e($cod) ?>"
                    <?= in_array($cod, $atividadesSel, true) ? 'checked' : '' ?>>
                  <span>
                    <div class="cb-title">
                      <span class="activity-code"><?= e($cod) ?></span> <?= e($nome) ?>
                    </div>
                    <?php if (in_array($cod, Demanda::ATIVIDADES_ORCAMENTO, true)): ?>
                      <div class="cb-desc"><em class="opt">exige orçamento entre 3</em></div>
                    <?php endif; ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="servicos-selected-count muted" id="servicosCountHint">Nenhuma atividade selecionada</div>
          </div>
        </section>

        <section class="panel sorteio-section">
          <div class="panel-header">
            <h3><i data-lucide="scale"></i> 3. Regra de escolha</h3>
          </div>
          <div class="panel-body">
            <div class="detail-grid">
              <div class="form-group">
                <label for="areaConstruidaM2">Área construída (m²)</label>
                <input class="form-control" type="number" min="0" step="0.01" id="areaConstruidaM2" name="areaConstruidaM2"
                  placeholder="Sala, loja, prédio, galpão…" value="<?= e(isset($d['areaConstruidaM2']) ? (string) $d['areaConstruidaM2'] : '') ?>">
                <div class="field-hint">Acima de 1.000 m² → orçamento entre 3</div>
              </div>
              <div class="form-group">
                <label for="areaTerrenoM2">Área do terreno (m²)</label>
                <input class="form-control" type="number" min="0" step="0.01" id="areaTerrenoM2" name="areaTerrenoM2"
                  placeholder="Terreno" value="<?= e(isset($d['areaTerrenoM2']) ? (string) $d['areaTerrenoM2'] : '') ?>">
                <div class="field-hint">Acima de 10.000 m² → orçamento entre 3</div>
              </div>
              <div class="form-group" style="grid-column:1 / -1;">
                <label class="switch-row">
                  <input type="checkbox" id="precoEdital" name="precoEdital" value="1" <?= !empty($d['precoEdital']) ? 'checked' : '' ?>>
                  <span>
                    <strong>Laudo com preço definido em edital</strong>
                    <small>Vai para a credenciada mais próxima da demanda (rota de carro), sem sorteio.</small>
                  </span>
                </label>
              </div>
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
          </div>
        </section>

        <section class="panel sorteio-section">
          <div class="panel-header">
            <h3><i data-lucide="building-2"></i> 4. Credenciadas na cidade da demanda</h3>
          </div>
          <div class="panel-body">
            <label class="switch-row" id="somenteCidadeRow">
              <input type="checkbox" id="somenteCidade" name="somenteCidade" value="1" <?= !empty($d['somenteCidade']) ? 'checked' : '' ?>>
              <span>
                <strong>Sortear só entre as credenciadas sediadas na cidade da demanda</strong>
                <small id="somenteCidadeHint">Disponível quando houver 2 ou mais aptas com endereço na cidade.</small>
              </span>
            </label>
          </div>
        </section>

        <section class="panel sorteio-section">
          <div class="panel-header">
            <h3><i data-lucide="list-checks"></i> Critérios aplicados</h3>
          </div>
          <div class="panel-body">
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
                <span><strong>Controle das chamadas (rodízio)</strong> — quem foi chamada só volta depois que todas as credenciadas ativas forem chamadas, salvo se for a única apta. Na regra “mais próxima”, o rodízio não se aplica.</span>
              </li>
            </ul>
          </div>
        </section>

        <div class="sorteio-actions">
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
            <div id="mapRaioHint" style="margin-top:0.2rem;">Sem raio: vale a região atendida</div>
          </div>
        </div>
      </div>

      <div class="panel impact-panel">
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
            <div class="impact-row warn"><span>Passam de 100 km (limite)</span><strong id="impLimite">—</strong></div>
            <div class="impact-row warn" id="impCidadeRow" hidden><span>Fora da cidade (restrito)</span><strong id="impCidade">—</strong></div>
            <div class="impact-row highlight"><span>Aptas</span><strong id="impOk">—</strong></div>
            <div class="impact-row muted-row"><span>Aguardando rodízio</span><strong id="impRodizio">—</strong></div>
          </div>
          <div class="impact-meta">
            <span><i data-lucide="route"></i> Mais próxima: <strong id="impProxima">—</strong></span>
            <span><i data-lucide="building-2"></i> Aptas na cidade: <strong id="impNaCidade">—</strong></span>
            <span><i data-lucide="satellite"></i> <span id="impFonte"><?= $provedorDistancia === 'google' ? 'Google Maps (rota de carro)' : 'OpenStreetMap (rota de carro)' ?></span></span>
          </div>
        </div>
      </div>
    </aside>
  </div>

<?php elseif ($etapa === 'elegiveis'): ?>
  <?php
    $eliminadas = $fluxo['eliminadas'] ?? [];
    $cidadeInfo = $fluxo['cidade'] ?? [];
    $rodizioInfo = $fluxo['rodizio'] ?? [];
  ?>
  <div class="metric-row">
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

  <div class="demanda-resumo">
    <div class="modalidade-card compact" data-modalidade="<?= e($modalidade) ?>">
      <div class="modalidade-card-icon"><i data-lucide="<?= $modalidade === 'proxima' ? 'locate-fixed' : ($modalidade === 'orcamento' ? 'receipt' : 'shuffle') ?>"></i></div>
      <div class="modalidade-card-body">
        <div class="modalidade-card-eyebrow">Regra desta demanda</div>
        <div class="modalidade-card-title"><?= e(modalidade_label($modalidade)) ?></div>
        <div class="modalidade-card-desc"><?= e($d['modalidadeMotivo'] ?? '') ?></div>
      </div>
    </div>
    <div class="alert alert-info" style="margin:0;">
      <strong><?= e($d['cidade'] ?? '') ?>/<?= e($d['uf'] ?? '') ?></strong> — <?= e($d['endereco'] ?? '') ?>
      · Região <strong><?= e(($d['regiaoCodigo'] ?? '') . ' - ' . ($d['regiaoNome'] ?? '')) ?></strong>
      · Atividade(s): <strong><?= e($d['servicoNecessario'] ?? '') ?></strong>
      <br><small>Distâncias: <?= e($fluxo['distancia']['rotulo'] ?? '') ?><?= !empty($cidadeInfo['restricaoAplicada']) ? ' · Restrito às sediadas em ' . e($d['cidade'] ?? '') : '' ?></small>
      <?php if (!empty($cidadeInfo['restricaoSolicitada']) && empty($cidadeInfo['restricaoAplicada'])): ?>
        <br><small>Restrição à cidade não aplicada: só <?= (int) ($cidadeInfo['aptasNaCidade'] ?? 0) ?> apta(s) sediada(s) em <?= e($d['cidade'] ?? '') ?>.</small>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel" style="margin-bottom:1.25rem;">
    <div class="panel-header">
      <h3>Credenciadas aptas</h3>
      <span class="muted"><?= count($elegiveis) ?> apta(s) · ordenadas pela rota de carro</span>
    </div>
    <?php if ($elegiveis === []): ?>
      <div class="panel-body"><div class="empty-state">Nenhuma credenciada atendeu aos critérios. Revise a região e as atividades.</div></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table data-table-premium">
          <thead>
            <tr><th>Credenciada</th><th>Cidade</th><th>Rota de carro</th><th>Ida e volta</th><th>Situação</th></tr>
          </thead>
          <tbody>
            <?php foreach ($elegiveis as $emp): ?>
              <tr class="<?= !empty($emp['emRodizio']) ? 'row-rodizio' : '' ?>">
                <td>
                  <a href="<?= e(page_url('empresa-detalhe', ['id' => $emp['empresaId'] ?? ''])) ?>"><strong><?= e($emp['nome']) ?></strong></a>
                  <div class="tags-wrap" style="margin-top:0.3rem;">
                    <?php foreach ($emp['servicosCompativeis'] ?? [] as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?>
                  </div>
                </td>
                <td><?= e($emp['cidade']) ?><?= !empty($emp['mesmaCidade']) ? ' <span class="tag tag-cidade">mesma cidade</span>' : '' ?></td>
                <td>
                  <strong><?= e(format_km($emp['distanciaKm'] ?? null)) ?></strong>
                  <?php if (!empty($emp['duracaoMin'])): ?><small class="muted">· ~<?= (int) $emp['duracaoMin'] ?> min</small><?php endif; ?>
                  <?php if (($emp['distanciaPrecisao'] ?? '') === 'cidade'): ?><small class="muted" title="Endereço não localizado com precisão; rota calculada a partir do centro da cidade"> (centro da cidade)</small><?php endif; ?>
                  <?php if (($emp['distanciaFonte'] ?? '') === 'estimativa'): ?><small class="muted"> (estimativa)</small><?php endif; ?>
                </td>
                <td><?= e(format_km($emp['idaVoltaKm'] ?? null)) ?><?= empty($emp['atendeAcima100km']) ? ' <span class="tag tag-km-limite">≤ 100 km</span>' : '' ?></td>
                <td>
                  <?php if (!empty($emp['emRodizio'])): ?>
                    <span class="tag tag-rodizio" title="Já chamada no ciclo atual do rodízio">Aguardando rodízio</span>
                  <?php else: ?>
                    <span class="tag tag-livre">Na vez</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h3>Credenciadas eliminadas</h3>
      <a class="btn btn-ghost btn-sm" href="<?= e(page_url('auditoria')) ?>">Ver fundamentação no painel</a>
    </div>
    <div class="panel-body">
      <?php if ($eliminadas === []): ?>
        <div class="empty-state">Nenhuma credenciada eliminada nesta análise.</div>
      <?php else: ?>
        <?php foreach ($eliminadas as $emp): ?>
          <?php $empClass = enriquecer_eliminacao([
            'id' => $emp['empresaId'] ?? '',
            'nome' => $emp['nome'] ?? '',
            'motivos' => $emp['motivosEliminacao'] ?? [],
            'status' => $emp['status'] ?? '',
          ]); ?>
          <div class="company-card eliminada tone-<?= e($empClass['tone'] ?? 'danger') ?>">
            <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;">
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
      <?php endif; ?>
    </div>
  </div>

  <div class="actions-row">
    <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio', ['etapa' => 'demanda'])) ?>">
      <i data-lucide="arrow-left"></i> Voltar aos parâmetros
    </a>
    <?php if (count($elegiveis) >= 1): ?>
      <a class="btn btn-accent btn-lg" href="<?= e(page_url('novo-sorteio', ['etapa' => 'preparacao'])) ?>">
        Avançar <i data-lucide="arrow-right"></i>
      </a>
    <?php endif; ?>
  </div>

<?php elseif ($etapa === 'preparacao'): ?>
  <div class="panel">
    <div class="panel-header">
      <h3><?= e(modalidade_label($modalidade)) ?></h3>
      <span class="chip-success"><i data-lucide="lock"></i> Critérios fixados</span>
    </div>
    <div class="panel-body">
      <?php if ($elegiveis === []): ?>
        <div class="alert alert-warning">Não há credenciadas aptas.</div>
        <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio', ['etapa' => 'demanda'])) ?>">Voltar à demanda</a>
      <?php else: ?>
        <div class="alert alert-info"><?= e($d['modalidadeMotivo'] ?? '') ?></div>

        <?php if ($modalidade === 'proxima'): ?>
          <h4 class="prep-title">Comparação pela rota de carro até a demanda</h4>
          <?php if ($rankingProxima === []): ?>
            <div class="alert alert-warning">Não foi possível apurar a rota de nenhuma apta. Revise o endereço da demanda.</div>
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
        <?php else: ?>
          <div class="detail-grid" style="margin-bottom:1rem;">
            <div class="detail-item">
              <div class="lbl"><?= $modalidade === 'orcamento' ? 'Serão sorteadas' : 'Será sorteada' ?></div>
              <div class="val"><?= $modalidade === 'orcamento' ? min(DrawService::QTD_ORCAMENTO, count($elegiveis)) . ' credenciada(s) para orçamento' : '1 credenciada' ?></div>
            </div>
            <div class="detail-item">
              <div class="lbl">Participam do sorteio</div>
              <div class="val"><?= count($pool) ?> de <?= count($elegiveis) ?> apta(s)</div>
            </div>
          </div>
          <?php if ($livres === []): ?>
            <div class="alert alert-warning">Exceção ao rodízio: todas as aptas já foram chamadas no ciclo<?= count($elegiveis) === 1 ? ' (é a única que realiza a demanda)' : '' ?>. O sorteio considera todas.</div>
          <?php elseif ($aguardandoRodizio !== [] && count($pool) < count($elegiveis)): ?>
            <div class="alert alert-info"><?= count($aguardandoRodizio) ?> apta(s) aguardando o rodízio ficam fora: <?= e(implode(', ', array_column($aguardandoRodizio, 'nome'))) ?>.</div>
          <?php endif; ?>
          <h4 class="prep-title">Participantes</h4>
          <?php foreach ($pool as $emp): ?>
            <div class="company-card elegivel">
              <h4><?= e($emp['nome']) ?></h4>
              <div class="company-meta">
                <span><?= e($emp['cidade']) ?></span>
                <span><?= e(format_km($emp['distanciaKm'] ?? null)) ?> de carro</span>
                <?php if (!empty($emp['emRodizio'])): ?><span class="tag tag-rodizio">já chamada no ciclo</span><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="actions-row">
          <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio', ['etapa' => 'elegiveis'])) ?>">Voltar</a>
          <button type="button" class="btn btn-accent btn-lg" data-open-modal="modalConfirmarSorteio" <?= $modalidade === 'proxima' && $rankingProxima === [] ? 'disabled' : '' ?>>
            <i data-lucide="<?= $modalidade === 'proxima' ? 'locate-fixed' : 'shuffle' ?>"></i>
            <?= $modalidade === 'proxima' ? 'Confirmar a mais próxima' : ($modalidade === 'orcamento' ? 'Sortear 3 para orçamento' : 'Realizar sorteio') ?>
          </button>
        </div>
      <?php endif; ?>
    </div>
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
  <div class="draw-stage" id="drawStage" data-modalidade="<?= e($modalidade) ?>">
    <div style="position:relative;z-index:1;">
      <div style="font-size:0.8rem;letter-spacing:0.12em;text-transform:uppercase;opacity:0.7;"><?= e(modalidade_label($modalidade)) ?></div>
      <h3 style="margin:0.5rem 0 0;font-family:var(--font-display);font-size:1.5rem;" id="drawStatus">
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

  <div class="panel">
    <div class="panel-body" style="text-align:center;">
      <button type="button" class="btn btn-accent btn-lg" id="btnExecutarSorteio" data-modalidade="<?= e($modalidade) ?>">
        <i data-lucide="<?= $modalidade === 'proxima' ? 'locate-fixed' : 'shuffle' ?>"></i>
        <?= $modalidade === 'proxima' ? 'Registrar a mais próxima' : ($modalidade === 'orcamento' ? 'Sortear 3 para orçamento' : (count($pool) === 1 ? 'Confirmar seleção' : 'Realizar sorteio')) ?>
      </button>
      <p style="margin-top:0.75rem;color:var(--muted);font-size:0.85rem;">
        A escolha é feita no servidor (CSPRNG + Fisher-Yates) e gera trilha no painel de auditoria.
      </p>
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
  <?php if (!$resultado): ?>
    <div class="alert alert-warning">Resultado não disponível.</div>
    <a class="btn btn-outline" href="<?= e(page_url('novo-sorteio')) ?>">Novo sorteio</a>
  <?php else: ?>
    <?php
      $embeddedResultado = $resultado;
      $embeddedSorteioId = $resultado['sorteioId'] ?? ($sorteio['id'] ?? '');
      require __DIR__ . '/partials/resultado-card.php';
    ?>
  <?php endif; ?>
<?php endif; ?>
