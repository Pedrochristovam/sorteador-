<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/types/ResultadoSorteio.php';
require_once dirname(__DIR__) . '/types/Sorteio.php';
require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/config/Database.php';

/**
 * Serviço de sorteio — Engenharia.
 *
 * Modalidades (definidas pela demanda):
 *   - sorteio   : sorteio de 1 entre as aptas que ainda não foram chamadas no ciclo do rodízio;
 *                 se todas já foram chamadas (ex.: é a única que realiza a atividade), sorteia entre todas as aptas.
 *   - proxima   : laudo com preço de edital — a apta de menor rota de carro leva (rodízio não se aplica).
 *   - orcamento : sorteio de 3 aptas (priorizando quem não foi chamado no ciclo); o operador registra
 *                 os orçamentos e a mais barata leva.
 * A aleatoriedade usa random_int (CSPRNG) com embaralhamento Fisher-Yates.
 */
final class DrawService
{
    public const QTD_ORCAMENTO = 3;

    /**
     * @param list<array> $elegiveis
     * @param array{totalAnalisadas?:int,totalEliminadas?:int,totalElegiveis?:int} $metricas
     * @return array{sorteio:array,resultado:array,meta:array}
     */
    public function realizar(
        array $elegiveis,
        array $demanda,
        array $criteriosUtilizados = [],
        array $metricas = []
    ): array {
        if ($elegiveis === []) {
            throw new InvalidArgumentException('Não há empresas aptas para o sorteio.');
        }

        $usuario = current_user();
        $agora = (new DateTimeImmutable('now', new DateTimeZone(app_config('timezone', 'America/Sao_Paulo'))))
            ->format('Y-m-d H:i:s');
        $sorteioId = generate_id('SRT');
        $modalidade = (string) ($demanda['modalidade'] ?? 'sorteio');
        $seed = bin2hex(random_bytes(16));

        $execucao = match ($modalidade) {
            'proxima' => $this->escolherMaisProxima($elegiveis),
            'orcamento' => $this->sortearParaOrcamento($elegiveis),
            default => $this->sortearComRodizio($elegiveis),
        };

        $selecionada = $execucao['selecionada'];
        $sorteadas = $execucao['sorteadas'];
        $status = $modalidade === 'orcamento' ? 'aguardando_orcamento' : 'concluido';

        $payloadIntegridade = json_encode([
            'sorteioId' => $sorteioId,
            'dataHora' => $agora,
            'modalidade' => $modalidade,
            'participantes' => array_column($execucao['participantes'], 'empresaId'),
            'sorteadas' => array_column($sorteadas, 'id'),
            'selecionada' => $selecionada['id'] ?? null,
            'seed' => $seed,
        ], JSON_UNESCAPED_UNICODE);
        $hash = 'sha256:' . hash('sha256', (string) $payloadIntegridade);

        $participantes = array_map(static fn(array $p): array => [
            'id' => $p['empresaId'] ?? $p['id'] ?? '',
            'nome' => $p['nome'] ?? '',
            'cnpj' => $p['cnpj'] ?? '',
            'cidade' => $p['cidade'] ?? '',
            'distanciaKm' => $p['distanciaKm'] ?? null,
            'emRodizio' => !empty($p['emRodizio']),
        ], $execucao['participantes']);

        $resultado = new ResultadoSorteio(
            sorteioId: $sorteioId,
            empresaSelecionada: $selecionada ?? [],
            demanda: $demanda,
            dataHora: $agora,
            participantes: $participantes,
            criteriosUtilizados: $criteriosUtilizados,
            hashIntegridade: $hash,
            modoSorteio: 'csprng',
        );

        $sorteio = (new Sorteio(
            id: $sorteioId,
            dataHora: $agora,
            demanda: $demanda,
            participantes: $participantes,
            empresaSelecionada: $selecionada,
            status: $status,
            usuarioId: (string) ($usuario['id'] ?? ''),
            usuarioNome: (string) ($usuario['nome'] ?? ''),
            criteriosUtilizados: $criteriosUtilizados,
            totalAnalisadas: (int) ($metricas['totalAnalisadas'] ?? count($elegiveis)),
            totalEliminadas: (int) ($metricas['totalEliminadas'] ?? 0),
            totalElegiveis: (int) ($metricas['totalElegiveis'] ?? count($elegiveis)),
        ))->toArray();

        $extras = [
            'modalidade' => $modalidade,
            'modalidadeMotivo' => (string) ($demanda['modalidadeMotivo'] ?? ''),
            'sorteadas' => $sorteadas,
            'orcamentos' => [],
            'ranking' => $execucao['ranking'],
            'rodizioIds' => $execucao['rodizioIds'],
            'rodizioNota' => $execucao['nota'],
            'hashIntegridade' => $hash,
            'seedReferencia' => 'seed:' . $seed,
            'modoSorteio' => 'csprng',
        ];

        return [
            'sorteio' => $sorteio + $extras,
            'resultado' => $resultado->toArray() + $extras,
            'meta' => [
                'mode' => 'csprng-fisher-yates-v2',
                'hashIntegridade' => $hash,
                'seedReferencia' => 'seed:' . $seed,
                'modalidade' => $modalidade,
                'unicidadeGarantida' => count($execucao['participantes']) === 1,
            ],
        ];
    }

    /**
     * @param list<array> $elegiveis
     * @return array{selecionada:?array,sorteadas:list<array>,participantes:list<array>,ranking:list<array>,rodizioIds:list<string>,nota:string}
     */
    private function sortearComRodizio(array $elegiveis): array
    {
        $livres = array_values(array_filter($elegiveis, static fn(array $e): bool => empty($e['emRodizio'])));
        $nota = '';
        if ($livres === []) {
            $livres = $elegiveis;
            $nota = count($elegiveis) === 1
                ? 'Exceção ao rodízio: é a única credenciada apta para esta demanda.'
                : 'Exceção ao rodízio: todas as aptas para esta demanda já foram chamadas no ciclo.';
        } elseif (count($livres) < count($elegiveis)) {
            $nota = sprintf('%d apta(s) aguardando o rodízio ficaram fora deste sorteio.', count($elegiveis) - count($livres));
        }

        $escolhida = $this->embaralhar($livres)[0];
        $selecionada = $this->resumoEmpresa($escolhida);

        return [
            'selecionada' => $selecionada,
            'sorteadas' => [$selecionada],
            'participantes' => $livres,
            'ranking' => [],
            'rodizioIds' => [$selecionada['id']],
            'nota' => $nota,
        ];
    }

    /** @param list<array> $elegiveis */
    private function escolherMaisProxima(array $elegiveis): array
    {
        $comRota = array_values(array_filter($elegiveis, static fn(array $e): bool => ($e['distanciaKm'] ?? null) !== null));
        if ($comRota === []) {
            throw new RuntimeException('Não foi possível apurar a rota de carro de nenhuma credenciada. Confira o endereço da demanda e tente de novo.');
        }
        usort($comRota, static fn(array $a, array $b): int
            => [(float) $a['distanciaKm'], (int) ($a['ordemCredenciamento'] ?? 0)] <=> [(float) $b['distanciaKm'], (int) ($b['ordemCredenciamento'] ?? 0)]);

        $selecionada = $this->resumoEmpresa($comRota[0]);
        $ranking = array_map(fn(array $e, int $i): array => $this->resumoEmpresa($e) + ['posicao' => $i + 1], array_slice($comRota, 0, 5), array_keys(array_slice($comRota, 0, 5)));

        $nota = 'Rodízio não se aplica: laudo com preço de edital vai para a credenciada mais próxima.';
        if (count($comRota) < count($elegiveis)) {
            $nota .= sprintf(' %d apta(s) sem rota apurada não entraram na comparação.', count($elegiveis) - count($comRota));
        }

        return [
            'selecionada' => $selecionada,
            'sorteadas' => [$selecionada],
            'participantes' => $elegiveis,
            'ranking' => $ranking,
            'rodizioIds' => [$selecionada['id']],
            'nota' => $nota,
        ];
    }

    /** @param list<array> $elegiveis */
    private function sortearParaOrcamento(array $elegiveis): array
    {
        $livres = array_values(array_filter($elegiveis, static fn(array $e): bool => empty($e['emRodizio'])));
        $chamadas = array_values(array_filter($elegiveis, static fn(array $e): bool => !empty($e['emRodizio'])));

        $escolhidas = array_slice($this->embaralhar($livres), 0, self::QTD_ORCAMENTO);
        $nota = '';
        if (count($escolhidas) < self::QTD_ORCAMENTO && $chamadas !== []) {
            $faltam = self::QTD_ORCAMENTO - count($escolhidas);
            $escolhidas = array_merge($escolhidas, array_slice($this->embaralhar($chamadas), 0, $faltam));
            $nota = 'Exceção ao rodízio: não havia 3 aptas fora do ciclo; completado com credenciadas já chamadas.';
        }
        if (count($escolhidas) < self::QTD_ORCAMENTO) {
            $nota = trim($nota . sprintf(' Só %d credenciada(s) apta(s) para esta demanda — orçamento com menos de 3.', count($escolhidas)));
        }

        $sorteadas = array_map(fn(array $e): array => $this->resumoEmpresa($e), $escolhidas);

        return [
            'selecionada' => null,
            'sorteadas' => $sorteadas,
            'participantes' => $livres !== [] && count($livres) >= self::QTD_ORCAMENTO ? $livres : $elegiveis,
            'ranking' => [],
            'rodizioIds' => array_column($sorteadas, 'id'),
            'nota' => $nota,
        ];
    }

    /**
     * Registra os orçamentos das 3 sorteadas; a de menor valor leva o serviço.
     *
     * @param array<string,mixed> $valores id => valor (null/'' = não enviou orçamento)
     * @return array sorteio atualizado
     */
    public function registrarOrcamentos(string $sorteioId, array $valores, string $observacao = ''): array
    {
        $sorteio = $this->findById($sorteioId);
        if ($sorteio === null) {
            throw new InvalidArgumentException('Sorteio não encontrado.');
        }
        if (($sorteio['modalidade'] ?? '') !== 'orcamento') {
            throw new InvalidArgumentException('Este sorteio não é da modalidade orçamento.');
        }

        $orcamentos = [];
        foreach ($sorteio['sorteadas'] ?? [] as $emp) {
            $bruto = $valores[$emp['id']] ?? null;
            $valor = null;
            if ($bruto !== null && $bruto !== '') {
                $txt = trim((string) $bruto);
                if (str_contains($txt, ',')) {
                    $txt = str_replace(['.', ','], ['', '.'], $txt);
                }
                $valor = is_numeric($txt) && (float) $txt > 0 ? round((float) $txt, 2) : null;
            }
            $orcamentos[] = $emp + ['valor' => $valor, 'enviou' => $valor !== null];
        }

        $validos = array_values(array_filter($orcamentos, static fn(array $o): bool => $o['valor'] !== null));
        if ($validos === []) {
            throw new InvalidArgumentException('Informe o valor de ao menos um orçamento.');
        }
        usort($validos, static fn(array $a, array $b): int => $a['valor'] <=> $b['valor']);
        $menor = $validos[0]['valor'];
        $empatadas = array_values(array_filter($validos, static fn(array $o): bool => $o['valor'] === $menor));
        $vencedora = count($empatadas) > 1 ? $this->embaralhar($empatadas)[0] : $empatadas[0];

        $selecionada = array_diff_key($vencedora, array_flip(['valor', 'enviou']));
        $sorteio['orcamentos'] = $orcamentos;
        $sorteio['orcamentoObservacao'] = trim($observacao);
        $sorteio['orcamentoEmpate'] = count($empatadas) > 1;
        $sorteio['orcamentoRegistradoEm'] = date('Y-m-d H:i:s');
        $sorteio['empresaSelecionada'] = $selecionada + ['valor' => $menor];
        $sorteio['status'] = 'concluido';

        $this->atualizarSorteio($sorteio);
        return $sorteio;
    }

    /** @param list<array> $itens */
    private function embaralhar(array $itens): array
    {
        $itens = array_values($itens);
        for ($i = count($itens) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$itens[$i], $itens[$j]] = [$itens[$j], $itens[$i]];
        }
        return $itens;
    }

    private function resumoEmpresa(array $e): array
    {
        return [
            'id' => $e['empresaId'] ?? $e['id'] ?? '',
            'nome' => $e['nome'] ?? '',
            'cnpj' => $e['cnpj'] ?? '',
            'cidade' => $e['cidade'] ?? '',
            'endereco' => $e['endereco'] ?? '',
            'telefone' => $e['telefone'] ?? null,
            'email' => $e['email'] ?? null,
            'distanciaKm' => $e['distanciaKm'] ?? null,
            'duracaoMin' => $e['duracaoMin'] ?? null,
        ];
    }

    /** @return list<array> */
    public function listHistorico(): array
    {
        if ($this->dbEnabled()) {
            try {
                return $this->listHistoricoFromDb();
            } catch (Throwable) {
                // fallback
            }
        }

        $sessao = $this->readSessionStorage($this->sessionPath());
        $mocks = require dirname(__DIR__) . '/mocks/sorteios.php';
        return array_values(array_merge($sessao, $mocks));
    }

    public function findById(string $id): ?array
    {
        foreach ($this->listHistorico() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    /** Persiste sorteio (MySQL ou arquivo). */
    public function persistirSessao(array $sorteio, array $meta = []): void
    {
        if ($this->dbEnabled()) {
            try {
                $this->persistirDb($sorteio, $meta);
                return;
            } catch (Throwable) {
                // fallback arquivo
            }
        }

        $path = $this->sessionPath();
        $atuais = $this->readSessionStorage($path);
        array_unshift($atuais, $sorteio);
        $this->writeSessionStorage($path, $atuais);
    }

    /** Grava um sorteio já montado no MySQL (usado na migração dos arquivos). */
    public function importar(array $sorteio): void
    {
        $this->persistirDb($sorteio, []);
    }

    /** Regrava um sorteio já persistido (ex.: após registrar orçamentos). */
    public function atualizarSorteio(array $sorteio): void
    {
        if ($this->dbEnabled()) {
            try {
                $this->persistirDb($sorteio, []);
                return;
            } catch (Throwable) {
                // fallback arquivo
            }
        }

        $path = $this->sessionPath();
        $atuais = $this->readSessionStorage($path);
        $achou = false;
        foreach ($atuais as $i => $item) {
            if (($item['id'] ?? '') === ($sorteio['id'] ?? '')) {
                $atuais[$i] = $sorteio;
                $achou = true;
                break;
            }
        }
        if (!$achou) {
            array_unshift($atuais, $sorteio);
        }
        $this->writeSessionStorage($path, $atuais);
    }

    private function sessionPath(): string
    {
        return dirname(__DIR__) . '/storage/sorteios_session.json';
    }

    private function dbEnabled(): bool
    {
        try {
            if (!Database::enabled()) {
                return false;
            }
            Database::pdo()->query('SELECT 1 FROM sorteios LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array> */
    private function listHistoricoFromDb(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT * FROM sorteios ORDER BY data_hora DESC')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapSorteioRow($row);
        }
        return $out;
    }

    private function mapSorteioRow(array $row): array
    {
        $demanda = json_decode((string) ($row['demanda_json'] ?? ''), true);
        if (!is_array($demanda)) {
            $demanda = [
                'cidade' => $row['demanda_cidade'],
                'endereco' => $row['demanda_endereco'],
                'servicoNecessario' => $row['demanda_servico'],
            ];
        }
        $extras = json_decode((string) ($row['extras_json'] ?? ''), true);

        return [
            'id' => $row['id'],
            'dataHora' => $row['data_hora'],
            'demanda' => $demanda,
            'participantes' => json_decode((string) $row['participantes_json'], true) ?: [],
            'empresaSelecionada' => json_decode((string) ($row['empresa_selecionada_json'] ?? ''), true),
            'status' => $row['status'],
            'usuarioId' => $row['usuario_id'] ?? '',
            'usuarioNome' => $row['usuario_nome'] ?? '',
            'criteriosUtilizados' => json_decode((string) ($row['criterios_json'] ?? ''), true) ?: [],
            'totalAnalisadas' => (int) $row['total_analisadas'],
            'totalEliminadas' => (int) $row['total_eliminadas'],
            'totalElegiveis' => (int) $row['total_elegiveis'],
            'hashIntegridade' => $row['hash_integridade'] ?? '',
            'modoSorteio' => $row['modo_sorteio'] ?? 'csprng',
        ] + (is_array($extras) ? $extras : []);
    }

    private function persistirDb(array $sorteio, array $meta = []): void
    {
        $pdo = Database::pdo();
        $demanda = $sorteio['demanda'] ?? [];
        $empresaId = $sorteio['empresaSelecionada']['id'] ?? null;

        if ($empresaId) {
            $check = $pdo->prepare('SELECT id FROM empresas WHERE id = ?');
            $check->execute([$empresaId]);
            if (!$check->fetchColumn()) {
                $empresaId = null;
            }
        }

        $extras = array_intersect_key($sorteio, array_flip([
            'modalidade', 'modalidadeMotivo', 'sorteadas', 'orcamentos', 'ranking', 'rodizioIds', 'rodizioNota',
            'seedReferencia', 'orcamentoObservacao', 'orcamentoEmpate', 'orcamentoRegistradoEm',
        ]));

        $stmt = $pdo->prepare(
            'INSERT INTO sorteios (
                id, data_hora, status, usuario_id, usuario_nome,
                demanda_cidade, demanda_endereco, demanda_tipo, demanda_servico, demanda_raio_km, demanda_json,
                criterios_json, participantes_json, empresa_selecionada_id, empresa_selecionada_json,
                total_analisadas, total_eliminadas, total_elegiveis, modo_sorteio, hash_integridade, seed_referencia, extras_json
            ) VALUES (
                :id, :data_hora, :status, :usuario_id, :usuario_nome,
                :demanda_cidade, :demanda_endereco, :demanda_tipo, :demanda_servico, NULL, :demanda_json,
                :criterios_json, :participantes_json, :empresa_selecionada_id, :empresa_selecionada_json,
                :total_analisadas, :total_eliminadas, :total_elegiveis, :modo_sorteio, :hash_integridade, :seed_referencia, :extras_json
            )
            ON DUPLICATE KEY UPDATE
                status=VALUES(status),
                empresa_selecionada_id=VALUES(empresa_selecionada_id),
                empresa_selecionada_json=VALUES(empresa_selecionada_json),
                hash_integridade=VALUES(hash_integridade),
                extras_json=VALUES(extras_json)'
        );

        $stmt->execute([
            ':id' => $sorteio['id'],
            ':data_hora' => $sorteio['dataHora'],
            ':status' => $sorteio['status'] ?? 'concluido',
            ':usuario_id' => $sorteio['usuarioId'] ?? null,
            ':usuario_nome' => $sorteio['usuarioNome'] ?? null,
            ':demanda_cidade' => $demanda['cidade'] ?? '',
            ':demanda_endereco' => $demanda['endereco'] ?? '',
            ':demanda_tipo' => $demanda['modalidade'] ?? '',
            ':demanda_servico' => $demanda['servicoNecessario'] ?? '',
            ':demanda_json' => json_encode($demanda, JSON_UNESCAPED_UNICODE),
            ':criterios_json' => json_encode($sorteio['criteriosUtilizados'] ?? [], JSON_UNESCAPED_UNICODE),
            ':participantes_json' => json_encode($sorteio['participantes'] ?? [], JSON_UNESCAPED_UNICODE),
            ':empresa_selecionada_id' => $empresaId,
            ':empresa_selecionada_json' => json_encode($sorteio['empresaSelecionada'] ?? null, JSON_UNESCAPED_UNICODE),
            ':total_analisadas' => (int) ($sorteio['totalAnalisadas'] ?? 0),
            ':total_eliminadas' => (int) ($sorteio['totalEliminadas'] ?? 0),
            ':total_elegiveis' => (int) ($sorteio['totalElegiveis'] ?? 0),
            ':modo_sorteio' => $meta['mode'] ?? ($sorteio['modoSorteio'] ?? 'csprng'),
            ':hash_integridade' => $meta['hashIntegridade'] ?? ($sorteio['hashIntegridade'] ?? null),
            ':seed_referencia' => $meta['seedReferencia'] ?? ($sorteio['seedReferencia'] ?? null),
            ':extras_json' => json_encode($extras, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return list<array> */
    private function readSessionStorage(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function writeSessionStorage(string $path, array $itens): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode(array_values($itens), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
