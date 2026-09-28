<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/types/Empresa.php';
require_once dirname(__DIR__) . '/types/Demanda.php';
require_once dirname(__DIR__) . '/types/EmpresaElegivel.php';
require_once dirname(__DIR__) . '/types/Criterio.php';
require_once dirname(__DIR__) . '/utils/helpers.php';
require_once __DIR__ . '/CompanyService.php';
require_once __DIR__ . '/DistanceService.php';
require_once __DIR__ . '/RodizioService.php';

/**
 * Elegibilidade do Sorteador de Engenharia.
 *
 * Ordem: atividade habilitada, credenciado ativo, região da demanda, documentação;
 * depois a rota de carro (só para quem passou) e o limite de 100 km ida e volta;
 * por fim a restrição opcional às sediadas na cidade da demanda.
 * O rodízio não elimina aqui: só marca quem já foi chamado no ciclo.
 */
final class EligibilityService
{
    public const LIMITE_IDA_VOLTA_KM = 100.0;

    private CompanyService $companyService;
    private DistanceService $distanceService;

    public function __construct(?CompanyService $companyService = null, ?DistanceService $distanceService = null)
    {
        $this->companyService = $companyService ?? new CompanyService();
        $this->distanceService = $distanceService ?? new DistanceService();
    }

    public function calcular(Demanda $demanda): array
    {
        $empresas = $this->companyService->listAll();
        $criterios = $this->resolverCriteriosAtivos($this->companyService->listCriterios(), $demanda->criteriosAdicionais);
        $codigosAtivos = array_map(static fn(Criterio $c): string => $c->codigo, $criterios);

        // 1) Critérios de cadastro
        $avaliacoes = [];
        foreach ($empresas as $empresa) {
            $avaliacoes[$empresa->id] = $this->avaliarCadastro($empresa, $demanda, $criterios);
        }

        // 2) Rota de carro só para quem passou no cadastro
        $origens = [];
        foreach ($empresas as $empresa) {
            if ($avaliacoes[$empresa->id]['aprovada']) {
                $origens[$empresa->id] = ['endereco' => $empresa->endereco, 'cidade' => $empresa->cidade, 'uf' => $empresa->uf];
            }
        }
        $rotas = $this->distanceService->rotasAte(
            ['endereco' => $demanda->endereco, 'cidade' => $demanda->cidade, 'uf' => $demanda->uf],
            $origens
        );

        // 3) Limite de 100 km ida e volta
        $aplicaLimite = in_array('limite_100km', $codigosAtivos, true);
        foreach ($empresas as $empresa) {
            $av = &$avaliacoes[$empresa->id];
            $rota = $rotas[$empresa->id] ?? null;
            $av['rota'] = $rota;
            if (!$aplicaLimite) {
                unset($av);
                continue;
            }
            [$ok, $motivo] = $this->checkLimite100km($empresa, $rota, $av['aprovada']);
            $av['criterios'][] = ['codigo' => 'limite_100km', 'nome' => 'Limite de deslocamento (100 km ida e volta)', 'atendido' => $ok, 'motivo' => $motivo];
            if (!$ok) {
                $av['aprovada'] = false;
                $av['motivos'][] = $motivo;
            }
            unset($av);
        }

        // 4) Restrição opcional às sediadas na cidade da demanda (vale com 2 ou mais)
        $cidadeDemanda = normalizar_texto($demanda->cidade);
        $naCidade = array_values(array_filter(
            $empresas,
            static fn(Empresa $e): bool => $avaliacoes[$e->id]['aprovada'] && normalizar_texto($e->cidade) === $cidadeDemanda
        ));
        $restricaoAplicada = $demanda->somenteCidade && count($naCidade) >= 2;
        if ($restricaoAplicada) {
            foreach ($empresas as $empresa) {
                $av = &$avaliacoes[$empresa->id];
                $mesma = normalizar_texto($empresa->cidade) === $cidadeDemanda;
                if ($av['aprovada']) {
                    $motivo = $mesma ? null : "Sorteio restrito às credenciadas sediadas em {$demanda->cidade}";
                    $av['criterios'][] = ['codigo' => 'somente_cidade', 'nome' => 'Sediada na cidade da demanda', 'atendido' => $mesma, 'motivo' => $motivo];
                    if (!$mesma) {
                        $av['aprovada'] = false;
                        $av['motivos'][] = $motivo;
                    }
                }
                unset($av);
            }
        }

        // 5) Rodízio geral (apenas marcação)
        $idsAtivas = array_values(array_map(
            static fn(Empresa $e): string => $e->id,
            array_filter($empresas, static fn(Empresa $e): bool => $e->isAtiva())
        ));
        $ciclo = (new RodizioService())->cicloAtual($idsAtivas);
        $chamadas = array_flip($ciclo['ids']);

        $elegiveis = [];
        $eliminadas = [];
        foreach ($empresas as $empresa) {
            $av = $avaliacoes[$empresa->id];
            $rota = $av['rota'];
            $item = (new EmpresaElegivel(
                empresaId: $empresa->id,
                nome: $empresa->nome,
                cnpj: $empresa->cnpj,
                cidade: $empresa->cidade,
                endereco: $empresa->endereco . ($empresa->bairro ? ' — ' . $empresa->bairro : ''),
                distanciaKm: $rota['km'] ?? null,
                servicosCompativeis: $av['servicosCompativeis'],
                criterios: $av['criterios'],
                elegivel: $av['aprovada'],
                status: $empresa->status,
                motivosEliminacao: $av['motivos'],
                extras: [
                    'uf' => $empresa->uf,
                    'duracaoMin' => $rota['minutos'] ?? null,
                    'idaVoltaKm' => isset($rota['km']) ? round($rota['km'] * 2, 1) : null,
                    'distanciaFonte' => $rota['fonte'] ?? null,
                    'distanciaPrecisao' => $rota['precisao'] ?? null,
                    'atendeAcima100km' => $empresa->atendeAcima100km,
                    'mesmaCidade' => normalizar_texto($empresa->cidade) === $cidadeDemanda,
                    'emRodizio' => isset($chamadas[$empresa->id]),
                    'ordemCredenciamento' => $empresa->ordemCredenciamento,
                    'telefone' => $empresa->telefone,
                    'email' => $empresa->email,
                ],
            ))->toArray();

            if ($av['aprovada']) {
                $elegiveis[] = $item;
            } else {
                $eliminadas[] = $item;
            }
        }

        $porDistancia = static fn(array $a, array $b): int
            => [$a['distanciaKm'] === null, $a['distanciaKm'] ?? 0, $a['ordemCredenciamento'] ?? 0]
            <=> [$b['distanciaKm'] === null, $b['distanciaKm'] ?? 0, $b['ordemCredenciamento'] ?? 0];
        usort($elegiveis, $porDistancia);
        usort($eliminadas, $porDistancia);

        $criteriosUtilizados = array_map(static fn(Criterio $c): string => $c->nome, $criterios);
        if ($restricaoAplicada) {
            $criteriosUtilizados[] = 'Sediada na cidade da demanda';
        }

        $fontes = array_values(array_unique(array_filter(array_column($rotas, 'fonte'))));

        return [
            'totalAnalisadas' => count($empresas),
            'totalEliminadas' => count($eliminadas),
            'totalElegiveis' => count($elegiveis),
            'elegiveis' => $elegiveis,
            'eliminadas' => $eliminadas,
            'criteriosUtilizados' => $criteriosUtilizados,
            'demanda' => $demanda->toArray(),
            'distancia' => [
                'provedor' => $this->distanceService->provider(),
                'fontes' => $fontes,
                'rotulo' => $this->distanceService->providerLabel($fontes[0] ?? null),
                'limiteIdaVoltaKm' => self::LIMITE_IDA_VOLTA_KM,
            ],
            'cidade' => [
                'nome' => $demanda->cidade,
                'aptasNaCidade' => count($naCidade),
                'restricaoSolicitada' => $demanda->somenteCidade,
                'restricaoAplicada' => $restricaoAplicada,
            ],
            'rodizio' => [
                'ciclo' => $ciclo['ciclo'],
                'inicioCiclo' => $ciclo['inicioCiclo'],
                'chamadasNoCiclo' => count($ciclo['ids']),
                'totalAtivas' => $ciclo['totalAtivas'],
                'elegiveisEmRodizio' => count(array_filter($elegiveis, static fn(array $e): bool => !empty($e['emRodizio']))),
            ],
        ];
    }

    /**
     * Se nenhum critério vier do formulário, aplica os obrigatórios do catálogo.
     *
     * @param list<Criterio> $catalogo
     * @param list<string> $selecionados
     * @return list<Criterio>
     */
    private function resolverCriteriosAtivos(array $catalogo, array $selecionados): array
    {
        return array_values(array_filter(
            $catalogo,
            static fn(Criterio $c): bool => $selecionados !== []
                ? in_array($c->codigo, $selecionados, true) || $c->obrigatorio
                : $c->obrigatorio
        ));
    }

    /**
     * @param list<Criterio> $criterios
     * @return array{aprovada:bool,criterios:list<array>,motivos:list<string>,servicosCompativeis:list<string>,rota:?array}
     */
    private function avaliarCadastro(Empresa $empresa, Demanda $demanda, array $criterios): array
    {
        $avaliacoes = [];
        $motivos = [];
        foreach ($criterios as $criterio) {
            if ($criterio->codigo === 'limite_100km') {
                continue; // depende da rota, avaliado depois
            }
            [$ok, $motivo] = match ($criterio->codigo) {
                'servico_compativel' => $this->checkAtividade($empresa, $demanda),
                'convenio_ativo' => $empresa->isAtiva()
                    ? [true, null]
                    : [false, 'Credenciado inativo — empresa desativada no credenciamento'],
                'localizacao' => $this->checkRegiao($empresa, $demanda),
                'documentacao_regular' => empty($empresa->observacoes) || !str_contains(mb_strtolower((string) $empresa->observacoes), 'suspend')
                    ? [true, null]
                    : [false, 'Pendências documentais / credenciamento suspenso'],
                default => [true, null],
            };
            $avaliacoes[] = ['codigo' => $criterio->codigo, 'nome' => $criterio->nome, 'atendido' => $ok, 'motivo' => $motivo];
            if (!$ok) {
                $motivos[] = $motivo ?? $criterio->nome;
            }
        }

        return [
            'aprovada' => $motivos === [],
            'criterios' => $avaliacoes,
            'motivos' => $motivos,
            'servicosCompativeis' => array_values(array_map(
                fn(string $cod): string => $this->nomeAtividade($cod),
                array_intersect($empresa->atividadesCodigos, $demanda->atividadesCodigos)
            )),
            'rota' => null,
        ];
    }

    /** @return array{0:bool,1:?string} */
    private function checkAtividade(Empresa $empresa, Demanda $demanda): array
    {
        if ($demanda->atividadesCodigos === []) {
            return [false, 'Nenhuma atividade foi informada na demanda'];
        }
        if (array_intersect($empresa->atividadesCodigos, $demanda->atividadesCodigos) !== []) {
            return [true, null];
        }
        return [false, 'Atividade não habilitada (requer: ' . implode(', ', $demanda->servicosNecessarios) . ')'];
    }

    /** @return array{0:bool,1:?string} */
    private function checkRegiao(Empresa $empresa, Demanda $demanda): array
    {
        if ($demanda->regiaoCodigo === '') {
            return [false, 'Região da demanda não informada'];
        }
        $regioes = array_merge($empresa->regioesMunicipiosCodigos, $empresa->estadosAtuacaoCodigos);
        if (in_array($demanda->regiaoCodigo, $regioes, true)) {
            return [true, null];
        }
        $nome = $demanda->regiaoNome !== '' ? "{$demanda->regiaoCodigo} - {$demanda->regiaoNome}" : $demanda->regiaoCodigo;
        return [false, "Não atende a região da demanda ({$nome})"];
    }

    /**
     * @param array{km:?float}|null $rota
     * @return array{0:bool,1:?string}
     */
    private function checkLimite100km(Empresa $empresa, ?array $rota, bool $jaAprovada): array
    {
        if ($empresa->atendeAcima100km) {
            return [true, null];
        }
        if (!$jaAprovada || ($rota['km'] ?? null) === null) {
            // Sem rota apurada não elimina: a região atendida continua valendo
            return [true, null];
        }
        $idaVolta = round($rota['km'] * 2, 1);
        if ($idaVolta <= self::LIMITE_IDA_VOLTA_KM) {
            return [true, null];
        }
        return [false, sprintf(
            'Atende somente até 100 km ida e volta — rota de carro %s (ida e volta %s)',
            format_km($rota['km']),
            format_km($idaVolta)
        )];
    }

    private function nomeAtividade(string $codigo): string
    {
        static $catalogo = null;
        $catalogo ??= $this->companyService->getCatalogo()['atividades'] ?? [];
        return $catalogo[$codigo] ?? $codigo;
    }
}
