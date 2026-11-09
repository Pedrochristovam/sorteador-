<?php

declare(strict_types=1);

require_once __DIR__ . '/DrawService.php';

/**
 * Controle das chamadas (rodízio geral).
 *
 * A empresa só entra no rodízio depois de ACEITAR a demanda (aba Standby),
 * ou quando for marcada manualmente no cadastro como "já realizou serviço".
 * Recusa ou aceite pendente não contam — a credenciada segue apta aos próximos sorteios.
 * Quando todas as ativas tiverem aceitado ao menos uma demanda no ciclo, o ciclo recomeça.
 * A marcação manual permanece até ser desmarcada (escolha manual continua liberada).
 */
final class RodizioService
{
    public function __construct(private readonly DrawService $drawService = new DrawService())
    {
    }

    /**
     * @param list<string> $idsAtivas IDs das credenciadas ativas hoje
     * @param list<string> $idsMarcacaoManual IDs marcadas no cadastro como serviço já realizado
     * @return array{ids:list<string>,idsMarcacaoManual:list<string>,inicioCiclo:?string,ciclo:int,totalAtivas:int}
     */
    public function cicloAtual(array $idsAtivas, array $idsMarcacaoManual = []): array
    {
        $historico = array_values(array_filter(
            $this->drawService->listHistorico(),
            static function (array $s): bool {
                $ids = $s['rodizioIds'] ?? null;
                return is_array($ids) && $ids !== [] && ($s['status'] ?? '') === 'concluido';
            }
        ));
        usort($historico, static fn(array $a, array $b): int => strcmp((string) $a['dataHora'], (string) $b['dataHora']));

        $universo = array_flip($idsAtivas);
        $chamadas = [];
        $inicio = null;
        $ciclo = 1;

        foreach ($historico as $sorteio) {
            $inicio ??= (string) $sorteio['dataHora'];
            foreach ($sorteio['rodizioIds'] as $id) {
                if (isset($universo[$id])) {
                    $chamadas[$id] = true;
                }
            }
            if ($universo !== [] && count($chamadas) >= count($universo)) {
                $chamadas = [];
                $inicio = null;
                $ciclo++;
            }
        }

        $manual = [];
        foreach ($idsMarcacaoManual as $id) {
            $id = (string) $id;
            if (isset($universo[$id])) {
                $manual[$id] = true;
                $chamadas[$id] = true;
            }
        }

        return [
            'ids' => array_keys($chamadas),
            'idsMarcacaoManual' => array_keys($manual),
            'inicioCiclo' => $inicio,
            'ciclo' => $ciclo,
            'totalAtivas' => count($universo),
        ];
    }
}
