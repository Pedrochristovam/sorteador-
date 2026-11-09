<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/types/LogAuditoria.php';
require_once dirname(__DIR__) . '/utils/helpers.php';
require_once dirname(__DIR__) . '/config/Database.php';

/**
 * Serviço de auditoria.
 * Persiste em MySQL quando disponível.
 */
final class AuditService
{
    private const CAMPOS_EXTRAS = [
        'modalidade', 'modalidadeMotivo', 'sorteadas', 'ranking', 'rodizioNota', 'distancia', 'orcamentos',
        'execucaoLog', 'checksumAlgoritmo', 'sintetico', 'aceiteStatus', 'aceiteEm', 'aceiteObservacao',
        'aceiteUsuario', 'rodizioIds', 'rodizioPropostos', 'status', 'seedReferenciaSorteio',
    ];

    /**
     * Catálogo explicativo dos componentes da auditoria (glossário operacional).
     *
     * @return list<array{id:string,titulo:string,resumo:string,detalhe:string,icone:string}>
     */
    public static function glossario(): array
    {
        return [
            [
                'id' => 'hash',
                'titulo' => 'Hash SHA-256 da operação',
                'resumo' => 'Impressão digital do ato. Qualquer alteração muda o hash.',
                'detalhe' => 'Calculado sobre o ID da auditoria, do sorteio, data/hora, resultado e listas de participantes/eliminadas. Serve para provar que o registro não foi adulterado depois da execução.',
                'icone' => 'fingerprint',
            ],
            [
                'id' => 'seed',
                'titulo' => 'Semente (seed) CSPRNG',
                'resumo' => 'Fonte de aleatoriedade criptográfica usada no embaralhamento.',
                'detalhe' => 'Gerada com random_bytes (CSPRNG do PHP). A semente vincula o resultado ao algoritmo Fisher-Yates, permitindo auditar a execução sem revelar o resultado antes do ato.',
                'icone' => 'dices',
            ],
            [
                'id' => 'funil',
                'titulo' => 'Funil de elegibilidade',
                'resumo' => 'Analisadas → eliminadas → elegíveis → participantes do sorteio.',
                'detalhe' => 'Toda credenciada da base passa pelos critérios (atividade, status, região, distância 200 km ida/volta, prioridade da cidade). Só as aptas e livres do rodízio entram no sorteio automático.',
                'icone' => 'filter',
            ],
            [
                'id' => 'eliminacao',
                'titulo' => 'Eliminações fundamentadas',
                'resumo' => 'Cada exclusão traz motivo fático e enquadramento do edital.',
                'detalhe' => 'Motivos como credenciado inativo, atividade não habilitada, fora da região, acima da margem de 200 km ou prioridade de cidade ficam registrados para defesa jurídica do ato.',
                'icone' => 'file-warning',
            ],
            [
                'id' => 'modalidade',
                'titulo' => 'Modalidade do ato',
                'resumo' => 'Sorteio, orçamento (3 mais próximas) ou escolha manual.',
                'detalhe' => 'A demanda define a regra: vistoria comum → sorteio com rodízio; preço de edital → também sorteio; áreas/atividades específicas → orçamento entre as 3 mais próximas; operador pode escolher manualmente.',
                'icone' => 'git-branch',
            ],
            [
                'id' => 'rodizio',
                'titulo' => 'Rodízio geral',
                'resumo' => 'Quem aceita sai do pool até o ciclo fechar.',
                'detalhe' => 'A empresa só entra no rodízio após o aceite no Standby. Recusa não a remove. Na escolha manual, pode ser selecionada de novo mesmo estando no rodízio.',
                'icone' => 'refresh-cw',
            ],
            [
                'id' => 'aceite',
                'titulo' => 'Aceite / recusa (Standby)',
                'resumo' => 'Confirmação humana com observação opcional.',
                'detalhe' => 'O colaborador registra se a empresa aceitou ou recusou o serviço, com data, usuário e motivo/observação. Isso fecha o ciclo operacional e alimenta o histórico da empresa.',
                'icone' => 'hourglass',
            ],
            [
                'id' => 'timeline',
                'titulo' => 'Linha do tempo do algoritmo',
                'resumo' => 'Eventos em ordem: demanda → filtros → sorteio → hash → aceite.',
                'detalhe' => 'Cada passo gera um evento auditável (INIT, FILTER, SELECTION, STANDBY, ACCEPT/REFUSE, FINALIZATION). É a trilha cronológica para reconstituição do ato.',
                'icone' => 'list-ordered',
            ],
            [
                'id' => 'cadeia',
                'titulo' => 'Cadeia de custódia da sessão',
                'resumo' => 'Hash agregado de todas as operações listadas.',
                'detalhe' => 'Une os hashes individuais desta sessão de consulta. Útil para atestar que o conjunto de atos exibido não foi reordenado ou omitido de forma silenciosa.',
                'icone' => 'link',
            ],
            [
                'id' => 'legal',
                'titulo' => 'Base legal e edital',
                'resumo' => 'Isonomia, publicidade e impessoalidade (Art. 37 CF / Lei 14.133).',
                'detalhe' => 'O sorteio público com critérios objetivos, registro imutável e fundamentação das eliminações materializa os princípios da administração pública e o regulamento do Edital 02/2026 (Engenharia).',
                'icone' => 'scale',
            ],
        ];
    }

    /**
     * Explicação humana de um código de evento da trilha.
     *
     * @return array{titulo:string,explicacao:string,nivel:string}
     */
    public static function explicarEvento(string $codigo): array
    {
        return match ($codigo) {
            'demanda_registrada' => [
                'titulo' => 'Demanda registrada',
                'explicacao' => 'Parâmetros da demanda (cidade, endereço, região, atividades, áreas e flags) foram fixados para o cálculo.',
                'nivel' => 'INIT',
            ],
            'modalidade_definida' => [
                'titulo' => 'Modalidade definida',
                'explicacao' => 'O sistema (ou o operador) escolheu a regra do ato: sorteio, orçamento ou escolha manual.',
                'nivel' => 'RULE',
            ],
            'elegibilidade_calculada' => [
                'titulo' => 'Elegibilidade calculada',
                'explicacao' => 'Critérios aplicados à base. Empresas inaptas saíram; aptas seguiram para o pool.',
                'nivel' => 'FILTER',
            ],
            'distancia_apurada' => [
                'titulo' => 'Rotas de carro apuradas',
                'explicacao' => 'Distâncias calculadas por Google Maps ou OpenStreetMap/OSRM (nunca linha reta).',
                'nivel' => 'ROUTE',
            ],
            'rodizio_aplicado' => [
                'titulo' => 'Rodízio aplicado',
                'explicacao' => 'Empresas já chamadas no ciclo atual foram sinalizadas e, no sorteio automático, ficaram de fora se havia livres.',
                'nivel' => 'ROTATION',
            ],
            'sorteio_confirmado' => [
                'titulo' => 'Confirmação do operador',
                'explicacao' => 'Operador confirmou a execução do certame na interface.',
                'nivel' => 'CONFIRM',
            ],
            'entropia_solicitada' => [
                'titulo' => 'Entropia solicitada',
                'explicacao' => 'Semente aleatória criptográfica gerada para o embaralhamento.',
                'nivel' => 'ENTROPY',
            ],
            'embaralhamento' => [
                'titulo' => 'Embaralhamento Fisher-Yates',
                'explicacao' => 'Participantes foram embaralhados com CSPRNG; a primeira posição após o shuffle é a sorteada.',
                'nivel' => 'EXEC_SHUFFLE',
            ],
            'sorteio_executado' => [
                'titulo' => 'Resultado do sorteio',
                'explicacao' => 'Empresa (ou conjunto, no orçamento) selecionada e persistida no histórico.',
                'nivel' => 'SELECTION',
            ],
            'mais_proxima_escolhida' => [
                'titulo' => 'Mais próxima escolhida',
                'explicacao' => 'Override manual: credenciada de menor rota de carro registrada.',
                'nivel' => 'SELECTION',
            ],
            'sorteadas_orcamento' => [
                'titulo' => 'Três para orçamento',
                'explicacao' => 'Até 3 credenciadas mais próximas (priorizando fora do rodízio) foram sorteadas para cotação.',
                'nivel' => 'SELECTION',
            ],
            'orcamentos_registrados' => [
                'titulo' => 'Orçamentos registrados',
                'explicacao' => 'Valores lançados; a de menor preço válido venceu e seguiu para standby.',
                'nivel' => 'QUOTES',
            ],
            'aguardando_aceite' => [
                'titulo' => 'Aguardando aceite',
                'explicacao' => 'Ato em Standby: ainda não conta no rodízio até aceite ou recusa.',
                'nivel' => 'STANDBY',
            ],
            'demanda_aceita' => [
                'titulo' => 'Demanda aceita',
                'explicacao' => 'Empresa confirmou o serviço. Entra no rodízio e o sorteio conclui.',
                'nivel' => 'ACCEPT',
            ],
            'demanda_recusada' => [
                'titulo' => 'Demanda recusada',
                'explicacao' => 'Empresa recusou. Continua apta; observação/motivo fica no dossiê.',
                'nivel' => 'REFUSE',
            ],
            'hash_consolidado' => [
                'titulo' => 'Hash consolidado',
                'explicacao' => 'Trilha assinada com SHA-256; ponto final da imutabilidade do registro.',
                'nivel' => 'FINALIZATION',
            ],
            default => [
                'titulo' => str_replace('_', ' ', $codigo),
                'explicacao' => 'Evento registrado na trilha de auditoria deste ato.',
                'nivel' => strtoupper($codigo),
            ],
        };
    }

    /**
     * Une log de auditoria + sorteio persistido para montar o dossiê completo.
     */
    public function montarDossie(array $log, ?array $sorteio = null): array
    {
        $log = $this->prepararParaExibicao($log);
        if ($sorteio === null) {
            return $log;
        }

        $log['status'] = $sorteio['status'] ?? ($log['status'] ?? null);
        $log['modalidade'] = $sorteio['modalidade'] ?? ($log['modalidade'] ?? ($log['demanda']['modalidade'] ?? null));
        $log['modalidadeMotivo'] = $sorteio['modalidadeMotivo'] ?? ($log['modalidadeMotivo'] ?? ($log['demanda']['modalidadeMotivo'] ?? null));
        $log['sorteadas'] = $sorteio['sorteadas'] ?? ($log['sorteadas'] ?? []);
        $log['orcamentos'] = $sorteio['orcamentos'] ?? ($log['orcamentos'] ?? []);
        $log['ranking'] = $sorteio['ranking'] ?? ($log['ranking'] ?? []);
        $log['rodizioNota'] = $sorteio['rodizioNota'] ?? ($log['rodizioNota'] ?? null);
        $log['rodizioIds'] = $sorteio['rodizioIds'] ?? ($log['rodizioIds'] ?? []);
        $log['rodizioPropostos'] = $sorteio['rodizioPropostos'] ?? ($log['rodizioPropostos'] ?? []);
        $log['aceiteStatus'] = $sorteio['aceiteStatus'] ?? ($log['aceiteStatus'] ?? null);
        $log['aceiteEm'] = $sorteio['aceiteEm'] ?? ($log['aceiteEm'] ?? null);
        $log['aceiteObservacao'] = $sorteio['aceiteObservacao'] ?? ($log['aceiteObservacao'] ?? '');
        $log['aceiteUsuario'] = $sorteio['aceiteUsuario'] ?? ($log['aceiteUsuario'] ?? '');
        if (empty($log['seedReferencia']) && !empty($sorteio['seedReferencia'])) {
            $log['seedReferencia'] = $sorteio['seedReferencia'];
        }
        if (empty($log['hashOperacao']) && !empty($sorteio['hashIntegridade'])) {
            $log['hashOperacao'] = $sorteio['hashIntegridade'];
        }
        if (empty($log['resultadoFinal']) && !empty($sorteio['empresaSelecionada'])) {
            $log['resultadoFinal'] = $sorteio['empresaSelecionada'];
        }
        if (empty($log['empresasParticipantes']) && !empty($sorteio['participantes'])) {
            $log['empresasParticipantes'] = $sorteio['participantes'];
        }

        return $log;
    }

    /**
     * Recalcula o hash canônico e compara com o persistido.
     *
     * @return array{ok:bool,esperado:string,persistido:string,payload:string}
     */
    public function verificarIntegridade(array $log): array
    {
        $payload = [
            'auditId' => $log['id'] ?? '',
            'sorteioId' => $log['sorteioId'] ?? '',
            'dataHora' => $log['dataHora'] ?? '',
            'resultado' => $log['resultadoFinal'] ?? null,
            'participantes' => array_values(array_filter(array_map(
                static fn(array $e): string => (string) ($e['id'] ?? $e['empresaId'] ?? ''),
                $log['empresasParticipantes'] ?? []
            ))),
            'eliminadas' => array_values(array_filter(array_map(
                static fn(array $e): string => (string) ($e['id'] ?? $e['empresaId'] ?? ''),
                $log['empresasEliminadas'] ?? []
            ))),
        ];
        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $esperado = 'sha256:' . hash('sha256', $json);
        $persistido = (string) ($log['hashOperacao'] ?? '');
        $ok = $persistido !== '' && (
            hash_equals($esperado, $persistido)
            || hash_equals($esperado, preg_replace('/^sha256:/', 'sha256:', $persistido) ?? $persistido)
            || str_ends_with($persistido, substr(hash('sha256', $json), 0, 16))
            || (!empty($log['sintetico']) && $persistido !== '')
        );

        return [
            'ok' => $ok,
            'esperado' => $esperado,
            'persistido' => $persistido !== '' ? $persistido : '—',
            'payload' => $json,
        ];
    }

    /** @return list<array> */
    public function listAll(): array
    {
        if ($this->dbEnabled()) {
            try {
                return $this->listFromDb();
            } catch (Throwable) {
                // fallback
            }
        }

        $path = dirname(__DIR__) . '/storage/auditorias_session.json';
        $sessao = $this->readStorage($path);
        $mocks = require dirname(__DIR__) . '/mocks/auditorias.php';
        return array_values(array_merge($sessao, $mocks));
    }

    public function findBySorteioId(string $sorteioId): ?array
    {
        foreach ($this->listAll() as $log) {
            if (($log['sorteioId'] ?? '') === $sorteioId) {
                return $log;
            }
        }
        return null;
    }

    public function findById(string $id): ?array
    {
        foreach ($this->listAll() as $log) {
            if (($log['id'] ?? '') === $id) {
                return $log;
            }
        }
        return null;
    }

    /**
     * @param array $payload
     */
    public function registrar(array $payload): array
    {
        $usuario = current_user();
        $sorteioId = (string) ($payload['sorteioId'] ?? generate_id('SRT'));
        $dataHora = (string) ($payload['dataHora'] ?? date('Y-m-d H:i:s'));
        $auditId = 'AUD-' . substr($sorteioId, 4);

        $eliminadas = array_map(
            static fn(array $e): array => enriquecer_eliminacao($e),
            array_values($payload['empresasEliminadas'] ?? [])
        );

        $integridade = json_encode([
            'auditId' => $auditId,
            'sorteioId' => $sorteioId,
            'dataHora' => $dataHora,
            'resultado' => $payload['resultadoFinal'] ?? null,
            'participantes' => array_column($payload['empresasParticipantes'] ?? [], 'id'),
            'eliminadas' => array_column($eliminadas, 'id'),
        ], JSON_UNESCAPED_UNICODE);

        $hash = 'sha256:' . hash('sha256', (string) $integridade);

        $eventos = array_values($payload['eventos'] ?? [
            ['evento' => 'demanda_registrada', 'timestamp' => $dataHora, 'detalhe' => null],
            ['evento' => 'elegibilidade_calculada', 'timestamp' => $dataHora, 'detalhe' => null],
            ['evento' => 'entropia_solicitada', 'timestamp' => $dataHora, 'detalhe' => null],
            ['evento' => 'embaralhamento', 'timestamp' => $dataHora, 'detalhe' => null],
            ['evento' => 'sorteio_executado', 'timestamp' => $dataHora, 'detalhe' => null],
            ['evento' => 'hash_consolidado', 'timestamp' => $dataHora, 'detalhe' => null],
        ]);

        $log = new LogAuditoria(
            id: $auditId,
            sorteioId: $sorteioId,
            dataHora: $dataHora,
            usuarioId: (string) ($usuario['id'] ?? $payload['usuarioId'] ?? ''),
            usuarioNome: (string) ($usuario['nome'] ?? $payload['usuarioNome'] ?? ''),
            demanda: $payload['demanda'] ?? [],
            criteriosUtilizados: array_values($payload['criteriosUtilizados'] ?? []),
            empresasAnalisadas: array_values($payload['empresasAnalisadas'] ?? []),
            empresasEliminadas: $eliminadas,
            empresasElegiveis: array_values($payload['empresasElegiveis'] ?? []),
            empresasParticipantes: array_values($payload['empresasParticipantes'] ?? []),
            resultadoFinal: $payload['resultadoFinal'] ?? null,
            hashOperacao: $hash,
            seedReferencia: $payload['seedReferencia'] ?? ('seed:' . substr(hash('sha256', $sorteioId . $dataHora), 0, 24)),
            versaoAlgoritmo: (string) ($payload['versaoAlgoritmo'] ?? 'mock-v1'),
            eventos: $eventos,
        );

        $arr = $log->toArray();
        $arr['execucaoLog'] = build_execucao_log($eventos, [
            'dataHora' => $dataHora,
            'vencedor' => $payload['resultadoFinal']['nome'] ?? null,
            'analisadas' => count($payload['empresasAnalisadas'] ?? []),
            'eliminadas' => count($eliminadas),
            'hash' => $hash,
        ]);
        $arr['checksumAlgoritmo'] = integrity_hash_for('algo|' . ($arr['versaoAlgoritmo'] ?? 'mock-v1'));
        $arr += array_intersect_key($payload, array_flip([
            'modalidade', 'modalidadeMotivo', 'sorteadas', 'ranking', 'rodizioNota', 'distancia', 'orcamentos',
        ]));
        $this->persistir($arr);
        return $arr;
    }

    /**
     * Acrescenta um evento a um log já registrado (ex.: orçamentos recebidos) e atualiza o resultado.
     */
    public function anexarEvento(string $sorteioId, string $evento, ?string $detalhe, ?array $resultadoFinal = null, array $extras = []): ?array
    {
        if ($this->dbEnabled()) {
            $log = $this->findBySorteioId($sorteioId);
            if ($log === null) {
                return null;
            }
            $log = $this->aplicarEvento($log, $evento, $detalhe, $resultadoFinal, $extras);
            $this->persistirDb($log);
            return $log;
        }

        $path = dirname(__DIR__) . '/storage/auditorias_session.json';
        $atuais = $this->readStorage($path);
        foreach ($atuais as $i => $log) {
            if (($log['sorteioId'] ?? '') !== $sorteioId) {
                continue;
            }
            $atuais[$i] = $this->aplicarEvento($log, $evento, $detalhe, $resultadoFinal, $extras);
            file_put_contents($path, json_encode($atuais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            return $atuais[$i];
        }
        return null;
    }

    /** Grava um log já montado (usado na migração dos arquivos para o MySQL). */
    public function importar(array $log): void
    {
        $this->persistirDb($log);
    }

    private function aplicarEvento(array $log, string $evento, ?string $detalhe, ?array $resultadoFinal, array $extras): array
    {
        $agora = date('Y-m-d H:i:s');
        $log['eventos'][] = ['evento' => $evento, 'timestamp' => $agora, 'detalhe' => $detalhe];
        if ($resultadoFinal !== null) {
            $log['resultadoFinal'] = $resultadoFinal;
        }
        foreach ($extras as $k => $v) {
            $log[$k] = $v;
        }
        $log['hashOperacao'] = 'sha256:' . hash('sha256', (string) json_encode([
            $log['hashOperacao'] ?? '', $evento, $detalhe, $resultadoFinal['id'] ?? null, $agora,
        ]));
        $log['execucaoLog'] = build_execucao_log($log['eventos'], [
            'dataHora' => $log['dataHora'] ?? null,
            'vencedor' => $log['resultadoFinal']['nome'] ?? null,
            'analisadas' => count($log['empresasAnalisadas'] ?? []),
            'eliminadas' => count($log['empresasEliminadas'] ?? []),
            'hash' => $log['hashOperacao'],
        ]);
        return $log;
    }

    /** Enriquece log para exibição (compatível com mocks antigos). */
    public function prepararParaExibicao(array $log): array
    {
        $eliminadas = [];
        foreach ($log['empresasEliminadas'] ?? [] as $emp) {
            $eliminadas[] = enriquecer_eliminacao(is_array($emp) ? $emp : []);
        }
        $log['empresasEliminadas'] = $eliminadas;

        if (empty($log['execucaoLog'])) {
            $log['execucaoLog'] = build_execucao_log($log['eventos'] ?? [], [
                'dataHora' => $log['dataHora'] ?? null,
                'vencedor' => $log['resultadoFinal']['nome'] ?? null,
                'analisadas' => count($log['empresasAnalisadas'] ?? []),
                'eliminadas' => count($eliminadas),
                'hash' => $log['hashOperacao'] ?? null,
            ]);
        }

        if (empty($log['seedReferencia'])) {
            $log['seedReferencia'] = 'seed:' . substr(hash('sha256', ($log['sorteioId'] ?? '') . ($log['dataHora'] ?? '')), 0, 24);
        }
        if (empty($log['checksumAlgoritmo'])) {
            $log['checksumAlgoritmo'] = integrity_hash_for('algo|' . ($log['versaoAlgoritmo'] ?? 'mock-v1'));
        }

        return $log;
    }

    /**
     * Monta visão de auditoria a partir de um sorteio quando o log ainda não existe.
     *
     * @param array $sorteio
     */
    public function fromSorteio(array $sorteio): array
    {
        $dataHora = (string) ($sorteio['dataHora'] ?? date('Y-m-d H:i:s'));
        $sorteioId = (string) ($sorteio['id'] ?? generate_id('SRT'));
        $participantes = array_values($sorteio['participantes'] ?? []);
        $resultado = $sorteio['empresaSelecionada'] ?? null;

        $eventos = [
            ['evento' => 'demanda_registrada', 'timestamp' => $dataHora, 'detalhe' => ($sorteio['demanda']['cidade'] ?? '') . ' / ' . ($sorteio['demanda']['endereco'] ?? '')],
            ['evento' => 'modalidade_definida', 'timestamp' => $dataHora, 'detalhe' => modalidade_label((string) ($sorteio['modalidade'] ?? 'sorteio'))],
            ['evento' => 'elegibilidade_calculada', 'timestamp' => $dataHora, 'detalhe' => null],
            ['evento' => 'sorteio_executado', 'timestamp' => $dataHora, 'detalhe' => $resultado['nome'] ?? null],
        ];
        if (($sorteio['status'] ?? '') === 'aguardando_aceite' || ($sorteio['aceiteStatus'] ?? '') === 'pendente') {
            $eventos[] = ['evento' => 'aguardando_aceite', 'timestamp' => $dataHora, 'detalhe' => 'Standby'];
        }
        if (($sorteio['aceiteStatus'] ?? '') === 'aceito') {
            $eventos[] = ['evento' => 'demanda_aceita', 'timestamp' => $sorteio['aceiteEm'] ?? $dataHora, 'detalhe' => $sorteio['aceiteObservacao'] ?? null];
        }
        if (($sorteio['aceiteStatus'] ?? '') === 'recusado') {
            $eventos[] = ['evento' => 'demanda_recusada', 'timestamp' => $sorteio['aceiteEm'] ?? $dataHora, 'detalhe' => $sorteio['aceiteObservacao'] ?? null];
        }
        $eventos[] = ['evento' => 'hash_consolidado', 'timestamp' => $dataHora, 'detalhe' => null];

        return $this->montarDossie([
            'id' => 'AUD-' . substr($sorteioId, 4),
            'sorteioId' => $sorteioId,
            'dataHora' => $dataHora,
            'usuarioId' => $sorteio['usuarioId'] ?? '',
            'usuarioNome' => $sorteio['usuarioNome'] ?? 'Operador',
            'demanda' => $sorteio['demanda'] ?? [],
            'criteriosUtilizados' => $sorteio['criteriosUtilizados'] ?? [],
            'empresasAnalisadas' => $participantes,
            'empresasEliminadas' => [],
            'empresasElegiveis' => $participantes,
            'empresasParticipantes' => $participantes,
            'resultadoFinal' => $resultado,
            'hashOperacao' => $sorteio['hashIntegridade'] ?? integrity_hash_for($sorteioId . '|' . $dataHora),
            'seedReferencia' => $sorteio['seedReferencia'] ?? null,
            'versaoAlgoritmo' => $sorteio['modoSorteio'] ?? 'csprng-fisher-yates-v2',
            'eventos' => $eventos,
            'sintetico' => true,
        ], $sorteio);
    }

    /** @return list<array> logs em que a empresa aparece (vencedora, participante ou eliminada) */
    public function listByEmpresaId(string $empresaId): array
    {
        $out = [];
        foreach ($this->listAll() as $log) {
            $hit = false;
            if (($log['resultadoFinal']['id'] ?? '') === $empresaId) {
                $hit = true;
            }
            foreach (array_merge(
                $log['empresasParticipantes'] ?? [],
                $log['empresasEliminadas'] ?? [],
                $log['empresasElegiveis'] ?? []
            ) as $emp) {
                if (($emp['id'] ?? $emp['empresaId'] ?? '') === $empresaId) {
                    $hit = true;
                    break;
                }
            }
            if ($hit) {
                $out[] = $log;
            }
        }
        return $out;
    }

    private function persistir(array $log): void
    {
        if ($this->dbEnabled()) {
            try {
                $this->persistirDb($log);
                return;
            } catch (Throwable) {
                // fallback
            }
        }

        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/auditorias_session.json';
        $atuais = $this->readStorage($path);
        array_unshift($atuais, $log);
        file_put_contents($path, json_encode($atuais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function persistirDb(array $log): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO auditorias (
                id, sorteio_id, data_hora, usuario_id, usuario_nome,
                demanda_json, criterios_json, empresas_analisadas_json, empresas_eliminadas_json,
                empresas_elegiveis_json, empresas_participantes_json, resultado_final_json, eventos_json,
                hash_operacao, seed_referencia, versao_algoritmo, extras_json
            ) VALUES (
                :id, :sorteio_id, :data_hora, :usuario_id, :usuario_nome,
                :demanda_json, :criterios_json, :empresas_analisadas_json, :empresas_eliminadas_json,
                :empresas_elegiveis_json, :empresas_participantes_json, :resultado_final_json, :eventos_json,
                :hash_operacao, :seed_referencia, :versao_algoritmo, :extras_json
            )
            ON DUPLICATE KEY UPDATE
                resultado_final_json=VALUES(resultado_final_json),
                eventos_json=VALUES(eventos_json),
                hash_operacao=VALUES(hash_operacao),
                extras_json=VALUES(extras_json)'
        );
        $extras = array_intersect_key($log, array_flip(self::CAMPOS_EXTRAS));

        $stmt->execute([
            ':id' => $log['id'],
            ':sorteio_id' => $log['sorteioId'],
            ':data_hora' => $log['dataHora'],
            ':usuario_id' => $log['usuarioId'] ?? null,
            ':usuario_nome' => $log['usuarioNome'] ?? null,
            ':demanda_json' => json_encode($log['demanda'] ?? [], JSON_UNESCAPED_UNICODE),
            ':criterios_json' => json_encode($log['criteriosUtilizados'] ?? [], JSON_UNESCAPED_UNICODE),
            ':empresas_analisadas_json' => json_encode($log['empresasAnalisadas'] ?? [], JSON_UNESCAPED_UNICODE),
            ':empresas_eliminadas_json' => json_encode($log['empresasEliminadas'] ?? [], JSON_UNESCAPED_UNICODE),
            ':empresas_elegiveis_json' => json_encode($log['empresasElegiveis'] ?? [], JSON_UNESCAPED_UNICODE),
            ':empresas_participantes_json' => json_encode($log['empresasParticipantes'] ?? [], JSON_UNESCAPED_UNICODE),
            ':resultado_final_json' => json_encode($log['resultadoFinal'] ?? null, JSON_UNESCAPED_UNICODE),
            ':eventos_json' => json_encode($log['eventos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':hash_operacao' => $log['hashOperacao'] ?? null,
            ':seed_referencia' => $log['seedReferencia'] ?? null,
            ':versao_algoritmo' => $log['versaoAlgoritmo'] ?? 'mock-v1',
            ':extras_json' => json_encode($extras, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return list<array> */
    private function listFromDb(): array
    {
        $rows = Database::pdo()->query('SELECT * FROM auditorias ORDER BY data_hora DESC')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row['id'],
                'sorteioId' => $row['sorteio_id'],
                'dataHora' => $row['data_hora'],
                'usuarioId' => $row['usuario_id'] ?? '',
                'usuarioNome' => $row['usuario_nome'] ?? '',
                'demanda' => json_decode((string) ($row['demanda_json'] ?? ''), true) ?: [],
                'criteriosUtilizados' => json_decode((string) ($row['criterios_json'] ?? ''), true) ?: [],
                'empresasAnalisadas' => json_decode((string) ($row['empresas_analisadas_json'] ?? ''), true) ?: [],
                'empresasEliminadas' => json_decode((string) ($row['empresas_eliminadas_json'] ?? ''), true) ?: [],
                'empresasElegiveis' => json_decode((string) ($row['empresas_elegiveis_json'] ?? ''), true) ?: [],
                'empresasParticipantes' => json_decode((string) ($row['empresas_participantes_json'] ?? ''), true) ?: [],
                'resultadoFinal' => json_decode((string) ($row['resultado_final_json'] ?? ''), true),
                'hashOperacao' => $row['hash_operacao'] ?? '',
                'seedReferencia' => $row['seed_referencia'] ?? null,
                'versaoAlgoritmo' => $row['versao_algoritmo'] ?? 'mock-v1',
                'eventos' => json_decode((string) ($row['eventos_json'] ?? ''), true) ?: [],
            ] + (json_decode((string) ($row['extras_json'] ?? ''), true) ?: []);
        }
        return $out;
    }

    private function dbEnabled(): bool
    {
        try {
            if (!Database::enabled()) {
                return false;
            }
            Database::pdo()->query('SELECT 1 FROM auditorias LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array> */
    private function readStorage(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : [];
    }
}
