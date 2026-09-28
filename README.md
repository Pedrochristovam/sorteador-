# Sorteador de Convênios

Sistema web corporativo para seleção transparente de empresas conveniadas.

## Stack

- PHP 8.1+
- HTML / CSS / JavaScript
- Lucide Icons
- MySQL 8 (opcional — sem as tabelas, os dados ficam em JSON em `storage/`)
- Rotas de carro: OpenStreetMap/OSRM ou Google Maps (chave no `.env`)

## Como executar

Na pasta do projeto:

```bash
php -S localhost:8080
```

Acesse: http://localhost:8080

## Banco de dados

1. Copie `.env.example` para `.env` e preencha usuário/senha do MySQL.
2. Crie as tabelas e importe os dados:

```bash
php database/migrar_para_mysql.php
php database/migrate_juridico.php
```

3. Confira: `php database/ver_banco.php`

## Base de empresas

A base real de credenciadas (com contatos) fica em `mocks/empresas.php`, **fora do Git**.
Sem ela, o sistema usa `mocks/empresas.exemplo.php` (empresas fictícias).
Para gerar a base real a partir da planilha: `python scripts/import_credenciamento.py`.

## Estrutura

```
config/       Configuração da aplicação
layouts/      Layout administrativo (sidebar)
pages/        Telas do sistema
api/          Endpoints JSON do fluxo de sorteio
services/     CompanyService, EligibilityService, DrawService, AuditService
types/        Tipagens / entidades PHP
mocks/        Dados fictícios
assets/       CSS e JS
utils/        Helpers
storage/      Persistência local de sessão (sorteios/auditorias gerados)
```

## Fluxo principal

1. Dashboard
2. Empresas Conveniadas (busca, filtros, detalhes)
3. Novo Sorteio → Demanda → Elegíveis → Preparação → Execução → Resultado
4. Histórico
5. Auditoria

## DrawService

A seleção de empresas é isolada em `services/DrawService.php`.  
Não utiliza `Math.random()` / `mt_rand()` como solução definitiva.  
Modos previstos em `config/app.php`:

- `mock` — determinístico para demonstração
- `secure_random` — `random_int()` (CSPRNG)
- `auditable` — stub para mecanismo auditável futuro

## Testes

```bash
php database/testar_fluxo_engenharia.php
```

## Próximas integrações (preparadas na arquitetura)

- Autenticação
- Mecanismo de sorteio auditável
- Publicação de resultado institucional
