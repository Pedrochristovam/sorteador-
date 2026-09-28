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
    json_response(['ok' => false, 'message' => 'Requisição inválida.'], 400);
}

$sorteioId = trim((string) ($input['sorteioId'] ?? ''));
$valores = is_array($input['valores'] ?? null) ? $input['valores'] : [];

try {
    $sorteio = (new DrawService())->registrarOrcamentos($sorteioId, $valores, (string) ($input['observacao'] ?? ''));

    $linhas = array_map(
        static fn(array $o): string => $o['nome'] . ': ' . ($o['valor'] !== null ? format_moeda($o['valor']) : 'não enviou'),
        $sorteio['orcamentos']
    );
    $vencedora = $sorteio['empresaSelecionada'];
    $detalhe = implode(' · ', $linhas) . ' → ' . $vencedora['nome'] . ($sorteio['orcamentoEmpate'] ? ' (empate desfeito por sorteio)' : '');

    (new AuditService())->anexarEvento($sorteioId, 'orcamentos_registrados', $detalhe, $vencedora, [
        'orcamentos' => $sorteio['orcamentos'],
    ]);

    if (($_SESSION['sorteio_fluxo']['ultimoResultado']['sorteio']['id'] ?? '') === $sorteioId) {
        $_SESSION['sorteio_fluxo']['ultimoResultado']['sorteio'] = $sorteio;
        $_SESSION['sorteio_fluxo']['ultimoResultado']['resultado']['empresaSelecionada'] = $vencedora;
        $_SESSION['sorteio_fluxo']['ultimoResultado']['resultado']['orcamentos'] = $sorteio['orcamentos'];
        sorteador_save_fluxo($_SESSION['sorteio_fluxo']);
    }

    json_response(['ok' => true, 'data' => $sorteio]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
