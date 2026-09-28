<?php

declare(strict_types=1);

/**
 * Leva os dados de Engenharia dos arquivos JSON (storage/) para o MySQL.
 * - faz backup dos JSON em storage/backup-AAAAMMDD-HHMMSS/
 * - cria as tabelas (schema.sql) e importa as empresas (migrate.php)
 * - importa sorteios e auditorias (arquivo + exemplos que o sistema já exibia)
 * - confere se o sistema enxerga no banco o mesmo que via nos arquivos
 * Pode ser executado de novo: os registros são atualizados, não duplicados.
 *
 * Uso: php database/migrar_para_mysql.php
 */

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/services/CompanyService.php';
require_once dirname(__DIR__) . '/services/DrawService.php';
require_once dirname(__DIR__) . '/services/AuditService.php';

$root = dirname(__DIR__);
$storage = "$root/storage";
$ler = static fn(string $p): array => is_file($p) ? (json_decode((string) file_get_contents($p), true) ?: []) : [];

// Retrato do que o sistema exibe hoje (modo arquivo), antes de criar as tabelas
$antes = [
    'empresas' => array_map(static fn($e) => $e->toArray(), (new CompanyService())->listAll()),
    'sorteios' => count((new DrawService())->listHistorico()),
    'auditorias' => count((new AuditService())->listAll()),
];

echo "==> Backup dos arquivos\n";
$backup = "$storage/backup-" . date('Ymd-His');
if (!is_dir($backup)) {
    mkdir($backup, 0775, true);
}
foreach (glob("$storage/*.json") ?: [] as $arq) {
    if (!str_starts_with(basename($arq), 'fluxo_')) {
        copy($arq, $backup . '/' . basename($arq));
    }
}
echo '    ' . str_replace($root . '/', '', $backup) . "\n";

echo "==> Tabelas e empresas (migrate.php)\n";
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/migrate.php'), $codigo);
if ($codigo !== 0) {
    fwrite(STDERR, "Falha no migrate.php — nada mais foi alterado. Os arquivos continuam valendo.\n");
    exit(1);
}

$pdo = Database::pdo();
foreach (['empresas', 'sorteios', 'auditorias'] as $tabela) {
    $pdo->query("SELECT 1 FROM {$tabela} LIMIT 1");
}
$temExtrasAud = (bool) $pdo->query("SHOW COLUMNS FROM auditorias LIKE 'extras_json'")->fetch();
if (!$temExtrasAud) {
    $pdo->exec('ALTER TABLE auditorias ADD COLUMN extras_json JSON NULL AFTER versao_algoritmo');
    echo "    coluna auditorias.extras_json adicionada\n";
}

echo "==> Sorteios\n";
$sorteios = array_merge($ler("$storage/sorteios_session.json"), require "$root/mocks/sorteios.php");
$drawService = new DrawService();
foreach ($sorteios as $s) {
    $drawService->importar($s);
}
echo '    ' . count($sorteios) . " importados\n";

echo "==> Auditorias\n";
$auditorias = array_merge($ler("$storage/auditorias_session.json"), require "$root/mocks/auditorias.php");
$auditService = new AuditService();
foreach ($auditorias as $a) {
    $auditService->importar($a);
}
echo '    ' . count($auditorias) . " importadas\n";

echo "==> Conferência (o que o sistema passa a ler do MySQL)\n";
$depoisEmpresas = [];
foreach ((new CompanyService())->listAll() as $e) {
    $depoisEmpresas[$e->id] = $e->toArray();
}
$diferentes = [];
foreach ($antes['empresas'] as $e) {
    $db = $depoisEmpresas[$e['id']] ?? null;
    if ($db === null) {
        $diferentes[] = "{$e['id']} ausente";
        continue;
    }
    foreach (['nome', 'cidade', 'uf', 'endereco', 'status', 'atividadesCodigos', 'regioesMunicipiosCodigos', 'estadosAtuacaoCodigos', 'atendeAcima100km'] as $campo) {
        $a = $e[$campo] ?? null;
        $b = $db[$campo] ?? null;
        if (is_array($a) && is_array($b)) {
            sort($a);
            sort($b);
        }
        if ($a != $b) {
            $diferentes[] = "{$e['id']}.{$campo}";
        }
    }
}

$sorteiosDb = count((new DrawService())->listHistorico());
$audDb = count((new AuditService())->listAll());
printf("    empresas:   %d no arquivo → %d no banco%s\n", count($antes['empresas']), count($depoisEmpresas), $diferentes === [] ? ' (dados idênticos)' : '');
printf("    sorteios:   %d exibidos antes → %d no banco\n", $antes['sorteios'], $sorteiosDb);
printf("    auditorias: %d exibidas antes → %d no banco\n", $antes['auditorias'], $audDb);

if ($diferentes !== [] || $sorteiosDb !== $antes['sorteios'] || $audDb !== $antes['auditorias']) {
    fwrite(STDERR, "ATENÇÃO — divergências: " . implode(', ', array_slice($diferentes, 0, 20)) . "\n");
    exit(2);
}
echo "==> Pronto. O sistema agora lê e grava no MySQL. Backup dos JSON em " . basename($backup) . "\n";
