<?php
/**
 * Configuração central da aplicação.
 * Preparado para futuras integrações (DB, auth, geolocalização, APIs).
 */

declare(strict_types=1);

return [
    'name' => 'Sorteador de Convênios',
    'short_name' => 'Sorteador',
    'version' => '1.1.0',
    'org' => 'Sistema Corporativo de Convênios',
    'timezone' => 'America/Sao_Paulo',
    'locale' => 'pt_BR',

    // Banco de dados MySQL (configurado via .env)
    'database' => [
        'enabled' => true,
        'driver' => 'mysql',
    ],

    // Futuro: autenticação
    'auth' => [
        'enabled' => false,
        'default_user' => [
            'id' => 'USR-001',
            'nome' => 'Operador Demo',
            'email' => 'operador@empresa.local',
            'perfil' => 'administrador',
        ],
    ],

    // Futuro: API de geolocalização / cálculo de distância
    'geo' => [
        'provider' => 'mock',
        'enabled' => false,
    ],

    // Mecanismo de sorteio: 'mock' | 'secure_random' | 'auditable' (futuro)
    'draw' => [
        'mode' => 'mock',
        'auditable' => false,
    ],
];
