<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/utils/session.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';

sorteador_boot_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../' . page_url('empresas'));
    exit;
}

$id = trim((string) ($_POST['id'] ?? ''));
$companyService = new CompanyService();
$empresa = $id !== '' ? $companyService->findById($id) : null;

if ($empresa === null) {
    header('Location: ../' . page_url('empresas', ['erro' => 'Empresa não encontrada.']));
    exit;
}

try {
    $companyService->delete($empresa->id);
    header('Location: ../' . page_url('empresas', ['excluida' => $empresa->nome]));
} catch (Throwable $e) {
    header('Location: ../' . page_url('empresas', ['erro' => 'Não foi possível excluir: ' . $e->getMessage()]));
}
exit;
