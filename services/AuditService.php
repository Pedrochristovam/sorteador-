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
        'execucaoLog', 'checksumAlgoritmo', 'sintetico', 'aceiteStatus', 'rodizioIds',
    ];

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

        return $this->prepararParaExibicao([
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
            'seedReferencia' => null,
            'versaoAlgoritmo' => 'mock-v1',
            'eventos' => [
                ['evento' => 'demanda_registrada', 'timestamp' => $dataHora, 'detalhe' => null],
                ['evento' => 'elegibilidade_calculada', 'timestamp' => $dataHora, 'detalhe' => null],
                ['evento' => 'sorteio_executado', 'timestamp' => $dataHora, 'detalhe' => null],
            ],
            'sintetico' => true,
        ]);
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
