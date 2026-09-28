<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';

sorteador_boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Método não permitido'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
$etapa = is_array($input) ? ($input['etapa'] ?? null) : null;

$fluxo = sorteador_get_fluxo() ?? [];

if (is_array($input) && isset($input['reset']) && $input['reset']) {
    sorteador_clear_fluxo();
    json_response(['ok' => true, 'reset' => true]);
}

if ($etapa) {
    $fluxo['etapa'] = $etapa;
    sorteador_save_fluxo($fluxo);
}

json_response(['ok' => true, 'fluxo' => $fluxo]);
