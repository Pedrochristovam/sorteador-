<?php

declare(strict_types=1);

/**
 * Resultado da análise de elegibilidade de uma empresa.
 */
final class EmpresaElegivel
{
    public function __construct(
        public readonly string $empresaId,
        public readonly string $nome,
        public readonly string $cnpj,
        public readonly string $cidade,
        public readonly string $endereco,
        /** Rota de carro (só ida) até a demanda; null quando não apurada. */
        public readonly ?float $distanciaKm,
        /** @var list<string> */
        public readonly array $servicosCompativeis,
        /** @var list<array{codigo:string,nome:string,atendido:bool,motivo:?string}> */
        public readonly array $criterios,
        public readonly bool $elegivel,
        public readonly string $status,
        /** @var list<string> */
        public readonly array $motivosEliminacao = [],
        /** Dados de rota, rodízio e cadastro exibidos nas etapas seguintes. */
        public readonly array $extras = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $base = ['empresaId', 'nome', 'cnpj', 'cidade', 'endereco', 'distanciaKm', 'servicosCompativeis', 'criterios', 'elegivel', 'status', 'motivosEliminacao'];
        return new self(
            empresaId: (string) $data['empresaId'],
            nome: (string) $data['nome'],
            cnpj: (string) ($data['cnpj'] ?? ''),
            cidade: (string) $data['cidade'],
            endereco: (string) $data['endereco'],
            distanciaKm: isset($data['distanciaKm']) ? (float) $data['distanciaKm'] : null,
            servicosCompativeis: array_values($data['servicosCompativeis'] ?? []),
            criterios: array_values($data['criterios'] ?? []),
            elegivel: (bool) $data['elegivel'],
            status: (string) ($data['status'] ?? 'ativa'),
            motivosEliminacao: array_values($data['motivosEliminacao'] ?? []),
            extras: array_diff_key($data, array_flip($base)),
        );
    }

    public function toArray(): array
    {
        return [
            'empresaId' => $this->empresaId,
            'nome' => $this->nome,
            'cnpj' => $this->cnpj,
            'cidade' => $this->cidade,
            'endereco' => $this->endereco,
            'distanciaKm' => $this->distanciaKm,
            'servicosCompativeis' => $this->servicosCompativeis,
            'criterios' => $this->criterios,
            'elegivel' => $this->elegivel,
            'status' => $this->status,
            'motivosEliminacao' => $this->motivosEliminacao,
        ] + $this->extras;
    }
}
