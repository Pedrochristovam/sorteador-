<?php

declare(strict_types=1);

/**
 * Aplica schema do módulo jurídico e importa mocks de sociedades.
 *
 * Uso: php database/migrate_juridico.php
 */

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/services/JuridicoService.php';

$mysqlBin = 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe';
$schema = __DIR__ . '/schema_juridico.sql';
$root = dirname(__DIR__);

echo "==> Módulo Jurídico — migrate\n";
Database::enabled();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$name = $_ENV['DB_NAME'] ?? 'sorteador_convenios';

if (!Database::enabled()) {
    echo "DB_ENABLED=false — schema não aplicado no MySQL.\n";
    echo "Mocks em mocks/sociedades.php continuam disponíveis via arquivo.\n";
    // Garante storage a partir dos mocks
    $service = new JuridicoService();
    $n = count($service->listAll());
    echo "Sociedades carregadas (arquivo/mocks): {$n}\n";
    exit(0);
}

echo "==> Aplicando schema_juridico.sql em {$name}\n";

try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $_ENV['DB_PORT'] ?? '3306');
    $pdoRoot = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database {$name} OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao criar database: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if (is_file($mysqlBin) && is_file($schema)) {
    $cmd = [
        $mysqlBin,
        '-h', $host,
        '-u', $user,
        '-p' . $pass,
        '--default-character-set=utf8mb4',
        '-e', 'source ' . str_replace('\\', '/', $schema),
    ];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes, $root);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            fwrite(STDERR, "mysql.exe falhou (code {$code})\n{$err}\n{$out}\nTentando PDO...\n");
        } else {
            echo "Schema jurídico aplicado via mysql.exe\n";
        }
    }
}

try {
    $pdo = Database::pdo();
    // Fallback: executa statements se tabela ainda não existe
    try {
        $pdo->query('SELECT 1 FROM sociedades_advogados LIMIT 1');
        echo "Tabela sociedades_advogados OK\n";
    } catch (Throwable) {
        echo "Aplicando schema via PDO split...\n";
        $sql = (string) file_get_contents($schema);
        $sql = preg_replace('/^USE\s+\w+\s*;/mi', '', $sql) ?? $sql;
        $parts = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($parts as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                // ignora duplicatas
                if (!str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'Duplicate')) {
                    echo "  aviso: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    // Importa mocks se tabela vazia
    $count = (int) $pdo->query('SELECT COUNT(*) FROM sociedades_advogados')->fetchColumn();
    if ($count === 0) {
        echo "==> Importando mocks de sociedades...\n";
        $service = new JuridicoService();
        $mocks = require dirname(__DIR__) . '/mocks/sociedades.php';
        foreach ($mocks as $row) {
            $service->save($row, $row['id'] ?? null);
            echo "  + " . ($row['razaoSocial'] ?? $row['id']) . "\n";
        }
    } else {
        echo "Sociedades no banco: {$count}\n";
    }

    echo "Concluído.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
