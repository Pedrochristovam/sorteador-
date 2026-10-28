<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/services/EligibilityService.php';
require_once dirname(__DIR__) . '/types/Demanda.php';

sorteador_boot_session();
set_time_limit(180);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Método não permitido'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$previewOnly = !empty($input['preview']);

$companyService = new CompanyService();
$catalogo = $companyService->getCatalogo();
$regioes = ($catalogo['regioesMunicipios'] ?? []) + ($catalogo['estadosAtuacao'] ?? []);

$demanda = Demanda::fromArray($input, $catalogo['atividades'] ?? [], $regioes);

$faltando = [];
if ($demanda->cidade === '') {
    $faltando[] = 'cidade';
}
if ($demanda->endereco === '') {
    $faltando[] = 'endereço da demanda';
}
if ($demanda->regiaoCodigo === '' || !isset($regioes[$demanda->regiaoCodigo])) {
    $faltando[] = 'região da demanda';
}
if ($demanda->atividadesCodigos === []) {
    $faltando[] = 'ao menos uma atividade';
}
if ($faltando !== []) {
    json_response(['ok' => false, 'message' => 'Preencha: ' . implode(', ', $faltando) . '.'], 422);
}

$resultado = (new EligibilityService($companyService))->calcular($demanda);

if (!$previewOnly) {
    $_SESSION['sorteio_fluxo'] = [
        'etapa' => 'elegiveis',
        'demanda' => $resultado['demanda'],
        'criteriosUtilizados' => $resultado['criteriosUtilizados'],
        'totalAnalisadas' => $resultado['totalAnalisadas'],
        'totalEliminadas' => $resultado['totalEliminadas'],
        'totalElegiveis' => $resultado['totalElegiveis'],
        'elegiveis' => $resultado['elegiveis'],
        'eliminadas' => $resultado['eliminadas'],
        'distancia' => $resultado['distancia'],
        'cidade' => $resultado['cidade'],
        'rodizio' => $resultado['rodizio'],
        'updatedAt' => date('c'),
    ];
    sorteador_save_fluxo($_SESSION['sorteio_fluxo']);
}

$contar = static function (array $lista, string $codigo): int {
    $n = 0;
    foreach ($lista as $emp) {
        foreach ($emp['criterios'] ?? [] as $c) {
            if (($c['codigo'] ?? '') === $codigo && empty($c['atendido'])) {
                $n++;
                break;
            }
        }
    }
    return $n;
};

$eliminadas = $resultado['eliminadas'];
$elegiveis = $resultado['elegiveis'];
$comRota = array_values(array_filter(
    $elegiveis,
    static fn(array $e): bool => $e['distanciaKm'] !== null && in_array($e['distanciaFonte'] ?? '', ['google', 'osm'], true)
));
$maisProxima = $comRota[0] ?? null;

json_response([
    'ok' => true,
    'preview' => $previewOnly,
    'data' => $previewOnly ? null : $resultado,
    'impacto' => [
        'total' => (int) $resultado['totalAnalisadas'],
        'desativadas' => $contar($eliminadas, 'convenio_ativo'),
        'semAtividade' => $contar($eliminadas, 'servico_compativel'),
        'foraRegiao' => $contar($eliminadas, 'localizacao'),
        'limite100' => $contar($eliminadas, 'limite_100km'),
        'foraCidade' => $contar($eliminadas, 'somente_cidade'),
        'elegiveis' => (int) $resultado['totalElegiveis'],
        'emRodizio' => (int) $resultado['rodizio']['elegiveisEmRodizio'],
        'aptasNaCidade' => (int) $resultado['cidade']['aptasNaCidade'],
        'restricaoCidade' => (bool) $resultado['cidade']['restricaoAplicada'],
        'distanciaMedia' => $comRota !== []
            ? round(array_sum(array_column($comRota, 'distanciaKm')) / count($comRota), 1)
            : null,
        'maisProxima' => $maisProxima ? ['nome' => $maisProxima['nome'], 'km' => $maisProxima['distanciaKm']] : null,
        'fonteDistancia' => $resultado['distancia']['rotulo'],
        'modalidade' => $resultado['demanda']['modalidade'],
        'modalidadeLabel' => modalidade_label($resultado['demanda']['modalidade']),
        'modalidadeMotivo' => $resultado['demanda']['modalidadeMotivo'],
    ],
]);
