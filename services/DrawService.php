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
 *   - proxima   : override manual — a apta de menor rota de carro leva (rodízio não se aplica).
 *                 Preço de edital NÃO usa esta modalidade: vai para sorteio com rodízio.
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
        // Rodízio só conta depois do aceite na aba Standby. Até lá rodizioIds fica vazio.
        $status = $modalidade === 'orcamento' ? 'aguardando_orcamento' : 'aguardando_aceite';
        $propostos = $execucao['rodizioIds'];

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
            'rodizioIds' => [],
            'rodizioPropostos' => $propostos,
            'rodizioNota' => $execucao['nota'],
            'aceiteStatus' => $modalidade === 'orcamento' ? null : 'pendente',
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
        // Só considera distâncias de rota de carro (Google driving / OSRM driving)
        $comRota = array_values(array_filter(
            $elegiveis,
            static fn(array $e): bool => ($e['distanciaKm'] ?? null) !== null
                && in_array($e['distanciaFonte'] ?? '', ['google', 'osm'], true)
        ));
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

    /**
     * Orçamento: participa só quem está entre as 3 mais próximas (rota de carro).
     * Não embaralha o universo inteiro — o pool é estritamente as 3 mais perto do destino.
     *
     * @param list<array> $elegiveis
     */
    private function sortearParaOrcamento(array $elegiveis): array
    {
        $comRota = array_values(array_filter(
            $elegiveis,
            static fn(array $e): bool => ($e['distanciaKm'] ?? null) !== null
                && in_array($e['distanciaFonte'] ?? '', ['google', 'osm'], true)
        ));
        usort(
            $comRota,
            static fn(array $a, array $b): int => [(float) $a['distanciaKm'], (int) ($a['ordemCredenciamento'] ?? 0)]
                <=> [(float) $b['distanciaKm'], (int) ($b['ordemCredenciamento'] ?? 0)]
        );

        $livres = array_values(array_filter($comRota, static fn(array $e): bool => empty($e['emRodizio'])));
        $chamadas = array_values(array_filter($comRota, static fn(array $e): bool => !empty($e['emRodizio'])));

        // As 3 mais próximas livres (já ordenadas por km)
        $escolhidas = array_slice($livres, 0, self::QTD_ORCAMENTO);
        $nota = 'Orçamento somente entre as 3 credenciadas mais próximas do destino (rota de carro).';
        if (count($escolhidas) < self::QTD_ORCAMENTO && $chamadas !== []) {
            $faltam = self::QTD_ORCAMENTO - count($escolhidas);
            $escolhidas = array_merge($escolhidas, array_slice($chamadas, 0, $faltam));
            // Reordena o conjunto final pela distância (mantém “as 3 mais perto” mesmo com exceção)
            usort(
                $escolhidas,
                static fn(array $a, array $b): int => [(float) $a['distanciaKm'], (int) ($a['ordemCredenciamento'] ?? 0)]
                    <=> [(float) $b['distanciaKm'], (int) ($b['ordemCredenciamento'] ?? 0)]
            );
            $escolhidas = array_slice($escolhidas, 0, self::QTD_ORCAMENTO);
            $nota .= ' Completado com já chamadas no rodízio por falta de 3 livres próximas.';
        }
        if (count($escolhidas) < self::QTD_ORCAMENTO) {
            $nota = trim($nota . sprintf(' Só %d credenciada(s) com rota apurada — orçamento com menos de 3.', count($escolhidas)));
        }
        if ($escolhidas === []) {
            throw new RuntimeException('Não há credenciadas com rota de carro até a demanda para montar o orçamento entre as 3 mais próximas.');
        }

        $sorteadas = array_map(fn(array $e): array => $this->resumoEmpresa($e), $escolhidas);
        $ranking = array_map(
            fn(array $e, int $i): array => $this->resumoEmpresa($e) + ['posicao' => $i + 1],
            $escolhidas,
            array_keys($escolhidas)
        );

        return [
            'selecionada' => null,
            'sorteadas' => $sorteadas,
            // Participam só as 3 — não o ranking completo de aptas
            'participantes' => array_map(fn(array $e): array => $this->resumoEmpresa($e) + [
                'emRodizio' => !empty($e['emRodizio']),
                'distanciaKm' => $e['distanciaKm'] ?? null,
            ], $escolhidas),
            'ranking' => $ranking,
            'rodizioIds' => array_column($sorteadas, 'id'),
            'nota' => $nota,
        ];
    }

    /**
     * Escolha manual: operador seleciona a empresa entre as mais próximas da demanda.
     *
     * @param array $empresaSelecionada resumo da empresa (id, nome, …)
     * @param list<array> $rankingProxima lista ordenada por rota de carro
     */
    public function registrarEscolhaManual(array $demanda, array $empresaSelecionada, array $rankingProxima = [], array $metricas = []): array
    {
        if (empty($empresaSelecionada['id'])) {
            throw new InvalidArgumentException('Selecione uma empresa.');
        }

        $usuario = current_user();
        $agora = (new DateTimeImmutable('now', new DateTimeZone(app_config('timezone', 'America/Sao_Paulo'))))
            ->format('Y-m-d H:i:s');
        $sorteioId = generate_id('SRT');
        $seed = bin2hex(random_bytes(16));

        $participantes = array_map(function (array $e): array {
            $r = $this->resumoEmpresa($e);
            return $r + [
                'emRodizio' => !empty($e['emRodizio']),
                'distanciaKm' => $e['distanciaKm'] ?? $r['distanciaKm'] ?? null,
            ];
        }, $rankingProxima !== [] ? $rankingProxima : [$empresaSelecionada]);

        $payloadIntegridade = json_encode([
            'sorteioId' => $sorteioId,
            'dataHora' => $agora,
            'modalidade' => 'manual',
            'selecionada' => $empresaSelecionada['id'],
            'seed' => $seed,
        ], JSON_UNESCAPED_UNICODE);
        $hash = 'sha256:' . hash('sha256', (string) $payloadIntegridade);

        $demanda = $demanda + [
            'modalidade' => 'manual',
            'modalidadeMotivo' => 'Escolha manual pelo operador entre as credenciadas mais próximas.',
        ];

        $sorteio = [
            'id' => $sorteioId,
            'dataHora' => $agora,
            'demanda' => $demanda,
            'participantes' => $participantes,
            'empresaSelecionada' => $empresaSelecionada,
            'status' => 'aguardando_aceite',
            'usuarioId' => (string) ($usuario['id'] ?? ''),
            'usuarioNome' => (string) ($usuario['nome'] ?? ''),
            'criteriosUtilizados' => $metricas['criteriosUtilizados'] ?? ['Escolha manual'],
            'totalAnalisadas' => (int) ($metricas['totalAnalisadas'] ?? count($participantes)),
            'totalEliminadas' => (int) ($metricas['totalEliminadas'] ?? 0),
            'totalElegiveis' => (int) ($metricas['totalElegiveis'] ?? count($participantes)),
            'modalidade' => 'manual',
            'modalidadeMotivo' => $demanda['modalidadeMotivo'],
            'sorteadas' => [$empresaSelecionada],
            'orcamentos' => [],
            'ranking' => array_slice(array_map(
                static fn(array $e, int $i): array => [
                    'id' => $e['empresaId'] ?? $e['id'] ?? '',
                    'nome' => $e['nome'] ?? '',
                    'distanciaKm' => $e['distanciaKm'] ?? null,
                    'posicao' => $i + 1,
                ],
                $rankingProxima,
                array_keys($rankingProxima)
            ), 0, 10),
            'rodizioIds' => [],
            'rodizioPropostos' => [(string) $empresaSelecionada['id']],
            'rodizioNota' => 'Escolha manual: entra no rodízio somente após o aceite na aba Standby.',
            'aceiteStatus' => 'pendente',
            'hashIntegridade' => $hash,
            'seedReferencia' => 'seed:' . $seed,
            'modoSorteio' => 'manual',
        ];

        $this->persistirSessao($sorteio, [
            'mode' => 'escolha-manual',
            'hashIntegridade' => $hash,
            'seedReferencia' => 'seed:' . $seed,
        ]);

        return $sorteio;
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
        $sorteio['status'] = 'aguardando_aceite';
        $sorteio['aceiteStatus'] = 'pendente';
        $sorteio['rodizioPropostos'] = [(string) $selecionada['id']];
        $sorteio['rodizioIds'] = [];

        $this->atualizarSorteio($sorteio);
        return $sorteio;
    }

    /**
     * Confirma se a empresa sorteada aceitou ou recusou a demanda.
     * Só no aceite a empresa entra no rodízio (fica de fora dos próximos até o ciclo fechar).
     *
     * @param 'aceito'|'recusado' $decisao
     */
    public function registrarAceite(string $sorteioId, string $decisao, string $observacao = ''): array
    {
        $decisao = strtolower(trim($decisao));
        if (!in_array($decisao, ['aceito', 'recusado'], true)) {
            throw new InvalidArgumentException('Informe se a empresa aceitou ou recusou o serviço.');
        }

        $sorteio = $this->findById($sorteioId);
        if ($sorteio === null) {
            throw new InvalidArgumentException('Sorteio não encontrado.');
        }
        if (($sorteio['status'] ?? '') !== 'aguardando_aceite') {
            throw new InvalidArgumentException('Este sorteio não está aguardando confirmação de aceite.');
        }
        if (empty($sorteio['empresaSelecionada']['id'])) {
            throw new InvalidArgumentException('Não há empresa selecionada para confirmar o aceite.');
        }

        $empresaId = (string) $sorteio['empresaSelecionada']['id'];
        $agora = date('Y-m-d H:i:s');
        $sorteio['aceiteStatus'] = $decisao;
        $sorteio['aceiteEm'] = $agora;
        $sorteio['aceiteObservacao'] = trim($observacao);
        $sorteio['aceiteUsuario'] = (string) (current_user()['nome'] ?? '');

        if ($decisao === 'aceito') {
            $propostos = $sorteio['rodizioPropostos'] ?? [];
            if (!is_array($propostos) || $propostos === []) {
                $propostos = [$empresaId];
            }
            $sorteio['rodizioIds'] = array_values(array_unique(array_map('strval', $propostos)));
            $sorteio['status'] = 'concluido';
        } else {
            $sorteio['rodizioIds'] = [];
            $sorteio['status'] = 'recusado';
        }

        $this->atualizarSorteio($sorteio);
        return $sorteio;
    }

    /** Sorteios aguardando aceite/recusa da empresa (aba Standby). */
    public function listStandby(): array
    {
        return array_values(array_filter(
            $this->listHistorico(),
            static fn(array $s): bool => ($s['status'] ?? '') === 'aguardando_aceite'
        ));
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

    /**
     * Histórico operacional da empresa: participações, vitórias, aceites e recusas com observação.
     *
     * @return array{
     *   resumo: array{participacoes:int,selecionada:int,aceitos:int,recusados:int,pendentes:int,orcamentos:int},
     *   itens: list<array>
     * }
     */
    public function historicoEmpresa(string $empresaId): array
    {
        $empresaId = trim($empresaId);
        $itens = [];

        foreach ($this->listHistorico() as $s) {
            $selecionadaId = (string) ($s['empresaSelecionada']['id'] ?? '');
            $foiSelecionada = $selecionadaId === $empresaId;

            $emParticipantes = false;
            foreach ($s['participantes'] ?? [] as $p) {
                if ((string) ($p['id'] ?? $p['empresaId'] ?? '') === $empresaId) {
                    $emParticipantes = true;
                    break;
                }
            }

            $emSorteadas = false;
            $valorOrcamento = null;
            foreach ($s['sorteadas'] ?? [] as $p) {
                if ((string) ($p['id'] ?? $p['empresaId'] ?? '') === $empresaId) {
                    $emSorteadas = true;
                    if (isset($p['valor'])) {
                        $valorOrcamento = $p['valor'];
                    }
                    break;
                }
            }
            if (!$emSorteadas) {
                foreach ($s['orcamentos'] ?? [] as $o) {
                    if ((string) ($o['id'] ?? '') === $empresaId) {
                        $emSorteadas = true;
                        $valorOrcamento = $o['valor'] ?? null;
                        break;
                    }
                }
            }

            if (!$foiSelecionada && !$emParticipantes && !$emSorteadas) {
                continue;
            }

            $status = (string) ($s['status'] ?? '');
            $aceite = (string) ($s['aceiteStatus'] ?? '');
            $modalidade = (string) ($s['modalidade'] ?? 'sorteio');
            $papel = 'participante';

            if ($foiSelecionada) {
                if ($status === 'recusado' || $aceite === 'recusado') {
                    $papel = 'recusado';
                } elseif ($status === 'aguardando_aceite' || $aceite === 'pendente') {
                    $papel = 'pendente';
                } elseif ($status === 'concluido' || $aceite === 'aceito') {
                    $papel = 'aceito';
                } else {
                    $papel = 'selecionada';
                }
            } elseif ($emSorteadas && $modalidade === 'orcamento') {
                $papel = 'orcamento';
            }

            $demanda = is_array($s['demanda'] ?? null) ? $s['demanda'] : [];
            $sel = is_array($s['empresaSelecionada'] ?? null) ? $s['empresaSelecionada'] : [];

            $itens[] = [
                'sorteioId' => (string) ($s['id'] ?? ''),
                'dataHora' => (string) ($s['dataHora'] ?? ''),
                'modalidade' => $modalidade,
                'status' => $status,
                'papel' => $papel,
                'foiSelecionada' => $foiSelecionada,
                'demanda' => [
                    'cidade' => (string) ($demanda['cidade'] ?? ''),
                    'uf' => (string) ($demanda['uf'] ?? ''),
                    'endereco' => (string) ($demanda['endereco'] ?? ''),
                    'servico' => (string) ($demanda['servicoNecessario'] ?? ($demanda['servicosNecessarios'][0] ?? '')),
                    'regiao' => trim(($demanda['regiaoCodigo'] ?? '') . ' ' . ($demanda['regiaoNome'] ?? '')),
                ],
                'distanciaKm' => $foiSelecionada
                    ? ($sel['distanciaKm'] ?? null)
                    : null,
                'valorOrcamento' => $valorOrcamento,
                'aceiteStatus' => $aceite !== '' ? $aceite : null,
                'aceiteEm' => $s['aceiteEm'] ?? null,
                'aceiteObservacao' => trim((string) ($s['aceiteObservacao'] ?? '')),
                'aceiteUsuario' => (string) ($s['aceiteUsuario'] ?? ''),
                'usuarioNome' => (string) ($s['usuarioNome'] ?? ''),
            ];
        }

        usort($itens, static fn(array $a, array $b): int => strcmp((string) $b['dataHora'], (string) $a['dataHora']));

        $contar = static fn(string $papel) => count(array_filter($itens, static fn(array $i): bool => $i['papel'] === $papel));

        return [
            'resumo' => [
                'participacoes' => count($itens),
                'selecionada' => count(array_filter($itens, static fn(array $i): bool => !empty($i['foiSelecionada']))),
                'aceitos' => $contar('aceito'),
                'recusados' => $contar('recusado'),
                'pendentes' => $contar('pendente'),
                'orcamentos' => $contar('orcamento'),
            ],
            'itens' => $itens,
        ];
    }

    /** Persiste sorteio (MySQL ou arquivo). */
    public function persistirSessao(array $sorteio, array $meta = []): void
    {
        if ($this->dbEnabled()) {
            $this->persistirDb($sorteio, $meta);
            return;
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
            'modalidade', 'modalidadeMotivo', 'sorteadas', 'orcamentos', 'ranking', 'rodizioIds', 'rodizioPropostos', 'rodizioNota',
            'seedReferencia', 'orcamentoObservacao', 'orcamentoEmpate', 'orcamentoRegistradoEm',
            'aceiteStatus', 'aceiteEm', 'aceiteObservacao', 'aceiteUsuario',
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
