<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/types/Empresa.php';
require_once dirname(__DIR__) . '/types/Criterio.php';
require_once dirname(__DIR__) . '/config/Database.php';

/**
 * Serviço de empresas credenciadas.
 * Usa MySQL quando DB_ENABLED=true; caso contrário, JSON/mocks.
 */
final class CompanyService
{
    private string $storagePath;

    /** @var list<Empresa>|null */
    private static ?array $cache = null;

    private static ?bool $dbReady = null;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? dirname(__DIR__) . '/storage/empresas.json';
    }

    /** Leitura e gravação precisam usar a mesma fonte: só usa MySQL se a tabela existir. */
    private function useDb(): bool
    {
        if (self::$dbReady !== null) {
            return self::$dbReady;
        }
        try {
            if (!Database::enabled()) {
                return self::$dbReady = false;
            }
            Database::pdo()->query('SELECT 1 FROM empresas LIMIT 1');
            return self::$dbReady = true;
        } catch (Throwable) {
            return self::$dbReady = false;
        }
    }

    /** @return list<Empresa> */
    public function listAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if ($this->useDb()) {
            try {
                self::$cache = $this->listAllFromDb();
                return self::$cache;
            } catch (Throwable $e) {
                // fallback silencioso para arquivo
            }
        }

        self::$cache = array_map(
            static fn(array $row): Empresa => Empresa::fromArray($row),
            $this->loadRawFile()
        );
        return self::$cache;
    }

    /** @return list<Empresa> */
    private function listAllFromDb(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT * FROM empresas ORDER BY ordem_credenciamento ASC, nome ASC')->fetchAll();
        $empresas = [];
        foreach ($rows as $row) {
            $empresas[] = $this->hydrateEmpresa($row);
        }
        return $empresas;
    }

    private function hydrateEmpresa(array $row): Empresa
    {
        $pdo = Database::pdo();
        $id = $row['id'];

        $servStmt = $pdo->prepare('SELECT servico, codigo_atividade FROM empresa_servicos WHERE empresa_id = ?');
        $servStmt->execute([$id]);
        $servicos = [];
        $atCodes = [];
        foreach ($servStmt->fetchAll() as $s) {
            $servicos[] = $s['servico'];
            if (!empty($s['codigo_atividade'])) {
                $atCodes[] = $s['codigo_atividade'];
            }
        }

        $regStmt = $pdo->prepare('SELECT codigo_regiao, tipo FROM empresa_regioes WHERE empresa_id = ?');
        $regStmt->execute([$id]);
        $catalogo = $this->getCatalogo();
        $mun = [];
        $munCodes = [];
        $est = [];
        $estCodes = [];
        foreach ($regStmt->fetchAll() as $r) {
            $code = $r['codigo_regiao'];
            if ($r['tipo'] === 'municipio_mg') {
                $munCodes[] = $code;
                $mun[] = $code . ' - ' . ($catalogo['regioesMunicipios'][$code] ?? $code);
            } else {
                $estCodes[] = $code;
                $est[] = $code . ' - ' . ($catalogo['estadosAtuacao'][$code] ?? $code);
            }
        }

        return Empresa::fromArray([
            'id' => $row['id'],
            'nome' => $row['nome'],
            'cnpj' => $row['cnpj'] ?? '',
            'cidade' => $row['cidade'],
            'uf' => $row['uf'] ?? 'MG',
            'endereco' => $row['endereco'],
            'bairro' => $row['bairro'] ?? '',
            'cep' => $row['cep'] ?? '',
            'servicos' => $servicos,
            'status' => $row['status'],
            'dataConvenio' => $row['data_convenio'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'telefone' => $row['telefone'],
            'email' => $row['email'],
            'responsavel' => $row['responsavel'],
            'observacoes' => $row['observacoes'],
            'numeroContrato' => $row['numero_contrato'] ?? '',
            'ordemCredenciamento' => (int) ($row['ordem_credenciamento'] ?? 0),
            'atividadesCodigos' => $atCodes,
            'regioesMunicipios' => $mun,
            'regioesMunicipiosCodigos' => $munCodes,
            'estadosAtuacao' => $est,
            'estadosAtuacaoCodigos' => $estCodes,
            'atendeAcima100km' => (bool) ($row['atende_acima_100km'] ?? true),
        ]);
    }

    /** @return list<array> */
    private function loadRawFile(): array
    {
        if (is_file($this->storagePath)) {
            $json = file_get_contents($this->storagePath);
            $data = json_decode((string) $json, true);
            if (is_array($data) && $data !== []) {
                return array_values($data);
            }
        }
        $base = dirname(__DIR__) . '/mocks/empresas.php';
        $mock = require (is_file($base) ? $base : dirname(__DIR__) . '/mocks/empresas.exemplo.php');
        return is_array($mock) ? array_values($mock) : [];
    }

    /** @param list<array> $rows */
    private function persistFile(array $rows): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $this->storagePath,
            json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        self::$cache = null;
    }

    public function findById(string $id): ?Empresa
    {
        foreach ($this->listAll() as $empresa) {
            if ($empresa->id === $id) {
                return $empresa;
            }
        }
        return null;
    }

    /**
     * @param array{busca?:string,cidade?:string,status?:string,servico?:string} $filters
     * @return list<Empresa>
     */
    public function search(array $filters = []): array
    {
        $result = $this->listAll();

        if (!empty($filters['busca'])) {
            $q = mb_strtolower(trim($filters['busca']));
            $result = array_values(array_filter(
                $result,
                static function (Empresa $e) use ($q): bool {
                    $haystack = mb_strtolower(
                        $e->nome . ' ' . $e->cnpj . ' ' . $e->cidade . ' ' . $e->endereco . ' ' . $e->numeroContrato . ' ' . $e->email
                    );
                    return str_contains($haystack, $q);
                }
            ));
        }

        if (!empty($filters['cidade'])) {
            $cidade = mb_strtolower(trim($filters['cidade']));
            $result = array_values(array_filter(
                $result,
                static fn(Empresa $e): bool => str_contains(mb_strtolower($e->cidade), $cidade)
            ));
        }

        if (!empty($filters['status'])) {
            $status = strtolower($filters['status']);
            $result = array_values(array_filter(
                $result,
                static fn(Empresa $e): bool => strtolower($e->status) === $status
            ));
        }

        if (!empty($filters['servico'])) {
            $servico = $filters['servico'];
            $result = array_values(array_filter(
                $result,
                static fn(Empresa $e): bool => in_array($servico, $e->servicos, true)
            ));
        }

        return $result;
    }

    /** @return list<string> */
    public function listCidades(): array
    {
        $cidades = array_map(static fn(Empresa $e): string => $e->cidade, $this->listAll());
        $cidades = array_unique($cidades);
        sort($cidades);
        return array_values($cidades);
    }

    /** @return list<string> */
    public function listServicos(): array
    {
        $catalogo = $this->getCatalogo();
        $servicos = array_values($catalogo['atividades'] ?? []);
        foreach ($this->listAll() as $empresa) {
            foreach ($empresa->servicos as $s) {
                if (!in_array($s, $servicos, true)) {
                    $servicos[] = $s;
                }
            }
        }
        sort($servicos);
        return $servicos;
    }

    public function countAll(): int
    {
        return count($this->listAll());
    }

    public function countAtivas(): int
    {
        return count(array_filter(
            $this->listAll(),
            static fn(Empresa $e): bool => $e->isAtiva()
        ));
    }

    /** @return list<Criterio> */
    public function listCriterios(): array
    {
        if ($this->useDb()) {
            try {
                $rows = Database::pdo()->query(
                    'SELECT codigo, nome, descricao, obrigatorio, tipo FROM criterios WHERE ativo = 1'
                )->fetchAll();
                return array_map(
                    static fn(array $row): Criterio => Criterio::fromArray([
                        'codigo' => $row['codigo'],
                        'nome' => $row['nome'],
                        'descricao' => $row['descricao'],
                        'obrigatorio' => (bool) $row['obrigatorio'],
                        'tipo' => $row['tipo'],
                    ]),
                    $rows
                );
            } catch (Throwable) {
                // fallback
            }
        }

        $raw = require dirname(__DIR__) . '/mocks/criterios.php';
        return array_map(
            static fn(array $row): Criterio => Criterio::fromArray($row),
            $raw
        );
    }

    /** @return list<string> */
    public function listTiposDemanda(): array
    {
        return ['Laudo de avaliação', 'Levantamento topográfico', 'Pontual', 'Emergencial'];
    }

    /** @return array{atividades:array<string,string>,regioesMunicipios:array<string,string>,estadosAtuacao:array<string,string>} */
    public function getCatalogo(): array
    {
        if ($this->useDb()) {
            try {
                $pdo = Database::pdo();
                $atividades = [];
                foreach ($pdo->query('SELECT codigo, nome FROM atividades_catalogo WHERE ativo = 1') as $row) {
                    $atividades[$row['codigo']] = $row['nome'];
                }
                $regMun = [];
                $est = [];
                foreach ($pdo->query('SELECT codigo, nome, tipo FROM regioes_catalogo WHERE ativo = 1') as $row) {
                    if ($row['tipo'] === 'municipio_mg') {
                        $regMun[$row['codigo']] = $row['nome'];
                    } else {
                        $est[$row['codigo']] = $row['nome'];
                    }
                }
                if ($atividades !== []) {
                    $referencia = require dirname(__DIR__) . '/mocks/catalogo_credenciamento.php';
                    $naOrdem = static function (array $itens, array $ordem): array {
                        $pos = array_flip(array_keys($ordem));
                        uksort($itens, static fn($a, $b) => [$pos[$a] ?? PHP_INT_MAX, $a] <=> [$pos[$b] ?? PHP_INT_MAX, $b]);
                        return $itens;
                    };
                    return [
                        'atividades' => $naOrdem($atividades, $referencia['atividades']),
                        'regioesMunicipios' => $naOrdem($regMun, $referencia['regioesMunicipios']),
                        'estadosAtuacao' => $naOrdem($est, $referencia['estadosAtuacao']),
                    ];
                }
            } catch (Throwable) {
                // fallback
            }
        }

        return require dirname(__DIR__) . '/mocks/catalogo_credenciamento.php';
    }

    public function nextId(): string
    {
        $max = 0;
        foreach ($this->listAll() as $empresa) {
            if (preg_match('/EMP-(\d+)/', $empresa->id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('EMP-%03d', $max + 1);
    }

    /**
     * @param array<string,mixed> $input
     */
    public function save(array $input, ?string $id = null): Empresa
    {
        $catalogo = $this->getCatalogo();
        $atividadesMap = $catalogo['atividades'] ?? [];
        $regMunMap = $catalogo['regioesMunicipios'] ?? [];
        $estMap = $catalogo['estadosAtuacao'] ?? [];

        $atCodes = array_values(array_filter(
            (array) ($input['atividadesCodigos'] ?? []),
            static fn($c): bool => is_string($c) && $c !== ''
        ));
        $munCodes = array_values(array_filter(
            (array) ($input['regioesMunicipiosCodigos'] ?? []),
            static fn($c): bool => is_string($c) && $c !== ''
        ));
        $estCodes = array_values(array_filter(
            (array) ($input['estadosAtuacaoCodigos'] ?? []),
            static fn($c): bool => is_string($c) && $c !== ''
        ));

        $servicos = [];
        foreach ($atCodes as $code) {
            if (isset($atividadesMap[$code])) {
                $servicos[] = $atividadesMap[$code];
            }
        }
        if ($servicos === [] && !empty($input['servicos']) && is_array($input['servicos'])) {
            $servicos = array_values(array_filter($input['servicos']));
        }
        if ($servicos === []) {
            $servicos = ['Credenciamento'];
        }

        $isNew = $id === null || $id === '';
        $empresaId = $isNew ? $this->nextId() : $id;

        $payload = [
            'id' => $empresaId,
            'nome' => trim((string) ($input['nome'] ?? '')),
            'cnpj' => trim((string) ($input['cnpj'] ?? '')),
            'cidade' => trim((string) ($input['cidade'] ?? '')),
            'uf' => strtoupper(trim((string) ($input['uf'] ?? 'MG'))),
            'endereco' => trim((string) ($input['endereco'] ?? '')),
            'bairro' => trim((string) ($input['bairro'] ?? '')),
            'cep' => trim((string) ($input['cep'] ?? '')),
            'servicos' => $servicos,
            'atividadesCodigos' => $atCodes,
            'regioesMunicipios' => array_map(
                static fn(string $c): string => $c . ' - ' . ($regMunMap[$c] ?? $c),
                $munCodes
            ),
            'regioesMunicipiosCodigos' => $munCodes,
            'estadosAtuacao' => array_map(
                static fn(string $c): string => $c . ' - ' . ($estMap[$c] ?? $c),
                $estCodes
            ),
            'estadosAtuacaoCodigos' => $estCodes,
            'status' => trim((string) ($input['status'] ?? 'ativa')) ?: 'ativa',
            'dataConvenio' => trim((string) ($input['dataConvenio'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            'numeroContrato' => trim((string) ($input['numeroContrato'] ?? '')),
            'ordemCredenciamento' => (int) ($input['ordemCredenciamento'] ?? 0),
            'atendeAcima100km' => filter_var($input['atendeAcima100km'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'telefone' => trim((string) ($input['telefone'] ?? '')) ?: null,
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'responsavel' => trim((string) ($input['responsavel'] ?? '')) ?: null,
            'observacoes' => trim((string) ($input['observacoes'] ?? '')) ?: null,
            'latitude' => isset($input['latitude']) && $input['latitude'] !== '' ? (float) $input['latitude'] : null,
            'longitude' => isset($input['longitude']) && $input['longitude'] !== '' ? (float) $input['longitude'] : null,
        ];

        if ($payload['nome'] === '' || $payload['cidade'] === '' || $payload['endereco'] === '') {
            throw new InvalidArgumentException('Nome, cidade e endereço são obrigatórios.');
        }

        if ($this->useDb()) {
            $this->saveToDb($payload);
        } else {
            $rows = $this->loadRawFile();
            $found = false;
            foreach ($rows as $i => $row) {
                if (($row['id'] ?? '') === $empresaId) {
                    $rows[$i] = $payload;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $rows[] = $payload;
            }
            $this->persistFile($rows);
        }

        self::$cache = null;
        return Empresa::fromArray($payload);
    }

    /** @param array<string,mixed> $payload */
    private function saveToDb(array $payload): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $sql = 'INSERT INTO empresas (
                id, nome, cnpj, cidade, uf, endereco, bairro, cep, status, data_convenio,
                numero_contrato, ordem_credenciamento, atende_acima_100km, telefone, email, responsavel, observacoes, latitude, longitude
            ) VALUES (
                :id, :nome, :cnpj, :cidade, :uf, :endereco, :bairro, :cep, :status, :data_convenio,
                :numero_contrato, :ordem_credenciamento, :atende_acima_100km, :telefone, :email, :responsavel, :observacoes, :latitude, :longitude
            ) ON DUPLICATE KEY UPDATE
                nome=VALUES(nome), cnpj=VALUES(cnpj), cidade=VALUES(cidade), uf=VALUES(uf),
                endereco=VALUES(endereco), bairro=VALUES(bairro), cep=VALUES(cep), status=VALUES(status),
                data_convenio=VALUES(data_convenio), numero_contrato=VALUES(numero_contrato),
                ordem_credenciamento=VALUES(ordem_credenciamento), atende_acima_100km=VALUES(atende_acima_100km), telefone=VALUES(telefone),
                email=VALUES(email), responsavel=VALUES(responsavel), observacoes=VALUES(observacoes),
                latitude=VALUES(latitude), longitude=VALUES(longitude)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $payload['id'],
                ':nome' => $payload['nome'],
                ':cnpj' => $payload['cnpj'],
                ':cidade' => $payload['cidade'],
                ':uf' => $payload['uf'],
                ':endereco' => $payload['endereco'],
                ':bairro' => $payload['bairro'],
                ':cep' => $payload['cep'],
                ':status' => $payload['status'],
                ':data_convenio' => $payload['dataConvenio'],
                ':numero_contrato' => $payload['numeroContrato'],
                ':ordem_credenciamento' => $payload['ordemCredenciamento'],
                ':atende_acima_100km' => $payload['atendeAcima100km'] ? 1 : 0,
                ':telefone' => $payload['telefone'],
                ':email' => $payload['email'],
                ':responsavel' => $payload['responsavel'],
                ':observacoes' => $payload['observacoes'],
                ':latitude' => $payload['latitude'],
                ':longitude' => $payload['longitude'],
            ]);

            $pdo->prepare('DELETE FROM empresa_servicos WHERE empresa_id = ?')->execute([$payload['id']]);
            $pdo->prepare('DELETE FROM empresa_regioes WHERE empresa_id = ?')->execute([$payload['id']]);

            $insServ = $pdo->prepare(
                'INSERT INTO empresa_servicos (empresa_id, servico, codigo_atividade) VALUES (?, ?, ?)'
            );
            foreach ($payload['servicos'] as $i => $servico) {
                $insServ->execute([
                    $payload['id'],
                    $servico,
                    $payload['atividadesCodigos'][$i] ?? null,
                ]);
            }

            $insReg = $pdo->prepare(
                'INSERT INTO empresa_regioes (empresa_id, codigo_regiao, tipo) VALUES (?, ?, ?)'
            );
            foreach ($payload['regioesMunicipiosCodigos'] as $code) {
                $insReg->execute([$payload['id'], $code, 'municipio_mg']);
            }
            foreach ($payload['estadosAtuacaoCodigos'] as $code) {
                $insReg->execute([$payload['id'], $code, 'estado']);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param list<string> $ids
     * @return int quantidade de empresas alteradas
     */
    public function setStatusMany(array $ids, string $status): int
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($v): bool => is_string($v) && $v !== '')));
        if ($ids === [] || !in_array($status, ['ativa', 'inativa'], true)) {
            return 0;
        }

        if ($this->useDb()) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::pdo()->prepare("UPDATE empresas SET status = ? WHERE id IN ($in) AND status <> ?");
            $stmt->execute(array_merge([$status], $ids, [$status]));
            self::$cache = null;
            return $stmt->rowCount();
        }

        $rows = $this->loadRawFile();
        $alvo = array_flip($ids);
        $count = 0;
        foreach ($rows as $i => $row) {
            if (isset($alvo[$row['id'] ?? '']) && ($row['status'] ?? 'ativa') !== $status) {
                $rows[$i]['status'] = $status;
                $count++;
            }
        }
        if ($count > 0) {
            $this->persistFile($rows);
        }
        return $count;
    }

    /**
     * @param list<string> $ids
     * @return int quantidade de empresas excluídas
     */
    public function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($v): bool => is_string($v) && $v !== '')));
        if ($ids === []) {
            return 0;
        }

        if ($this->useDb()) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::pdo()->prepare("DELETE FROM empresas WHERE id IN ($in)");
            $stmt->execute($ids);
            self::$cache = null;
            return $stmt->rowCount();
        }

        $rows = $this->loadRawFile();
        $alvo = array_flip($ids);
        $filtered = array_values(array_filter(
            $rows,
            static fn(array $row): bool => !isset($alvo[$row['id'] ?? ''])
        ));
        $count = count($rows) - count($filtered);
        if ($count > 0) {
            $this->persistFile($filtered);
        }
        return $count;
    }

    public function delete(string $id): bool
    {
        if ($this->useDb()) {
            $stmt = Database::pdo()->prepare('DELETE FROM empresas WHERE id = ?');
            $stmt->execute([$id]);
            self::$cache = null;
            return $stmt->rowCount() > 0;
        }

        $rows = $this->loadRawFile();
        $filtered = array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['id'] ?? '') !== $id
        ));
        if (count($filtered) === count($rows)) {
            return false;
        }
        $this->persistFile($filtered);
        return true;
    }
}
