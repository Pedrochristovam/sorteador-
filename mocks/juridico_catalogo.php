<?php

declare(strict_types=1);

/**
 * Catálogos do Credenciamento Jurídico — Edital 01/2026.
 */
return [
    'atividades' => [
        'JUR-CONT' => 'Contencioso judicial',
        'JUR-CONS' => 'Consultivo',
        'JUR-EXTRA' => 'Extrajudicial',
        'JUR-TRAB' => 'Trabalhista',
        'JUR-CIV' => 'Cível',
        'JUR-ADM' => 'Administrativo',
        'JUR-TRIB' => 'Tributário',
        'JUR-REC' => 'Recuperação / Falência',
    ],
    'polos' => [
        'COM-BH' => 'Comarca de Belo Horizonte',
        'COM-UBERLANDIA' => 'Comarca de Uberlândia',
        'COM-JUIZ-DE-FORA' => 'Comarca de Juiz de Fora',
        'COM-MONTES-CLAROS' => 'Comarca de Montes Claros',
        'COM-UBERABA' => 'Comarca de Uberaba',
        'COM-GOVERNADOR-VALADARES' => 'Comarca de Governador Valadares',
        'COM-POUSO-ALEGRE' => 'Comarca de Pouso Alegre',
        'COM-DIVINOPOLIS' => 'Comarca de Divinópolis',
        'COM-IPATINGA' => 'Comarca de Ipatinga',
        'COM-SETES-LAGOAS' => 'Comarca de Sete Lagoas',
        'COM-VARGINHA' => 'Comarca de Varginha',
        'COM-PATOS-DE-MINAS' => 'Comarca de Patos de Minas',
        'POLO-CENTRAL' => 'Polo Central (MG)',
        'POLO-SUL' => 'Polo Sul de Minas',
        'POLO-TRIANGULO' => 'Polo Triângulo Mineiro',
        'POLO-NORTE' => 'Polo Norte de Minas',
        'POLO-ZONA-MATA' => 'Polo Zona da Mata',
    ],
    'criterios' => [
        [
            'codigo' => 'atividade_compativel',
            'nome' => 'Atividade jurídica compatível',
            'descricao' => 'A sociedade deve ter optado pela atividade demandada na Ficha de Opção.',
            'obrigatorio' => true,
        ],
        [
            'codigo' => 'polo_abrangencia',
            'nome' => 'Polo / comarca de abrangência',
            'descricao' => 'A sociedade deve ter indicado o polo/comarca da demanda no Anexo X.',
            'obrigatorio' => true,
        ],
        [
            'codigo' => 'credenciamento_ativo',
            'nome' => 'Credenciamento ativo',
            'descricao' => 'Somente sociedades ativas e contratadas participam.',
            'obrigatorio' => true,
        ],
        [
            'codigo' => 'sem_demanda_aberta',
            'nome' => 'Sem demanda em andamento',
            'descricao' => 'Sociedade com OS/processo sob seus cuidados fica temporariamente fora da amostra.',
            'obrigatorio' => true,
        ],
        [
            'codigo' => 'documentacao_regular',
            'nome' => 'Documentação regular',
            'descricao' => 'Sociedade sem pendências cadastrais ou documentais.',
            'obrigatorio' => true,
        ],
    ],
];
