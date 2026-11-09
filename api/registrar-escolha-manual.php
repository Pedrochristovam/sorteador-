<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';
require_once dirname(__DIR__) . '/services/EligibilityService.php';
require_once dirname(__DIR__) . '/services/DrawService.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/types/Demanda.php';

sorteador_boot_session();
set_time_limit(180);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Método não permitido'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['ok' => false, 'message' => 'JSON inválido'], 422);
}

$empresaId = trim((string) ($input['empresaId'] ?? ''));
if ($empresaId === '') {
    json_response(['ok' => false, 'message' => 'Selecione a empresa.'], 422);
}

$companyService = new CompanyService();
$catalogo = $companyService->getCatalogo();
$regioes = ($catalogo['regioesMunicipios'] ?? []) + ($catalogo['estadosAtuacao'] ?? []);
$demanda = Demanda::fromArray($input, $catalogo['atividades'] ?? [], $regioes);

$faltando = [];
if ($demanda->cidade === '') {
    $faltando[] = 'cidade';
}
if ($demanda->endereco === '') {
    $faltando[] = 'endereço';
}
if ($demanda->regiaoCodigo === '' || !isset($regioes[$demanda->regiaoCodigo])) {
    $faltando[] = 'região';
}
if ($demanda->atividadesCodigos === []) {
    $faltando[] = 'atividade';
}
if ($faltando !== []) {
    json_response(['ok' => false, 'message' => 'Preencha: ' . implode(', ', $faltando) . '.'], 422);
}

try {
    $resultado = (new EligibilityService($companyService))->calcular($demanda);
    $elegiveis = $resultado['elegiveis'];
    $comRota = array_values(array_filter(
        $elegiveis,
        static fn(array $e): bool => ($e['distanciaKm'] ?? null) !== null
            && in_array($e['distanciaFonte'] ?? '', ['google', 'osm'], true)
    ));
    usort(
        $comRota,
        static fn(array $a, array $b): int => [(float) $a['distanciaKm'], (int) ($a['ordemCredenciamento'] ?? 0)]
            <=> [(float) $b['distanciaKm'], (int) ($b['ordemCredenciamento'] ?? 0)]
    );

    $escolhida = null;
    foreach ($comRota as $e) {
        if (($e['empresaId'] ?? '') === $empresaId) {
            $escolhida = $e;
            break;
        }
    }
    if ($escolhida === null) {
        json_response(['ok' => false, 'message' => 'A empresa escolhida não está entre as mais próximas aptas desta demanda.'], 422);
    }

    $resumo = [
        'id' => $escolhida['empresaId'],
        'nome' => $escolhida['nome'],
        'cnpj' => $escolhida['cnpj'] ?? '',
        'cidade' => $escolhida['cidade'] ?? '',
        'endereco' => $escolhida['endereco'] ?? '',
        'distanciaKm' => $escolhida['distanciaKm'],
        'duracaoMin' => $escolhida['duracaoMin'] ?? null,
        'telefone' => $escolhida['telefone'] ?? null,
        'email' => $escolhida['email'] ?? null,
    ];

    $demandaArr = $resultado['demanda'];
    $demandaArr['modalidade'] = 'manual';
    $demandaArr['modalidadeMotivo'] = 'Escolha manual pelo operador entre as credenciadas mais próximas.';

    $sorteio = (new DrawService())->registrarEscolhaManual(
        $demandaArr,
        $resumo,
        $comRota,
        [
            'criteriosUtilizados' => $resultado['criteriosUtilizados'],
            'totalAnalisadas' => $resultado['totalAnalisadas'],
            'totalEliminadas' => $resultado['totalEliminadas'],
            'totalElegiveis' => $resultado['totalElegiveis'],
        ]
    );

    (new AuditService())->registrar([
        'sorteioId' => $sorteio['id'],
        'dataHora' => $sorteio['dataHora'],
        'demanda' => $demandaArr,
        'criteriosUtilizados' => $resultado['criteriosUtilizados'],
        'empresasAnalisadas' => array_map(static fn(array $e): array => [
            'id' => $e['empresaId'] ?? '',
            'nome' => $e['nome'] ?? '',
            'distanciaKm' => $e['distanciaKm'] ?? null,
        ], array_merge($elegiveis, $resultado['eliminadas'])),
        'empresasEliminadas' => [],
        'empresasElegiveis' => array_map(static fn(array $e): array => [
            'id' => $e['empresaId'] ?? '',
            'nome' => $e['nome'] ?? '',
            'distanciaKm' => $e['distanciaKm'] ?? null,
        ], $elegiveis),
        'empresasParticipantes' => $sorteio['participantes'],
        'resultadoFinal' => $resumo,
        'seedReferencia' => $sorteio['seedReferencia'] ?? null,
        'versaoAlgoritmo' => 'escolha-manual',
        'eventos' => [
            ['evento' => 'demanda_registrada', 'timestamp' => $sorteio['dataHora'], 'detalhe' => $demanda->cidade . ' / ' . $demanda->endereco],
            ['evento' => 'modalidade_definida', 'timestamp' => $sorteio['dataHora'], 'detalhe' => 'Escolha manual'],
            ['evento' => 'distancia_apurada', 'timestamp' => $sorteio['dataHora'], 'detalhe' => $resultado['distancia']['rotulo'] ?? ''],
            ['evento' => 'sorteio_executado', 'timestamp' => $sorteio['dataHora'], 'detalhe' => 'Escolhida manualmente: ' . $resumo['nome'] . ' (' . format_km($resumo['distanciaKm']) . ')'],
            ['evento' => 'aguardando_aceite', 'timestamp' => $sorteio['dataHora'], 'detalhe' => 'Standby'],
            ['evento' => 'hash_consolidado', 'timestamp' => $sorteio['dataHora'], 'detalhe' => null],
        ],
        'modalidade' => 'manual',
        'modalidadeMotivo' => $demandaArr['modalidadeMotivo'],
        'ranking' => $sorteio['ranking'],
        'distancia' => $resultado['distancia'],
    ]);

    json_response([
        'ok' => true,
        'data' => $sorteio,
        'message' => 'Escolha registrada. Confirme o aceite na aba Standby.',
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
}
