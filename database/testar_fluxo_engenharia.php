<?php

declare(strict_types=1);

/**
 * Teste ponta a ponta do sorteador de Engenharia contra o servidor local.
 * Uso: php database/testar_fluxo_engenharia.php [http://localhost:8080]
 * Os sorteios de teste não ficam gravados: no MySQL são apagados ao final (auditorias caem em cascata);
 * sem MySQL, o histórico/auditoria em arquivo é restaurado do backup.
 */

$base = rtrim($argv[1] ?? 'http://localhost:8080', '/');
$root = dirname(__DIR__);
$storage = $root . '/storage';
$arquivos = ['sorteios_session.json', 'auditorias_session.json'];
$cookie = tempnam(sys_get_temp_dir(), 'sorteador_teste_');

require_once $root . '/utils/helpers.php';
require_once $root . '/config/Database.php';
require_once $root . '/services/CompanyService.php';

$pdo = null;
$sorteiosAntes = [];
try {
    if (Database::enabled()) {
        $pdo = Database::pdo();
        $sorteiosAntes = $pdo->query('SELECT id FROM sorteios')->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Throwable) {
    $pdo = null;
}

$falhas = 0;
function ok(bool $cond, string $msg): void
{
    global $falhas;
    echo ($cond ? '  [OK]   ' : '  [FALHA] ') . $msg . PHP_EOL;
    if (!$cond) {
        $falhas++;
    }
}

function http(string $method, string $url, ?array $json = null): array
{
    global $cookie;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 240,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($json ?? [], JSON_UNESCAPED_UNICODE);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Accept: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'json' => json_decode($body, true)];
}

function pagina(string $rotulo, string $url): void
{
    $r = http('GET', $url);
    $erroPhp = preg_match('/(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught)/', $r['body'], $m);
    ok($r['code'] === 200 && !$erroPhp, sprintf('%s → HTTP %d%s', $rotulo, $r['code'], $erroPhp ? ' (' . $m[1] . ')' : ''));
    if ($erroPhp) {
        $pos = strpos($r['body'], $m[1]);
        echo '         ' . trim(strip_tags(substr($r['body'], $pos, 300))) . PHP_EOL;
    }
}

// ---------- backup ----------
foreach ($arquivos as $a) {
    if (is_file("$storage/$a")) {
        copy("$storage/$a", "$storage/$a.bak-teste");
    }
}

try {
    $empresas = array_map(static fn($e) => $e->toArray(), (new CompanyService())->listAll());
    echo 'Armazenamento: ' . ($pdo ? 'MySQL' : 'arquivos JSON') . PHP_EOL;
    $ativas = array_values(array_filter($empresas, static fn($e) => ($e['status'] ?? '') === 'ativa'));

    // cidade com mais credenciadas ativas habilitadas em F
    $porCidade = [];
    foreach ($ativas as $e) {
        if (in_array('F', $e['atividadesCodigos'] ?? [], true)) {
            $porCidade[$e['cidade']][] = $e;
        }
    }
    uasort($porCidade, static fn($a, $b) => count($b) <=> count($a));
    $cidade = (string) array_key_first($porCidade);
    $ref = $porCidade[$cidade][0];
    $regiao = (string) (($ref['regioesMunicipiosCodigos'] ?? [])[0] ?? ($ref['estadosAtuacaoCodigos'] ?? [])[0] ?? '');
    echo "Base: " . count($empresas) . " empresas / " . count($ativas) . " ativas" . PHP_EOL;
    echo "Demanda de teste: $cidade ({$ref['uf']}) · região $regiao · " . count($porCidade[$cidade]) . " credenciadas F na cidade" . PHP_EOL . PHP_EOL;

    $demandaBase = [
        'cidade' => $cidade,
        'uf' => $ref['uf'] ?? 'MG',
        'endereco' => $ref['endereco'] ?? 'Centro',
        'regiaoCodigo' => $regiao,
        'atividadesCodigos' => ['F'],
        'areaConstruidaM2' => '250',
        'areaTerrenoM2' => '400',
        'precoEdital' => false,
        'modalidadeSolicitada' => 'auto',
        'somenteCidade' => false,
    ];

    // ---------- 1. validação ----------
    echo "1. Validação da demanda" . PHP_EOL;
    $r = http('POST', "$base/api/calcular-elegiveis.php", ['preview' => true, 'cidade' => '']);
    ok($r['code'] === 422, 'demanda vazia recusada (HTTP ' . $r['code'] . ': ' . ($r['json']['message'] ?? '') . ')');

    // ---------- 2. sorteio com rodízio ----------
    echo PHP_EOL . "2. Vistoria de obra comum → sorteio com rodízio" . PHP_EOL;
    $t = microtime(true);
    $r = http('POST', "$base/api/calcular-elegiveis.php", $demandaBase + ['preview' => true]);
    $imp = $r['json']['impacto'] ?? [];
    ok(($r['json']['ok'] ?? false) === true, sprintf('preview em %.1fs · %d aptas de %d · fonte: %s', microtime(true) - $t, $imp['elegiveis'] ?? -1, $imp['total'] ?? -1, $imp['fonteDistancia'] ?? '?'));
    $fonte = strtolower((string) ($imp['fonteDistancia'] ?? ''));
    ok(
        str_contains($fonte, 'google') || str_contains($fonte, 'openstreetmap') || str_contains($fonte, 'rota de carro'),
        'distância é rota de carro (não linha reta): ' . ($imp['fonteDistancia'] ?? '?')
    );
    ok(($imp['modalidade'] ?? '') === 'sorteio', 'modalidade sugerida = sorteio (' . ($imp['modalidadeMotivo'] ?? '') . ')');
    echo "         desativadas {$imp['desativadas']} · sem atividade {$imp['semAtividade']} · fora da região {$imp['foraRegiao']} · limite 100km {$imp['limite100']} · na cidade {$imp['aptasNaCidade']} · mais próxima: " . ($imp['maisProxima']['nome'] ?? '-') . ' ' . ($imp['maisProxima']['km'] ?? '') . " km" . PHP_EOL;

    $r = http('POST', "$base/api/calcular-elegiveis.php", $demandaBase);
    $dados = $r['json']['data'] ?? [];
    ok(!empty($dados['elegiveis']), 'cálculo completo salvo no fluxo (' . count($dados['elegiveis'] ?? []) . ' aptas)');
    $kms = array_values(array_filter(array_column($dados['elegiveis'] ?? [], 'distanciaKm'), static fn($v) => $v !== null));
    $ordenado = $kms;
    sort($ordenado);
    ok($kms === $ordenado, 'aptas ordenadas pela rota de carro');
    $fontesRuins = array_filter(
        $dados['elegiveis'] ?? [],
        static fn(array $e): bool => ($e['distanciaKm'] ?? null) !== null && !in_array($e['distanciaFonte'] ?? '', ['google', 'osm'], true)
    );
    ok($fontesRuins === [], 'nenhuma apta com km que não seja rota de carro (google/osm)');
    $semRegiao = array_filter($dados['elegiveis'] ?? [], static function ($e) use ($empresas, $regiao) {
        foreach ($empresas as $emp) {
            if ($emp['id'] === $e['empresaId']) {
                return !in_array($regiao, array_merge($emp['regioesMunicipiosCodigos'] ?? [], $emp['estadosAtuacaoCodigos'] ?? []), true);
            }
        }
        return true;
    });
    ok($semRegiao === [], 'todas as aptas atendem a região da demanda');
    $limitadasAcima = array_filter($dados['elegiveis'] ?? [], static fn($e) => empty($e['atendeAcima100km']) && ($e['idaVoltaKm'] ?? 0) > 100);
    ok($limitadasAcima === [], 'nenhuma apta "até 100 km" com ida e volta acima de 100 km');

    foreach (['demanda', 'elegiveis', 'preparacao', 'execucao'] as $etapa) {
        pagina("novo-sorteio etapa $etapa", "$base/index.php?page=novo-sorteio&etapa=$etapa");
    }

    $r = http('POST', "$base/api/realizar-sorteio.php", ['confirmado' => true]);
    $s1 = $r['json']['data']['sorteio'] ?? [];
    ok(($r['json']['ok'] ?? false) && !empty($s1['empresaSelecionada']['id']), 'sorteio executado → ' . ($s1['empresaSelecionada']['nome'] ?? $r['body']));
    ok(($s1['status'] ?? '') === 'aguardando_aceite' && ($s1['rodizioIds'] ?? []) === [], 'fica em standby: aguardando aceite, ainda fora do rodízio');
    pagina('resultado (sorteio)', "$base/index.php?page=novo-sorteio&etapa=resultado");
    pagina('standby com pendente', "$base/index.php?page=standby");

    $r = http('POST', "$base/api/calcular-elegiveis.php", $demandaBase);
    $flag = null;
    foreach ($r['json']['data']['elegiveis'] ?? [] as $e) {
        if ($e['empresaId'] === ($s1['empresaSelecionada']['id'] ?? '')) {
            $flag = !empty($e['emRodizio']);
        }
    }
    ok($flag === false, 'antes do aceite a sorteada ainda NÃO está no rodízio');

    $r = http('POST', "$base/api/registrar-aceite.php", ['sorteioId' => $s1['id'] ?? '', 'decisao' => 'aceito', 'observacao' => 'teste']);
    $s1a = $r['json']['data'] ?? [];
    ok(($r['json']['ok'] ?? false) && ($s1a['status'] ?? '') === 'concluido' && ($s1a['rodizioIds'] ?? []) === [$s1['empresaSelecionada']['id'] ?? ''], 'aceite confirma e coloca no rodízio');

    $r = http('POST', "$base/api/calcular-elegiveis.php", $demandaBase);
    $flag = null;
    foreach ($r['json']['data']['elegiveis'] ?? [] as $e) {
        if ($e['empresaId'] === ($s1['empresaSelecionada']['id'] ?? '')) {
            $flag = !empty($e['emRodizio']);
        }
    }
    ok($flag === true, 'depois do aceite a sorteada aparece como "já chamada" (rodízio)');
    $r = http('POST', "$base/api/realizar-sorteio.php", ['confirmado' => true]);
    $s2 = $r['json']['data']['sorteio'] ?? [];
    $nLivres = count(array_filter($r['json']['data']['sorteio']['participantes'] ?? [], static fn($p) => empty($p['emRodizio'])));
    ok(($s2['empresaSelecionada']['id'] ?? '') !== ($s1['empresaSelecionada']['id'] ?? '') || $nLivres === 0, '2º sorteio não repete a empresa já aceita → ' . ($s2['empresaSelecionada']['nome'] ?? '?'));
    // Confirma o 2º também (senão fica pendente no standby)
    http('POST', "$base/api/registrar-aceite.php", ['sorteioId' => $s2['id'] ?? '', 'decisao' => 'aceito']);

    // ---------- 3. mais próxima ----------
    echo PHP_EOL . "3. Laudo com preço em edital → mais próxima" . PHP_EOL;
    $r = http('POST', "$base/api/calcular-elegiveis.php", ['precoEdital' => true] + $demandaBase);
    ok(($r['json']['impacto']['modalidade'] ?? '') === 'proxima', 'modalidade = mais próxima');
    $primeira = $r['json']['data']['elegiveis'][0] ?? [];
    $r = http('POST', "$base/api/realizar-sorteio.php", ['confirmado' => true]);
    $s3 = $r['json']['data']['sorteio'] ?? [];
    ok(($s3['empresaSelecionada']['id'] ?? '') === ($primeira['empresaId'] ?? '-'), sprintf('escolhida a mais próxima: %s (%s km)', $s3['empresaSelecionada']['nome'] ?? '?', $s3['empresaSelecionada']['distanciaKm'] ?? '?'));
    ok(($s3['status'] ?? '') === 'aguardando_aceite', 'mais próxima também vai para standby');
    ok(count($s3['ranking'] ?? []) > 0, 'ranking de distâncias registrado (' . count($s3['ranking'] ?? []) . ')');
    http('POST', "$base/api/registrar-aceite.php", ['sorteioId' => $s3['id'] ?? '', 'decisao' => 'aceito']);
    pagina('resultado (mais próxima)', "$base/index.php?page=novo-sorteio&etapa=resultado");

    // ---------- 4. orçamento ----------
    echo PHP_EOL . "4. Área construída > 1000 m² → orçamento entre 3" . PHP_EOL;
    $r = http('POST', "$base/api/calcular-elegiveis.php", ['areaConstruidaM2' => '1500'] + $demandaBase);
    ok(($r['json']['impacto']['modalidade'] ?? '') === 'orcamento', 'modalidade = orçamento (' . ($r['json']['impacto']['modalidadeMotivo'] ?? '') . ')');
    $r = http('POST', "$base/api/realizar-sorteio.php", ['confirmado' => true]);
    $s4 = $r['json']['data']['sorteio'] ?? [];
    $ids = array_column($s4['sorteadas'] ?? [], 'id');
    ok(count($ids) === 3 && count(array_unique($ids)) === 3, '3 empresas distintas sorteadas: ' . implode(', ', array_column($s4['sorteadas'] ?? [], 'nome')));
    ok(($s4['status'] ?? '') === 'aguardando_orcamento' && empty($s4['empresaSelecionada']), 'status aguardando orçamento, sem vencedora ainda');
    pagina('resultado (aguardando orçamentos)', "$base/index.php?page=novo-sorteio&etapa=resultado");
    pagina('histórico com orçamento pendente', "$base/index.php?page=historico");
    pagina('dashboard com orçamento pendente', "$base/index.php?page=dashboard");

    $valores = [];
    foreach ($ids as $i => $id) {
        $valores[$id] = [4200.5, 3150, 5000][$i];
    }
    $r = http('POST', "$base/api/registrar-orcamentos.php", ['sorteioId' => $s4['id'] ?? '', 'valores' => $valores, 'observacao' => 'teste automatizado']);
    $s4b = $r['json']['data'] ?? $r['json']['sorteio'] ?? [];
    ok(($r['json']['ok'] ?? false) === true, 'orçamentos registrados' . (($r['json']['ok'] ?? false) ? '' : ' → ' . $r['body']));
    $venc = $s4b['empresaSelecionada']['id'] ?? null;
    ok($venc === ($ids[1] ?? '-') && ($s4b['status'] ?? '') === 'aguardando_aceite', 'menor valor (R$ 3.150) venceu e vai para standby');
    $r = http('POST', "$base/api/registrar-aceite.php", ['sorteioId' => $s4['id'] ?? '', 'decisao' => 'aceito']);
    ok(($r['json']['data']['status'] ?? '') === 'concluido', 'aceite da vencedora do orçamento conclui o sorteio');
    pagina('resultado (orçamentos registrados)', "$base/index.php?page=resultado&id=" . urlencode($s4['id'] ?? ''));
    pagina('histórico detalhe (orçamento)', "$base/index.php?page=historico-detalhe&id=" . urlencode($s4['id'] ?? ''));
    pagina('auditoria (orçamento)', "$base/index.php?page=auditoria&sorteio=" . urlencode($s4['id'] ?? ''));

    // Recusa libera a empresa no próximo sorteio
    echo PHP_EOL . "4b. Recusa libera a empresa no rodízio" . PHP_EOL;
    $r = http('POST', "$base/api/calcular-elegiveis.php", $demandaBase);
    $r = http('POST', "$base/api/realizar-sorteio.php", ['confirmado' => true]);
    $sRec = $r['json']['data']['sorteio'] ?? [];
    $idRec = $sRec['empresaSelecionada']['id'] ?? '';
    ok(($sRec['status'] ?? '') === 'aguardando_aceite' && $idRec !== '', 'sorteio para teste de recusa → ' . ($sRec['empresaSelecionada']['nome'] ?? '?'));
    $r = http('POST', "$base/api/registrar-aceite.php", ['sorteioId' => $sRec['id'] ?? '', 'decisao' => 'recusado', 'observacao' => 'teste recusa']);
    ok(($r['json']['data']['status'] ?? '') === 'recusado' && ($r['json']['data']['rodizioIds'] ?? null) === [], 'recusa não coloca no rodízio');
    $r = http('POST', "$base/api/calcular-elegiveis.php", $demandaBase);
    $aindaLivre = null;
    foreach ($r['json']['data']['elegiveis'] ?? [] as $e) {
        if ($e['empresaId'] === $idRec) {
            $aindaLivre = empty($e['emRodizio']);
        }
    }
    ok($aindaLivre === true, 'empresa que recusou continua livre no próximo sorteio');

    // atividade C (Laudo Rural) também vai para orçamento
    $r = http('POST', "$base/api/calcular-elegiveis.php", ['preview' => true, 'atividadesCodigos' => ['C']] + $demandaBase);
    ok(($r['json']['impacto']['modalidade'] ?? '') === 'orcamento', 'Laudo Rural (C) → orçamento');
    $r = http('POST', "$base/api/calcular-elegiveis.php", ['preview' => true, 'areaTerrenoM2' => '12000'] + $demandaBase);
    ok(($r['json']['impacto']['modalidade'] ?? '') === 'orcamento', 'terreno > 10.000 m² → orçamento');

    // ---------- 5. somente a cidade ----------
    echo PHP_EOL . "5. Restringir às credenciadas da cidade" . PHP_EOL;
    $r = http('POST', "$base/api/calcular-elegiveis.php", ['somenteCidade' => true] + $demandaBase);
    $cid = $r['json']['data']['cidade'] ?? [];
    $el = $r['json']['data']['elegiveis'] ?? [];
    if (($cid['aptasNaCidade'] ?? 0) >= 2) {
        $fora = array_filter($el, static fn($e) => empty($e['mesmaCidade']));
        ok($fora === [] && count($el) === $cid['aptasNaCidade'], sprintf('restrição aplicada: %d aptas, todas em %s', count($el), $cidade));
    } else {
        ok(empty($cid['restricaoAplicada']), 'menos de 2 na cidade → restrição não aplicada');
    }

    // ---------- 6. páginas ----------
    echo PHP_EOL . "6. Renderização das páginas" . PHP_EOL;
    $empId = $ativas[0]['id'] ?? '';
    foreach ([
        'dashboard' => 'page=dashboard',
        'empresas' => 'page=empresas',
        'empresas (até 100km filtro)' => 'page=empresas&q=' . urlencode($cidade),
        'empresa nova' => 'page=empresa-form',
        'empresa editar' => 'page=empresa-form&id=' . urlencode($empId),
        'empresa detalhe' => 'page=empresa-detalhe&id=' . urlencode($empId),
        'histórico' => 'page=historico',
        'auditoria' => 'page=auditoria',
        'resultado mais próxima' => 'page=resultado&id=' . urlencode($s3['id'] ?? ''),
        'auditoria sorteio' => 'page=auditoria&sorteio=' . urlencode($s1['id'] ?? ''),
    ] as $rotulo => $q) {
        pagina($rotulo, "$base/index.php?$q");
    }
} catch (Throwable $e) {
    ok(false, 'exceção: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    foreach ($arquivos as $a) {
        if (is_file("$storage/$a.bak-teste")) {
            copy("$storage/$a.bak-teste", "$storage/$a");
            unlink("$storage/$a.bak-teste");
        }
    }
    if ($pdo) {
        $novos = array_diff($pdo->query('SELECT id FROM sorteios')->fetchAll(PDO::FETCH_COLUMN), $sorteiosAntes);
        if ($novos !== []) {
            $del = $pdo->prepare('DELETE FROM sorteios WHERE id = ?');
            foreach ($novos as $id) {
                $del->execute([$id]);
            }
        }
        echo PHP_EOL . count($novos) . ' sorteio(s) de teste removido(s) do MySQL.';
    }
    @unlink($cookie);
    echo PHP_EOL . ($falhas === 0 ? 'TUDO OK' : "$falhas FALHA(S)") . ' — histórico e auditoria restaurados.' . PHP_EOL;
}
