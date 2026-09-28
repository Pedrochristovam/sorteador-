<?php

declare(strict_types=1);

/**
 * Conta os registros guardados em arquivo (storage + exemplos) e aponta inconsistências
 * que impediriam a gravação no MySQL.
 * Uso: php database/diagnostico_dados.php
 */

$root = dirname(__DIR__);
$ler = static fn(string $p): array => is_file($p) ? (json_decode((string) file_get_contents($p), true) ?: []) : [];

$empresas = $ler("$root/storage/empresas.json");
$sorteiosArq = $ler("$root/storage/sorteios_session.json");
$sorteiosMock = require "$root/mocks/sorteios.php";
$audArq = $ler("$root/storage/auditorias_session.json");
$audMock = require "$root/mocks/auditorias.php";

$sorteios = array_merge($sorteiosArq, $sorteiosMock);
$auditorias = array_merge($audArq, $audMock);

$idsSorteio = array_column($sorteios, 'id');
$idsEmpresa = array_column($empresas, 'id');

printf("Empresas: %d\n", count($empresas));
printf("Sorteios: %d no arquivo + %d de exemplo (ids únicos: %d)\n", count($sorteiosArq), count($sorteiosMock), count(array_unique($idsSorteio)));
printf("Auditorias: %d no arquivo + %d de exemplo (ids únicos: %d)\n", count($audArq), count($audMock), count(array_unique(array_column($auditorias, 'id'))));

$orfas = array_filter($auditorias, static fn($a) => !in_array($a['sorteioId'] ?? '', $idsSorteio, true));
printf("Auditorias sem sorteio correspondente: %d\n", count($orfas));
foreach ($orfas as $a) {
    echo "  - {$a['id']} → {$a['sorteioId']}\n";
}

$semEmpresa = array_filter($sorteios, static fn($s) => !empty($s['empresaSelecionada']['id']) && !in_array($s['empresaSelecionada']['id'], $idsEmpresa, true));
printf("Sorteios cuja vencedora não está no cadastro atual: %d\n", count($semEmpresa));

$datasRuins = array_filter($sorteios, static fn($s) => strtotime((string) ($s['dataHora'] ?? '')) === false);
printf("Sorteios com data inválida: %d\n", count($datasRuins));

$chaves = [];
foreach ($empresas as $e) {
    $chaves += array_flip(array_keys($e));
}
echo 'Campos das empresas no arquivo: ' . implode(', ', array_keys($chaves)) . "\n";
