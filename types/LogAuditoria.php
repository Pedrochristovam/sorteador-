<?php

declare(strict_types=1);

/**
 * Log de auditoria de uma operação de sorteio.
 * Preparado para hash, identificadores e rastreabilidade futura.
 */
final class LogAuditoria
{
    public function __construct(
        public readonly string $id,
        public readonly string $sorteioId,
        public readonly string $dataHora,
        public readonly string $usuarioId,
        public readonly string $usuarioNome,
        public readonly array $demanda,
        /** @var list<string> */
        public readonly array $criteriosUtilizados,
        /** @var list<array> */
        public readonly array $empresasAnalisadas,
        /** @var list<array> */
        public readonly array $empresasEliminadas,
        /** @var list<array> */
        public readonly array $empresasElegiveis,
        /** @var list<array> */
        public readonly array $empresasParticipantes,
        public readonly ?array $resultadoFinal,
        public readonly string $hashOperacao = '',
        public readonly ?string $seedReferencia = null,
        public readonly string $versaoAlgoritmo = 'mock-v1',
        /** @var list<array{evento:string,timestamp:string,detalhe:?string}> */
        public readonly array $eventos = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            sorteioId: (string) $data['sorteioId'],
            dataHora: (string) $data['dataHora'],
            usuarioId: (string) $data['usuarioId'],
            usuarioNome: (string) $data['usuarioNome'],
            demanda: $data['demanda'] ?? [],
            criteriosUtilizados: array_values($data['criteriosUtilizados'] ?? []),
            empresasAnalisadas: array_values($data['empresasAnalisadas'] ?? []),
            empresasEliminadas: array_values($data['empresasEliminadas'] ?? []),
            empresasElegiveis: array_values($data['empresasElegiveis'] ?? []),
            empresasParticipantes: array_values($data['empresasParticipantes'] ?? []),
            resultadoFinal: $data['resultadoFinal'] ?? null,
            hashOperacao: (string) ($data['hashOperacao'] ?? ''),
            seedReferencia: $data['seedReferencia'] ?? null,
            versaoAlgoritmo: (string) ($data['versaoAlgoritmo'] ?? 'mock-v1'),
            eventos: array_values($data['eventos'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sorteioId' => $this->sorteioId,
            'dataHora' => $this->dataHora,
            'usuarioId' => $this->usuarioId,
            'usuarioNome' => $this->usuarioNome,
            'demanda' => $this->demanda,
            'criteriosUtilizados' => $this->criteriosUtilizados,
            'empresasAnalisadas' => $this->empresasAnalisadas,
            'empresasEliminadas' => $this->empresasEliminadas,
            'empresasElegiveis' => $this->empresasElegiveis,
            'empresasParticipantes' => $this->empresasParticipantes,
            'resultadoFinal' => $this->resultadoFinal,
            'hashOperacao' => $this->hashOperacao,
            'seedReferencia' => $this->seedReferencia,
            'versaoAlgoritmo' => $this->versaoAlgoritmo,
            'eventos' => $this->eventos,
        ];
    }
}
