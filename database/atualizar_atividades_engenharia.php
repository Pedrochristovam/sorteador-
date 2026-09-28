<?php

declare(strict_types=1);

/**
 * Regrava os nomes das atividades das empresas a partir dos códigos (A–F) do catálogo atual.
 * Ex.: "Atividade C" → "Laudo Rural".
 *
 * Uso: php database/atualizar_atividades_engenharia.php
 */

require_once dirname(__DIR__) . '/services/CompanyService.php';

$service = new CompanyService();
$catalogo = $service->getCatalogo()['atividades'];
$alteradas = 0;

foreach ($service->listAll() as $empresa) {
    $nomes = array_values(array_map(static fn(string $c): string => $catalogo[$c] ?? $c, $empresa->atividadesCodigos));
    if ($nomes === [] || $nomes === $empresa->servicos) {
        continue;
    }
    $service->save($empresa->toArray(), $empresa->id);
    $alteradas++;
    echo "{$empresa->id}: " . implode(', ', $empresa->servicos) . ' → ' . implode(', ', $nomes) . "\n";
}

echo "\nEmpresas atualizadas: {$alteradas}\n";
