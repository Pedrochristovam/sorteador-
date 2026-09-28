<?php

declare(strict_types=1);

/**
 * Instala schema e importa empresas do storage/empresas.json (ou mocks).
 *
 * Uso: php database/migrate.php
 */

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/types/Empresa.php';

$mysqlBin = 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe';
$schema = __DIR__ . '/schema.sql';
$root = dirname(__DIR__);

echo "==> Carregando .env\n";
Database::enabled(); // bootstrap env

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$name = $_ENV['DB_NAME'] ?? 'sorteador_convenios';

echo "==> Aplicando schema em {$name}\n";

if (is_file($mysqlBin)) {
    $cmd = [
        $mysqlBin,
        '-h', $host,
        '-u', $user,
        '-p' . $pass,
        '--default-character-set=utf8mb4',
        '-e', 'source ' . str_replace('\\', '/', $schema),
    ];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $root);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            fwrite(STDERR, "Falha ao aplicar schema via mysql.exe (code {$code})\n{$err}\n{$out}\n");
            // fallback PDO partial
        } else {
            echo "Schema aplicado via mysql.exe\n";
        }
    }
} else {
    echo "mysql.exe não encontrado no caminho padrão — tentando via PDO após create database...\n";
}

// Garante DB via PDO
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $_ENV['DB_PORT'] ?? '3306');
    $pdoRoot = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro conexão: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Reaplica schema via mysql client se falhou antes — ou executa SQL em pedaços
try {
    $pdo = Database::pdo();
    $pdo->query('SELECT 1 FROM empresas LIMIT 1');
    echo "Tabela empresas já existe\n";
} catch (Throwable $e) {
    echo "Aplicando schema via PDO (split)...\n";
    $sql = file_get_contents($schema);
    // Remove CREATE DATABASE / USE — já estamos no DB via DSN
    $sql = preg_replace('/CREATE DATABASE[\s\S]*?;/i', '', (string) $sql);
    $sql = preg_replace('/USE\s+\w+\s*;/i', '', (string) $sql);
    $statements = array_filter(array_map('trim', explode(';', (string) $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdoRoot->exec("USE `{$name}`");
            $pdoRoot->exec($stmt);
        } catch (Throwable $ex) {
            // ignora duplicatas em seeds
            if (!str_contains($ex->getMessage(), 'Duplicate') && !str_contains($ex->getMessage(), 'already exists')) {
                fwrite(STDERR, 'SQL warn: ' . $ex->getMessage() . "\n");
            }
        }
    }
    // reconnect with dbname
    $ref = new ReflectionClass(Database::class);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
}

$pdo = Database::pdo();

$jsonPath = $root . '/storage/empresas.json';
$empresas = [];
if (is_file($jsonPath)) {
    $empresas = json_decode((string) file_get_contents($jsonPath), true) ?: [];
    echo 'Importando de storage/empresas.json (' . count($empresas) . ")\n";
} else {
    $mockEmpresas = is_file($root . '/mocks/empresas.php') ? 'mocks/empresas.php' : 'mocks/empresas.exemplo.php';
    $empresas = require $root . '/' . $mockEmpresas;
    echo "Importando de {$mockEmpresas} (" . count($empresas) . ")\n";
}

$pdo->beginTransaction();
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('DELETE FROM empresa_regioes');
    $pdo->exec('DELETE FROM empresa_servicos');
    $pdo->exec('DELETE FROM empresas');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $insEmp = $pdo->prepare(
        'INSERT INTO empresas (
            id, nome, cnpj, cidade, uf, endereco, bairro, cep, status, data_convenio,
            numero_contrato, ordem_credenciamento, telefone, email, responsavel, observacoes, latitude, longitude,
            atende_acima_100km
        ) VALUES (
            :id, :nome, :cnpj, :cidade, :uf, :endereco, :bairro, :cep, :status, :data_convenio,
            :numero_contrato, :ordem_credenciamento, :telefone, :email, :responsavel, :observacoes, :latitude, :longitude,
            :atende_acima_100km
        )'
    );
    $insServ = $pdo->prepare(
        'INSERT INTO empresa_servicos (empresa_id, servico, codigo_atividade) VALUES (:empresa_id, :servico, :codigo)'
    );
    $insReg = $pdo->prepare(
        'INSERT INTO empresa_regioes (empresa_id, codigo_regiao, tipo) VALUES (:empresa_id, :codigo, :tipo)'
    );

    foreach ($empresas as $e) {
        $data = is_array($e) ? $e : [];
        $insEmp->execute([
            ':id' => $data['id'],
            ':nome' => $data['nome'],
            ':cnpj' => $data['cnpj'] ?? '',
            ':cidade' => $data['cidade'],
            ':uf' => $data['uf'] ?? 'MG',
            ':endereco' => $data['endereco'],
            ':bairro' => $data['bairro'] ?? '',
            ':cep' => $data['cep'] ?? '',
            ':status' => $data['status'] ?? 'ativa',
            ':data_convenio' => $data['dataConvenio'] ?? date('Y-m-d'),
            ':numero_contrato' => $data['numeroContrato'] ?? '',
            ':ordem_credenciamento' => (int) ($data['ordemCredenciamento'] ?? 0),
            ':telefone' => $data['telefone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':responsavel' => $data['responsavel'] ?? null,
            ':observacoes' => $data['observacoes'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':atende_acima_100km' => filter_var($data['atendeAcima100km'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ]);

        $codes = $data['atividadesCodigos'] ?? [];
        foreach ($data['servicos'] ?? [] as $i => $servico) {
            $insServ->execute([
                ':empresa_id' => $data['id'],
                ':servico' => $servico,
                ':codigo' => $codes[$i] ?? null,
            ]);
        }

        foreach ($data['regioesMunicipiosCodigos'] ?? [] as $code) {
            $insReg->execute([
                ':empresa_id' => $data['id'],
                ':codigo' => $code,
                ':tipo' => 'municipio_mg',
            ]);
        }
        foreach ($data['estadosAtuacaoCodigos'] ?? [] as $code) {
            $insReg->execute([
                ':empresa_id' => $data['id'],
                ':codigo' => $code,
                ':tipo' => 'estado',
            ]);
        }
    }

    $pdo->commit();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM empresas')->fetchColumn();
    echo "==> Migração concluída. Empresas no banco: {$count}\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Erro importação: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
