<?php

$companyService = new CompanyService();
$catalogo = $companyService->getCatalogo();
$id = trim((string) ($_GET['id'] ?? ''));
$empresa = $id !== '' ? $companyService->findById($id) : null;
$isEdit = $empresa !== null;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'nome' => $_POST['nome'] ?? '',
        'cnpj' => $_POST['cnpj'] ?? '',
        'cidade' => $_POST['cidade'] ?? '',
        'uf' => $_POST['uf'] ?? 'MG',
        'endereco' => $_POST['endereco'] ?? '',
        'bairro' => $_POST['bairro'] ?? '',
        'cep' => $_POST['cep'] ?? '',
        'status' => $_POST['status'] ?? 'ativa',
        'dataConvenio' => $_POST['dataConvenio'] ?? date('Y-m-d'),
        'numeroContrato' => $_POST['numeroContrato'] ?? '',
        'ordemCredenciamento' => $_POST['ordemCredenciamento'] ?? 0,
        'telefone' => $_POST['telefone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'responsavel' => $_POST['responsavel'] ?? '',
        'observacoes' => $_POST['observacoes'] ?? '',
        'atividadesCodigos' => $_POST['atividadesCodigos'] ?? [],
        'regioesMunicipiosCodigos' => $_POST['regioesMunicipiosCodigos'] ?? [],
        'estadosAtuacaoCodigos' => $_POST['estadosAtuacaoCodigos'] ?? [],
        'atendeAcima100km' => ($_POST['atendeAcima100km'] ?? '1') === '1',
    ];

    try {
        $saved = $companyService->save($input, $isEdit ? $id : null);
        redirect('empresa-detalhe', ['id' => $saved->id, 'saved' => 1]);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $empresa = Empresa::fromArray(array_merge(
            $empresa?->toArray() ?? [
                'id' => '',
                'nome' => '',
                'cnpj' => '',
                'cidade' => '',
                'uf' => 'MG',
                'endereco' => '',
                'bairro' => '',
                'cep' => '',
                'servicos' => [],
                'status' => 'ativa',
                'dataConvenio' => date('Y-m-d'),
            ],
            $input,
            ['id' => $id ?: 'novo']
        ));
    }
}

$valores = $empresa?->toArray() ?? [
    'nome' => '',
    'cnpj' => '',
    'cidade' => '',
    'uf' => 'MG',
    'endereco' => '',
    'bairro' => '',
    'cep' => '',
    'status' => 'ativa',
    'dataConvenio' => date('Y-m-d'),
    'numeroContrato' => '',
    'ordemCredenciamento' => 0,
    'telefone' => '',
    'email' => '',
    'responsavel' => '',
    'observacoes' => '',
    'atividadesCodigos' => [],
    'regioesMunicipiosCodigos' => [],
    'estadosAtuacaoCodigos' => [],
    'atendeAcima100km' => true,
];
$atendeAcima = filter_var($valores['atendeAcima100km'] ?? true, FILTER_VALIDATE_BOOLEAN);

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('dashboard')) ?>">Início</a>
  <span>/</span>
  <a href="<?= e(page_url('empresas')) ?>">Empresas Credenciadas</a>
  <span>/</span>
  <span><?= $isEdit ? 'Editar empresa' : 'Nova empresa' ?></span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1><?= $isEdit ? 'Editar Empresa' : 'Nova Empresa' ?></h1>
      <?php if ($isEdit): ?>
        <span class="count-pill mono"><?= e($id) ?></span>
      <?php endif; ?>
    </div>
    <p class="subtitle">
      <?= $isEdit
        ? 'Atualize os dados cadastrais, atividades e abrangência da empresa credenciada.'
        : 'Preencha o cadastro para habilitar a empresa nos processos de sorteio.' ?>
    </p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url($isEdit ? 'empresa-detalhe' : 'empresas', $isEdit ? ['id' => $id] : [])) ?>">
      Cancelar
    </a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-warning"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="form-stack">
  <?php if ($isEdit): ?>
    <?php $estaAtiva = ($valores['status'] ?? 'ativa') === 'ativa'; ?>
    <section class="panel status-panel <?= $estaAtiva ? 'status-panel-active' : 'status-panel-inactive' ?>">
      <div class="panel-body status-panel-body">
        <div>
          <div class="status-panel-title">
            <?= $estaAtiva ? 'Empresa ativa no credenciamento' : 'Empresa desativada' ?>
          </div>
          <p class="status-panel-desc">
            <?= $estaAtiva
              ? 'Empresas ativas podem participar dos sorteios. Desative para excluí-la temporariamente dos processos.'
              : 'Esta empresa está desativada e não entra em novos sorteios. Reative para voltar a habilitá-la.' ?>
          </p>
        </div>
        <div class="status-toggle-wrap">
          <input type="hidden" name="status" id="status" value="<?= e((string) ($valores['status'] ?? 'ativa')) ?>">
          <button type="button" class="btn <?= $estaAtiva ? 'btn-danger-soft' : 'btn-accent' ?>" id="btnToggleStatus">
            <i data-lucide="<?= $estaAtiva ? 'ban' : 'check-circle' ?>"></i>
            <?= $estaAtiva ? 'Desativar empresa' : 'Reativar empresa' ?>
          </button>
        </div>
      </div>
    </section>
  <?php else: ?>
    <input type="hidden" name="status" value="ativa">
  <?php endif; ?>

  <section class="panel">
    <div class="panel-header"><h3>1. Dados cadastrais</h3></div>
    <div class="panel-body">
      <div class="detail-grid">
        <div class="form-group" style="grid-column:1 / -1;">
          <label for="nome">Razão social / Nome *</label>
          <input class="form-control" type="text" id="nome" name="nome" required value="<?= e((string) $valores['nome']) ?>">
        </div>
        <div class="form-group">
          <label for="numeroContrato">Nº do contrato</label>
          <input class="form-control" type="text" id="numeroContrato" name="numeroContrato" value="<?= e((string) ($valores['numeroContrato'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="cnpj">CNPJ</label>
          <input class="form-control" type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" value="<?= e((string) $valores['cnpj']) ?>">
        </div>
        <div class="form-group">
          <label for="dataConvenio">Data do credenciamento</label>
          <input class="form-control" type="date" id="dataConvenio" name="dataConvenio" value="<?= e((string) $valores['dataConvenio']) ?>">
        </div>
        <div class="form-group">
          <label for="ordemCredenciamento">Ordem no credenciamento</label>
          <input class="form-control" type="number" id="ordemCredenciamento" name="ordemCredenciamento" min="0"
            value="<?= e((string) ($valores['ordemCredenciamento'] ?? 0)) ?>">
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>2. Contato e endereço</h3></div>
    <div class="panel-body">
      <div class="detail-grid">
        <div class="form-group">
          <label for="email">E-mail</label>
          <input class="form-control" type="email" id="email" name="email" value="<?= e((string) ($valores['email'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="telefone">Telefone</label>
          <input class="form-control" type="text" id="telefone" name="telefone" value="<?= e((string) ($valores['telefone'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="responsavel">Responsável</label>
          <input class="form-control" type="text" id="responsavel" name="responsavel" value="<?= e((string) ($valores['responsavel'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="cidade">Cidade *</label>
          <input class="form-control" type="text" id="cidade" name="cidade" required value="<?= e((string) $valores['cidade']) ?>">
        </div>
        <div class="form-group">
          <label for="uf">UF</label>
          <select class="form-control" id="uf" name="uf">
            <?php foreach ($ufs as $uf): ?>
              <option value="<?= e($uf) ?>" <?= (($valores['uf'] ?? 'MG') === $uf) ? 'selected' : '' ?>><?= e($uf) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="cep">CEP</label>
          <input class="form-control" type="text" id="cep" name="cep" value="<?= e((string) ($valores['cep'] ?? '')) ?>">
        </div>
        <div class="form-group" style="grid-column:1 / -1;">
          <label for="endereco">Endereço *</label>
          <input class="form-control" type="text" id="endereco" name="endereco" required value="<?= e((string) $valores['endereco']) ?>">
        </div>
        <div class="form-group">
          <label for="bairro">Bairro</label>
          <input class="form-control" type="text" id="bairro" name="bairro" value="<?= e((string) ($valores['bairro'] ?? '')) ?>">
        </div>
        <div class="form-group" style="grid-column:1 / -1;">
          <label for="observacoes">Observações</label>
          <textarea class="form-control" id="observacoes" name="observacoes"><?= e((string) ($valores['observacoes'] ?? '')) ?></textarea>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>3. Atividades habilitadas</h3></div>
    <div class="panel-body">
      <div class="checkbox-grid">
        <?php foreach ($catalogo['atividades'] as $code => $label): ?>
          <label class="checkbox-item">
            <input type="checkbox" name="atividadesCodigos[]" value="<?= e($code) ?>"
              <?= in_array($code, $valores['atividadesCodigos'] ?? [], true) ? 'checked' : '' ?>>
            <span>
              <div class="cb-title"><?= e($code) ?> — <?= e($label) ?></div>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>4. Abrangência territorial</h3></div>
    <div class="panel-body">
      <p class="form-section-label">Distância de atendimento</p>
      <p class="field-hint" style="margin:0 0 0.6rem;">
        <i data-lucide="route"></i>
        Contada pela rota de carro entre a cidade da empresa e a demanda, somando ida e volta. Vira critério do sorteio.
      </p>
      <div class="choice-grid" style="margin-bottom:1.25rem;">
        <label class="choice-card">
          <input type="radio" name="atendeAcima100km" value="1" <?= $atendeAcima ? 'checked' : '' ?>>
          <span>
            <strong>Atende acima de 100 km</strong>
            <small>Participa de demandas a qualquer distância, desde que atenda a região.</small>
          </span>
        </label>
        <label class="choice-card">
          <input type="radio" name="atendeAcima100km" value="0" <?= $atendeAcima ? '' : 'checked' ?>>
          <span>
            <strong>Atende somente até 100 km</strong>
            <small>50 km de ida + 50 km de volta. Fica fora de demandas mais distantes.</small>
          </span>
        </label>
      </div>

      <p class="form-section-label">Regiões de municípios (MG)</p>
      <div class="checkbox-grid" style="margin-bottom:1.25rem;">
        <?php foreach ($catalogo['regioesMunicipios'] as $code => $label): ?>
          <label class="checkbox-item">
            <input type="checkbox" name="regioesMunicipiosCodigos[]" value="<?= e($code) ?>"
              <?= in_array($code, $valores['regioesMunicipiosCodigos'] ?? [], true) ? 'checked' : '' ?>>
            <span><div class="cb-title"><?= e($code) ?> — <?= e($label) ?></div></span>
          </label>
        <?php endforeach; ?>
      </div>

      <p class="form-section-label">Estados da federação</p>
      <div class="checkbox-grid">
        <?php foreach ($catalogo['estadosAtuacao'] as $code => $label): ?>
          <label class="checkbox-item">
            <input type="checkbox" name="estadosAtuacaoCodigos[]" value="<?= e($code) ?>"
              <?= in_array($code, $valores['estadosAtuacaoCodigos'] ?? [], true) ? 'checked' : '' ?>>
            <span><div class="cb-title"><?= e($code) ?> — <?= e($label) ?></div></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="form-sticky-actions">
    <a class="btn btn-outline" href="<?= e(page_url($isEdit ? 'empresa-detalhe' : 'empresas', $isEdit ? ['id' => $id] : [])) ?>">Cancelar</a>
    <button type="submit" class="btn btn-accent btn-lg">
      <i data-lucide="save"></i>
      <?= $isEdit ? 'Salvar alterações' : 'Cadastrar empresa' ?>
    </button>
  </div>
</form>

<?php if ($isEdit): ?>
<section class="panel danger-zone">
  <div class="panel-body danger-zone-body">
    <div>
      <div class="danger-zone-title"><i data-lucide="alert-triangle"></i> Excluir empresa</div>
      <p class="danger-zone-desc">
        Remove a empresa definitivamente do credenciamento. Os sorteios já realizados continuam no histórico.
        Se a intenção for apenas tirá-la dos próximos sorteios, prefira <strong>desativar</strong>.
      </p>
    </div>
    <form method="post" action="api/excluir-empresa.php"
      data-confirm="Excluir definitivamente &quot;<?= e((string) $valores['nome']) ?>&quot;? Esta ação não pode ser desfeita.">
      <input type="hidden" name="id" value="<?= e($id) ?>">
      <button type="submit" class="btn btn-danger">
        <i data-lucide="trash-2"></i> Excluir empresa
      </button>
    </form>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnToggleStatus');
  const input = document.getElementById('status');
  const panel = document.querySelector('.status-panel');
  if (!btn || !input) return;

  btn.addEventListener('click', () => {
    const ativa = input.value === 'ativa';
    const novo = ativa ? 'inativa' : 'ativa';
    const ok = confirm(
      ativa
        ? 'Desativar esta empresa? Ela não participará de novos sorteios.'
        : 'Reativar esta empresa? Ela voltará a ficar elegível nos sorteios.'
    );
    if (!ok) return;

    input.value = novo;
    // Salva imediatamente o status junto com o restante do formulário
    btn.closest('form').requestSubmit();
  });
});
</script>
<?php endif; ?>
