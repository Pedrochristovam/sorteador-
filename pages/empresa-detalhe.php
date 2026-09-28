<?php

$companyService = new CompanyService();
$id = (string) ($_GET['id'] ?? '');
$empresa = $id !== '' ? $companyService->findById($id) : null;

if (!$empresa): ?>
  <div class="alert alert-warning">Empresa não encontrada.</div>
  <a class="btn btn-outline" href="<?= e(page_url('empresas')) ?>">Voltar</a>
<?php return; endif;

$hash = integrity_hash_for(json_encode($empresa->toArray(), JSON_UNESCAPED_UNICODE));

$auditService = new AuditService();
$participacoes = $auditService->listByEmpresaId($empresa->id);
?>

<?php if (!empty($_GET['saved'])): ?>
  <div class="alert alert-success">
    Empresa salva com sucesso.
    <?php if (!$empresa->isAtiva()): ?>
      Ela está <strong>desativada</strong> e será eliminada automaticamente nos próximos sorteios (motivo: desativação cadastral).
    <?php endif; ?>
  </div>
<?php endif; ?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <a href="<?= e(page_url('empresas')) ?>">Empresas Credenciadas</a>
  <span>/</span>
  <span>Ficha da empresa</span>
</nav>

<div class="empresas-hero<?= $empresa->isAtiva() ? '' : ' empresas-hero-inactive' ?>">
  <div class="company-hero-identity">
    <div class="company-avatar xl <?= $empresa->isAtiva() ? 'tone-' . e(avatar_tone($empresa->id)) : 'tone-inactive' ?>">
      <?= e(iniciais_empresa($empresa->nome)) ?>
    </div>
    <div>
      <div class="dash-title-row">
        <h1><?= e($empresa->nome) ?></h1>
        <span class="status-pill <?= $empresa->isAtiva() ? 'success' : 'danger' ?>">
          <span class="dot"></span> <?= $empresa->isAtiva() ? 'Ativa' : 'Desativada' ?>
        </span>
      </div>
      <p class="subtitle">
        Contrato #<?= e($empresa->numeroContrato ?: '—') ?>
        · <?= e($empresa->cidade) ?>/<?= e($empresa->uf) ?>
        · Credenciada em <?= e(format_date($empresa->dataConvenio)) ?>
        <?php if (!$empresa->isAtiva()): ?>
          · <strong class="text-danger">Fora dos sorteios</strong>
        <?php endif; ?>
      </p>
    </div>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url('empresas')) ?>">
      <i data-lucide="arrow-left"></i> Voltar
    </a>
    <a class="btn btn-accent" href="<?= e(page_url('empresa-form', ['id' => $empresa->id])) ?>">
      <i data-lucide="pencil"></i> Editar / <?= $empresa->isAtiva() ? 'Desativar' : 'Reativar' ?>
    </a>
    <form method="post" action="api/excluir-empresa.php" class="inline-form"
      data-confirm="Excluir definitivamente &quot;<?= e($empresa->nome) ?>&quot;? Esta ação não pode ser desfeita. O histórico de sorteios já realizados é preservado.">
      <input type="hidden" name="id" value="<?= e($empresa->id) ?>">
      <button type="submit" class="btn btn-danger-soft">
        <i data-lucide="trash-2"></i> Excluir
      </button>
    </form>
  </div>
</div>

<div class="kpi-grid kpi-grid-empresas">
  <article class="kpi-card kpi-blue">
    <div class="kpi-label">Contrato</div>
    <div class="kpi-value" style="font-size:1.45rem;">#<?= e($empresa->numeroContrato ?: '—') ?></div>
    <div class="kpi-hint">Ordem <?= (int) $empresa->ordemCredenciamento ?: '—' ?></div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Atividades</div>
    <div class="kpi-value"><?= count($empresa->servicos) ?></div>
    <div class="kpi-hint">Habilitadas no credenciamento</div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Abrangência</div>
    <div class="kpi-value"><?= count($empresa->regioesMunicipiosCodigos) + count($empresa->estadosAtuacaoCodigos) ?></div>
    <div class="kpi-hint">Regiões + estados</div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Status Cadastral</div>
    <div class="kpi-last-footer" style="margin-top:0.7rem;">
      <span class="chip-success"><i data-lucide="shield-check"></i> Homologada</span>
    </div>
    <div class="kpi-hint" style="margin-top:0.55rem;">ID <?= e($empresa->id) ?></div>
  </article>
</div>

<div class="detail-layout">
  <section class="panel">
    <div class="panel-header"><h3>Perfil da Empresa</h3></div>
    <div class="panel-body">
      <div class="detail-grid">
        <div class="detail-item">
          <div class="lbl">CNPJ</div>
          <div class="val mono"><?= e($empresa->cnpj ?: '—') ?></div>
        </div>
        <div class="detail-item">
          <div class="lbl">Responsável</div>
          <div class="val"><?= e($empresa->responsavel ?? '—') ?></div>
        </div>
        <div class="detail-item">
          <div class="lbl">E-mail</div>
          <div class="val"><?= e($empresa->email ?? '—') ?></div>
        </div>
        <div class="detail-item">
          <div class="lbl">Telefone</div>
          <div class="val"><?= e($empresa->telefone ?? '—') ?></div>
        </div>
        <div class="detail-item" style="grid-column:1 / -1;">
          <div class="lbl">Endereço</div>
          <div class="val">
            <?= e($empresa->endereco) ?>
            <?= $empresa->bairro ? ' — ' . e($empresa->bairro) : '' ?>
            <?= $empresa->cep ? ' · CEP ' . e($empresa->cep) : '' ?>
          </div>
        </div>
        <div class="detail-item">
          <div class="lbl">Cidade / UF</div>
          <div class="val"><?= e($empresa->cidade) ?>/<?= e($empresa->uf) ?></div>
        </div>
        <div class="detail-item">
          <div class="lbl">Data do credenciamento</div>
          <div class="val"><?= e(format_date($empresa->dataConvenio)) ?></div>
        </div>
      </div>

      <?php if ($empresa->observacoes): ?>
        <div class="alert alert-info" style="margin-top:1.25rem;margin-bottom:0;">
          <strong>Observações:</strong> <?= e($empresa->observacoes) ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>Operação &amp; Abrangência</h3></div>
    <div class="panel-body">
      <div class="lbl" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);font-weight:650;">Atividades habilitadas</div>
      <div class="tags-wrap" style="margin-top:0.5rem;margin-bottom:1.2rem;">
        <?php foreach ($empresa->servicos as $s): ?>
          <span class="tag"><?= e($s) ?></span>
        <?php endforeach; ?>
        <?php if ($empresa->atividadesCodigos): ?>
          <span class="muted" style="font-size:0.8rem;">(<?= e(implode(', ', $empresa->atividadesCodigos)) ?>)</span>
        <?php endif; ?>
      </div>

      <div class="lbl" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);font-weight:650;">Distância de atendimento</div>
      <div class="tags-wrap" style="margin-top:0.5rem;margin-bottom:1.2rem;">
        <?php if ($empresa->atendeAcima100km): ?>
          <span class="tag tag-km-livre"><i data-lucide="route"></i> Atende acima de 100 km</span>
        <?php else: ?>
          <span class="tag tag-km-limite"><i data-lucide="route"></i> Somente até 100 km (50 ida + 50 volta)</span>
        <?php endif; ?>
      </div>

      <?php if ($empresa->regioesMunicipios): ?>
        <div class="lbl" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);font-weight:650;">Regiões / municípios (MG)</div>
        <div class="tags-wrap" style="margin-top:0.5rem;margin-bottom:1.2rem;">
          <?php foreach ($empresa->regioesMunicipios as $r): ?>
            <span class="tag"><?= e($r) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($empresa->estadosAtuacao): ?>
        <div class="lbl" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);font-weight:650;">Estados de atuação</div>
        <div class="tags-wrap" style="margin-top:0.5rem;">
          <?php foreach ($empresa->estadosAtuacao as $r): ?>
            <span class="tag"><?= e($r) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="panel" style="margin-top:1.25rem;">
  <div class="panel-header">
    <h3>Participação em sorteios &amp; auditoria</h3>
    <a class="btn btn-ghost btn-sm" href="<?= e(page_url('auditoria')) ?>">Abrir painel de auditoria</a>
  </div>
  <div class="panel-body">
    <?php if ($participacoes === []): ?>
      <div class="empty-state">Esta empresa ainda não consta em operações auditadas.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table data-table-premium">
          <thead>
            <tr>
              <th>Sorteio</th>
              <th>Data</th>
              <th>Papel</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($participacoes as $log): ?>
              <?php
                $sid = (string) ($log['sorteioId'] ?? '');
                $papel = 'Participante';
                if (($log['resultadoFinal']['id'] ?? '') === $empresa->id) {
                    $papel = 'Contemplada';
                } else {
                    foreach ($log['empresasEliminadas'] ?? [] as $elim) {
                        if (($elim['id'] ?? $elim['empresaId'] ?? '') === $empresa->id) {
                            $papel = 'Eliminada';
                            break;
                        }
                    }
                }
              ?>
              <tr>
                <td class="mono"><?= e($sid) ?></td>
                <td><?= e(format_datetime($log['dataHora'] ?? null)) ?></td>
                <td>
                  <span class="status-pill <?= $papel === 'Contemplada' ? 'success' : ($papel === 'Eliminada' ? 'danger' : '') ?>">
                    <?= e($papel) ?>
                  </span>
                </td>
                <td>
                  <div class="row-actions">
                    <a class="btn btn-outline btn-sm" href="<?= e(page_url('auditoria', ['sorteio' => $sid])) ?>">Auditoria</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(page_url('historico-detalhe', ['id' => $sid])) ?>">Histórico</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="integrity-bar">
  <div>
    <strong>Protocolo de Integridade do Registro</strong>
    <div class="mono integrity-hash"><?= e($hash) ?></div>
  </div>
  <a href="<?= e(page_url('auditoria')) ?>">Validar Assinatura Digital ICP-Brasil →</a>
</section>
