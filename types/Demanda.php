<?php

declare(strict_types=1);

/**
 * Representa a demanda que originará o sorteio (Engenharia).
 */
final class Demanda
{
    /** Atividades que sempre exigem orçamento entre 3 credenciadas. */
    public const ATIVIDADES_ORCAMENTO = ['C', 'D', 'E'];
    public const LIMITE_AREA_CONSTRUIDA_M2 = 1000.0;
    public const LIMITE_AREA_TERRENO_M2 = 10000.0;

    public const MODALIDADES = [
        'sorteio' => 'Sorteio com rodízio',
        'proxima' => 'Mais próxima (preço definido em edital)',
        'orcamento' => 'Orçamento entre 3 sorteadas',
    ];

    /**
     * @param list<string> $atividadesCodigos
     * @param list<string> $servicosNecessarios
     * @param list<string> $criteriosAdicionais
     */
    public function __construct(
        public readonly string $cidade,
        public readonly string $uf,
        public readonly string $endereco,
        public readonly string $regiaoCodigo,
        public readonly string $regiaoNome,
        public readonly array $atividadesCodigos,
        /** Nomes das atividades (exibição e compatibilidade com o histórico). */
        public readonly array $servicosNecessarios,
        public readonly string $servicoNecessario,
        public readonly ?float $areaConstruidaM2 = null,
        public readonly ?float $areaTerrenoM2 = null,
        public readonly bool $precoEdital = false,
        /** auto | sorteio | proxima | orcamento */
        public readonly string $modalidadeSolicitada = 'auto',
        public readonly bool $somenteCidade = false,
        public readonly array $criteriosAdicionais = [],
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {
    }

    /**
     * @param array<string,string> $catalogoAtividades código => nome
     * @param array<string,string> $catalogoRegioes código => nome (municípios MG + estados)
     */
    public static function fromArray(array $data, array $catalogoAtividades = [], array $catalogoRegioes = []): self
    {
        $codigos = self::normalizeLista($data['atividadesCodigos'] ?? []);
        if ($codigos === [] && $catalogoAtividades !== []) {
            // Compatibilidade: demandas antigas guardavam só o nome da atividade
            $porNome = array_flip(array_map('mb_strtolower', $catalogoAtividades));
            foreach (self::normalizeServicos($data) as $nome) {
                $cod = $porNome[mb_strtolower($nome)] ?? null;
                if ($cod !== null) {
                    $codigos[] = $cod;
                }
            }
        }

        $nomes = $catalogoAtividades !== []
            ? array_values(array_map(static fn(string $c): string => $catalogoAtividades[$c] ?? $c, $codigos))
            : self::normalizeServicos($data);

        $regiao = strtoupper(trim((string) ($data['regiaoCodigo'] ?? '')));

        return new self(
            cidade: trim((string) ($data['cidade'] ?? '')),
            uf: strtoupper(trim((string) ($data['uf'] ?? 'MG'))) ?: 'MG',
            endereco: trim((string) ($data['endereco'] ?? '')),
            regiaoCodigo: $regiao,
            regiaoNome: (string) ($catalogoRegioes[$regiao] ?? ($data['regiaoNome'] ?? '')),
            atividadesCodigos: array_values(array_unique($codigos)),
            servicosNecessarios: $nomes,
            servicoNecessario: implode(' | ', $nomes),
            areaConstruidaM2: self::numeroOuNull($data['areaConstruidaM2'] ?? null),
            areaTerrenoM2: self::numeroOuNull($data['areaTerrenoM2'] ?? null),
            precoEdital: filter_var($data['precoEdital'] ?? false, FILTER_VALIDATE_BOOLEAN),
            modalidadeSolicitada: in_array($data['modalidadeSolicitada'] ?? 'auto', ['auto', 'sorteio', 'proxima', 'orcamento'], true)
                ? (string) ($data['modalidadeSolicitada'] ?? 'auto')
                : 'auto',
            somenteCidade: filter_var($data['somenteCidade'] ?? false, FILTER_VALIDATE_BOOLEAN),
            criteriosAdicionais: array_values($data['criteriosAdicionais'] ?? []),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
        );
    }

    /**
     * Regra definida pela própria demanda:
     *   - Laudo Rural, Levantamento Topográfico, Georreferenciamento, ou área construída > 1.000 m²,
     *     ou terreno > 10.000 m² → orçamento entre 3 sorteadas (a mais barata leva);
     *   - laudo com preço definido em edital → a mais próxima da demanda;
     *   - demais casos → sorteio com rodízio.
     *
     * @return array{codigo:string,motivo:string}
     */
    public function modalidadeSugerida(): array
    {
        $orcamento = array_values(array_intersect($this->atividadesCodigos, self::ATIVIDADES_ORCAMENTO));
        if ($orcamento !== []) {
            return ['codigo' => 'orcamento', 'motivo' => 'Atividade ' . implode(', ', $orcamento) . ' exige orçamento entre 3 credenciadas.'];
        }
        if (($this->areaConstruidaM2 ?? 0) > self::LIMITE_AREA_CONSTRUIDA_M2) {
            return ['codigo' => 'orcamento', 'motivo' => 'Área construída acima de 1.000 m² exige orçamento entre 3 credenciadas.'];
        }
        if (($this->areaTerrenoM2 ?? 0) > self::LIMITE_AREA_TERRENO_M2) {
            return ['codigo' => 'orcamento', 'motivo' => 'Terreno acima de 10.000 m² exige orçamento entre 3 credenciadas.'];
        }
        if ($this->precoEdital) {
            return ['codigo' => 'proxima', 'motivo' => 'Laudo com preço definido em edital: escolhe a credenciada mais próxima.'];
        }
        return ['codigo' => 'sorteio', 'motivo' => 'Sem preço de edital nem exigência de orçamento: sorteio com rodízio.'];
    }

    /** @return array{codigo:string,motivo:string,manual:bool} */
    public function modalidadeAplicada(): array
    {
        $sugerida = $this->modalidadeSugerida();
        if ($this->modalidadeSolicitada === 'auto' || $this->modalidadeSolicitada === $sugerida['codigo']) {
            return $sugerida + ['manual' => false];
        }
        return [
            'codigo' => $this->modalidadeSolicitada,
            'motivo' => 'Definida manualmente pelo operador (sugestão do sistema: ' . self::MODALIDADES[$sugerida['codigo']] . ').',
            'manual' => true,
        ];
    }

    /** @return list<string> */
    public static function normalizeServicos(array $data): array
    {
        $raw = $data['servicosNecessarios'] ?? $data['servicoNecessario'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\s*\|\s*|\s*,\s*/', $raw) ?: [];
        }
        return self::normalizeLista($raw);
    }

    /** @return list<string> */
    private static function normalizeLista(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private static function numeroOuNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = trim((string) $v);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        return is_numeric($v) ? (float) $v : null;
    }

    public function toArray(): array
    {
        $modalidade = $this->modalidadeAplicada();
        return [
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'endereco' => $this->endereco,
            'regiaoCodigo' => $this->regiaoCodigo,
            'regiaoNome' => $this->regiaoNome,
            'atividadesCodigos' => $this->atividadesCodigos,
            'servicosNecessarios' => $this->servicosNecessarios,
            'servicoNecessario' => $this->servicoNecessario,
            'areaConstruidaM2' => $this->areaConstruidaM2,
            'areaTerrenoM2' => $this->areaTerrenoM2,
            'precoEdital' => $this->precoEdital,
            'modalidadeSolicitada' => $this->modalidadeSolicitada,
            'modalidade' => $modalidade['codigo'],
            'modalidadeMotivo' => $modalidade['motivo'],
            'modalidadeManual' => $modalidade['manual'],
            'somenteCidade' => $this->somenteCidade,
            'criteriosAdicionais' => $this->criteriosAdicionais,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            // Campos antigos mantidos vazios para o histórico/exportação
            'tipoDemanda' => '',
        ];
    }
}
