<?php

$companyService = new CompanyService();
$id = (string) ($_GET['id'] ?? '');
$empresa = $id !== '' ? $companyService->findById($id) : null;

if (!$empresa): ?>
  <div class="alert alert-warning">Empresa não encontrada.</div>
  <a class="btn btn-outline" href="<?= e(page_url('empresas')) ?>">Voltar</a>
<?php return; endif;

$hash = integrity_hash_for(json_encode($empresa->toArray(), JSON_UNESCAPED_UNICODE));

$historico = (new DrawService())->historicoEmpresa($empresa->id);
$resumo = $historico['resumo'];
$itens = $historico['itens'];

$aceitos = array_values(array_filter($itens, static fn(array $i): bool => $i['papel'] === 'aceito'));
$recusados = array_values(array_filter($itens, static fn(array $i): bool => $i['papel'] === 'recusado'));
$pendentes = array_values(array_filter($itens, static fn(array $i): bool => $i['papel'] === 'pendente'));
$demais = array_values(array_filter($itens, static fn(array $i): bool => !in_array($i['papel'], ['aceito', 'recusado', 'pendente'], true)));

$papelMeta = static function (string $papel): array {
    return match ($papel) {
        'aceito' => ['label' => 'Aceitou o serviço', 'tone' => 'success', 'icon' => 'check-circle-2'],
        'recusado' => ['label' => 'Recusou o serviço', 'tone' => 'danger', 'icon' => 'x-circle'],
        'pendente' => ['label' => 'Aguardando aceite', 'tone' => 'warning', 'icon' => 'hourglass'],
        'selecionada' => ['label' => 'Selecionada', 'tone' => 'success', 'icon' => 'trophy'],
        'orcamento' => ['label' => 'Convidada a orçar', 'tone' => 'info', 'icon' => 'receipt'],
        default => ['label' => 'Participou do sorteio', 'tone' => 'neutral', 'icon' => 'users'],
    };
};
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
        <?php if ($empresa->criadoEm): ?>
          · Cadastrada no sistema em <?= e(format_datetime($empresa->criadoEm)) ?>
        <?php endif; ?>
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
    <div class="kpi-label">Participações</div>
    <div class="kpi-value"><?= (int) $resumo['participacoes'] ?></div>
    <div class="kpi-hint">Sorteios em que entrou</div>
  </article>
  <article class="kpi-card kpi-green">
    <div class="kpi-label">Aceitos</div>
    <div class="kpi-value"><?= (int) $resumo['aceitos'] ?></div>
    <div class="kpi-hint">Serviços confirmados</div>
  </article>
  <article class="kpi-card kpi-red">
    <div class="kpi-label">Recusas</div>
    <div class="kpi-value"><?= (int) $resumo['recusados'] ?></div>
    <div class="kpi-hint">Com ou sem motivo</div>
  </article>
  <article class="kpi-card kpi-last">
    <div class="kpi-label">Pendentes</div>
    <div class="kpi-value"><?= (int) $resumo['pendentes'] ?></div>
    <div class="kpi-hint">Aguardando Standby</div>
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
        <div class="detail-item">
          <div class="lbl">Cadastrada no sistema</div>
          <div class="val"><?= $empresa->criadoEm ? e(format_datetime($empresa->criadoEm)) : '—' ?></div>
        </div>
        <?php if ($empresa->atualizadoEm): ?>
          <div class="detail-item">
            <div class="lbl">Última atualização</div>
            <div class="val"><?= e(format_datetime($empresa->atualizadoEm)) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($empresa->observacoes): ?>
        <div class="alert alert-info" style="margin-top:1.25rem;margin-bottom:0;">
          <strong>Observações cadastrais:</strong> <?= e($empresa->observacoes) ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($empresa->servicoRealizado): ?>
  <section class="panel">
    <div class="panel-header">
      <h3><i data-lucide="repeat"></i> No rodízio (marcação manual)</h3>
      <span class="tag tag-rodizio">Fora dos sorteios automáticos</span>
    </div>
    <div class="panel-body">
      <p style="margin:0 0 0.65rem;color:var(--ink-soft);line-height:1.45;">
        <?= e($empresa->servicoRealizadoObs ?: 'Serviço já realizado — sem detalhe informado.') ?>
      </p>
      <div class="muted" style="font-size:0.85rem;">
        <?php if ($empresa->servicoRealizadoEm): ?>
          Marcada em <?= e(format_datetime($empresa->servicoRealizadoEm)) ?> ·
        <?php endif; ?>
        Continua disponível na <a href="<?= e(page_url('escolha-manual')) ?>">escolha manual</a>.
      </div>
    </div>
  </section>
  <?php endif; ?>

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
          <span class="tag tag-km-livre"><i data-lucide="route"></i> Atende acima de 200 km</span>
        <?php else: ?>
          <span class="tag tag-km-limite"><i data-lucide="route"></i> Somente até 200 km (100 ida + 100 volta)</span>
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

<?php
$renderCard = static function (array $item) use ($papelMeta): void {
    $meta = $papelMeta($item['papel']);
    $dem = $item['demanda'];
    $sid = $item['sorteioId'];
    ?>
    <article class="emp-hist-card tone-<?= e($meta['tone']) ?>">
      <header class="emp-hist-head">
        <div>
          <span class="badge badge-<?= e($meta['tone'] === 'info' ? 'neutral' : $meta['tone']) ?>">
            <i data-lucide="<?= e($meta['icon']) ?>"></i> <?= e($meta['label']) ?>
          </span>
          <span class="modalidade-pill modalidade-<?= e($item['modalidade']) ?>"><?= e(modalidade_label($item['modalidade'])) ?></span>
        </div>
        <time class="muted"><?= e(format_datetime($item['dataHora'] ?: null)) ?></time>
      </header>

      <div class="emp-hist-body">
        <strong class="mono"><?= e($sid) ?></strong>
        <p>
          <?= e(($dem['cidade'] ?: '—') . ($dem['uf'] ? '/' . $dem['uf'] : '')) ?>
          <?php if ($dem['endereco'] !== ''): ?> · <?= e($dem['endereco']) ?><?php endif; ?>
        </p>
        <?php if ($dem['servico'] !== ''): ?>
          <p class="muted">Serviço: <?= e($dem['servico']) ?></p>
        <?php endif; ?>
        <div class="emp-hist-meta">
          <?php if ($item['distanciaKm'] !== null): ?>
            <span><i data-lucide="route"></i> <?= e(format_km((float) $item['distanciaKm'])) ?></span>
          <?php endif; ?>
          <?php if ($item['valorOrcamento'] !== null && $item['valorOrcamento'] !== ''): ?>
            <span><i data-lucide="banknote"></i> <?= e(format_moeda((float) $item['valorOrcamento'])) ?></span>
          <?php endif; ?>
          <?php if ($item['usuarioNome'] !== ''): ?>
            <span><i data-lucide="user"></i> Operador: <?= e($item['usuarioNome']) ?></span>
          <?php endif; ?>
        </div>

        <?php if (in_array($item['papel'], ['aceito', 'recusado', 'pendente', 'selecionada'], true)): ?>
          <div class="emp-hist-aceite">
            <?php if (!empty($item['aceiteEm'])): ?>
              <div><strong>Decisão em:</strong> <?= e(format_datetime($item['aceiteEm'])) ?></div>
            <?php endif; ?>
            <?php if ($item['aceiteUsuario'] !== ''): ?>
              <div><strong>Registrado por:</strong> <?= e($item['aceiteUsuario']) ?></div>
            <?php endif; ?>
            <?php if ($item['aceiteObservacao'] !== ''): ?>
              <div class="emp-hist-obs">
                <strong><?= $item['papel'] === 'recusado' ? 'Motivo da recusa' : 'Observação' ?>:</strong>
                <?= e($item['aceiteObservacao']) ?>
              </div>
            <?php elseif ($item['papel'] === 'recusado'): ?>
              <div class="muted">Recusa sem motivo informado.</div>
            <?php elseif ($item['papel'] === 'aceito'): ?>
              <div class="muted">Aceite sem observação adicional.</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <footer class="emp-hist-actions">
        <a class="btn btn-outline btn-sm" href="<?= e(page_url('historico-detalhe', ['id' => $sid])) ?>">Ver sorteio</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(page_url('auditoria', ['sorteio' => $sid])) ?>">Auditoria</a>
        <?php if ($item['papel'] === 'pendente'): ?>
          <a class="btn btn-accent btn-sm" href="<?= e(page_url('standby')) ?>">Abrir Standby</a>
        <?php endif; ?>
      </footer>
    </article>
    <?php
};
?>

<section class="panel emp-hist-panel" style="margin-top:1.25rem;" id="historico-operacional">
  <div class="panel-header">
    <h3>Histórico operacional</h3>
    <span class="muted"><?= (int) $resumo['participacoes'] ?> registro(s) · <?= (int) $resumo['selecionada'] ?> vez(es) selecionada</span>
  </div>
  <div class="panel-body">
    <?php if ($itens === []): ?>
      <div class="empty-state">Esta empresa ainda não participou de nenhum sorteio, escolha manual ou orçamento.</div>
    <?php else: ?>
      <?php if ($pendentes !== []): ?>
        <h4 class="emp-hist-section-title"><i data-lucide="hourglass"></i> Aguardando aceite</h4>
        <div class="emp-hist-grid">
          <?php foreach ($pendentes as $item) { $renderCard($item); } ?>
        </div>
      <?php endif; ?>

      <?php if ($aceitos !== []): ?>
        <h4 class="emp-hist-section-title"><i data-lucide="check-circle-2"></i> Serviços aceitos</h4>
        <div class="emp-hist-grid">
          <?php foreach ($aceitos as $item) { $renderCard($item); } ?>
        </div>
      <?php endif; ?>

      <?php if ($recusados !== []): ?>
        <h4 class="emp-hist-section-title"><i data-lucide="x-circle"></i> Serviços recusados</h4>
        <div class="emp-hist-grid">
          <?php foreach ($recusados as $item) { $renderCard($item); } ?>
        </div>
      <?php endif; ?>

      <?php if ($demais !== []): ?>
        <h4 class="emp-hist-section-title"><i data-lucide="shuffle"></i> Demais participações</h4>
        <div class="emp-hist-grid">
          <?php foreach ($demais as $item) { $renderCard($item); } ?>
        </div>
      <?php endif; ?>
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
