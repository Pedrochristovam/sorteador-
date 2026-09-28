<?php

declare(strict_types=1);

/**
 * Lista as tabelas do banco configurado no .env e a quantidade de linhas de cada uma.
 * Uso: php database/ver_banco.php
 */

require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/config/Database.php';

try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'Não foi possível conectar ao MySQL: ' . $e->getMessage() . "\n");
    exit(1);
}

$banco = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$versao = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
echo "MySQL $versao · banco: $banco\n\n";

$tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($tabelas === []) {
    echo "Nenhuma tabela criada neste banco.\n";
    exit(0);
}

foreach ($tabelas as $t) {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`')->fetchColumn();
    printf("  %-32s %6d linha(s)\n", $t, $n);
}
