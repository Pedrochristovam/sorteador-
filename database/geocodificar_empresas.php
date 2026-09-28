<?php

declare(strict_types=1);

/**
 * Pré-geocodifica os endereços das empresas (cache em storage/geo_cache.json)
 * para que o primeiro sorteio não precise esperar o OpenStreetMap.
 *
 * Uso: php database/geocodificar_empresas.php
 */

require_once dirname(__DIR__) . '/services/CompanyService.php';
require_once dirname(__DIR__) . '/services/DistanceService.php';

$empresas = (new CompanyService())->listAll();
$distance = new DistanceService();
$contagem = ['endereco' => 0, 'cep' => 0, 'cidade' => 0, 'falha' => 0];

echo 'Geocodificando ' . count($empresas) . " empresas...\n";
foreach ($empresas as $i => $empresa) {
    $geo = $distance->geocodificar([
        'endereco' => $empresa->endereco,
        'cidade' => $empresa->cidade,
        'uf' => $empresa->uf,
    ]);
    $tipo = $geo['precisao'] ?? 'falha';
    $contagem[$tipo] = ($contagem[$tipo] ?? 0) + 1;
    printf("[%2d/%d] %-8s %s — %s\n", $i + 1, count($empresas), $tipo, $empresa->id, $empresa->cidade);
}

echo "\nPrecisão: " . json_encode($contagem) . "\n";
