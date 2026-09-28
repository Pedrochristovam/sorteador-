<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/services/DrawService.php';
require_once dirname(__DIR__) . '/services/AuditService.php';

sorteador_boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Método não permitido'], 405);
}

$fluxo = sorteador_get_fluxo();
if (!$fluxo || empty($fluxo['elegiveis'])) {
    json_response(['ok' => false, 'message' => 'Fluxo de sorteio não encontrado. Recalcule as empresas elegíveis.'], 400);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['confirmado'])) {
    json_response(['ok' => false, 'message' => 'Confirmação obrigatória para executar o sorteio.'], 422);
}

$resumo = static fn(array $e): array => [
    'id' => $e['empresaId'] ?? $e['id'] ?? '',
    'nome' => $e['nome'] ?? '',
    'cnpj' => $e['cnpj'] ?? '',
];

try {
    $drawService = new DrawService();
    $auditService = new AuditService();
    $demanda = $fluxo['demanda'];

    $execucao = $drawService->realizar(
        $fluxo['elegiveis'],
        $demanda,
        $fluxo['criteriosUtilizados'] ?? [],
        [
            'totalAnalisadas' => $fluxo['totalAnalisadas'] ?? 0,
            'totalEliminadas' => $fluxo['totalEliminadas'] ?? 0,
            'totalElegiveis' => $fluxo['totalElegiveis'] ?? count($fluxo['elegiveis']),
        ]
    );
    $sorteio = $execucao['sorteio'];
    $drawService->persistirSessao($sorteio, $execucao['meta']);

    $modalidade = (string) $sorteio['modalidade'];
    $agora = $sorteio['dataHora'];
    $inicio = $fluxo['updatedAt'] ?? $agora;
    $nomesSorteadas = implode(', ', array_column($sorteio['sorteadas'], 'nome'));

    $eventos = [
        ['evento' => 'demanda_registrada', 'timestamp' => $inicio, 'detalhe' => ($demanda['cidade'] ?? '') . ' / ' . ($demanda['servicoNecessario'] ?? '')],
        ['evento' => 'modalidade_definida', 'timestamp' => $inicio, 'detalhe' => modalidade_label($modalidade) . ' — ' . ($demanda['modalidadeMotivo'] ?? '')],
        ['evento' => 'distancia_apurada', 'timestamp' => $inicio, 'detalhe' => $fluxo['distancia']['rotulo'] ?? ''],
        ['evento' => 'elegibilidade_calculada', 'timestamp' => $inicio, 'detalhe' => null],
    ];
    if (!empty($sorteio['rodizioNota'])) {
        $eventos[] = ['evento' => 'rodizio_aplicado', 'timestamp' => $agora, 'detalhe' => $sorteio['rodizioNota']];
    }
    $eventos[] = ['evento' => 'sorteio_confirmado', 'timestamp' => $agora, 'detalhe' => 'Confirmação do operador'];
    if ($modalidade === 'proxima') {
        $eventos[] = ['evento' => 'mais_proxima_escolhida', 'timestamp' => $agora, 'detalhe' => $nomesSorteadas . ' (' . format_km($sorteio['empresaSelecionada']['distanciaKm'] ?? null) . ')'];
    } else {
        $eventos[] = ['evento' => 'entropia_solicitada', 'timestamp' => $agora, 'detalhe' => 'CSPRNG random_int'];
        $eventos[] = ['evento' => 'embaralhamento', 'timestamp' => $agora, 'detalhe' => 'Fisher-Yates'];
        $eventos[] = $modalidade === 'orcamento'
            ? ['evento' => 'sorteadas_orcamento', 'timestamp' => $agora, 'detalhe' => $nomesSorteadas]
            : ['evento' => 'sorteio_executado', 'timestamp' => $agora, 'detalhe' => 'Selecionada: ' . $nomesSorteadas];
    }
    $eventos[] = ['evento' => 'hash_consolidado', 'timestamp' => $agora, 'detalhe' => null];

    $audit = $auditService->registrar([
        'sorteioId' => $sorteio['id'],
        'dataHora' => $agora,
        'demanda' => $demanda,
        'criteriosUtilizados' => $fluxo['criteriosUtilizados'] ?? [],
        'empresasAnalisadas' => array_map($resumo, array_merge($fluxo['elegiveis'] ?? [], $fluxo['eliminadas'] ?? [])),
        'empresasEliminadas' => array_map(static fn(array $e): array => [
            'id' => $e['empresaId'] ?? '',
            'nome' => $e['nome'] ?? '',
            'cnpj' => $e['cnpj'] ?? '',
            'status' => $e['status'] ?? '',
            'distanciaKm' => $e['distanciaKm'] ?? null,
            'motivos' => $e['motivosEliminacao'] ?? [],
        ], $fluxo['eliminadas'] ?? []),
        'empresasElegiveis' => array_map(static fn(array $e): array => [
            'id' => $e['empresaId'] ?? '',
            'nome' => $e['nome'] ?? '',
            'cnpj' => $e['cnpj'] ?? '',
            'distanciaKm' => $e['distanciaKm'] ?? null,
            'emRodizio' => !empty($e['emRodizio']),
        ], $fluxo['elegiveis'] ?? []),
        'empresasParticipantes' => $sorteio['participantes'],
        'resultadoFinal' => $sorteio['empresaSelecionada'],
        'seedReferencia' => $sorteio['seedReferencia'] ?? null,
        'versaoAlgoritmo' => $execucao['meta']['mode'],
        'eventos' => $eventos,
        'modalidade' => $modalidade,
        'modalidadeMotivo' => $demanda['modalidadeMotivo'] ?? '',
        'sorteadas' => $sorteio['sorteadas'],
        'ranking' => $sorteio['ranking'],
        'rodizioNota' => $sorteio['rodizioNota'],
        'distancia' => $fluxo['distancia'] ?? null,
    ]);

    $_SESSION['sorteio_fluxo']['etapa'] = 'resultado';
    $_SESSION['sorteio_fluxo']['ultimoResultado'] = $execucao;
    $_SESSION['sorteio_fluxo']['ultimaAuditoria'] = $audit;
    $_SESSION['ultimo_resultado_id'] = $sorteio['id'];
    sorteador_save_fluxo($_SESSION['sorteio_fluxo']);

    json_response([
        'ok' => true,
        'data' => $execucao,
        'auditoriaId' => $audit['id'] ?? null,
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
