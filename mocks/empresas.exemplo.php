<?php

declare(strict_types=1);

/**
 * Empresas FICTÍCIAS de exemplo, usadas quando mocks/empresas.php não existe.
 * A base real (planilha de credenciamento, com contatos) fica em mocks/empresas.php, fora do Git.
 * Para gerá-la: python scripts/import_credenciamento.py
 */

$empresa = static function (string $id, string $nome, string $cidade, string $endereco, string $cep, array $atividades, array $regioes, array $estados, int $ordem, bool $acima100 = true, string $status = 'ativa'): array {
    $nomesAtividades = ['A' => 'Laudo Simplificado', 'B' => 'Laudo Completo', 'C' => 'Laudo Rural', 'D' => 'Levantamento Topográfico', 'E' => 'Georreferenciamento', 'F' => 'Vistoria de Obra'];
    $nomesRegioes = ['I' => 'Central (MG)', 'II' => 'Zona da Mata (MG)', 'III' => 'Sul de Minas', 'IV' => 'Triângulo (MG)', 'VIII' => 'Norte de Minas'];
    $nomesEstados = ['XXV' => 'Espírito Santo', 'XXVII' => 'São Paulo'];
    return [
        'id' => $id,
        'nome' => $nome,
        'cnpj' => '',
        'cidade' => $cidade,
        'uf' => 'MG',
        'endereco' => $endereco,
        'bairro' => '',
        'cep' => $cep,
        'servicos' => array_map(static fn($c) => $nomesAtividades[$c], $atividades),
        'atividadesCodigos' => $atividades,
        'regioesMunicipios' => array_map(static fn($c) => "$c - {$nomesRegioes[$c]}", $regioes),
        'regioesMunicipiosCodigos' => $regioes,
        'estadosAtuacao' => array_map(static fn($c) => "$c - {$nomesEstados[$c]}", $estados),
        'estadosAtuacaoCodigos' => $estados,
        'status' => $status,
        'dataConvenio' => '2020-05-11',
        'numeroContrato' => (string) (9000 + $ordem),
        'ordemCredenciamento' => $ordem,
        'atendeAcima100km' => $acima100,
        'telefone' => sprintf('(00) 0000-%04d', $ordem),
        'email' => 'contato' . $ordem . '@exemplo.com.br',
        'responsavel' => '',
        'observacoes' => 'Empresa fictícia de exemplo',
        'latitude' => null,
        'longitude' => null,
    ];
};

return [
    $empresa('EMP-001', 'Alfa Engenharia de Avaliações Ltda', 'Belo Horizonte', 'Av. Afonso Pena, 1000, Centro, Belo Horizonte/MG', '30130-001', ['A', 'B', 'F'], ['I', 'II'], [], 1),
    $empresa('EMP-002', 'Beta Perícias e Laudos Ltda', 'Belo Horizonte', 'Rua da Bahia, 500, Centro, Belo Horizonte/MG', '30160-011', ['A', 'D', 'F'], ['I'], [], 2, false),
    $empresa('EMP-003', 'Gama Topografia e Geo Ltda', 'Contagem', 'Av. João César de Oliveira, 200, Contagem/MG', '32040-000', ['C', 'D', 'E'], ['I', 'VIII'], [], 3),
    $empresa('EMP-004', 'Delta Engenharia Mineira S.A.', 'Juiz de Fora', 'Rua Halfeld, 300, Centro, Juiz de Fora/MG', '36010-000', ['A', 'B', 'C', 'F'], ['II', 'III'], ['XXV'], 4),
    $empresa('EMP-005', 'Épsilon Avaliações Imobiliárias Ltda', 'Uberlândia', 'Av. Rondon Pacheco, 400, Uberlândia/MG', '38400-000', ['A', 'B', 'E', 'F'], ['IV'], ['XXVII'], 5),
    $empresa('EMP-006', 'Zeta Vistorias Técnicas Ltda', 'Pouso Alegre', 'Av. Dr. Lisboa, 150, Pouso Alegre/MG', '37550-000', ['A', 'F'], ['III'], [], 6, true, 'inativa'),
];
