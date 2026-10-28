<?php
/** @var array $embeddedResultado */
/** @var string $embeddedSorteioId */
$sid = $embeddedSorteioId;
$registro = $sid !== '' ? (new DrawService())->findById($sid) : null;
$r = $embeddedResultado;
if (is_array($registro)) {
    foreach (['modalidade', 'modalidadeMotivo', 'sorteadas', 'orcamentos', 'ranking', 'rodizioNota', 'status', 'empresaSelecionada', 'hashIntegridade', 'orcamentoEmpate', 'orcamentoObservacao', 'aceiteStatus'] as $k) {
        if (array_key_exists($k, $registro)) {
            $r[$k] = $registro[$k];
        }
    }
}
$empresa = $r['empresaSelecionada'] ?? [];
$demanda = $r['demanda'] ?? [];
$modalidade = (string) ($r['modalidade'] ?? '');
$aguardandoOrcamento = ($r['status'] ?? '') === 'aguardando_orcamento';
$aguardandoAceite = ($r['status'] ?? '') === 'aguardando_aceite';
$recusado = ($r['status'] ?? '') === 'recusado';

// Contatos atualizados das sorteadas (para pedir orçamento)
$contatos = [];
if (!empty($r['sorteadas'])) {
    $cs = new CompanyService();
    foreach ($r['sorteadas'] as $s) {
        $emp = $cs->findById((string) ($s['id'] ?? ''));
        $contatos[$s['id']] = [
            'telefone' => $emp?->telefone ?? ($s['telefone'] ?? null),
            'email' => $emp?->email ?? ($s['email'] ?? null),
            'responsavel' => $emp?->responsavel,
        ];
    }
}
?>

<div class="result-card" id="resultadoCard">
  <div class="result-card-header">
    <div class="eyebrow"><?= e(app_config('org')) ?></div>
    <h2><?= $modalidade === 'orcamento' ? 'SORTEIO PARA ORÇAMENTO' : ($modalidade === 'proxima' ? 'CREDENCIADA MAIS PRÓXIMA' : 'RESULTADO DO SORTEIO') ?></h2>
  </div>
  <div class="result-card-body">
    <?php if ($modalidade !== ''): ?>
      <div class="result-row">
        <span class="lbl">Modalidade</span>
        <span class="val">
          <span class="modalidade-pill modalidade-<?= e($modalidade) ?>"><?= e(modalidade_label($modalidade)) ?></span>
          <?php if (!empty($r['modalidadeMotivo'])): ?>
            <small class="result-note"><?= e($r['modalidadeMotivo']) ?></small>
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <?php if ($aguardandoOrcamento): ?>
      <div class="result-row">
        <span class="lbl">Situação</span>
        <span class="val"><span class="badge badge-warning">Aguardando orçamentos</span></span>
      </div>
    <?php elseif ($aguardandoAceite): ?>
      <div class="result-row">
        <span class="lbl">Situação</span>
        <span class="val">
          <span class="badge badge-warning">Aguardando aceite</span>
          <small class="result-note">Confirme na aba <a href="<?= e(page_url('standby')) ?>">Standby</a> se a empresa aceitou ou recusou. Só no aceite ela entra no rodízio.</small>
        </span>
      </div>
    <?php elseif ($recusado): ?>
      <div class="result-row">
        <span class="lbl">Situação</span>
        <span class="val"><span class="badge badge-danger">Recusado pela empresa</span></span>
      </div>
    <?php endif; ?>

    <?php if (!$aguardandoOrcamento): ?>
      <div class="result-row">
        <span class="lbl"><?= $modalidade === 'orcamento' ? 'Vencedora (menor orçamento)' : 'Empresa selecionada' ?></span>
        <span class="val">
          <?= e($empresa['nome'] ?? '—') ?>
          <?php if (isset($empresa['valor'])): ?> — <strong><?= e(format_moeda((float) $empresa['valor'])) ?></strong><?php endif; ?>
        </span>
      </div>
      <div class="result-row">
        <span class="lbl">CNPJ</span>
        <span class="val mono"><?= e(($empresa['cnpj'] ?? '') ?: '—') ?></span>
      </div>
      <?php if (isset($empresa['distanciaKm'])): ?>
        <div class="result-row">
          <span class="lbl">Rota de carro até a demanda</span>
          <span class="val">
            <?= e(format_km((float) $empresa['distanciaKm'])) ?>
            <?php if (!empty($empresa['duracaoMin'])): ?> · ~<?= (int) $empresa['duracaoMin'] ?> min<?php endif; ?>
            · ida e volta <?= e(format_km((float) $empresa['distanciaKm'] * 2)) ?>
          </span>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="result-row">
      <span class="lbl">Local da demanda</span>
      <span class="val"><?= e(trim(($demanda['endereco'] ?? '') . ' — ' . ($demanda['cidade'] ?? '') . (!empty($demanda['uf']) ? '/' . $demanda['uf'] : ''), ' —')) ?></span>
    </div>
    <?php if (!empty($demanda['regiaoCodigo'])): ?>
      <div class="result-row">
        <span class="lbl">Região da demanda</span>
        <span class="val"><?= e($demanda['regiaoCodigo'] . ' - ' . ($demanda['regiaoNome'] ?? '')) ?></span>
      </div>
    <?php endif; ?>
    <div class="result-row">
      <span class="lbl">Atividade(s)</span>
      <span class="val"><?= e($demanda['servicoNecessario'] ?? '—') ?></span>
    </div>
    <div class="result-row">
      <span class="lbl">Data e horário</span>
      <span class="val"><?= e(format_datetime($r['dataHora'] ?? null)) ?></span>
    </div>
    <div class="result-row">
      <span class="lbl">ID do sorteio</span>
      <span class="val mono"><?= e($sid) ?></span>
    </div>

    <?php if (!empty($r['rodizioNota'])): ?>
      <div class="result-row">
        <span class="lbl">Rodízio</span>
        <span class="val"><small class="result-note"><?= e($r['rodizioNota']) ?></small></span>
      </div>
    <?php endif; ?>

    <?php if ($modalidade === 'proxima' && !empty($r['ranking'])): ?>
      <div class="result-row result-row-stack">
        <span class="lbl">Comparação de distâncias (rota de carro)</span>
        <ol class="ranking-list">
          <?php foreach ($r['ranking'] as $pos): ?>
            <li class="<?= ($pos['id'] ?? '') === ($empresa['id'] ?? '') ? 'is-winner' : '' ?>">
              <span><?= e($pos['nome']) ?> <small class="muted"><?= e($pos['cidade'] ?? '') ?></small></span>
              <strong><?= e(format_km(isset($pos['distanciaKm']) ? (float) $pos['distanciaKm'] : null)) ?></strong>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endif; ?>

    <?php if ($modalidade === 'orcamento' && !empty($r['sorteadas'])): ?>
      <div class="result-row result-row-stack">
        <span class="lbl"><?= $aguardandoOrcamento ? 'Credenciadas sorteadas — peça o orçamento a cada uma' : 'Orçamentos recebidos' ?></span>

        <?php if ($aguardandoOrcamento): ?>
          <form id="formOrcamentos" class="orcamento-form" data-sorteio-id="<?= e($sid) ?>">
            <?php foreach ($r['sorteadas'] as $i => $s): ?>
              <?php $ct = $contatos[$s['id']] ?? []; ?>
              <div class="orcamento-item">
                <div class="orcamento-num"><?= $i + 1 ?></div>
                <div class="orcamento-info">
                  <strong><?= e($s['nome']) ?></strong>
                  <span class="muted"><?= e($s['cidade'] ?? '') ?><?= isset($s['distanciaKm']) ? ' · ' . e(format_km((float) $s['distanciaKm'])) . ' de carro' : '' ?></span>
                  <span class="orcamento-contato">
                    <?php if (!empty($ct['telefone'])): ?><span><i data-lucide="phone"></i> <?= e($ct['telefone']) ?></span><?php endif; ?>
                    <?php if (!empty($ct['email'])): ?><a href="mailto:<?= e($ct['email']) ?>"><i data-lucide="mail"></i> <?= e($ct['email']) ?></a><?php endif; ?>
                  </span>
                </div>
                <label class="orcamento-valor">
                  <span>Valor do orçamento (R$)</span>
                  <input class="form-control" type="text" inputmode="decimal" name="valor[<?= e($s['id']) ?>]"
                    data-empresa-id="<?= e($s['id']) ?>" placeholder="Ex.: 3.500,00">
                  <small class="muted">Deixe em branco se não enviou</small>
                </label>
              </div>
            <?php endforeach; ?>
            <div class="form-group" style="margin-top:0.75rem;">
              <label for="orcamentoObs">Observação (opcional)</label>
              <input class="form-control" type="text" id="orcamentoObs" placeholder="Ex.: pedidos enviados por e-mail em 28/09; prazo de 3 dias">
            </div>
            <p id="orcamentoErro" class="alert alert-warning" style="display:none;margin-top:0.75rem;"></p>
            <button type="submit" class="btn btn-accent" style="margin-top:0.75rem;">
              <i data-lucide="badge-check"></i> Registrar orçamentos e definir a mais barata
            </button>
          </form>
        <?php else: ?>
          <table class="orcamento-tabela">
            <thead><tr><th>Credenciada</th><th>Rota</th><th>Orçamento</th></tr></thead>
            <tbody>
              <?php foreach ($r['orcamentos'] ?? [] as $o): ?>
                <tr class="<?= ($o['id'] ?? '') === ($empresa['id'] ?? '') ? 'is-winner' : '' ?>">
                  <td><?= e($o['nome']) ?></td>
                  <td><?= e(format_km(isset($o['distanciaKm']) ? (float) $o['distanciaKm'] : null)) ?></td>
                  <td><?= $o['valor'] !== null ? e(format_moeda((float) $o['valor'])) : '<span class="muted">não enviou</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php if (!empty($r['orcamentoEmpate'])): ?>
            <small class="result-note">Empate no menor valor desfeito por sorteio (CSPRNG).</small>
          <?php endif; ?>
          <?php if (!empty($r['orcamentoObservacao'])): ?>
            <small class="result-note">Obs.: <?= e($r['orcamentoObservacao']) ?></small>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="result-row result-row-stack">
      <span class="lbl"><?= $modalidade === 'proxima' ? 'Credenciadas aptas comparadas' : 'Empresas participantes' ?></span>
      <div class="participantes-lista">
        <?php foreach ($r['participantes'] ?? [] as $p): ?>
          <div class="participante-item"><?= e($p['nome'] ?? '') ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="result-row result-row-stack">
      <span class="lbl">Critérios utilizados</span>
      <div class="participantes-lista">
        <?php foreach ($r['criteriosUtilizados'] ?? [] as $c): ?>
          <div class="participante-item"><?= e($c) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (!empty($r['hashIntegridade'])): ?>
      <div class="result-row">
        <span class="lbl">Hash de integridade</span>
        <span class="val mono" style="font-size:0.75rem;word-break:break-all;"><?= e($r['hashIntegridade']) ?></span>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="actions-row" style="justify-content:center;margin-top:1.5rem;">
  <button type="button" class="btn btn-primary" onclick="window.print()">
    <i data-lucide="download"></i> Baixar resultado
  </button>
  <button type="button" class="btn btn-outline" id="btnCompartilhar"
    data-share-title="Resultado do Sorteio <?= e($sid) ?>"
    data-share-text="<?= $aguardandoOrcamento
      ? 'Sorteadas para orçamento: ' . e(implode(', ', array_column($r['sorteadas'] ?? [], 'nome')))
      : 'Empresa selecionada: ' . e($empresa['nome'] ?? '') ?> — ID <?= e($sid) ?>">
    <i data-lucide="share-2"></i> Compartilhar resultado
  </button>
  <a class="btn btn-outline" href="<?= e(page_url('auditoria', ['sorteio' => $sid])) ?>">
    <i data-lucide="shield-check"></i> Visualizar auditoria
  </a>
  <a class="btn btn-ghost" href="<?= e(page_url('historico-detalhe', ['id' => $sid])) ?>">
    Ver no histórico
  </a>
</div>
