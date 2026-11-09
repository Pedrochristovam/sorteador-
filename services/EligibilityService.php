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
 * depois a rota de carro e o limite de 200 km ida e volta (100 km ida + 100 km volta);
 * no sorteio automático, se houver credenciada na cidade da demanda, só entram as da cidade
 * dentro desse limite — senão o fluxo aponta para escolha manual.
 * O rodízio não elimina aqui: só marca quem já foi chamado no ciclo (exceto na escolha manual).
 */
final class EligibilityService
{
    /** Margem operacional: 100 km de ida + 100 km de volta. */
    public const LIMITE_IDA_KM = 100.0;
    public const LIMITE_IDA_VOLTA_KM = 200.0;

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

        // 3) Limite de 200 km ida e volta (100 + 100) — cadastro "até o limite"
        $aplicaLimite = in_array('limite_100km', $codigosAtivos, true);
        foreach ($empresas as $empresa) {
            $av = &$avaliacoes[$empresa->id];
            $rota = $rotas[$empresa->id] ?? null;
            $av['rota'] = $rota;
            if (!$aplicaLimite) {
                unset($av);
                continue;
            }
            [$ok, $motivo] = $this->checkLimiteDeslocamento($empresa, $rota, $av['aprovada']);
            $av['criterios'][] = [
                'codigo' => 'limite_100km',
                'nome' => 'Limite de deslocamento (200 km ida e volta)',
                'atendido' => $ok,
                'motivo' => $motivo,
            ];
            if (!$ok) {
                $av['aprovada'] = false;
                $av['motivos'][] = $motivo;
            }
            unset($av);
        }

        // 4) Restrição opcional às sediadas na cidade (checkbox do formulário)
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

        // 5) Rodízio geral (aceite no Standby + marcação manual no cadastro)
        $idsAtivas = array_values(array_map(
            static fn(Empresa $e): string => $e->id,
            array_filter($empresas, static fn(Empresa $e): bool => $e->isAtiva())
        ));
        $idsMarcacaoManual = array_values(array_map(
            static fn(Empresa $e): string => $e->id,
            array_filter($empresas, static fn(Empresa $e): bool => $e->isAtiva() && $e->servicoRealizado)
        ));
        $ciclo = (new RodizioService())->cicloAtual($idsAtivas, $idsMarcacaoManual);
        $chamadas = array_flip($ciclo['ids']);
        $marcacaoManual = array_flip($ciclo['idsMarcacaoManual'] ?? []);

        $elegiveis = [];
        $eliminadas = [];
        foreach ($empresas as $empresa) {
            $av = $avaliacoes[$empresa->id];
            $rota = $av['rota'];
            // Só propaga km se for rota de carro real — nunca linha reta
            $rotaCarro = DistanceService::isRotaCarro($rota);
            $kmCarro = $rotaCarro ? (float) $rota['km'] : null;
            $item = (new EmpresaElegivel(
                empresaId: $empresa->id,
                nome: $empresa->nome,
                cnpj: $empresa->cnpj,
                cidade: $empresa->cidade,
                endereco: $empresa->endereco . ($empresa->bairro ? ' — ' . $empresa->bairro : ''),
                distanciaKm: $kmCarro,
                servicosCompativeis: $av['servicosCompativeis'],
                criterios: $av['criterios'],
                elegivel: $av['aprovada'],
                status: $empresa->status,
                motivosEliminacao: $av['motivos'],
                extras: [
                    'uf' => $empresa->uf,
                    'duracaoMin' => $rotaCarro ? ($rota['minutos'] ?? null) : null,
                    'idaVoltaKm' => $kmCarro !== null ? round($kmCarro * 2, 1) : null,
                    'distanciaFonte' => $rotaCarro ? ($rota['fonte'] ?? null) : ($rota['fonte'] ?? 'indisponivel'),
                    'distanciaPrecisao' => $rota['precisao'] ?? null,
                    'distanciaModo' => $rotaCarro ? 'driving' : null,
                    'atendeAcima100km' => $empresa->atendeAcima100km,
                    'mesmaCidade' => normalizar_texto($empresa->cidade) === $cidadeDemanda,
                    'emRodizio' => isset($chamadas[$empresa->id]),
                    'rodizioPorMarcacao' => isset($marcacaoManual[$empresa->id]),
                    'servicoRealizadoObs' => $empresa->servicoRealizadoObs,
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

        // 6) Priorização por cidade + margem 100/100 no sorteio automático
        // Escolha manual: não aplica (lista todas as aptas; rodízio não impede nova escolha)
        $modoManual = ($demanda->modalidadeSolicitada ?? 'auto') === 'manual';
        $naCidadeAntes = array_values(array_filter($elegiveis, static fn(array $e): bool => !empty($e['mesmaCidade'])));
        $priorizacaoCidade = false;
        $semEmpresasNaCidade = false;
        $semEmpresasNoLimite = false;
        $redirecionarManual = false;
        $mensagemCidade = null;

        if (!$modoManual) {
            if ($naCidadeAntes === []) {
                // Nenhuma apta na cidade → não segue sorteio automático
                $semEmpresasNaCidade = true;
                $redirecionarManual = true;
                $mensagemCidade = sprintf(
                    'Não há credenciadas aptas sediadas em %s. Use a escolha manual para selecionar uma empresa.',
                    $demanda->cidade
                );
                foreach ($elegiveis as $e) {
                    $e['elegivel'] = false;
                    $e['motivosEliminacao'][] = $mensagemCidade;
                    $e['criterios'][] = [
                        'codigo' => 'prioridade_cidade',
                        'nome' => 'Prioridade cidade da demanda',
                        'atendido' => false,
                        'motivo' => $mensagemCidade,
                    ];
                    $eliminadas[] = $e;
                }
                $elegiveis = [];
            } else {
                // Há empresas na cidade: sorteio só com as da cidade dentro de 200 km ida/volta
                $priorizacaoCidade = true;
                $noLimite = [];
                $foraLimite = [];
                foreach ($elegiveis as $e) {
                    if (empty($e['mesmaCidade'])) {
                        $e['elegivel'] = false;
                        $motivo = sprintf('Sorteio automático prioriza credenciadas sediadas em %s (dentro de 200 km ida e volta).', $demanda->cidade);
                        $e['motivosEliminacao'][] = $motivo;
                        $e['criterios'][] = [
                            'codigo' => 'prioridade_cidade',
                            'nome' => 'Prioridade cidade da demanda',
                            'atendido' => false,
                            'motivo' => $motivo,
                        ];
                        $foraLimite[] = $e;
                        continue;
                    }
                    $ida = $e['distanciaKm'] ?? null;
                    $idaVolta = $e['idaVoltaKm'] ?? ($ida !== null ? round((float) $ida * 2, 1) : null);
                    if ($ida === null || $idaVolta === null || $idaVolta > self::LIMITE_IDA_VOLTA_KM) {
                        $e['elegivel'] = false;
                        $motivo = sprintf(
                            'Na cidade da demanda, mas fora da margem de 200 km ida e volta (100+100)%s',
                            $idaVolta !== null ? ' — ida e volta ' . format_km((float) $idaVolta) : ''
                        );
                        $e['motivosEliminacao'][] = $motivo;
                        $e['criterios'][] = [
                            'codigo' => 'prioridade_cidade_km',
                            'nome' => 'Margem 100 km ida + 100 km volta',
                            'atendido' => false,
                            'motivo' => $motivo,
                        ];
                        $foraLimite[] = $e;
                        continue;
                    }
                    $noLimite[] = $e;
                }
                $eliminadas = array_merge($eliminadas, $foraLimite);
                $elegiveis = $noLimite;
                if ($elegiveis === []) {
                    $semEmpresasNoLimite = true;
                    $redirecionarManual = true;
                    $mensagemCidade = sprintf(
                        'Há credenciadas em %s, mas nenhuma dentro de 200 km ida e volta (100+100). Use a escolha manual.',
                        $demanda->cidade
                    );
                }
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
        if ($priorizacaoCidade) {
            $criteriosUtilizados[] = 'Prioridade: cidade da demanda até 200 km ida e volta';
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
                'limiteIdaKm' => self::LIMITE_IDA_KM,
                'limiteIdaVoltaKm' => self::LIMITE_IDA_VOLTA_KM,
            ],
            'cidade' => [
                'nome' => $demanda->cidade,
                'aptasNaCidade' => count($naCidadeAntes),
                'restricaoSolicitada' => $demanda->somenteCidade,
                'restricaoAplicada' => $restricaoAplicada,
                'priorizacaoAplicada' => $priorizacaoCidade,
                'semEmpresasNaCidade' => $semEmpresasNaCidade,
                'semEmpresasNoLimite' => $semEmpresasNoLimite,
                'redirecionarManual' => $redirecionarManual,
                'mensagem' => $mensagemCidade,
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
    private function checkLimiteDeslocamento(Empresa $empresa, ?array $rota, bool $jaAprovada): array
    {
        if ($empresa->atendeAcima100km) {
            return [true, null];
        }
        // Só aplica o limite com ROTA DE CARRO real (Google/OSRM). Sem isso, não elimina.
        if (!$jaAprovada || !DistanceService::isRotaCarro($rota)) {
            return [true, null];
        }
        $idaVolta = round($rota['km'] * 2, 1);
        if ($idaVolta <= self::LIMITE_IDA_VOLTA_KM) {
            return [true, null];
        }
        return [false, sprintf(
            'Atende somente até 200 km ida e volta (100+100) — rota de carro %s (ida e volta %s)',
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
