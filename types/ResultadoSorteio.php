<?php

declare(strict_types=1);

/**
 * Resultado institucional de um sorteio (para publicação / download).
 */
final class ResultadoSorteio
{
    public function __construct(
        public readonly string $sorteioId,
        public readonly array $empresaSelecionada,
        public readonly array $demanda,
        public readonly string $dataHora,
        /** @var list<array> */
        public readonly array $participantes,
        /** @var list<string> */
        public readonly array $criteriosUtilizados,
        public readonly string $hashIntegridade = '',
        public readonly string $modoSorteio = 'mock',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sorteioId: (string) $data['sorteioId'],
            empresaSelecionada: $data['empresaSelecionada'] ?? [],
            demanda: $data['demanda'] ?? [],
            dataHora: (string) $data['dataHora'],
            participantes: array_values($data['participantes'] ?? []),
            criteriosUtilizados: array_values($data['criteriosUtilizados'] ?? []),
            hashIntegridade: (string) ($data['hashIntegridade'] ?? ''),
            modoSorteio: (string) ($data['modoSorteio'] ?? 'mock'),
        );
    }

    public function toArray(): array
    {
        return [
            'sorteioId' => $this->sorteioId,
            'empresaSelecionada' => $this->empresaSelecionada,
            'demanda' => $this->demanda,
            'dataHora' => $this->dataHora,
            'participantes' => $this->participantes,
            'criteriosUtilizados' => $this->criteriosUtilizados,
            'hashIntegridade' => $this->hashIntegridade,
            'modoSorteio' => $this->modoSorteio,
        ];
    }
}
