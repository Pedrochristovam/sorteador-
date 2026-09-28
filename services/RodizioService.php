<?php

declare(strict_types=1);

require_once __DIR__ . '/DrawService.php';

/**
 * Controle das chamadas (rodízio geral).
 *
 * Toda credenciada chamada (sorteada, escolhida por proximidade ou sorteada para orçamento)
 * fica fora dos próximos sorteios até que todas as credenciadas ativas tenham sido chamadas.
 * Quando todas foram chamadas, o ciclo recomeça.
 * Só contam sorteios feitos pelo motor atual (registros com "rodizioIds").
 */
final class RodizioService
{
    public function __construct(private readonly DrawService $drawService = new DrawService())
    {
    }

    /**
     * @param list<string> $idsAtivas IDs das credenciadas ativas hoje
     * @return array{ids:list<string>,inicioCiclo:?string,ciclo:int,totalAtivas:int}
     */
    public function cicloAtual(array $idsAtivas): array
    {
        $historico = array_values(array_filter(
            $this->drawService->listHistorico(),
            static fn(array $s): bool => isset($s['rodizioIds']) && is_array($s['rodizioIds'])
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

        return [
            'ids' => array_keys($chamadas),
            'inicioCiclo' => $inicio,
            'ciclo' => $ciclo,
            'totalAtivas' => count($universo),
        ];
    }
}
