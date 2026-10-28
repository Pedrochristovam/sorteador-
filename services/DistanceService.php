<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/Database.php';

/**
 * Distância por ROTA DE CARRO entre as empresas e o endereço da demanda.
 *
 * Nunca usa linha reta (haversine) para decidir elegibilidade, ranking ou limite de 100 km.
 * Só entram no sistema distâncias vindas de:
 *   - google : Google Maps Distance Matrix com mode=driving (GOOGLE_MAPS_API_KEY no .env)
 *   - osm    : OpenStreetMap Nominatim (geocode) + OSRM profile=driving (sem chave)
 * Se a rota de carro falhar, km fica null (indisponível) — a empresa não é ranqueada por
 * proximidade e o limite de 100 km não a elimina por estimativa.
 * Cache em storage/geo_cache.json (somente rotas google/osm são gravadas).
 */
final class DistanceService
{
    private const USER_AGENT = 'SorteadorMGI/1.0 (sorteador de credenciados)';
    /** Endpoints OSRM públicos (profile driving = malha viária de carro). */
    private const OSRM_TABLE = [
        'https://router.project-osrm.org/table/v1/driving/',
        'https://routing.openstreetmap.de/routed-car/table/v1/driving/',
    ];

    private string $cachePath;
    private ?array $cache = null;
    private float $ultimaChamadaNominatim = 0.0;

    public function __construct(?string $cachePath = null)
    {
        $this->cachePath = $cachePath ?? dirname(__DIR__) . '/storage/geo_cache.json';
    }

    public function provider(): string
    {
        Database::enabled(); // carrega o .env
        $forcado = strtolower(trim((string) ($_ENV['GEO_PROVIDER'] ?? '')));
        if ($forcado === 'osm') {
            return 'osm';
        }
        return $this->googleKey() !== '' ? 'google' : 'osm';
    }

    public function providerLabel(?string $fonte = null): string
    {
        return match ($fonte ?? $this->provider()) {
            'google' => 'Google Maps (rota de carro)',
            'osm' => 'OpenStreetMap (rota de carro)',
            'estimativa' => 'Inválida — linha reta (não usada)',
            default => 'Rota de carro indisponível',
        };
    }

    /** True só se a distância veio de rota de carro real (Google driving ou OSRM driving). */
    public static function isRotaCarro(?array $rota): bool
    {
        $fonte = $rota['fonte'] ?? '';
        return in_array($fonte, ['google', 'osm'], true) && isset($rota['km']) && $rota['km'] !== null;
    }

    /**
     * @param array<string,array{endereco:string,cidade:string,uf:string}> $origens id => endereço da empresa
     * @param array{endereco:string,cidade:string,uf:string} $destino
     * @return array<string,array{km:?float,minutos:?float,fonte:string,precisao:string}>
     */
    public function rotasAte(array $destino, array $origens): array
    {
        if ($origens === []) {
            return [];
        }

        $resultado = [];
        $pendentes = [];
        $provider = $this->provider();
        $destKey = $this->chave($destino);

        foreach ($origens as $id => $origem) {
            $key = $provider . '|' . $this->chave($origem) . '>' . $destKey;
            $hit = $this->cacheGet('rotas', $key);
            if ($hit !== null) {
                $resultado[$id] = $hit;
            } else {
                $pendentes[$id] = $origem;
            }
        }

        if ($pendentes !== []) {
            $novos = $provider === 'google'
                ? $this->rotasGoogle($destino, $pendentes)
                : $this->rotasOsm($destino, $pendentes);

            foreach ($novos as $id => $rota) {
                // Descarta qualquer residual de estimativa/linha reta (cache antigo ou fallback legado)
                if (!self::isRotaCarro($rota) && ($rota['fonte'] ?? '') === 'estimativa') {
                    $rota = ['km' => null, 'minutos' => null, 'fonte' => 'indisponivel', 'precisao' => $rota['precisao'] ?? 'sem_rota_carro'];
                }
                $resultado[$id] = $rota;
                if (self::isRotaCarro($rota)) {
                    $this->cacheSet('rotas', $provider . '|' . $this->chave($pendentes[$id]) . '>' . $destKey, $rota);
                }
            }
            $this->salvarCache();
        }

        // Garante: o que sai daqui ou é rota de carro, ou km null
        foreach ($resultado as $id => $rota) {
            if (($rota['km'] ?? null) !== null && !self::isRotaCarro($rota)) {
                $resultado[$id] = ['km' => null, 'minutos' => null, 'fonte' => 'indisponivel', 'precisao' => 'sem_rota_carro'];
            }
        }

        return $resultado;
    }

    /**
     * @param array{endereco:string,cidade:string,uf:string} $local
     * @return array{lat:float,lng:float,precisao:string}|null
     */
    public function geocodificar(array $local): ?array
    {
        $key = $this->chave($local);
        $hit = $this->cacheGet('geocode', $key);
        if ($hit !== null) {
            return $hit['lat'] === null ? null : $hit;
        }

        $geo = null;
        foreach ($this->consultasGeocode($local) as [$params, $precisao]) {
            $row = $this->nominatim($params);
            if ($row !== null) {
                $geo = ['lat' => (float) $row['lat'], 'lng' => (float) $row['lon'], 'precisao' => $precisao];
                break;
            }
        }

        $this->cacheSet('geocode', $key, $geo ?? ['lat' => null, 'lng' => null, 'precisao' => 'nao_encontrado']);
        $this->salvarCache();
        return $geo;
    }

    // ---------------------------------------------------------------- Google

    /**
     * @param array<string,array{endereco:string,cidade:string,uf:string}> $origens
     * @return array<string,array{km:?float,minutos:?float,fonte:string,precisao:string}>
     */
    private function rotasGoogle(array $destino, array $origens): array
    {
        $out = [];
        $destTexto = $this->textoEndereco($destino);

        foreach (array_chunk($origens, 25, true) as $lote) {
            $ids = array_keys($lote);
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
                'origins' => implode('|', array_map(fn(array $o): string => $this->textoEndereco($o), $lote)),
                'destinations' => $destTexto,
                'mode' => 'driving',
                'language' => 'pt-BR',
                'region' => 'br',
                'key' => $this->googleKey(),
            ]);
            $json = $this->httpJson($url);

            if (($json['status'] ?? '') !== 'OK') {
                // Chave inválida/cota: cai para OpenStreetMap neste lote
                $out += $this->rotasOsm($destino, $lote);
                continue;
            }

            foreach ($ids as $i => $id) {
                $el = $json['rows'][$i]['elements'][0] ?? [];
                if (($el['status'] ?? '') === 'OK') {
                    $out[$id] = [
                        'km' => round(((float) $el['distance']['value']) / 1000, 1),
                        'minutos' => round(((float) $el['duration']['value']) / 60),
                        'fonte' => 'google',
                        'precisao' => 'endereco',
                        'modo' => 'driving',
                    ];
                } else {
                    $out += $this->rotasOsm($destino, [$id => $lote[$id]]);
                }
            }
        }

        return $out;
    }

    // ------------------------------------------------------- OpenStreetMap

    /**
     * @param array<string,array{endereco:string,cidade:string,uf:string}> $origens
     * @return array<string,array{km:?float,minutos:?float,fonte:string,precisao:string}>
     */
    private function rotasOsm(array $destino, array $origens): array
    {
        $out = [];
        $dest = $this->geocodificar($destino);
        if ($dest === null) {
            foreach ($origens as $id => $_) {
                $out[$id] = ['km' => null, 'minutos' => null, 'fonte' => 'indisponivel', 'precisao' => 'destino_nao_localizado'];
            }
            return $out;
        }

        $coords = [];
        foreach ($origens as $id => $origem) {
            $geo = $this->geocodificar($origem);
            if ($geo === null) {
                $out[$id] = ['km' => null, 'minutos' => null, 'fonte' => 'indisponivel', 'precisao' => 'origem_nao_localizada'];
            } else {
                $coords[$id] = $geo;
            }
        }

        foreach (array_chunk($coords, 60, true) as $lote) {
            $ids = array_keys($lote);
            $pontos = [sprintf('%.6f,%.6f', $dest['lng'], $dest['lat'])];
            foreach ($lote as $geo) {
                $pontos[] = sprintf('%.6f,%.6f', $geo['lng'], $geo['lat']);
            }
            $sources = implode(';', range(1, count($lote)));
            $query = implode(';', $pontos) . '?sources=' . $sources . '&destinations=0&annotations=distance,duration';

            $json = null;
            foreach (self::OSRM_TABLE as $base) {
                $json = $this->httpJson($base . $query);
                if (($json['code'] ?? '') === 'Ok') {
                    break;
                }
            }
            $ok = ($json['code'] ?? '') === 'Ok';

            foreach ($ids as $i => $id) {
                $metros = $ok ? ($json['distances'][$i][0] ?? null) : null;
                $segundos = $ok ? ($json['durations'][$i][0] ?? null) : null;
                $precisao = self::piorPrecisao($lote[$id]['precisao'], $dest['precisao']);
                if ($metros !== null && (float) $metros >= 0) {
                    $out[$id] = [
                        'km' => round(((float) $metros) / 1000, 1),
                        'minutos' => $segundos !== null ? round(((float) $segundos) / 60) : null,
                        'fonte' => 'osm',
                        'precisao' => $precisao,
                        'modo' => 'driving',
                    ];
                } else {
                    // Sem rota de carro: NÃO inventa km por linha reta
                    $out[$id] = [
                        'km' => null,
                        'minutos' => null,
                        'fonte' => 'indisponivel',
                        'precisao' => $precisao,
                        'modo' => 'driving',
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Tentativas do mais preciso para o menos preciso.
     *
     * @return list<array{0:array<string,string>,1:string}>
     */
    private function consultasGeocode(array $local): array
    {
        $cidade = trim((string) ($local['cidade'] ?? ''));
        $uf = strtoupper(trim((string) ($local['uf'] ?? '')));
        $rua = $this->limparLogradouro((string) ($local['endereco'] ?? ''));
        $consultas = [];

        if ($rua !== '' && $cidade !== '') {
            $consultas[] = [['street' => $rua, 'city' => $cidade, 'state' => $uf, 'country' => 'Brasil'], 'endereco'];
            $consultas[] = [['q' => "{$rua}, {$cidade} - {$uf}, Brasil"], 'endereco'];
        }
        if (preg_match('/\b(\d{2})\.?(\d{3})-?(\d{3})\b/', (string) ($local['endereco'] ?? ''), $m)) {
            $consultas[] = [['postalcode' => "{$m[1]}{$m[2]}-{$m[3]}", 'country' => 'Brasil'], 'cep'];
        }
        if ($cidade !== '') {
            $consultas[] = [['city' => $cidade, 'state' => $uf, 'country' => 'Brasil'], 'cidade'];
        }
        return $consultas;
    }

    /** Mantém "Logradouro, número" e descarta complemento, bairro e CEP. */
    private function limparLogradouro(string $endereco): string
    {
        $s = preg_replace('/\bCEP\b.*$/iu', '', $endereco) ?? $endereco;
        $s = preg_replace('/\b\d{2}\.?\d{3}-\d{3}\b/', '', $s) ?? $s;
        $s = preg_replace('/\b(sala|sl|salas|apto?|apartamento|loja|lj|conj(unto)?|andar|bloco|cj)\.?\s*[\w\/-]+/iu', '', $s) ?? $s;
        $s = str_replace([' — ', ' – ', ' - '], ', ', $s);

        $partes = array_values(array_filter(array_map('trim', explode(',', $s)), static fn($p) => $p !== ''));
        if ($partes === []) {
            return '';
        }
        $rua = $partes[0];
        if (isset($partes[1]) && preg_match('/^(n[º°o.]?\s*)?\d+[a-z]?$/iu', $partes[1])) {
            $rua .= ' ' . preg_replace('/^n[º°o.]?\s*/iu', '', $partes[1]);
        }
        return trim($rua);
    }

    /** @param array<string,string> $params */
    private function nominatim(array $params): ?array
    {
        $espera = 1.1 - (microtime(true) - $this->ultimaChamadaNominatim);
        if ($espera > 0) {
            usleep((int) ($espera * 1_000_000));
        }
        $this->ultimaChamadaNominatim = microtime(true);

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params + [
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'br',
        ]);
        $json = $this->httpJson($url);
        return is_array($json) && isset($json[0]['lat']) ? $json[0] : null;
    }

    // ---------------------------------------------------------------- util

    private function textoEndereco(array $local): string
    {
        $rua = $this->limparLogradouro((string) ($local['endereco'] ?? ''));
        return implode(', ', array_filter([$rua, $local['cidade'] ?? '', ($local['uf'] ?? '') ?: null, 'Brasil']));
    }

    private function chave(array $local): string
    {
        $txt = mb_strtolower(trim(($local['endereco'] ?? '') . '|' . ($local['cidade'] ?? '') . '|' . ($local['uf'] ?? '')));
        return md5(preg_replace('/\s+/', ' ', $txt) ?? $txt);
    }

    private function googleKey(): string
    {
        Database::enabled();
        return trim((string) ($_ENV['GOOGLE_MAPS_API_KEY'] ?? ''));
    }

    private function httpJson(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: pt-BR'],
        ]);
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            // PHP no Windows vem sem bundle de CA; usa o repositório de certificados do sistema
            curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        $body = curl_exec($ch);
        curl_close($ch);
        if (!is_string($body) || $body === '') {
            return null;
        }
        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }

    private function cacheGet(string $grupo, string $key): ?array
    {
        $this->carregarCache();
        $hit = $this->cache[$grupo][$key] ?? null;
        // Ignora entradas antigas de estimativa (linha reta) — força novo cálculo por rota de carro
        if ($grupo === 'rotas' && is_array($hit) && ($hit['fonte'] ?? '') === 'estimativa') {
            return null;
        }
        return $hit;
    }

    private function cacheSet(string $grupo, string $key, array $valor): void
    {
        $this->carregarCache();
        $this->cache[$grupo][$key] = $valor + ['em' => date('c')];
    }

    private function carregarCache(): void
    {
        if ($this->cache !== null) {
            return;
        }
        $data = is_file($this->cachePath) ? json_decode((string) file_get_contents($this->cachePath), true) : null;
        $this->cache = is_array($data) ? $data + ['geocode' => [], 'rotas' => []] : ['geocode' => [], 'rotas' => []];
    }

    private function salvarCache(): void
    {
        if ($this->cache === null) {
            return;
        }
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->cachePath, json_encode($this->cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** A precisão de uma rota é a pior entre origem e destino. */
    private static function piorPrecisao(string $a, string $b): string
    {
        $ordem = ['endereco' => 3, 'cep' => 2, 'cidade' => 1];
        return ($ordem[$a] ?? 0) <= ($ordem[$b] ?? 0) ? $a : $b;
    }
}
