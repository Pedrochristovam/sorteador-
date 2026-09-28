<?php

$juridico = new JuridicoService();
$catalogo = $juridico->getCatalogo();
$id = trim((string) ($_GET['id'] ?? ''));
$sociedade = $id !== '' ? $juridico->findById($id) : null;
$isEdit = $sociedade !== null;
$errors = [];

$ufs = ['MG', 'SP', 'RJ', 'ES', 'BA', 'GO', 'DF', 'PR', 'SC', 'RS', 'MT', 'MS', 'PA', 'AM', 'CE', 'PE', 'RN', 'PB', 'AL', 'SE', 'MA', 'PI', 'TO', 'RO', 'AC', 'AP', 'RR'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'razaoSocial' => $_POST['razaoSocial'] ?? '',
        'nomeFantasia' => $_POST['nomeFantasia'] ?? '',
        'cnpj' => $_POST['cnpj'] ?? '',
        'oabSociedade' => $_POST['oabSociedade'] ?? '',
        'cidadeSede' => $_POST['cidadeSede'] ?? '',
        'ufSede' => $_POST['ufSede'] ?? 'MG',
        'endereco' => $_POST['endereco'] ?? '',
        'bairro' => $_POST['bairro'] ?? '',
        'cep' => $_POST['cep'] ?? '',
        'status' => $_POST['status'] ?? 'ativa',
        'statusOperacional' => $_POST['statusOperacional'] ?? 'apto',
        'dataCredenciamento' => $_POST['dataCredenciamento'] ?? date('Y-m-d'),
        'numeroContrato' => $_POST['numeroContrato'] ?? '',
        'ordemCredenciamento' => $_POST['ordemCredenciamento'] ?? 0,
        'telefone' => $_POST['telefone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'responsavel' => $_POST['responsavel'] ?? '',
        'oabResponsavel' => $_POST['oabResponsavel'] ?? '',
        'processoSei' => $_POST['processoSei'] ?? '',
        'observacoes' => $_POST['observacoes'] ?? '',
        'atividadesCodigos' => $_POST['atividadesCodigos'] ?? [],
        'polosCodigos' => $_POST['polosCodigos'] ?? [],
    ];

    try {
        $saved = $juridico->save($input, $isEdit ? $id : null);
        redirect('juridico-sociedade-detalhe', ['id' => $saved->id, 'saved' => 1]);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $sociedade = SociedadeAdvogados::fromArray(array_merge(
            $sociedade?->toArray() ?? [
                'id' => '',
                'razaoSocial' => '',
                'cidadeSede' => '',
                'status' => 'ativa',
                'statusOperacional' => 'apto',
                'dataCredenciamento' => date('Y-m-d'),
                'ordemCredenciamento' => 0,
            ],
            $input
        ));
        $isEdit = $id !== '';
    }
}

$valores = $sociedade?->toArray() ?? [
    'razaoSocial' => '',
    'nomeFantasia' => '',
    'cnpj' => '',
    'oabSociedade' => '',
    'cidadeSede' => '',
    'ufSede' => 'MG',
    'endereco' => '',
    'bairro' => '',
    'cep' => '',
    'status' => 'ativa',
    'statusOperacional' => 'apto',
    'dataCredenciamento' => date('Y-m-d'),
    'numeroContrato' => '',
    'ordemCredenciamento' => 0,
    'telefone' => '',
    'email' => '',
    'responsavel' => '',
    'oabResponsavel' => '',
    'processoSei' => '',
    'observacoes' => '',
    'atividadesCodigos' => [],
    'polosCodigos' => [],
];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(page_url('juridico-dashboard')) ?>">Jurídico</a>
  <span>/</span>
  <a href="<?= e(page_url('juridico-sociedades')) ?>">Sociedades</a>
  <span>/</span>
  <span><?= $isEdit ? 'Editar' : 'Nova' ?></span>
</nav>

<div class="empresas-hero">
  <div>
    <div class="dash-title-row">
      <h1><?= $isEdit ? 'Editar Sociedade' : 'Nova Sociedade de Advogados' ?></h1>
      <span class="count-pill">Edital 01/2026</span>
    </div>
    <p class="subtitle">Cadastro + Ficha de Opção (atividades e polos/comarcas do Anexo X).</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-outline" href="<?= e(page_url($isEdit ? 'juridico-sociedade-detalhe' : 'juridico-sociedades', $isEdit ? ['id' => $id] : [])) ?>">Cancelar</a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-warning"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="form-stack">
  <?php if ($isEdit): ?>
    <?php $estaAtiva = ($valores['status'] ?? '') === 'ativa'; ?>
    <section class="panel status-panel <?= $estaAtiva ? 'status-panel-active' : 'status-panel-inactive' ?>">
      <div class="panel-body status-panel-body">
        <div>
          <div class="status-panel-title"><?= $estaAtiva ? 'Sociedade ativa no credenciamento' : 'Sociedade desativada / suspensa' ?></div>
          <p class="status-panel-desc">
            Status cadastral e situação operacional controlam a participação nos sorteios jurídicos.
          </p>
        </div>
        <div class="detail-grid" style="min-width:280px;">
          <div class="form-group">
            <label for="status">Status cadastral</label>
            <select class="form-control" id="status" name="status">
              <option value="ativa" <?= ($valores['status'] ?? '') === 'ativa' ? 'selected' : '' ?>>Ativa</option>
              <option value="inativa" <?= ($valores['status'] ?? '') === 'inativa' ? 'selected' : '' ?>>Desativada</option>
              <option value="suspensa" <?= ($valores['status'] ?? '') === 'suspensa' ? 'selected' : '' ?>>Suspensa</option>
            </select>
          </div>
          <div class="form-group">
            <label for="statusOperacional">Situação operacional</label>
            <select class="form-control" id="statusOperacional" name="statusOperacional">
              <option value="apto" <?= ($valores['statusOperacional'] ?? '') === 'apto' ? 'selected' : '' ?>>Apto a sorteio</option>
              <option value="indisponivel_demanda" <?= ($valores['statusOperacional'] ?? '') === 'indisponivel_demanda' ? 'selected' : '' ?>>Indisponível — Em atendimento</option>
              <option value="contemplado" <?= ($valores['statusOperacional'] ?? '') === 'contemplado' ? 'selected' : '' ?>>Contemplado</option>
            </select>
          </div>
        </div>
      </div>
    </section>
  <?php else: ?>
    <input type="hidden" name="status" value="ativa">
    <input type="hidden" name="statusOperacional" value="apto">
  <?php endif; ?>

  <section class="panel">
    <div class="panel-header"><h3>1. Dados da sociedade</h3></div>
    <div class="panel-body">
      <div class="detail-grid">
        <div class="form-group" style="grid-column:1 / -1;">
          <label for="razaoSocial">Razão social *</label>
          <input class="form-control" type="text" id="razaoSocial" name="razaoSocial" required value="<?= e((string) $valores['razaoSocial']) ?>">
        </div>
        <div class="form-group">
          <label for="nomeFantasia">Nome fantasia</label>
          <input class="form-control" type="text" id="nomeFantasia" name="nomeFantasia" value="<?= e((string) ($valores['nomeFantasia'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="cnpj">CNPJ</label>
          <input class="form-control" type="text" id="cnpj" name="cnpj" value="<?= e((string) ($valores['cnpj'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="oabSociedade">OAB da sociedade</label>
          <input class="form-control" type="text" id="oabSociedade" name="oabSociedade" placeholder="OAB/MG 0000" value="<?= e((string) ($valores['oabSociedade'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="numeroContrato">Nº do contrato</label>
          <input class="form-control" type="text" id="numeroContrato" name="numeroContrato" value="<?= e((string) ($valores['numeroContrato'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="dataCredenciamento">Data do credenciamento</label>
          <input class="form-control" type="date" id="dataCredenciamento" name="dataCredenciamento" value="<?= e((string) $valores['dataCredenciamento']) ?>">
        </div>
        <div class="form-group">
          <label for="ordemCredenciamento">Ordem no credenciamento</label>
          <input class="form-control" type="number" id="ordemCredenciamento" name="ordemCredenciamento" min="0" value="<?= e((string) ($valores['ordemCredenciamento'] ?? 0)) ?>">
        </div>
        <div class="form-group">
          <label for="processoSei">Processo SEI!</label>
          <input class="form-control" type="text" id="processoSei" name="processoSei" value="<?= e((string) ($valores['processoSei'] ?? '')) ?>">
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>2. Contato e sede</h3></div>
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
          <label for="oabResponsavel">OAB do responsável</label>
          <input class="form-control" type="text" id="oabResponsavel" name="oabResponsavel" value="<?= e((string) ($valores['oabResponsavel'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="cidadeSede">Cidade sede *</label>
          <input class="form-control" type="text" id="cidadeSede" name="cidadeSede" required value="<?= e((string) $valores['cidadeSede']) ?>">
        </div>
        <div class="form-group">
          <label for="ufSede">UF</label>
          <select class="form-control" id="ufSede" name="ufSede">
            <?php foreach ($ufs as $uf): ?>
              <option value="<?= e($uf) ?>" <?= (($valores['ufSede'] ?? 'MG') === $uf) ? 'selected' : '' ?>><?= e($uf) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1 / -1;">
          <label for="endereco">Endereço</label>
          <input class="form-control" type="text" id="endereco" name="endereco" value="<?= e((string) ($valores['endereco'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="bairro">Bairro</label>
          <input class="form-control" type="text" id="bairro" name="bairro" value="<?= e((string) ($valores['bairro'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="cep">CEP</label>
          <input class="form-control" type="text" id="cep" name="cep" value="<?= e((string) ($valores['cep'] ?? '')) ?>">
        </div>
        <div class="form-group" style="grid-column:1 / -1;">
          <label for="observacoes">Observações</label>
          <textarea class="form-control" id="observacoes" name="observacoes"><?= e((string) ($valores['observacoes'] ?? '')) ?></textarea>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>3. Ficha de Opção — Atividades jurídicas</h3></div>
    <div class="panel-body">
      <div class="checkbox-grid servicos-grid">
        <?php foreach ($catalogo['atividades'] as $code => $label): ?>
          <label class="checkbox-item criteria-card">
            <input type="checkbox" name="atividadesCodigos[]" value="<?= e($code) ?>"
              <?= in_array($code, $valores['atividadesCodigos'] ?? [], true) ? 'checked' : '' ?>>
            <span><div class="cb-title"><?= e($label) ?></div><div class="cb-desc mono"><?= e($code) ?></div></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-header"><h3>4. Ficha de Opção — Polos / Comarcas (Anexo X)</h3></div>
    <div class="panel-body">
      <div class="checkbox-grid servicos-grid">
        <?php foreach ($catalogo['polos'] as $code => $label): ?>
          <label class="checkbox-item criteria-card">
            <input type="checkbox" name="polosCodigos[]" value="<?= e($code) ?>"
              <?= in_array($code, $valores['polosCodigos'] ?? [], true) ? 'checked' : '' ?>>
            <span><div class="cb-title"><?= e($label) ?></div><div class="cb-desc mono"><?= e($code) ?></div></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="form-sticky-actions">
    <a class="btn btn-outline" href="<?= e(page_url($isEdit ? 'juridico-sociedade-detalhe' : 'juridico-sociedades', $isEdit ? ['id' => $id] : [])) ?>">Cancelar</a>
    <button type="submit" class="btn btn-accent btn-lg">
      <i data-lucide="save"></i>
      <?= $isEdit ? 'Salvar alterações' : 'Cadastrar sociedade' ?>
    </button>
  </div>
</form>
