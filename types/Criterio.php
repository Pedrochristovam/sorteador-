<?php

declare(strict_types=1);

/**
 * Critério de elegibilidade configurável.
 */
final class Criterio
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $nome,
        public readonly string $descricao,
        public readonly bool $obrigatorio = true,
        public readonly string $tipo = 'boolean',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            codigo: (string) $data['codigo'],
            nome: (string) $data['nome'],
            descricao: (string) ($data['descricao'] ?? ''),
            obrigatorio: (bool) ($data['obrigatorio'] ?? true),
            tipo: (string) ($data['tipo'] ?? 'boolean'),
        );
    }

    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'obrigatorio' => $this->obrigatorio,
            'tipo' => $this->tipo,
        ];
    }
}
