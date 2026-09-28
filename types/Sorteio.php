<?php

declare(strict_types=1);

/**
 * Registro de um sorteio realizado ou em andamento.
 */
final class Sorteio
{
    public function __construct(
        public readonly string $id,
        public readonly string $dataHora,
        public readonly array $demanda,
        /** @var list<array> */
        public readonly array $participantes,
        public readonly ?array $empresaSelecionada,
        public readonly string $status,
        public readonly string $usuarioId,
        public readonly string $usuarioNome,
        /** @var list<string> */
        public readonly array $criteriosUtilizados = [],
        public readonly int $totalAnalisadas = 0,
        public readonly int $totalEliminadas = 0,
        public readonly int $totalElegiveis = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            dataHora: (string) $data['dataHora'],
            demanda: $data['demanda'] ?? [],
            participantes: array_values($data['participantes'] ?? []),
            empresaSelecionada: $data['empresaSelecionada'] ?? null,
            status: (string) $data['status'],
            usuarioId: (string) ($data['usuarioId'] ?? ''),
            usuarioNome: (string) ($data['usuarioNome'] ?? ''),
            criteriosUtilizados: array_values($data['criteriosUtilizados'] ?? []),
            totalAnalisadas: (int) ($data['totalAnalisadas'] ?? 0),
            totalEliminadas: (int) ($data['totalEliminadas'] ?? 0),
            totalElegiveis: (int) ($data['totalElegiveis'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dataHora' => $this->dataHora,
            'demanda' => $this->demanda,
            'participantes' => $this->participantes,
            'empresaSelecionada' => $this->empresaSelecionada,
            'status' => $this->status,
            'usuarioId' => $this->usuarioId,
            'usuarioNome' => $this->usuarioNome,
            'criteriosUtilizados' => $this->criteriosUtilizados,
            'totalAnalisadas' => $this->totalAnalisadas,
            'totalEliminadas' => $this->totalEliminadas,
            'totalElegiveis' => $this->totalElegiveis,
        ];
    }
}
