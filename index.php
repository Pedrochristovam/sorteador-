<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

// As páginas são incluídas após o header do layout; sem buffer, redirect() após POST falha.
ob_start();

require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/utils/session.php';
require_once __DIR__ . '/services/CompanyService.php';
require_once __DIR__ . '/services/EligibilityService.php';
require_once __DIR__ . '/services/DrawService.php';
require_once __DIR__ . '/services/AuditService.php';
require_once __DIR__ . '/services/JuridicoService.php';
require_once __DIR__ . '/types/SociedadeAdvogados.php';

sorteador_boot_session();

$allowedPages = [
    'dashboard',
    'empresas',
    'empresa-detalhe',
    'empresa-form',
    'novo-sorteio',
    'historico',
    'historico-detalhe',
    'auditoria',
    'resultado',
    // Módulo Jurídico — Edital 01/2026
    'juridico-dashboard',
    'juridico-sociedades',
    'juridico-sociedade-form',
    'juridico-sociedade-detalhe',
    'juridico-sorteio',
    'juridico-historico',
];

$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$pageTitles = [
    'dashboard' => 'Dashboard',
    'empresas' => 'Empresas Credenciadas',
    'empresa-detalhe' => 'Detalhes da Empresa',
    'empresa-form' => 'Cadastro de Empresa',
    'novo-sorteio' => 'Novo Sorteio',
    'historico' => 'Histórico de Sorteios',
    'historico-detalhe' => 'Detalhe do Sorteio',
    'auditoria' => 'Regulamentos & Auditoria',
    'resultado' => 'Resultado do Sorteio',
    'juridico-dashboard' => 'Módulo Jurídico',
    'juridico-sociedades' => 'Sociedades de Advogados',
    'juridico-sociedade-form' => 'Cadastro de Sociedade',
    'juridico-sociedade-detalhe' => 'Ficha da Sociedade',
    'juridico-sorteio' => 'Sorteio Jurídico',
    'juridico-historico' => 'Histórico Jurídico',
];

if ($page === 'empresa-form') {
    $pageTitles['empresa-form'] = !empty($_GET['id']) ? 'Editar Empresa' : 'Nova Empresa';
}
if ($page === 'juridico-sociedade-form') {
    $pageTitles['juridico-sociedade-form'] = !empty($_GET['id']) ? 'Editar Sociedade' : 'Nova Sociedade';
}

$isJuridico = str_starts_with($page, 'juridico-');
$currentPage = $page;
$pageTitle = $pageTitles[$page] ?? 'Sorteador';
$user = current_user();
$pageScripts = [];

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!is_file($pageFile)) {
    http_response_code(404);
    $pageFile = __DIR__ . '/pages/dashboard.php';
    $currentPage = 'dashboard';
    $pageTitle = 'Dashboard';
    $isJuridico = false;
}

require __DIR__ . '/layouts/main.php';
