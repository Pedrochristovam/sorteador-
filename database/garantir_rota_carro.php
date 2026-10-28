<?php

declare(strict_types=1);

/**
 * Confere que as distâncias usadas pelo sistema são ROTA DE CARRO (não linha reta).
 * Uso: php database/garantir_rota_carro.php
 */

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';
require_once dirname(__DIR__) . '/services/DistanceService.php';
require_once dirname(__DIR__) . '/services/EligibilityService.php';
require_once dirname(__DIR__) . '/types/Demanda.php';

$falhas = 0;
$ok = static function (bool $cond, string $msg) use (&$falhas): void {
    echo ($cond ? '  [OK]   ' : '  [FALHA] ') . $msg . PHP_EOL;
    if (!$cond) {
        $falhas++;
    }
};

$ds = new DistanceService();
echo 'Provedor ativo: ' . $ds->providerLabel() . PHP_EOL;

// Limpa estimativas antigas do cache
$cachePath = dirname(__DIR__) . '/storage/geo_cache.json';
if (is_file($cachePath)) {
    $cache = json_decode((string) file_get_contents($cachePath), true) ?: [];
    $antes = count($cache['rotas'] ?? []);
    $cache['rotas'] = array_filter(
        $cache['rotas'] ?? [],
        static fn(array $r): bool => in_array($r['fonte'] ?? '', ['google', 'osm'], true)
    );
    file_put_contents($cachePath, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo 'Cache: removidas ' . ($antes - count($cache['rotas'])) . " entradas que não eram rota de carro\n\n";
}

$destino = ['endereco' => 'Praça Sete de Setembro', 'cidade' => 'Belo Horizonte', 'uf' => 'MG'];
$empresas = array_slice((new CompanyService())->listAll(), 0, 8);
$origens = [];
foreach ($empresas as $e) {
    $origens[$e->id] = ['endereco' => $e->endereco, 'cidade' => $e->cidade, 'uf' => $e->uf];
}

echo "1. Rotas diretas até {$destino['endereco']}, {$destino['cidade']}\n";
$rotas = $ds->rotasAte($destino, $origens);
$comCarro = 0;
foreach ($empresas as $e) {
    $r = $rotas[$e->id] ?? [];
    $carro = DistanceService::isRotaCarro($r);
    if ($carro) {
        $comCarro++;
    }
    // Se tem km, TEM que ser carro
    if (($r['km'] ?? null) !== null) {
        $ok($carro, sprintf('%s → %s km / %s min [%s]', $e->nome, $r['km'], $r['minutos'] ?? '-', $r['fonte'] ?? '-'));
        $ok(($r['modo'] ?? 'driving') === 'driving' || $carro, '  modo driving');
    } else {
        echo '  [—]    ' . $e->nome . ' → sem rota (km null, fonte=' . ($r['fonte'] ?? '-') . ")\n";
    }
}
$ok($comCarro >= 3, "pelo menos 3 rotas de carro apuradas ({$comCarro})");

echo "\n2. Fluxo de elegibilidade (limite 100 km / mais próxima)\n";
$demanda = Demanda::fromArray([
    'cidade' => 'Belo Horizonte',
    'uf' => 'MG',
    'endereco' => 'Praça Sete de Setembro',
    'regiaoCodigo' => 'I',
    'atividadesCodigos' => ['F'],
    'areaConstruidaM2' => '200',
    'areaTerrenoM2' => '300',
    'precoEdital' => false,
    'modalidadeSolicitada' => 'auto',
    'somenteCidade' => false,
]);
$resultado = (new EligibilityService())->calcular($demanda);
$fontes = [];
foreach ($resultado['elegiveis'] as $e) {
    if (($e['distanciaKm'] ?? null) !== null) {
        $fontes[] = $e['distanciaFonte'] ?? '';
        $ok(
            in_array($e['distanciaFonte'] ?? '', ['google', 'osm'], true),
            ($e['nome'] ?? '') . ' elegível com ' . ($e['distanciaKm'] ?? '?') . ' km fonte=' . ($e['distanciaFonte'] ?? '?')
        );
    }
}
$ok($fontes !== [], 'há elegíveis com rota de carro');
$ok(!in_array('estimativa', $fontes, true), 'nenhuma elegível usa estimativa/linha reta');
$ok(
    str_contains((string) ($resultado['distancia']['rotulo'] ?? ''), 'rota de carro'),
    'rótulo do provedor: ' . ($resultado['distancia']['rotulo'] ?? '-')
);

echo "\n" . ($falhas === 0 ? 'GARANTIDO: distâncias do sistema são rota de carro (Google driving / OSRM driving).' : "{$falhas} FALHA(S)") . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
