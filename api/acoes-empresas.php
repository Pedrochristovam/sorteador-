<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';

sorteador_boot_session();

$filters = [
    'busca' => trim((string) ($_POST['busca'] ?? '')),
    'cidade' => trim((string) ($_POST['cidade'] ?? '')),
    'status' => trim((string) ($_POST['status'] ?? '')),
    'servico' => trim((string) ($_POST['servico'] ?? '')),
];
$voltar = array_filter($filters);
if (!empty($_POST['per'])) {
    $voltar['per'] = (int) $_POST['per'];
}

$back = static function (array $extra) use ($voltar): never {
    header('Location: ../' . page_url('empresas', $voltar + $extra));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $back([]);
}

$acao = (string) ($_POST['acao'] ?? '');
if (!in_array($acao, ['desativar', 'reativar', 'excluir'], true)) {
    $back(['erro' => 'Ação em lote inválida.']);
}

$companyService = new CompanyService();

if (($_POST['todas_filtradas'] ?? '0') === '1') {
    $ids = array_map(
        static fn(Empresa $e): string => $e->id,
        $companyService->search(array_filter($filters))
    );
} else {
    $ids = array_values(array_filter(
        (array) ($_POST['ids'] ?? []),
        static fn($v): bool => is_string($v) && $v !== ''
    ));
}

if ($ids === []) {
    $back(['erro' => 'Nenhuma empresa selecionada.']);
}

try {
    $qtd = match ($acao) {
        'desativar' => $companyService->setStatusMany($ids, 'inativa'),
        'reativar' => $companyService->setStatusMany($ids, 'ativa'),
        'excluir' => $companyService->deleteMany($ids),
    };
} catch (Throwable $e) {
    $back(['erro' => 'Falha na ação em lote: ' . $e->getMessage()]);
}

$back(['lote' => $acao, 'qtd' => $qtd, 'sel' => count($ids)]);
