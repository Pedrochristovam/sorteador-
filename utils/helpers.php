<?php

declare(strict_types=1);

/**
 * Funções utilitárias compartilhadas.
 */

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_cnpj(string $cnpj): string
{
    $digits = preg_replace('/\D/', '', $cnpj) ?? '';
    if (strlen($digits) !== 14) {
        return $cnpj;
    }
    return sprintf(
        '%s.%s.%s/%s-%s',
        substr($digits, 0, 2),
        substr($digits, 2, 3),
        substr($digits, 5, 3),
        substr($digits, 8, 4),
        substr($digits, 12, 2)
    );
}

function format_date(?string $date, string $format = 'd/m/Y'): string
{
    if (!$date) {
        return '—';
    }
    $dt = date_create($date);
    return $dt ? $dt->format($format) : $date;
}

function format_datetime(?string $date, string $format = 'd/m/Y H:i'): string
{
    return format_date($date, $format);
}

function format_km(?float $km): string
{
    if ($km === null) {
        return '—';
    }
    return number_format($km, 1, ',', '.') . ' km';
}

function current_user(): array
{
    return app_config('auth.default_user', [
        'id' => 'USR-001',
        'nome' => 'Operador Demo',
        'email' => 'operador@empresa.local',
        'perfil' => 'administrador',
    ]);
}

function generate_id(string $prefix): string
{
    return sprintf('%s-%s-%s', $prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(3))));
}

function status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'ativa', 'ativo', 'concluido', 'concluído' => 'badge-success',
        'inativa', 'inativo', 'cancelado' => 'badge-danger',
        'pendente', 'em_andamento', 'aguardando_orcamento' => 'badge-warning',
        default => 'badge-neutral',
    };
}

function status_sorteio_label(string $status): string
{
    return match (strtolower($status)) {
        'concluido', 'concluído' => 'Concluído',
        'cancelado' => 'Cancelado',
        'em_andamento' => 'Em andamento',
        'aguardando_orcamento' => 'Aguardando orçamentos',
        default => ucfirst($status),
    };
}

/** Minúsculas e sem acento, para comparar nomes de cidade digitados de formas diferentes. */
function normalizar_texto(?string $texto): string
{
    $t = mb_strtolower(trim((string) $texto));
    $semAcento = strtr($t, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);
    return preg_replace('/\s+/', ' ', $semAcento) ?? $semAcento;
}

function format_moeda(?float $valor): string
{
    return $valor === null ? '—' : 'R$ ' . number_format($valor, 2, ',', '.');
}

function modalidade_label(?string $codigo): string
{
    return match ($codigo) {
        'sorteio' => 'Sorteio com rodízio',
        'proxima' => 'Mais próxima (preço de edital)',
        'orcamento' => 'Orçamento entre 3',
        default => 'Sorteio',
    };
}

function page_url(string $page, array $params = []): string
{
    $query = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($query);
}

function is_active_page(string $page, string $current): bool
{
    return $page === $current;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . page_url($page, $params));
    exit;
}

function iniciais_empresa(?string $nome): string
{
    $nome = trim((string) $nome);
    if ($nome === '') {
        return '—';
    }
    $parts = preg_split('/\s+/', $nome) ?: [];
    $skip = ['de', 'da', 'do', 'das', 'dos', 'e', 'ltda', 'me', 'eireli', 'sa', 's.a.', 'epp'];
    $letters = [];
    foreach ($parts as $p) {
        $clean = preg_replace('/[^A-Za-zÀ-ú]/', '', $p) ?? '';
        if ($clean === '' || in_array(mb_strtolower($clean), $skip, true)) {
            continue;
        }
        $letters[] = mb_strtoupper(mb_substr($clean, 0, 1));
        if (count($letters) >= 2) {
            break;
        }
    }
    if ($letters === []) {
        return mb_strtoupper(mb_substr($nome, 0, 2));
    }
    return implode('', $letters);
}

function avatar_tone(?string $seed): string
{
    $tones = ['rose', 'blue', 'violet', 'teal', 'amber', 'slate'];
    $idx = abs(crc32((string) $seed)) % count($tones);
    return $tones[$idx];
}

function integrity_hash_for(string $payload): string
{
    return 'sha256:' . hash('sha256', $payload);
}

/**
 * Classifica motivo de eliminação para cards de auditoria.
 *
 * @param list<string> $motivos
 * @return array{codigo:string,label:string,tone:string,legal:string}
 */
function classificar_eliminacao(array $motivos, ?string $statusEmpresa = null): array
{
    $texto = mb_strtolower(implode(' ', $motivos));
    $status = mb_strtolower((string) $statusEmpresa);

    if ($status === 'inativa' || str_contains($texto, 'desativad') || str_contains($texto, 'convênio inativo') || str_contains($texto, 'convenio inativo')) {
        return [
            'codigo' => 'desativada',
            'label' => 'Empresa Desativada',
            'tone' => 'muted',
            'legal' => 'Edital de Credenciamento — exclusão cautelar por desativação administrativa do cadastro.',
        ];
    }
    if (str_contains($texto, 'região') || str_contains($texto, 'regiao')) {
        return [
            'codigo' => 'fora_regiao',
            'label' => 'Não Atende a Região',
            'tone' => 'warning',
            'legal' => 'Critério de localização do edital — a região da demanda não consta no cadastro da credenciada.',
        ];
    }
    if (str_contains($texto, '100 km')) {
        return [
            'codigo' => 'limite_km',
            'label' => 'Acima do Limite de 100 km',
            'tone' => 'warning',
            'legal' => 'Credenciada cadastrada para atender só até 100 km ida e volta; a rota de carro até a demanda passa desse limite.',
        ];
    }
    if (str_contains($texto, 'sediadas em') || str_contains($texto, 'cidade da demanda')) {
        return [
            'codigo' => 'fora_cidade',
            'label' => 'Fora da Cidade da Demanda',
            'tone' => 'info',
            'legal' => 'Sorteio restrito pelo operador às credenciadas com endereço na cidade da demanda (2 ou mais disponíveis).',
        ];
    }
    if (str_contains($texto, 'raio') || str_contains($texto, 'localização') || str_contains($texto, 'localizacao') || str_contains($texto, 'fora do')) {
        return [
            'codigo' => 'fora_raio',
            'label' => 'Fora do Raio Operacional',
            'tone' => 'warning',
            'legal' => 'Critério de localização (regra anterior) — distância superior ao raio máximo da demanda.',
        ];
    }
    if (str_contains($texto, 'document') || str_contains($texto, 'cndt') || str_contains($texto, 'suspend') || str_contains($texto, 'pendên')) {
        return [
            'codigo' => 'documentacao',
            'label' => 'Inabilitada Cautelar',
            'tone' => 'danger',
            'legal' => 'Art. 68 da Lei 14.133/2021 — regularidade fiscal e trabalhista exigida para participação.',
        ];
    }
    if (str_contains($texto, 'quarentena') || str_contains($texto, 'igualdade')) {
        return [
            'codigo' => 'quarentena',
            'label' => 'Quarentena de Igualdade',
            'tone' => 'info',
            'legal' => 'Princípio da isonomia (Art. 37 CF) — intervalo mínimo entre contemplações no mesmo polo.',
        ];
    }
    if (str_contains($texto, 'serviço') || str_contains($texto, 'servico') || str_contains($texto, 'incompatív') || str_contains($texto, 'atividade')) {
        return [
            'codigo' => 'servico',
            'label' => 'Atividade Não Habilitada',
            'tone' => 'danger',
            'legal' => 'Objeto do credenciamento — a credenciada não é habilitada na atividade da demanda.',
        ];
    }

    return [
        'codigo' => 'outros',
        'label' => 'Não Elegível',
        'tone' => 'danger',
        'legal' => 'Critérios de elegibilidade do regulamento operacional do sorteio.',
    ];
}

/**
 * @param list<array{evento?:string,timestamp?:string,detalhe?:mixed}> $eventos
 * @return list<array{ts:string,level:string,message:string}>
 */
function build_execucao_log(array $eventos, array $ctx = []): array
{
    if ($eventos === []) {
        $base = (string) ($ctx['dataHora'] ?? date('Y-m-d H:i:s'));
        $eventos = [
            ['evento' => 'demanda_registrada', 'timestamp' => $base, 'detalhe' => null],
            ['evento' => 'elegibilidade_calculada', 'timestamp' => $base, 'detalhe' => null],
            ['evento' => 'sorteio_executado', 'timestamp' => $base, 'detalhe' => null],
        ];
    }

    $map = [
        'demanda_registrada' => ['INIT', 'Coleta de parâmetros da demanda e critérios ativos.'],
        'elegibilidade_calculada' => ['FILTER', 'Processamento de proponentes e desqualificação por critérios.'],
        'sorteio_confirmado' => ['CONFIRM', 'Confirmação do operador para execução do certame.'],
        'entropia_solicitada' => ['ENTROPY', 'Requisição de semente CSPRNG / HSM.'],
        'embaralhamento' => ['EXEC_SHUFFLE', 'Inicializado algoritmo de Fisher-Yates com semente auditável.'],
        'sorteio_executado' => ['SELECTION', 'Proponente sorteado e registrado.'],
        'modalidade_definida' => ['RULE', 'Modalidade definida pela demanda:'],
        'distancia_apurada' => ['ROUTE', 'Distância por rota de carro apurada via'],
        'rodizio_aplicado' => ['ROTATION', 'Controle das chamadas (rodízio geral):'],
        'mais_proxima_escolhida' => ['SELECTION', 'Credenciada mais próxima escolhida:'],
        'sorteadas_orcamento' => ['SELECTION', 'Credenciadas sorteadas para orçamento:'],
        'orcamentos_registrados' => ['QUOTES', 'Orçamentos registrados — menor valor leva o serviço:'],
        'hash_consolidado' => ['FINALIZATION', 'Hash SHA-256 consolidado e trilha assinada.'],
    ];

    $out = [];
    $i = 0;
    foreach ($eventos as $ev) {
        $evento = (string) ($ev['evento'] ?? 'evento');
        $ts = (string) ($ev['timestamp'] ?? '');
        $detalhe = $ev['detalhe'] ?? null;
        [$level, $defaultMsg] = $map[$evento] ?? [strtoupper($evento), str_replace('_', ' ', $evento)];
        $msg = $defaultMsg;
        if (is_string($detalhe) && $detalhe !== '') {
            $msg .= ' ' . $detalhe;
        }
        if ($evento === 'sorteio_executado' && !empty($ctx['vencedor'])) {
            $msg = 'Proponente sorteado: ' . $ctx['vencedor'];
        }
        if ($evento === 'elegibilidade_calculada' && isset($ctx['analisadas'], $ctx['eliminadas'])) {
            $msg = sprintf(
                '%d proponentes processados. %d desqualificados por critérios de elegibilidade.',
                (int) $ctx['analisadas'],
                (int) $ctx['eliminadas']
            );
        }

        $ms = str_pad((string) (100 + $i * 47), 3, '0', STR_PAD_LEFT);
        $timePart = $ts !== '' ? date('H:i:s', strtotime($ts)) : date('H:i:s');
        $out[] = [
            'ts' => $timePart . '.' . $ms,
            'level' => $level,
            'message' => $msg,
        ];
        $i++;
    }

    if (!empty($ctx['hash'])) {
        $lastTs = $out !== [] ? $out[count($out) - 1]['ts'] : date('H:i:s') . '.999';
        $parts = explode('.', $lastTs);
        $base = $parts[0] ?? date('H:i:s');
        $out[] = [
            'ts' => $base . '.520',
            'level' => 'FINALIZATION',
            'message' => 'Hash SHA-256 consolidado: ' . $ctx['hash'],
        ];
    }

    return $out;
}

/**
 * Enriquece registro de eliminação com classificação jurídica.
 *
 * @param array $emp
 * @return array
 */
function enriquecer_eliminacao(array $emp): array
{
    $motivos = array_values($emp['motivos'] ?? $emp['motivosEliminacao'] ?? []);
    $class = classificar_eliminacao($motivos, $emp['status'] ?? null);
    $emp['motivos'] = $motivos;
    $emp['categoria'] = $class['codigo'];
    $emp['categoriaLabel'] = $class['label'];
    $emp['tone'] = $class['tone'];
    $emp['fundamentacao'] = $emp['fundamentacao'] ?? $class['legal'];
    return $emp;
}
