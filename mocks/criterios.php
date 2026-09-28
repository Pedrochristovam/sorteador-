<?php

declare(strict_types=1);

/**
 * Critérios de elegibilidade — Sorteador de Engenharia.
 */

return [
    [
        'codigo' => 'servico_compativel',
        'nome' => 'Atividade habilitada',
        'descricao' => 'A empresa deve estar habilitada em ao menos uma das atividades solicitadas na demanda.',
        'obrigatorio' => true,
        'tipo' => 'boolean',
    ],
    [
        'codigo' => 'convenio_ativo',
        'nome' => 'Credenciado ativo',
        'descricao' => 'Somente empresas com credenciamento ativo participam do sorteio.',
        'obrigatorio' => true,
        'tipo' => 'boolean',
    ],
    [
        'codigo' => 'localizacao',
        'nome' => 'Atende a região da demanda',
        'descricao' => 'A empresa deve ter no cadastro a região (ou estado) onde fica a demanda. A distância não elimina quem atende a região.',
        'obrigatorio' => true,
        'tipo' => 'region',
    ],
    [
        'codigo' => 'limite_100km',
        'nome' => 'Limite de deslocamento (100 km ida e volta)',
        'descricao' => 'Empresa cadastrada para atender só até 100 km (50 km ida + 50 km volta) sai quando a rota de carro até a demanda passa desse limite.',
        'obrigatorio' => true,
        'tipo' => 'distance',
    ],
    [
        'codigo' => 'documentacao_regular',
        'nome' => 'Documentação regular',
        'descricao' => 'Empresa sem pendências cadastrais ou documentais.',
        'obrigatorio' => true,
        'tipo' => 'boolean',
    ],
];
