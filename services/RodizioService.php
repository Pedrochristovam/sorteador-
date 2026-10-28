<?php

declare(strict_types=1);

require_once __DIR__ . '/DrawService.php';

/**
 * Controle das chamadas (rodízio geral).
 *
 * A empresa só entra no rodízio depois de ACEITAR a demanda (aba Standby).
 * Recusa ou aceite pendente não contam — a credenciada segue apta aos próximos sorteios.
 * Quando todas as ativas tiverem aceitado ao menos uma demanda no ciclo, o ciclo recomeça.
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

        return [
            'ids' => array_keys($chamadas),
            'inicioCiclo' => $inicio,
            'ciclo' => $ciclo,
            'totalAtivas' => count($universo),
        ];
    }
}
