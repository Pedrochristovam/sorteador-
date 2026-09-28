<?php

declare(strict_types=1);

/**
 * Sugestão automática da região da demanda.
 * UF fora de MG → código do estado no edital; cidades de MG → região de planejamento.
 * A lista de cidades é parcial: o operador sempre confirma a região no formulário.
 */

return [
    'uf' => [
        'AC' => 'XI', 'AM' => 'XII', 'RO' => 'XIII', 'AP' => 'XIV', 'PA' => 'XV',
        'MA' => 'XVI', 'PI' => 'XVII', 'CE' => 'XVIII', 'RN' => 'XIX', 'PB' => 'XX',
        'PE' => 'XXI', 'AL' => 'XXII', 'SE' => 'XXIII', 'BA' => 'XXIV', 'ES' => 'XXV',
        'RJ' => 'XXVI', 'SP' => 'XXVII', 'PR' => 'XXVIII', 'SC' => 'XXIX', 'RS' => 'XXX',
        'MS' => 'XXXI', 'MT' => 'XXXII', 'GO' => 'XXXIII', 'DF' => 'XXXIII', 'TO' => 'XXXIV',
        'RR' => 'XXXV',
    ],
    'mg' => [
        'I' => [
            'Belo Horizonte', 'Contagem', 'Betim', 'Nova Lima', 'Sabará', 'Santa Luzia',
            'Ribeirão das Neves', 'Vespasiano', 'Lagoa Santa', 'Sete Lagoas', 'Ibirité',
            'Brumadinho', 'Ouro Preto', 'Mariana', 'Conselheiro Lafaiete', 'Curvelo',
            'Barbacena', 'Itabirito', 'Pedro Leopoldo', 'Esmeraldas', 'Caeté',
        ],
        'II' => [
            'Juiz de Fora', 'Muriaé', 'Ubá', 'Viçosa', 'Cataguases', 'Leopoldina',
            'Manhuaçu', 'Ponte Nova', 'Carangola', 'Santos Dumont', 'Além Paraíba',
        ],
        'III' => [
            'Pouso Alegre', 'Varginha', 'Poços de Caldas', 'Passos', 'Lavras', 'Três Corações',
            'Itajubá', 'Alfenas', 'Guaxupé', 'São Lourenço', 'Extrema', 'Três Pontas',
            'São Sebastião do Paraíso', 'Caxambu', 'Santa Rita do Sapucaí',
        ],
        'IV' => [
            'Uberlândia', 'Uberaba', 'Ituiutaba', 'Araguari', 'Frutal', 'Iturama', 'Tupaciguara',
        ],
        'V' => [
            'Patos de Minas', 'Araxá', 'Patrocínio', 'Monte Carmelo', 'Coromandel',
            'Carmo do Paranaíba', 'São Gotardo', 'Sacramento',
        ],
        'VI' => [
            'Divinópolis', 'Nova Serrana', 'Itapecerica', 'Formiga', 'Itaúna', 'Bom Despacho',
            'Oliveira', 'Campo Belo', 'Arcos', 'Lagoa da Prata', 'Pará de Minas',
        ],
        'VII' => [
            'Unaí', 'Paracatu', 'João Pinheiro', 'Buritis', 'Arinos',
        ],
        'VIII' => [
            'Montes Claros', 'Janaúba', 'Januária', 'Pirapora', 'Salinas', 'Bocaiúva',
            'Porteirinha', 'Espinosa', 'Taiobeiras', 'Várzea da Palma', 'São Francisco',
        ],
        'IX' => [
            'Teófilo Otoni', 'Diamantina', 'Almenara', 'Araçuaí', 'Capelinha', 'Nanuque',
            'Itaobim', 'Minas Novas', 'Pedra Azul', 'Jequitinhonha',
        ],
        'X' => [
            'Governador Valadares', 'Ipatinga', 'Coronel Fabriciano', 'Timóteo', 'Caratinga',
            'Aimorés', 'Mantena', 'Guanhães', 'Itabira', 'João Monlevade', 'Resplendor',
        ],
    ],
];
