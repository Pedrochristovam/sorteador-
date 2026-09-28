<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/types/SociedadeAdvogados.php';
require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/config/Database.php';

/**
 * Serviço do módulo Jurídico — Edital 01/2026 (Sociedades de Advogados).
 * Persistência MySQL quando disponível; fallback em storage/sociedades.json + mocks.
 */
final class JuridicoService
{
    private string $storagePath;

    public function __construct()
    {
        $this->storagePath = dirname(__DIR__) . '/storage/sociedades.json';
    }

    /** @return array{atividades:array<string,string>,polos:array<string,string>,criterios:list<array>} */
    public function getCatalogo(): array
    {
        if ($this->dbEnabled()) {
            try {
                $pdo = Database::pdo();
                $atividades = [];
                foreach ($pdo->query('SELECT codigo, nome FROM juridico_atividades_catalogo WHERE ativo = 1 ORDER BY nome') as $row) {
                    $atividades[$row['codigo']] = $row['nome'];
                }
                $polos = [];
                foreach ($pdo->query('SELECT codigo, nome FROM juridico_polos_catalogo WHERE ativo = 1 ORDER BY nome') as $row) {
                    $polos[$row['codigo']] = $row['nome'];
                }
                $criterios = [];
                foreach ($pdo->query('SELECT codigo, nome, descricao, obrigatorio FROM juridico_criterios WHERE ativo = 1') as $row) {
                    $criterios[] = [
                        'codigo' => $row['codigo'],
                        'nome' => $row['nome'],
                        'descricao' => $row['descricao'],
                        'obrigatorio' => (bool) $row['obrigatorio'],
                    ];
                }
                if ($atividades !== []) {
                    return compact('atividades', 'polos', 'criterios');
                }
            } catch (Throwable) {
                // fallback
            }
        }

        return require dirname(__DIR__) . '/mocks/juridico_catalogo.php';
    }

    /** @return list<SociedadeAdvogados> */
    public function listAll(): array
    {
        if ($this->dbEnabled()) {
            try {
                return $this->listFromDb();
            } catch (Throwable) {
                // fallback
            }
        }

        return $this->listFromFiles();
    }

    public function findById(string $id): ?SociedadeAdvogados
    {
        foreach ($this->listAll() as $s) {
            if ($s->id === $id) {
                return $s;
            }
        }
        return null;
    }

    /**
     * @param array{busca?:string,status?:string,statusOperacional?:string,polo?:string,atividade?:string} $filters
     * @return list<SociedadeAdvogados>
     */
    public function search(array $filters): array
    {
        $items = $this->listAll();
        $busca = mb_strtolower(trim((string) ($filters['busca'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));
        $operacional = trim((string) ($filters['statusOperacional'] ?? ''));
        $polo = trim((string) ($filters['polo'] ?? ''));
        $atividade = trim((string) ($filters['atividade'] ?? ''));

        return array_values(array_filter($items, static function (SociedadeAdvogados $s) use ($busca, $status, $operacional, $polo, $atividade): bool {
            if ($status !== '' && strcasecmp($s->status, $status) !== 0) {
                return false;
            }
            if ($operacional !== '' && strcasecmp($s->statusOperacional, $operacional) !== 0) {
                return false;
            }
            if ($polo !== '' && !in_array($polo, $s->polosCodigos, true) && !in_array($polo, $s->polos, true)) {
                return false;
            }
            if ($atividade !== '' && !in_array($atividade, $s->atividadesCodigos, true) && !in_array($atividade, $s->atividades, true)) {
                return false;
            }
            if ($busca !== '') {
                $hay = mb_strtolower(implode(' ', [
                    $s->razaoSocial, $s->nomeFantasia, $s->cnpj, $s->oabSociedade,
                    $s->cidadeSede, $s->email ?? '', $s->numeroContrato, $s->responsavel ?? '',
                ]));
                if (!str_contains($hay, $busca)) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function countAtivas(): int
    {
        return count(array_filter($this->listAll(), static fn(SociedadeAdvogados $s): bool => $s->isAtiva()));
    }

    public function countAptas(): int
    {
        return count(array_filter($this->listAll(), static fn(SociedadeAdvogados $s): bool => $s->isAptaSorteio()));
    }

    /**
     * @param array<string,mixed> $input
     */
    public function save(array $input, ?string $id = null): SociedadeAdvogados
    {
        $catalogo = $this->getCatalogo();
        $atividadesCodigos = array_values(array_filter(array_map('strval', $input['atividadesCodigos'] ?? [])));
        $polosCodigos = array_values(array_filter(array_map('strval', $input['polosCodigos'] ?? [])));

        $atividades = [];
        foreach ($atividadesCodigos as $code) {
            if (isset($catalogo['atividades'][$code])) {
                $atividades[] = $catalogo['atividades'][$code];
            }
        }
        $polos = [];
        foreach ($polosCodigos as $code) {
            if (isset($catalogo['polos'][$code])) {
                $polos[] = $catalogo['polos'][$code];
            }
        }

        $nome = trim((string) ($input['razaoSocial'] ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Razão social é obrigatória.');
        }

        $payload = [
            'id' => $id ?: generate_id('SOC'),
            'razaoSocial' => $nome,
            'nomeFantasia' => trim((string) ($input['nomeFantasia'] ?? '')),
            'cnpj' => trim((string) ($input['cnpj'] ?? '')),
            'oabSociedade' => trim((string) ($input['oabSociedade'] ?? '')),
            'cidadeSede' => trim((string) ($input['cidadeSede'] ?? '')),
            'ufSede' => trim((string) ($input['ufSede'] ?? 'MG')) ?: 'MG',
            'endereco' => trim((string) ($input['endereco'] ?? '')),
            'bairro' => trim((string) ($input['bairro'] ?? '')),
            'cep' => trim((string) ($input['cep'] ?? '')),
            'status' => in_array(($input['status'] ?? 'ativa'), ['ativa', 'inativa', 'suspensa'], true)
                ? (string) $input['status'] : 'ativa',
            'statusOperacional' => in_array(($input['statusOperacional'] ?? 'apto'), ['apto', 'indisponivel_demanda', 'contemplado'], true)
                ? (string) $input['statusOperacional'] : 'apto',
            'dataCredenciamento' => trim((string) ($input['dataCredenciamento'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            'numeroContrato' => trim((string) ($input['numeroContrato'] ?? '')),
            'ordemCredenciamento' => (int) ($input['ordemCredenciamento'] ?? 0),
            'atividadesCodigos' => $atividadesCodigos,
            'atividades' => $atividades,
            'polosCodigos' => $polosCodigos,
            'polos' => $polos,
            'telefone' => trim((string) ($input['telefone'] ?? '')) ?: null,
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'responsavel' => trim((string) ($input['responsavel'] ?? '')) ?: null,
            'oabResponsavel' => trim((string) ($input['oabResponsavel'] ?? '')),
            'processoSei' => trim((string) ($input['processoSei'] ?? '')),
            'observacoes' => trim((string) ($input['observacoes'] ?? '')) ?: null,
        ];

        if ($payload['cidadeSede'] === '') {
            throw new InvalidArgumentException('Cidade sede é obrigatória.');
        }

        $sociedade = SociedadeAdvogados::fromArray($payload);

        if ($this->dbEnabled()) {
            try {
                $this->persistDb($sociedade);
                return $sociedade;
            } catch (Throwable) {
                // fallback arquivo
            }
        }

        $this->persistFile($sociedade);
        return $sociedade;
    }

    private function persistDb(SociedadeAdvogados $s): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO sociedades_advogados (
                    id, razao_social, nome_fantasia, cnpj, oab_sociedade, cidade_sede, uf_sede,
                    endereco, bairro, cep, telefone, email, responsavel, oab_responsavel,
                    status, status_operacional, data_credenciamento, numero_contrato,
                    ordem_credenciamento, processo_sei, observacoes
                ) VALUES (
                    :id, :razao, :fantasia, :cnpj, :oab, :cidade, :uf,
                    :endereco, :bairro, :cep, :telefone, :email, :responsavel, :oab_resp,
                    :status, :status_op, :data_cred, :contrato,
                    :ordem, :sei, :obs
                )
                ON DUPLICATE KEY UPDATE
                    razao_social=VALUES(razao_social), nome_fantasia=VALUES(nome_fantasia),
                    cnpj=VALUES(cnpj), oab_sociedade=VALUES(oab_sociedade),
                    cidade_sede=VALUES(cidade_sede), uf_sede=VALUES(uf_sede),
                    endereco=VALUES(endereco), bairro=VALUES(bairro), cep=VALUES(cep),
                    telefone=VALUES(telefone), email=VALUES(email), responsavel=VALUES(responsavel),
                    oab_responsavel=VALUES(oab_responsavel), status=VALUES(status),
                    status_operacional=VALUES(status_operacional), data_credenciamento=VALUES(data_credenciamento),
                    numero_contrato=VALUES(numero_contrato), ordem_credenciamento=VALUES(ordem_credenciamento),
                    processo_sei=VALUES(processo_sei), observacoes=VALUES(observacoes)'
            );
            $stmt->execute([
                ':id' => $s->id,
                ':razao' => $s->razaoSocial,
                ':fantasia' => $s->nomeFantasia,
                ':cnpj' => $s->cnpj,
                ':oab' => $s->oabSociedade,
                ':cidade' => $s->cidadeSede,
                ':uf' => $s->ufSede,
                ':endereco' => $s->endereco,
                ':bairro' => $s->bairro,
                ':cep' => $s->cep,
                ':telefone' => $s->telefone,
                ':email' => $s->email,
                ':responsavel' => $s->responsavel,
                ':oab_resp' => $s->oabResponsavel,
                ':status' => $s->status,
                ':status_op' => $s->statusOperacional,
                ':data_cred' => $s->dataCredenciamento,
                ':contrato' => $s->numeroContrato,
                ':ordem' => $s->ordemCredenciamento,
                ':sei' => $s->processoSei,
                ':obs' => $s->observacoes,
            ]);

            $pdo->prepare('DELETE FROM sociedade_atividades WHERE sociedade_id = ?')->execute([$s->id]);
            $insA = $pdo->prepare('INSERT INTO sociedade_atividades (sociedade_id, codigo_atividade) VALUES (?, ?)');
            foreach ($s->atividadesCodigos as $code) {
                $insA->execute([$s->id, $code]);
            }

            $pdo->prepare('DELETE FROM sociedade_polos WHERE sociedade_id = ?')->execute([$s->id]);
            $insP = $pdo->prepare('INSERT INTO sociedade_polos (sociedade_id, codigo_polo) VALUES (?, ?)');
            foreach ($s->polosCodigos as $code) {
                $insP->execute([$s->id, $code]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function persistFile(SociedadeAdvogados $s): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $atuais = [];
        if (is_file($this->storagePath)) {
            $json = json_decode((string) file_get_contents($this->storagePath), true);
            $atuais = is_array($json) ? $json : [];
        } else {
            $atuais = require dirname(__DIR__) . '/mocks/sociedades.php';
        }

        $found = false;
        foreach ($atuais as $i => $row) {
            if (($row['id'] ?? '') === $s->id) {
                $atuais[$i] = $s->toArray();
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($atuais, $s->toArray());
        }

        file_put_contents($this->storagePath, json_encode($atuais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @return list<SociedadeAdvogados> */
    private function listFromFiles(): array
    {
        $rows = [];
        if (is_file($this->storagePath)) {
            $json = json_decode((string) file_get_contents($this->storagePath), true);
            if (is_array($json) && $json !== []) {
                $rows = $json;
            }
        }
        if ($rows === []) {
            $rows = require dirname(__DIR__) . '/mocks/sociedades.php';
        }
        return array_map(static fn(array $r): SociedadeAdvogados => SociedadeAdvogados::fromArray($r), $rows);
    }

    /** @return list<SociedadeAdvogados> */
    private function listFromDb(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT * FROM sociedades_advogados ORDER BY ordem_credenciamento, razao_social')->fetchAll();
        $catalogo = $this->getCatalogo();
        $out = [];

        $stmtAtiv = $pdo->prepare('SELECT codigo_atividade FROM sociedade_atividades WHERE sociedade_id = ?');
        $stmtPolo = $pdo->prepare('SELECT codigo_polo FROM sociedade_polos WHERE sociedade_id = ?');

        foreach ($rows as $row) {
            $stmtAtiv->execute([$row['id']]);
            $ativCodes = array_column($stmtAtiv->fetchAll(), 'codigo_atividade');
            $stmtPolo->execute([$row['id']]);
            $poloCodes = array_column($stmtPolo->fetchAll(), 'codigo_polo');

            $atividades = [];
            foreach ($ativCodes as $c) {
                $atividades[] = $catalogo['atividades'][$c] ?? $c;
            }
            $polos = [];
            foreach ($poloCodes as $c) {
                $polos[] = $catalogo['polos'][$c] ?? $c;
            }

            $out[] = SociedadeAdvogados::fromArray([
                'id' => $row['id'],
                'razaoSocial' => $row['razao_social'],
                'nomeFantasia' => $row['nome_fantasia'] ?? '',
                'cnpj' => $row['cnpj'] ?? '',
                'oabSociedade' => $row['oab_sociedade'] ?? '',
                'cidadeSede' => $row['cidade_sede'],
                'ufSede' => $row['uf_sede'] ?? 'MG',
                'endereco' => $row['endereco'] ?? '',
                'bairro' => $row['bairro'] ?? '',
                'cep' => $row['cep'] ?? '',
                'status' => $row['status'],
                'statusOperacional' => $row['status_operacional'],
                'dataCredenciamento' => $row['data_credenciamento'],
                'numeroContrato' => $row['numero_contrato'] ?? '',
                'ordemCredenciamento' => (int) ($row['ordem_credenciamento'] ?? 0),
                'atividadesCodigos' => $ativCodes,
                'atividades' => $atividades,
                'polosCodigos' => $poloCodes,
                'polos' => $polos,
                'telefone' => $row['telefone'],
                'email' => $row['email'],
                'responsavel' => $row['responsavel'],
                'oabResponsavel' => $row['oab_responsavel'] ?? '',
                'processoSei' => $row['processo_sei'] ?? '',
                'observacoes' => $row['observacoes'],
            ]);
        }

        return $out;
    }

    private function dbEnabled(): bool
    {
        try {
            if (!Database::enabled()) {
                return false;
            }
            Database::pdo()->query('SELECT 1 FROM sociedades_advogados LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
