<?php

declare(strict_types=1);

/**
 * Logs de auditoria mockados — alinhados aos sorteios do histórico.
 */

return [
    [
        'id' => 'AUD-20260310-A1B2C3',
        'sorteioId' => 'SRT-20260310-A1B2C3',
        'dataHora' => '2026-03-10 14:32:00',
        'usuarioId' => 'USR-001',
        'usuarioNome' => 'Operador Demo',
        'demanda' => [
            'cidade' => 'São Paulo',
            'endereco' => 'Av. Paulista, 1000 — Bela Vista',
            'tipoDemanda' => 'Serviço contínuo',
            'servicoNecessario' => 'TI',
            'raioMaximoKm' => 30,
        ],
        'criteriosUtilizados' => [
            'Serviço compatível',
            'Convênio ativo',
            'Critério de localização',
            'Documentação regular',
        ],
        'empresasAnalisadas' => [
            ['id' => 'EMP-001', 'nome' => 'TechSol Soluções Digitais Ltda', 'cnpj' => '12.345.678/0001-90'],
            ['id' => 'EMP-002', 'nome' => 'Norte Manutenção Predial S.A.', 'cnpj' => '98.765.432/0001-10'],
            ['id' => 'EMP-008', 'nome' => 'DataNet Telecomunicações S.A.', 'cnpj' => '11.222.333/0001-44'],
            ['id' => 'EMP-011', 'nome' => 'Construtora Firenze Ltda', 'cnpj' => '55.666.777/0001-88'],
            ['id' => 'EMP-012', 'nome' => 'Alfa Geotecnia & Sondagens', 'cnpj' => '22.333.444/0001-55'],
        ],
        'empresasEliminadas' => [
            [
                'id' => 'EMP-011',
                'nome' => 'Construtora Firenze Ltda',
                'cnpj' => '55.666.777/0001-88',
                'status' => 'ativa',
                'motivos' => ['Certidão CNDT vencida — pendências documentais'],
                'categoriaLabel' => 'Inabilitada Cautelar',
                'tone' => 'danger',
                'fundamentacao' => 'Art. 68 da Lei 14.133/2021 — regularidade fiscal e trabalhista exigida para participação.',
            ],
            [
                'id' => 'EMP-012',
                'nome' => 'Alfa Geotecnia & Sondagens',
                'cnpj' => '22.333.444/0001-55',
                'status' => 'ativa',
                'distanciaKm' => 48.6,
                'motivos' => ['Fora do critério de localização (48.6 km > 30 km)'],
                'categoriaLabel' => 'Fora do Raio Operacional',
                'tone' => 'warning',
                'fundamentacao' => 'Critério de localização do edital — distância superior ao raio máximo da demanda.',
            ],
            [
                'id' => 'EMP-002',
                'nome' => 'Norte Manutenção Predial S.A.',
                'cnpj' => '98.765.432/0001-10',
                'status' => 'ativa',
                'motivos' => ['Serviço incompatível com a demanda'],
                'categoriaLabel' => 'Serviço Incompatível',
                'tone' => 'danger',
                'fundamentacao' => 'Objeto do certame — atividade habilitada incompatível com a demanda.',
            ],
        ],
        'empresasElegiveis' => [
            ['id' => 'EMP-001', 'nome' => 'TechSol Soluções Digitais Ltda', 'cnpj' => '12.345.678/0001-90'],
            ['id' => 'EMP-008', 'nome' => 'DataNet Telecomunicações S.A.', 'cnpj' => '11.222.333/0001-44'],
        ],
        'empresasParticipantes' => [
            ['id' => 'EMP-001', 'nome' => 'TechSol Soluções Digitais Ltda', 'cnpj' => '12.345.678/0001-90'],
            ['id' => 'EMP-008', 'nome' => 'DataNet Telecomunicações S.A.', 'cnpj' => '11.222.333/0001-44'],
        ],
        'resultadoFinal' => [
            'id' => 'EMP-001',
            'nome' => 'TechSol Soluções Digitais Ltda',
            'cnpj' => '12.345.678/0001-90',
        ],
        'hashOperacao' => 'sha256:25b98f947cae0c5ad7e8c3e018836ed4e48fe569347388c6d83eff9611a17bcc',
        'seedReferencia' => 'seed:a1b2c3d4e5f678901234abcd',
        'versaoAlgoritmo' => 'auditable-v1',
        'eventos' => [
            ['evento' => 'demanda_registrada', 'timestamp' => '2026-03-10 14:28:00', 'detalhe' => 'Demanda de TI em São Paulo'],
            ['evento' => 'elegibilidade_calculada', 'timestamp' => '2026-03-10 14:29:10', 'detalhe' => null],
            ['evento' => 'entropia_solicitada', 'timestamp' => '2026-03-10 14:31:40', 'detalhe' => 'CSPRNG'],
            ['evento' => 'embaralhamento', 'timestamp' => '2026-03-10 14:31:50', 'detalhe' => 'Fisher-Yates'],
            ['evento' => 'sorteio_executado', 'timestamp' => '2026-03-10 14:32:00', 'detalhe' => 'Empresa selecionada: EMP-001'],
            ['evento' => 'hash_consolidado', 'timestamp' => '2026-03-10 14:32:05', 'detalhe' => null],
        ],
    ],
    [
        'id' => 'AUD-20260305-D4E5F6',
        'sorteioId' => 'SRT-20260305-D4E5F6',
        'dataHora' => '2026-03-05 09:15:00',
        'usuarioId' => 'USR-001',
        'usuarioNome' => 'Operador Demo',
        'demanda' => [
            'cidade' => 'São Paulo',
            'endereco' => 'Rua Augusta, 500 — Consolação',
            'tipoDemanda' => 'Emergencial',
            'servicoNecessario' => 'Limpeza',
            'raioMaximoKm' => 25,
        ],
        'criteriosUtilizados' => ['Serviço compatível', 'Convênio ativo', 'Critério de localização'],
        'empresasAnalisadas' => [
            ['id' => 'EMP-002', 'nome' => 'Norte Manutenção Predial S.A.'],
            ['id' => 'EMP-007', 'nome' => 'CleanPro Serviços Ambientais'],
            ['id' => 'EMP-013', 'nome' => 'Beta Perícias e Engenharia'],
        ],
        'empresasEliminadas' => [
            [
                'id' => 'EMP-013',
                'nome' => 'Beta Perícias e Engenharia',
                'cnpj' => '33.444.555/0001-66',
                'motivos' => ['Quarentena de igualdade — contemplada há 11 dias (mínimo 30)'],
                'categoriaLabel' => 'Quarentena de Igualdade',
                'tone' => 'info',
                'fundamentacao' => 'Princípio da isonomia (Art. 37 CF) — intervalo mínimo entre contemplações no mesmo polo.',
            ],
        ],
        'empresasElegiveis' => [
            ['id' => 'EMP-002', 'nome' => 'Norte Manutenção Predial S.A.'],
            ['id' => 'EMP-007', 'nome' => 'CleanPro Serviços Ambientais'],
        ],
        'empresasParticipantes' => [
            ['id' => 'EMP-002', 'nome' => 'Norte Manutenção Predial S.A.'],
            ['id' => 'EMP-007', 'nome' => 'CleanPro Serviços Ambientais'],
        ],
        'resultadoFinal' => [
            'id' => 'EMP-007',
            'nome' => 'CleanPro Serviços Ambientais',
            'cnpj' => '78.901.234/0001-56',
        ],
        'hashOperacao' => 'sha256:d4e5f6a7b8c9012345678901abcdef0123456789abcdef0123456789abcd',
        'seedReferencia' => 'seed:d4e5f6a7b8c901234567ef01',
        'versaoAlgoritmo' => 'auditable-v1',
        'eventos' => [
            ['evento' => 'demanda_registrada', 'timestamp' => '2026-03-05 09:10:00', 'detalhe' => null],
            ['evento' => 'elegibilidade_calculada', 'timestamp' => '2026-03-05 09:12:00', 'detalhe' => null],
            ['evento' => 'sorteio_executado', 'timestamp' => '2026-03-05 09:15:00', 'detalhe' => 'Empresa selecionada: EMP-007'],
        ],
    ],
];
