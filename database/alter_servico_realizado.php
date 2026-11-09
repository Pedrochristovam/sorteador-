<?php

declare(strict_types=1);

/**
 * Adiciona colunas de marcação manual de serviço realizado (rodízio).
 * Uso: php database/alter_servico_realizado.php
 */

require_once dirname(__DIR__) . '/config/Database.php';

if (!Database::enabled()) {
    fwrite(STDERR, "MySQL não configurado.\n");
    exit(1);
}

$pdo = Database::pdo();
$cols = $pdo->query('SHOW COLUMNS FROM empresas')->fetchAll(PDO::FETCH_COLUMN);

$add = static function (PDO $pdo, array $cols, string $name, string $ddl): void {
    if (in_array($name, $cols, true)) {
        echo "OK (já existe): {$name}\n";
        return;
    }
    $pdo->exec("ALTER TABLE empresas ADD COLUMN {$ddl}");
    echo "Adicionada: {$name}\n";
};

$add($pdo, $cols, 'servico_realizado', 'servico_realizado TINYINT(1) NOT NULL DEFAULT 0 AFTER atende_acima_100km');
$cols = $pdo->query('SHOW COLUMNS FROM empresas')->fetchAll(PDO::FETCH_COLUMN);
$add($pdo, $cols, 'servico_realizado_obs', 'servico_realizado_obs TEXT NULL AFTER servico_realizado');
$cols = $pdo->query('SHOW COLUMNS FROM empresas')->fetchAll(PDO::FETCH_COLUMN);
$add($pdo, $cols, 'servico_realizado_em', 'servico_realizado_em DATETIME NULL AFTER servico_realizado_obs');

echo "Pronto.\n";
