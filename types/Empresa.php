<?php

declare(strict_types=1);

/**
 * Representa uma empresa credenciada / conveniada.
 */
final class Empresa
{
    public function __construct(
        public readonly string $id,
        public readonly string $nome,
        public readonly string $cnpj,
        public readonly string $cidade,
        public readonly string $uf,
        public readonly string $endereco,
        public readonly string $bairro,
        public readonly string $cep,
        /** @var list<string> */
        public readonly array $servicos,
        public readonly string $status,
        public readonly string $dataConvenio,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $telefone = null,
        public readonly ?string $email = null,
        public readonly ?string $responsavel = null,
        public readonly ?string $observacoes = null,
        public readonly string $numeroContrato = '',
        public readonly int $ordemCredenciamento = 0,
        /** @var list<string> */
        public readonly array $atividadesCodigos = [],
        /** @var list<string> */
        public readonly array $regioesMunicipios = [],
        /** @var list<string> */
        public readonly array $regioesMunicipiosCodigos = [],
        /** @var list<string> */
        public readonly array $estadosAtuacao = [],
        /** @var list<string> */
        public readonly array $estadosAtuacaoCodigos = [],
        /** false = atende somente até 100 km ida e volta (50 km ida + 50 km volta). */
        public readonly bool $atendeAcima100km = true,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            nome: (string) $data['nome'],
            cnpj: (string) ($data['cnpj'] ?? ''),
            cidade: (string) $data['cidade'],
            uf: (string) ($data['uf'] ?? 'MG'),
            endereco: (string) $data['endereco'],
            bairro: (string) ($data['bairro'] ?? ''),
            cep: (string) ($data['cep'] ?? ''),
            servicos: array_values($data['servicos'] ?? []),
            status: (string) $data['status'],
            dataConvenio: (string) $data['dataConvenio'],
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            telefone: $data['telefone'] ?? null,
            email: $data['email'] ?? null,
            responsavel: $data['responsavel'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            numeroContrato: (string) ($data['numeroContrato'] ?? ''),
            ordemCredenciamento: (int) ($data['ordemCredenciamento'] ?? 0),
            atividadesCodigos: array_values($data['atividadesCodigos'] ?? []),
            regioesMunicipios: array_values($data['regioesMunicipios'] ?? []),
            regioesMunicipiosCodigos: array_values($data['regioesMunicipiosCodigos'] ?? []),
            estadosAtuacao: array_values($data['estadosAtuacao'] ?? []),
            estadosAtuacaoCodigos: array_values($data['estadosAtuacaoCodigos'] ?? []),
            atendeAcima100km: filter_var($data['atendeAcima100km'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cnpj' => $this->cnpj,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'endereco' => $this->endereco,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'servicos' => $this->servicos,
            'status' => $this->status,
            'dataConvenio' => $this->dataConvenio,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'responsavel' => $this->responsavel,
            'observacoes' => $this->observacoes,
            'numeroContrato' => $this->numeroContrato,
            'ordemCredenciamento' => $this->ordemCredenciamento,
            'atividadesCodigos' => $this->atividadesCodigos,
            'regioesMunicipios' => $this->regioesMunicipios,
            'regioesMunicipiosCodigos' => $this->regioesMunicipiosCodigos,
            'estadosAtuacao' => $this->estadosAtuacao,
            'estadosAtuacaoCodigos' => $this->estadosAtuacaoCodigos,
            'atendeAcima100km' => $this->atendeAcima100km,
        ];
    }

    public function enderecoCompleto(): string
    {
        return trim($this->endereco . ', ' . $this->cidade . ' - ' . $this->uf, ' ,-');
    }

    public function isAtiva(): bool
    {
        return strtolower($this->status) === 'ativa';
    }
}
