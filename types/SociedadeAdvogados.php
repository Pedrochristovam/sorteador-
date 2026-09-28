<?php

declare(strict_types=1);

/**
 * Sociedade de Advogados — Credenciamento Edital 01/2026.
 */
final class SociedadeAdvogados
{
    /**
     * @param list<string> $atividadesCodigos
     * @param list<string> $atividades
     * @param list<string> $polosCodigos
     * @param list<string> $polos
     */
    public function __construct(
        public readonly string $id,
        public readonly string $razaoSocial,
        public readonly string $nomeFantasia,
        public readonly string $cnpj,
        public readonly string $oabSociedade,
        public readonly string $cidadeSede,
        public readonly string $ufSede,
        public readonly string $endereco,
        public readonly string $bairro,
        public readonly string $cep,
        public readonly string $status,
        public readonly string $statusOperacional,
        public readonly string $dataCredenciamento,
        public readonly string $numeroContrato,
        public readonly int $ordemCredenciamento,
        public readonly array $atividadesCodigos = [],
        public readonly array $atividades = [],
        public readonly array $polosCodigos = [],
        public readonly array $polos = [],
        public readonly ?string $telefone = null,
        public readonly ?string $email = null,
        public readonly ?string $responsavel = null,
        public readonly string $oabResponsavel = '',
        public readonly string $processoSei = '',
        public readonly ?string $observacoes = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            razaoSocial: (string) $data['razaoSocial'],
            nomeFantasia: (string) ($data['nomeFantasia'] ?? ''),
            cnpj: (string) ($data['cnpj'] ?? ''),
            oabSociedade: (string) ($data['oabSociedade'] ?? ''),
            cidadeSede: (string) $data['cidadeSede'],
            ufSede: (string) ($data['ufSede'] ?? 'MG'),
            endereco: (string) ($data['endereco'] ?? ''),
            bairro: (string) ($data['bairro'] ?? ''),
            cep: (string) ($data['cep'] ?? ''),
            status: (string) ($data['status'] ?? 'ativa'),
            statusOperacional: (string) ($data['statusOperacional'] ?? 'apto'),
            dataCredenciamento: (string) ($data['dataCredenciamento'] ?? date('Y-m-d')),
            numeroContrato: (string) ($data['numeroContrato'] ?? ''),
            ordemCredenciamento: (int) ($data['ordemCredenciamento'] ?? 0),
            atividadesCodigos: array_values($data['atividadesCodigos'] ?? []),
            atividades: array_values($data['atividades'] ?? []),
            polosCodigos: array_values($data['polosCodigos'] ?? []),
            polos: array_values($data['polos'] ?? []),
            telefone: $data['telefone'] ?? null,
            email: $data['email'] ?? null,
            responsavel: $data['responsavel'] ?? null,
            oabResponsavel: (string) ($data['oabResponsavel'] ?? ''),
            processoSei: (string) ($data['processoSei'] ?? ''),
            observacoes: $data['observacoes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'razaoSocial' => $this->razaoSocial,
            'nomeFantasia' => $this->nomeFantasia,
            'cnpj' => $this->cnpj,
            'oabSociedade' => $this->oabSociedade,
            'cidadeSede' => $this->cidadeSede,
            'ufSede' => $this->ufSede,
            'endereco' => $this->endereco,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'status' => $this->status,
            'statusOperacional' => $this->statusOperacional,
            'dataCredenciamento' => $this->dataCredenciamento,
            'numeroContrato' => $this->numeroContrato,
            'ordemCredenciamento' => $this->ordemCredenciamento,
            'atividadesCodigos' => $this->atividadesCodigos,
            'atividades' => $this->atividades,
            'polosCodigos' => $this->polosCodigos,
            'polos' => $this->polos,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'responsavel' => $this->responsavel,
            'oabResponsavel' => $this->oabResponsavel,
            'processoSei' => $this->processoSei,
            'observacoes' => $this->observacoes,
        ];
    }

    public function isAtiva(): bool
    {
        return strtolower($this->status) === 'ativa';
    }

    public function isAptaSorteio(): bool
    {
        return $this->isAtiva() && strtolower($this->statusOperacional) === 'apto';
    }
}
