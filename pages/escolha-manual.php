<?php

declare(strict_types=1);

$companyService = new CompanyService();
$drawService = new DrawService();
$catalogo = $companyService->getCatalogo();
$atividades = $catalogo['atividades'] ?? [];
$regMun = $catalogo['regioesMunicipios'] ?? [];
$estados = $catalogo['estadosAtuacao'] ?? [];

$diaFiltro = trim((string) ($_GET['dia'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $diaFiltro)) {
    $diaFiltro = date('Y-m-d');
}

$manuais = array_values(array_filter(
    $drawService->listHistorico(),
    static function (array $s) use ($diaFiltro): bool {
        if (($s['modalidade'] ?? '') !== 'manual') {
            return false;
        }
        $dia = substr((string) ($s['dataHora'] ?? ''), 0, 10);
        return $dia === $diaFiltro;
    }
));
usort($manuais, static fn(array $a, array $b): int => strcmp((string) $b['dataHora'], (string) $a['dataHora']));

$standbyUrl = page_url('standby');
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <span>Escolha manual</span>
</nav>

<section class="manual-hero">
  <div class="manual-hero-copy">
    <p class="eyebrow"><i data-lucide="hand"></i> Operação · Engenharia</p>
    <h1>Escolher manualmente</h1>
    <p class="lede">
      Informe a demanda, veja as credenciadas pela rota de carro e escolha a empresa.
      Quem já está no rodízio pode ser escolhida de novo aqui.
    </p>
    <ol class="manual-steps" aria-label="Passos">
      <li><span>1</span> Demanda</li>
      <li><span>2</span> Mais próximas</li>
      <li><span>3</span> Confirmar</li>
    </ol>
  </div>
  <div class="manual-hero-stats">
    <div class="manual-stat">
      <span>Escolhas neste dia</span>
      <strong><?= count($manuais) ?></strong>
      <small><?= e(format_date($diaFiltro)) ?></small>
    </div>
    <a class="manual-stat-link" href="<?= e($standbyUrl) ?>">
      <i data-lucide="hourglass"></i>
      <span>Abrir Standby</span>
    </a>
  </div>
</section>

<div class="manual-layout">
  <section class="manual-work" aria-labelledby="manualWorkTitle">
    <div id="manualWorkspace" class="manual-workspace">
      <header class="manual-card-head">
        <div class="manual-card-icon"><i data-lucide="map-pinned"></i></div>
        <div>
          <h2 id="manualWorkTitle">Nova escolha</h2>
          <p>Preencha o local da demanda e as atividades necessárias.</p>
        </div>
      </header>

      <form id="formManual" class="manual-form" novalidate>
        <div class="manual-form-grid">
          <div class="form-group">
            <label for="manCidade">Cidade *</label>
            <input class="form-control" id="manCidade" name="cidade" required placeholder="Ex.: Belo Horizonte" autocomplete="address-level2">
          </div>
          <div class="form-group man-uf">
            <label for="manUf">UF *</label>
            <input class="form-control" id="manUf" name="uf" maxlength="2" value="MG" required>
          </div>
          <div class="form-group man-full">
            <label for="manEndereco">Endereço da demanda *</label>
            <input class="form-control" id="manEndereco" name="endereco" required placeholder="Rua, número, bairro…">
          </div>
          <div class="form-group man-full">
            <label for="manRegiao">Região *</label>
            <select class="form-control" id="manRegiao" name="regiaoCodigo" required>
              <option value="">Selecione…</option>
              <optgroup label="Regiões MG">
                <?php foreach ($regMun as $cod => $nome): ?>
                  <option value="<?= e($cod) ?>"><?= e($cod . ' — ' . $nome) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Estados">
                <?php foreach ($estados as $cod => $nome): ?>
                  <option value="<?= e($cod) ?>"><?= e($cod . ' — ' . $nome) ?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="form-group man-full">
            <span class="label-like">Atividades *</span>
            <div class="manual-atividades" role="group" aria-label="Atividades">
              <?php foreach ($atividades as $cod => $nome): ?>
                <label class="manual-chip">
                  <input type="checkbox" name="atividadesCodigos" value="<?= e($cod) ?>">
                  <span><strong><?= e($cod) ?></strong> <?= e($nome) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="manual-form-actions">
          <button type="submit" class="btn btn-accent btn-lg" id="btnBuscar">
            <i data-lucide="route"></i> Buscar mais próximas
          </button>
        </div>
      </form>

      <div id="manualResultados" class="manual-resultados" hidden>
        <div class="manual-resultados-head">
          <div>
            <h3>Credenciadas mais próximas</h3>
            <p class="muted" id="manualFonte"></p>
          </div>
          <span id="manualCount" class="count-pill">0</span>
        </div>
        <p id="manualVazio" class="manual-empty" hidden>Nenhuma credenciada apta com rota de carro para este endereço.</p>
        <div id="manualListaEmpresas" class="manual-empresas"></div>
        <div class="manual-form-actions">
          <button type="button" class="btn btn-accent btn-lg" id="btnConfirmar" disabled>
            <i data-lucide="check"></i> Confirmar escolha
          </button>
        </div>
        <p id="manualMsg" class="manual-msg" hidden></p>
      </div>

      <div id="manualSucesso" class="manual-sucesso" hidden>
        <div class="manual-sucesso-icon" aria-hidden="true"><i data-lucide="check-circle-2"></i></div>
        <div class="manual-sucesso-body">
          <h3>Escolha registrada</h3>
          <p id="manualSucessoTexto"></p>
          <div class="manual-sucesso-actions">
            <button type="button" class="btn btn-accent" id="btnEscolherMais">
              <i data-lucide="plus"></i> Escolher mais
            </button>
            <a class="btn btn-outline" id="manualLinkDetalhe" href="#">Ver detalhe</a>
            <a class="btn btn-ghost" href="<?= e($standbyUrl) ?>">Ir ao Standby</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <aside class="manual-side" aria-labelledby="manualSideTitle">
    <header class="manual-card-head compact">
      <div class="manual-card-icon tone-ink"><i data-lucide="calendar-days"></i></div>
      <div>
        <h2 id="manualSideTitle">Escolhas do dia</h2>
        <p>Histórico das seleções manuais.</p>
      </div>
    </header>

    <form class="manual-calendar" method="get" action="index.php">
      <input type="hidden" name="page" value="escolha-manual">
      <label for="diaFiltro">Filtrar por dia</label>
      <div class="manual-calendar-row">
        <input class="form-control" type="date" id="diaFiltro" name="dia" value="<?= e($diaFiltro) ?>">
        <button type="submit" class="btn btn-outline">Ver</button>
      </div>
    </form>

    <?php if ($manuais === []): ?>
      <div class="manual-day-empty">
        <i data-lucide="calendar-x"></i>
        <p>Nenhuma escolha em <strong><?= e(format_date($diaFiltro)) ?></strong>.</p>
      </div>
    <?php else: ?>
      <ul class="manual-day-list">
        <?php foreach ($manuais as $s): ?>
          <?php
            $emp = $s['empresaSelecionada'] ?? [];
            $dem = $s['demanda'] ?? [];
            $sid = (string) ($s['id'] ?? '');
            $status = (string) ($s['status'] ?? '');
          ?>
          <li class="manual-day-item">
            <div class="manual-day-item-top">
              <time datetime="<?= e($s['dataHora'] ?? '') ?>"><?= e(format_datetime($s['dataHora'] ?? null, 'H:i')) ?></time>
              <span class="badge <?= e(status_badge_class($status)) ?>"><?= e(status_sorteio_label($status)) ?></span>
            </div>
            <strong><?= e($emp['nome'] ?? '—') ?></strong>
            <span class="manual-day-meta">
              <?= e(($dem['cidade'] ?? '—') . ' · ' . ($dem['endereco'] ?? '')) ?>
              <?php if (isset($emp['distanciaKm'])): ?>
                · <?= e(format_km((float) $emp['distanciaKm'])) ?>
              <?php endif; ?>
            </span>
            <a href="<?= e(page_url('historico-detalhe', ['id' => $sid])) ?>">Ver <?= e($sid) ?> →</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</div>

<style>
.page-escolha-manual .manual-hero {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1.5rem 2.5rem;
  align-items: flex-end;
  margin: 0 0 2rem;
  padding: 1.35rem 1.5rem 1.5rem;
  border-radius: 22px;
  background:
    radial-gradient(520px 180px at 100% 0%, rgba(244, 63, 94, 0.12), transparent 60%),
    linear-gradient(180deg, #fff 0%, #fafbfd 100%);
  border: 1px solid var(--line, #eef2f7);
  box-shadow: 0 12px 28px -20px rgba(15, 23, 42, 0.18);
}
.manual-hero .eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #e11d48;
}
.manual-hero .eyebrow svg { width: 14px; height: 14px; }
.manual-hero h1 {
  margin: 0.4rem 0 0.55rem;
  font-family: var(--font-display, Outfit, sans-serif);
  font-size: clamp(1.85rem, 3.2vw, 2.45rem);
  font-weight: 650;
  letter-spacing: -0.035em;
  color: #0f172a;
  line-height: 1.05;
}
.manual-hero .lede {
  max-width: 40rem;
  margin: 0;
  color: #64748b;
  line-height: 1.55;
  font-size: 0.98rem;
}
.manual-steps {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  list-style: none;
  margin: 1rem 0 0;
  padding: 0;
}
.manual-steps li {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.75rem 0.35rem 0.35rem;
  border-radius: 999px;
  background: #fff;
  border: 1px solid var(--line, #eef2f7);
  font-size: 0.8rem;
  font-weight: 650;
  color: #475569;
}
.manual-steps li span {
  display: grid;
  place-items: center;
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #fb7185, #e11d48);
  color: #fff;
  font-size: 0.72rem;
}
.manual-hero-stats {
  display: flex;
  align-items: stretch;
  gap: 0.75rem;
  flex-wrap: wrap;
}
.manual-stat {
  display: grid;
  gap: 0.2rem;
  min-width: 8.5rem;
  padding: 0.9rem 1.1rem;
  border-radius: 16px;
  background: #fff;
  border: 1px solid var(--line, #eef2f7);
  box-shadow: 0 8px 20px -16px rgba(225, 29, 72, 0.45);
}
.manual-stat span {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  font-weight: 700;
}
.manual-stat strong {
  font-family: var(--font-display, Outfit, sans-serif);
  font-size: 1.85rem;
  line-height: 1;
  letter-spacing: -0.04em;
  color: #e11d48;
}
.manual-stat small { font-size: 0.78rem; color: #64748b; }
.manual-stat-link {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-width: 7.5rem;
  padding: 0.9rem 1.1rem;
  border-radius: 16px;
  background: #0f172a;
  color: #fff;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 650;
  box-shadow: 0 12px 24px -14px rgba(15, 23, 42, 0.55);
  transition: transform .15s, background .15s;
}
.manual-stat-link:hover {
  background: #1e293b;
  color: #fff;
  transform: translateY(-1px);
}
.manual-stat-link svg { width: 18px; height: 18px; opacity: 0.9; }

.manual-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.85fr);
  gap: 1.35rem 1.5rem;
  align-items: start;
  margin-bottom: 3rem;
}

.manual-workspace,
.manual-side {
  background: #fff !important;
  border: 1px solid var(--line, #eef2f7) !important;
  border-radius: 20px !important;
  box-shadow: 0 12px 28px -20px rgba(15, 23, 42, 0.2) !important;
  padding: 1.35rem 1.4rem 1.5rem !important;
}

.manual-card-head {
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--line, #eef2f7);
}
.manual-card-head.compact { margin-bottom: 1rem; padding-bottom: 0.85rem; }
.manual-card-head h2 {
  margin: 0 0 0.2rem;
  font-size: 1.15rem;
  font-weight: 650;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.manual-card-head p {
  margin: 0;
  font-size: 0.88rem;
  color: #64748b;
  line-height: 1.4;
}
.manual-card-icon {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 13px;
  background: linear-gradient(145deg, #fff1f2, #ffe4e6);
  color: #e11d48;
  flex-shrink: 0;
}
.manual-card-icon.tone-ink {
  background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
  color: #334155;
}
.manual-card-icon svg { width: 20px; height: 20px; }

.manual-form-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 5.5rem;
  gap: 0.95rem 1rem;
}
.manual-form-grid .man-full { grid-column: 1 / -1; }
.manual-form-grid .form-group { margin: 0; }
.manual-form-grid label,
.manual-form-grid .label-like {
  display: block;
  margin-bottom: 0.4rem;
  font-size: 0.84rem;
  font-weight: 650;
  color: #334155;
}
.manual-atividades {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.manual-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 0.8rem;
  border-radius: 999px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  font-size: 0.82rem;
  color: #334155;
  transition: background .15s, border-color .15s, box-shadow .15s, transform .15s;
}
.manual-chip:hover {
  border-color: rgba(225, 29, 72, 0.3);
  transform: translateY(-1px);
}
.manual-chip input { accent-color: #e11d48; }
.manual-chip:has(input:checked) {
  background: linear-gradient(180deg, #fff1f2, #fff);
  border-color: rgba(225, 29, 72, 0.45);
  color: #9f1239;
  box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1);
}
.manual-form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  justify-content: flex-end;
  margin-top: 1.25rem;
}

.manual-resultados {
  margin-top: 1.5rem;
  padding-top: 1.35rem;
  border-top: 1px dashed #e2e8f0;
  animation: manualFade .28s ease;
}
@keyframes manualFade {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: none; }
}
.manual-resultados-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.manual-resultados-head h3 {
  margin: 0 0 0.2rem;
  font-size: 1.05rem;
  font-weight: 650;
  color: #0f172a;
}
.manual-resultados-head .muted {
  margin: 0;
  font-size: 0.84rem;
}
.manual-empty {
  margin: 0;
  padding: 1.25rem;
  text-align: center;
  color: #64748b;
  background: #f8fafc;
  border-radius: 14px;
  border: 1px dashed #e2e8f0;
}
.manual-empresas {
  display: grid;
  gap: 0.6rem;
  max-height: min(54vh, 460px);
  overflow: auto;
  padding: 0.15rem 0.2rem 0.15rem 0;
}
.manual-emp {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 0.75rem 0.9rem;
  align-items: center;
  padding: 0.9rem 1rem;
  border-radius: 16px;
  background: #fff;
  border: 1px solid #eef2f7;
  cursor: pointer;
  transition: border-color .15s, background .15s, box-shadow .2s, transform .15s;
}
.manual-emp input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.manual-emp:hover {
  border-color: rgba(225, 29, 72, 0.28);
  box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.3);
  transform: translateY(-1px);
}
.manual-emp.is-selected {
  background: linear-gradient(180deg, #fff1f2, #fff);
  border-color: rgba(225, 29, 72, 0.5);
  box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.12), 0 12px 24px -18px rgba(225, 29, 72, 0.35);
}
.manual-emp-rank {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.8rem;
  font-weight: 750;
  color: #64748b;
  background: #f1f5f9;
}
.manual-emp.is-selected .manual-emp-rank {
  background: linear-gradient(135deg, #fb7185, #e11d48);
  color: #fff;
  box-shadow: 0 6px 14px -6px rgba(225, 29, 72, 0.7);
}
.manual-emp-body { min-width: 0; }
.manual-emp-body strong {
  display: block;
  font-size: 0.96rem;
  line-height: 1.3;
  color: #0f172a;
}
.manual-emp-city {
  display: block;
  font-size: 0.82rem;
  color: #64748b;
  margin-top: 0.2rem;
}
.manual-emp-flag {
  display: inline-flex;
  margin-top: 0.35rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: #fffbeb;
  color: #b45309;
  font-size: 0.7rem;
  font-weight: 700;
}
.manual-emp-km {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.manual-emp-km em {
  display: block;
  font-style: normal;
  font-weight: 750;
  font-size: 1rem;
  color: #0f172a;
  letter-spacing: -0.02em;
}
.manual-emp.is-selected .manual-emp-km em { color: #be123c; }
.manual-emp-km small {
  display: block;
  font-weight: 500;
  color: #94a3b8;
  font-size: 0.72rem;
  margin-top: 0.15rem;
}
.manual-msg { margin: 0.85rem 0 0; font-size: 0.9rem; }
.manual-msg.is-err { color: #be123c; }
.manual-msg.is-ok { color: #047857; }

.manual-sucesso {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  margin-top: 0.35rem;
  padding: 1.2rem 1.25rem;
  border-radius: 18px;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.14), rgba(16, 185, 129, 0.03));
  border: 1px solid rgba(16, 185, 129, 0.28);
  animation: manualFade .3s ease;
}
.manual-sucesso-icon {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: #ecfdf5;
  color: #059669;
  flex-shrink: 0;
}
.manual-sucesso-icon i { width: 1.55rem; height: 1.55rem; }
.manual-sucesso-body h3 { margin: 0 0 0.35rem; font-size: 1.08rem; color: #065f46; }
.manual-sucesso-body p { margin: 0 0 0.95rem; color: #334155; font-size: 0.92rem; line-height: 1.45; }
.manual-sucesso-actions { display: flex; flex-wrap: wrap; gap: 0.55rem; }

.manual-side { position: sticky; top: 1rem; }
.manual-calendar { margin-bottom: 1.1rem; }
.manual-calendar label {
  display: block;
  font-size: 0.8rem;
  font-weight: 650;
  margin-bottom: 0.4rem;
  color: #475569;
}
.manual-calendar-row { display: flex; gap: 0.5rem; }
.manual-calendar-row .form-control { flex: 1; min-width: 0; }
.manual-day-empty {
  text-align: center;
  padding: 1.75rem 0.9rem;
  color: #64748b;
  background: #f8fafc;
  border-radius: 14px;
  border: 1px dashed #e2e8f0;
}
.manual-day-empty i { width: 1.75rem; height: 1.75rem; margin-bottom: 0.55rem; opacity: 0.65; }
.manual-day-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.65rem; }
.manual-day-item {
  display: grid;
  gap: 0.28rem;
  padding: 0.9rem 0.95rem;
  border-radius: 14px;
  background: #fafbfd;
  border: 1px solid #eef2f7;
  transition: border-color .15s, box-shadow .15s;
}
.manual-day-item:hover {
  border-color: rgba(225, 29, 72, 0.25);
  box-shadow: 0 8px 18px -16px rgba(15, 23, 42, 0.25);
}
.manual-day-item-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}
.manual-day-item-top time {
  font-size: 0.78rem;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
}
.manual-day-item strong { font-size: 0.92rem; line-height: 1.3; color: #0f172a; }
.manual-day-meta {
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.manual-day-item a {
  font-size: 0.8rem;
  margin-top: 0.25rem;
  color: #e11d48;
  font-weight: 650;
}

@media (max-width: 980px) {
  .manual-layout { grid-template-columns: 1fr; }
  .manual-side { position: static; }
}
@media (max-width: 640px) {
  .page-escolha-manual .manual-hero { padding: 1.1rem 1.05rem 1.2rem; }
  .manual-form-grid { grid-template-columns: 1fr 4.5rem; }
  .manual-emp { grid-template-columns: auto minmax(0, 1fr); }
  .manual-emp-km { grid-column: 2; text-align: left; }
  .manual-workspace, .manual-side { padding: 1.05rem !important; }
}
</style>

<script>
(function () {
  const form = document.getElementById('formManual');
  const resultados = document.getElementById('manualResultados');
  const lista = document.getElementById('manualListaEmpresas');
  const btnBuscar = document.getElementById('btnBuscar');
  const btnConfirmar = document.getElementById('btnConfirmar');
  const btnMais = document.getElementById('btnEscolherMais');
  const sucesso = document.getElementById('manualSucesso');
  const msg = document.getElementById('manualMsg');
  const vazio = document.getElementById('manualVazio');
  const fonte = document.getElementById('manualFonte');
  const countPill = document.getElementById('manualCount');
  const workspace = document.getElementById('manualWorkspace');

  let payloadBase = null;
  let selecionada = null;
  let ultimoRegistro = null;

  function icons(root) {
    if (window.lucide?.createIcons) window.lucide.createIcons(root ? { nodes: [root] } : undefined);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }
  function fmtKm(n) {
    return n == null ? '—' : Number(n).toFixed(1).replace('.', ',') + ' km';
  }
  function showMsg(text, ok) {
    msg.textContent = text || '';
    msg.className = 'manual-msg ' + (ok ? 'is-ok' : 'is-err');
    msg.hidden = !text;
  }
  function resetFormulario() {
    sucesso.hidden = true;
    resultados.hidden = true;
    lista.innerHTML = '';
    selecionada = null;
    payloadBase = null;
    btnConfirmar.disabled = true;
    showMsg('', true);
    form.hidden = false;
    form.reset();
    document.getElementById('manUf').value = 'MG';
    form.querySelectorAll('input, select, button').forEach((el) => { el.disabled = false; });
  }
  function prependDia(s) {
    const diaAtual = document.getElementById('diaFiltro')?.value || '';
    const diaReg = String(s.dataHora || '').slice(0, 10);
    if (diaAtual && diaReg && diaAtual !== diaReg) return;

    document.querySelector('.manual-day-empty')?.remove();
    let ul = document.querySelector('.manual-day-list');
    if (!ul) {
      ul = document.createElement('ul');
      ul.className = 'manual-day-list';
      document.querySelector('.manual-side')?.appendChild(ul);
    }
    const emp = s.empresaSelecionada || {};
    const dem = s.demanda || {};
    const hora = String(s.dataHora || '').slice(11, 16);
    const li = document.createElement('li');
    li.className = 'manual-day-item';
    li.innerHTML = `
      <div class="manual-day-item-top">
        <time>${esc(hora)}</time>
        <span class="badge badge-warning">Aguardando aceite</span>
      </div>
      <strong>${esc(emp.nome || '—')}</strong>
      <span class="manual-day-meta">${esc((dem.cidade || '—') + ' · ' + (dem.endereco || ''))}${emp.distanciaKm != null ? ' · ' + fmtKm(emp.distanciaKm) : ''}</span>
      <a href="index.php?page=historico-detalhe&id=${encodeURIComponent(s.id || '')}">Ver ${esc(s.id || '')} →</a>`;
    ul.prepend(li);
    const stat = document.querySelector('.manual-stat strong');
    if (stat) stat.textContent = String((parseInt(stat.textContent, 10) || 0) + 1);
  }

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    showMsg('', true);
    btnConfirmar.disabled = true;
    selecionada = null;
    const atividades = [...form.querySelectorAll('input[name="atividadesCodigos"]:checked')].map((i) => i.value);
    if (!atividades.length) {
      showMsg('Selecione ao menos uma atividade.', false);
      resultados.hidden = false;
      return;
    }
    payloadBase = {
      cidade: form.cidade.value.trim(),
      uf: form.uf.value.trim().toUpperCase() || 'MG',
      endereco: form.endereco.value.trim(),
      regiaoCodigo: form.regiaoCodigo.value,
      atividadesCodigos: atividades,
      modalidadeSolicitada: 'manual',
      preview: true,
    };
    btnBuscar.disabled = true;
    try {
      const res = await fetch('api/calcular-elegiveis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payloadBase),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Falha ao calcular');

      const elegiveis = (data.data?.elegiveis || []).filter(
        (e) => e.distanciaKm != null && ['google', 'osm'].includes(e.distanciaFonte)
      );
      elegiveis.sort((a, b) => (a.distanciaKm - b.distanciaKm) || ((a.ordemCredenciamento || 0) - (b.ordemCredenciamento || 0)));

      fonte.textContent = data.data?.distancia?.rotulo || data.impacto?.fonteDistancia || 'Rota de carro';
      countPill.textContent = String(elegiveis.length);
      lista.innerHTML = '';
      vazio.hidden = elegiveis.length > 0;

      elegiveis.forEach((e, i) => {
        const label = document.createElement('label');
        label.className = 'manual-emp';
        label.innerHTML = `
          <input type="radio" name="empresaManual" value="${esc(e.empresaId)}">
          <span class="manual-emp-rank">${i + 1}</span>
          <span class="manual-emp-body">
            <strong>${esc(e.nome)}</strong>
            <span class="manual-emp-city">${esc(e.cidade || '')}</span>
            ${e.emRodizio ? `<span class="manual-emp-flag">${e.rodizioPorMarcacao ? 'Rodízio (cadastro)' : 'No rodízio'} · pode escolher de novo${e.servicoRealizadoObs ? ' — ' + esc(e.servicoRealizadoObs) : ''}</span>` : ''}
          </span>
          <span class="manual-emp-km">
            <em>${fmtKm(e.distanciaKm)}</em>
            <small>${e.duracaoMin ? '~' + e.duracaoMin + ' min' : 'rota de carro'}${e.idaVoltaKm != null ? ' · ida/volta ' + fmtKm(e.idaVoltaKm) : ''}</small>
          </span>`;
        label.querySelector('input').addEventListener('change', () => {
          selecionada = e.empresaId;
          btnConfirmar.disabled = false;
          lista.querySelectorAll('.manual-emp').forEach((n) => n.classList.remove('is-selected'));
          label.classList.add('is-selected');
        });
        lista.appendChild(label);
      });

      resultados.hidden = false;
      resultados.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (err) {
      showMsg(err.message || 'Erro ao buscar', false);
      resultados.hidden = false;
    } finally {
      btnBuscar.disabled = false;
      icons(workspace);
    }
  });

  btnConfirmar.addEventListener('click', async () => {
    if (!selecionada || !payloadBase) return;
    btnConfirmar.disabled = true;
    showMsg('', true);
    try {
      const res = await fetch('api/registrar-escolha-manual.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ ...payloadBase, preview: false, empresaId: selecionada }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Falha ao registrar');

      ultimoRegistro = data.data;
      prependDia(data.data);
      form.hidden = true;
      resultados.hidden = true;
      sucesso.hidden = false;
      document.getElementById('manualSucessoTexto').textContent =
        (data.message || 'Registrado.') + ' Protocolo ' + (data.data?.id || '') + '.';
      document.getElementById('manualLinkDetalhe').href =
        'index.php?page=historico-detalhe&id=' + encodeURIComponent(data.data?.id || '');
      icons(sucesso);
      sucesso.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (err) {
      showMsg(err.message || 'Erro', false);
      btnConfirmar.disabled = false;
    }
  });

  btnMais.addEventListener('click', () => {
    ultimoRegistro = null;
    resetFormulario();
    document.getElementById('manCidade').focus();
    window.scrollTo({ top: Math.max(0, workspace.offsetTop - 24), behavior: 'smooth' });
  });

  document.getElementById('diaFiltro')?.addEventListener('change', (ev) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', 'escolha-manual');
    url.searchParams.set('dia', ev.target.value);
    window.location.href = url.toString();
  });

  icons();
})();
</script>
