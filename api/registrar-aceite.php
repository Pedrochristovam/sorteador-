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

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['ok' => false, 'message' => 'JSON inválido'], 422);
}

$sorteioId = trim((string) ($input['sorteioId'] ?? ''));
$decisao = strtolower(trim((string) ($input['decisao'] ?? '')));
$observacao = trim((string) ($input['observacao'] ?? ''));

if ($sorteioId === '' || !in_array($decisao, ['aceito', 'recusado'], true)) {
    json_response(['ok' => false, 'message' => 'Informe o sorteio e se a empresa aceitou ou recusou.'], 422);
}

try {
    $draw = new DrawService();
    $sorteio = $draw->registrarAceite($sorteioId, $decisao, $observacao);

    $nome = (string) ($sorteio['empresaSelecionada']['nome'] ?? '');
    $agora = (string) ($sorteio['aceiteEm'] ?? date('Y-m-d H:i:s'));
    $detalhe = $nome . ($observacao !== '' ? ' — ' . $observacao : '');

    (new AuditService())->anexarEvento(
        $sorteioId,
        $decisao === 'aceito' ? 'demanda_aceita' : 'demanda_recusada',
        $detalhe,
        $sorteio['empresaSelecionada'] ?? null,
        [
            'aceiteStatus' => $sorteio['aceiteStatus'] ?? $decisao,
            'rodizioIds' => $sorteio['rodizioIds'] ?? [],
        ]
    );

    json_response([
        'ok' => true,
        'data' => $sorteio,
        'message' => $decisao === 'aceito'
            ? 'Aceite registrado. A empresa entra no rodízio e não participa dos próximos sorteios até o ciclo fechar.'
            : 'Recusa registrada. A empresa continua apta a participar dos próximos sorteios.',
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Erro ao registrar: ' . $e->getMessage()], 500);
}
