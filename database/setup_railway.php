<?php

declare(strict_types=1);

/**
 * Bootstrap seguro para produção (Railway / Docker).
 * - aplica schemas se as tabelas ainda não existem
 * - importa dados de exemplo só quando as tabelas estão vazias
 * - NÃO apaga dados já existentes
 *
 * Uso: php database/setup_railway.php
 */

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/services/JuridicoService.php';

$root = dirname(__DIR__);

echo "==> Setup Railway / produção\n";

if (!Database::enabled()) {
    fwrite(STDERR, "DB_ENABLED não está true — defina DB_ENABLED=true nas variáveis do Railway.\n");
    exit(1);
}

$cfg = Database::connectionConfig();
echo "    host={$cfg['host']} port={$cfg['port']} db={$cfg['name']} user={$cfg['user']}\n";

// Aguarda o MySQL ficar pronto (plugin sobe em paralelo)
$pdo = null;
$ultimoErro = '';
for ($i = 1; $i <= 30; $i++) {
    try {
        $pdo = Database::pdo();
        $pdo->query('SELECT 1');
        echo "    MySQL OK (tentativa {$i})\n";
        break;
    } catch (Throwable $e) {
        $ultimoErro = $e->getMessage();
        echo "    aguardando MySQL... ({$i}/30)\n";
        // limpa singleton para nova tentativa
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        sleep(2);
    }
}
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Não conectou ao MySQL: {$ultimoErro}\n");
    exit(1);
}

$aplicarSql = static function (PDO $pdo, string $arquivo): void {
    $sql = (string) file_get_contents($arquivo);
    $sql = preg_replace('/CREATE DATABASE[\s\S]*?;/i', '', $sql) ?? $sql;
    $sql = preg_replace('/USE\s+\w+\s*;/i', '', $sql) ?? $sql;
    // Remove comentários de linha para não atrapalhar o split
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            if (
                str_contains($msg, 'Duplicate')
                || str_contains($msg, 'already exists')
                || str_contains($msg, '1050')
            ) {
                continue;
            }
            fwrite(STDERR, 'SQL warn: ' . $msg . "\n");
        }
    }
};

$temTabela = static function (PDO $pdo, string $tabela): bool {
    try {
        $pdo->query("SELECT 1 FROM `{$tabela}` LIMIT 1");
        return true;
    } catch (Throwable) {
        return false;
    }
};

echo "==> Schema Engenharia\n";
if (!$temTabela($pdo, 'empresas')) {
    $aplicarSql($pdo, "$root/database/schema.sql");
    echo "    tabelas criadas\n";
} else {
    echo "    já existe\n";
}

// extras_json (migração antiga)
try {
    if (!$pdo->query("SHOW COLUMNS FROM auditorias LIKE 'extras_json'")->fetch()) {
        $pdo->exec('ALTER TABLE auditorias ADD COLUMN extras_json JSON NULL AFTER versao_algoritmo');
        echo "    coluna auditorias.extras_json adicionada\n";
    }
} catch (Throwable) {
    // tabela pode não ter sido criada ainda em falha parcial
}

echo "==> Schema Jurídico\n";
if (!$temTabela($pdo, 'sociedades_advogados')) {
    $aplicarSql($pdo, "$root/database/schema_juridico.sql");
    echo "    tabelas criadas\n";
} else {
    echo "    já existe\n";
}

echo "==> Dados iniciais (só se vazio)\n";
$nEmpresas = (int) $pdo->query('SELECT COUNT(*) FROM empresas')->fetchColumn();
if ($nEmpresas === 0) {
    // Reusa a lógica de importação do migrate.php sem o DELETE destrutivo
    $mockEmpresas = is_file("$root/mocks/empresas.php") ? 'mocks/empresas.php' : 'mocks/empresas.exemplo.php';
    $empresas = require "$root/{$mockEmpresas}";
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
    $pdo->beginTransaction();
    try {
        foreach ($empresas as $data) {
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
                $insReg->execute([':empresa_id' => $data['id'], ':codigo' => $code, ':tipo' => 'municipio_mg']);
            }
            foreach ($data['estadosAtuacaoCodigos'] ?? [] as $code) {
                $insReg->execute([':empresa_id' => $data['id'], ':codigo' => $code, ':tipo' => 'estado']);
            }
        }
        $pdo->commit();
        echo '    empresas importadas de ' . $mockEmpresas . ': ' . count($empresas) . "\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, 'Falha ao importar empresas: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
} else {
    echo "    empresas: {$nEmpresas} (mantidas)\n";
}

$nSoc = (int) $pdo->query('SELECT COUNT(*) FROM sociedades_advogados')->fetchColumn();
if ($nSoc === 0 && is_file("$root/mocks/sociedades.php")) {
    require_once "$root/types/SociedadeAdvogados.php";
    $service = new JuridicoService();
    $mocks = require "$root/mocks/sociedades.php";
    foreach ($mocks as $row) {
        $service->save($row, $row['id'] ?? null);
    }
    echo '    sociedades importadas: ' . count($mocks) . "\n";
} else {
    echo "    sociedades: {$nSoc} (mantidas)\n";
}

echo "==> Pronto\n";
