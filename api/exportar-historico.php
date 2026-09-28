<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/services/DrawService.php';

sorteador_boot_session();

$drawService = new DrawService();
$historico = $drawService->listHistorico();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="historico_sorteios_' . date('Ymd') . '.csv"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, [
    'ID', 'Data/Hora', 'Cidade', 'Serviço', 'Participantes',
    'Empresa Selecionada', 'CNPJ', 'Status', 'Operador',
], ';');

foreach ($historico as $s) {
    fputcsv($out, [
        $s['id'] ?? '',
        $s['dataHora'] ?? '',
        $s['demanda']['cidade'] ?? '',
        $s['demanda']['servicoNecessario'] ?? ($s['demanda']['tipoDemanda'] ?? ''),
        count($s['participantes'] ?? []),
        $s['empresaSelecionada']['nome'] ?? '',
        $s['empresaSelecionada']['cnpj'] ?? '',
        $s['status'] ?? '',
        $s['usuarioNome'] ?? '',
    ], ';');
}

fclose($out);
exit;
