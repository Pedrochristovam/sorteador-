<?php

declare(strict_types=1);

/**
 * Consulta a rota de carro de algumas credenciadas até um endereço.
 * Uso: php database/testar_rota.php "Rua X, 100" "Cidade" "UF"
 */

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';
require_once dirname(__DIR__) . '/services/DistanceService.php';

$destino = [
    'endereco' => $argv[1] ?? 'Praça Sete de Setembro',
    'cidade' => $argv[2] ?? 'Belo Horizonte',
    'uf' => $argv[3] ?? 'MG',
];

$empresas = array_slice((new CompanyService())->listAll(), 0, 6);
$origens = [];
foreach ($empresas as $e) {
    $origens[$e->id] = ['endereco' => $e->endereco, 'cidade' => $e->cidade, 'uf' => $e->uf];
}

$distancias = new DistanceService();
echo 'Provedor: ' . $distancias->providerLabel() . PHP_EOL;
$t = microtime(true);
$rotas = $distancias->rotasAte($destino, $origens);
printf("Destino: %s, %s/%s (%.1fs)\n", $destino['endereco'], $destino['cidade'], $destino['uf'], microtime(true) - $t);
foreach ($empresas as $e) {
    $r = $rotas[$e->id] ?? null;
    printf("  %-45s %-18s %8s km %6s min  [%s/%s]\n", mb_substr($e->nome, 0, 45), $e->cidade, $r['km'] ?? '-', $r['minutos'] ?? '-', $r['fonte'] ?? '-', $r['precisao'] ?? '-');
}
