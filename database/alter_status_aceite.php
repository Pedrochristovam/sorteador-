<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/Database.php';

$pdo = Database::pdo();
$pdo->exec(
    "ALTER TABLE sorteios MODIFY status ENUM(
        'concluido','cancelado','em_andamento','aguardando_orcamento','aguardando_aceite','recusado'
    ) NOT NULL DEFAULT 'concluido'"
);
echo "ENUM sorteios.status atualizado.\n";
