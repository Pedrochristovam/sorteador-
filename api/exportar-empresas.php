<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';

sorteador_boot_session();

$companyService = new CompanyService();
$empresas = $companyService->listAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="empresas_credenciadas_' . date('Ymd') . '.csv"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, [
    'ID', 'Nome', 'CNPJ', 'Contrato', 'Cidade', 'UF', 'Endereço', 'CEP',
    'Status', 'Atividades', 'E-mail', 'Telefone', 'Data Credenciamento',
], ';');

foreach ($empresas as $empresa) {
    fputcsv($out, [
        $empresa->id,
        $empresa->nome,
        $empresa->cnpj,
        $empresa->numeroContrato,
        $empresa->cidade,
        $empresa->uf,
        $empresa->endereco,
        $empresa->cep,
        $empresa->status,
        implode(' | ', $empresa->servicos),
        $empresa->email ?? '',
        $empresa->telefone ?? '',
        $empresa->dataConvenio,
    ], ';');
}

fclose($out);
exit;
