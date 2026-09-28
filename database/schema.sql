-- Sorteador de Convênios — schema MySQL 8
-- Banco completo para empresas, sorteios, auditoria e catálogos

CREATE DATABASE IF NOT EXISTS sorteador_convenios
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sorteador_convenios;

-- Usuários (preparado para autenticação futura)
CREATE TABLE IF NOT EXISTS usuarios (
  id VARCHAR(40) PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NULL,
  perfil ENUM('administrador','operador','auditor','visualizador') NOT NULL DEFAULT 'operador',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Catálogo de atividades (A–F)
CREATE TABLE IF NOT EXISTS atividades_catalogo (
  codigo VARCHAR(10) PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao VARCHAR(255) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Regiões de municípios (I–X) e estados (XI–XXXV)
CREATE TABLE IF NOT EXISTS regioes_catalogo (
  codigo VARCHAR(10) PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  tipo ENUM('municipio_mg','estado') NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Critérios de elegibilidade
CREATE TABLE IF NOT EXISTS criterios (
  codigo VARCHAR(60) PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  descricao VARCHAR(500) NOT NULL DEFAULT '',
  obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
  tipo VARCHAR(40) NOT NULL DEFAULT 'boolean',
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Empresas credenciadas
CREATE TABLE IF NOT EXISTS empresas (
  id VARCHAR(40) PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  cnpj VARCHAR(20) NOT NULL DEFAULT '',
  cidade VARCHAR(120) NOT NULL,
  uf CHAR(2) NOT NULL DEFAULT 'MG',
  endereco VARCHAR(255) NOT NULL,
  bairro VARCHAR(120) NOT NULL DEFAULT '',
  cep VARCHAR(20) NOT NULL DEFAULT '',
  status ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
  data_convenio DATE NOT NULL,
  numero_contrato VARCHAR(60) NOT NULL DEFAULT '',
  ordem_credenciamento INT NOT NULL DEFAULT 0,
  -- 1 = atende acima de 100 km (ida e volta); 0 = somente até 100 km (50 ida + 50 volta)
  atende_acima_100km TINYINT(1) NOT NULL DEFAULT 1,
  telefone VARCHAR(180) NULL,
  email VARCHAR(180) NULL,
  responsavel VARCHAR(160) NULL,
  observacoes TEXT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_empresas_cidade (cidade),
  INDEX idx_empresas_status (status),
  INDEX idx_empresas_nome (nome)
) ENGINE=InnoDB;

-- Serviços/atividades da empresa (N:N lógico em linhas)
CREATE TABLE IF NOT EXISTS empresa_servicos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id VARCHAR(40) NOT NULL,
  servico VARCHAR(120) NOT NULL,
  codigo_atividade VARCHAR(10) NULL,
  CONSTRAINT fk_emp_serv_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
  INDEX idx_emp_serv_empresa (empresa_id),
  INDEX idx_emp_serv_servico (servico)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS empresa_regioes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empresa_id VARCHAR(40) NOT NULL,
  codigo_regiao VARCHAR(10) NOT NULL,
  tipo ENUM('municipio_mg','estado') NOT NULL,
  CONSTRAINT fk_emp_reg_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
  INDEX idx_emp_reg_empresa (empresa_id),
  INDEX idx_emp_reg_codigo (codigo_regiao)
) ENGINE=InnoDB;

-- Sorteios
CREATE TABLE IF NOT EXISTS sorteios (
  id VARCHAR(40) PRIMARY KEY,
  data_hora DATETIME NOT NULL,
  status ENUM('concluido','cancelado','em_andamento','aguardando_orcamento') NOT NULL DEFAULT 'concluido',
  usuario_id VARCHAR(40) NULL,
  usuario_nome VARCHAR(160) NULL,
  demanda_cidade VARCHAR(120) NOT NULL,
  demanda_endereco VARCHAR(255) NOT NULL,
  demanda_tipo VARCHAR(120) NOT NULL DEFAULT '',
  demanda_servico VARCHAR(120) NOT NULL,
  demanda_raio_km DECIMAL(8,2) NULL DEFAULT 50,
  demanda_json JSON NULL,
  criterios_json JSON NULL,
  participantes_json JSON NOT NULL,
  empresa_selecionada_id VARCHAR(40) NULL,
  empresa_selecionada_json JSON NULL,
  total_analisadas INT NOT NULL DEFAULT 0,
  total_eliminadas INT NOT NULL DEFAULT 0,
  total_elegiveis INT NOT NULL DEFAULT 0,
  modo_sorteio VARCHAR(40) NOT NULL DEFAULT 'mock',
  hash_integridade VARCHAR(128) NULL,
  seed_referencia VARCHAR(128) NULL,
  -- modalidade, sorteadas para orçamento, orçamentos, ranking por distância e IDs do rodízio
  extras_json JSON NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sorteios_data (data_hora),
  INDEX idx_sorteios_status (status),
  CONSTRAINT fk_sorteio_empresa FOREIGN KEY (empresa_selecionada_id) REFERENCES empresas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Logs de auditoria
CREATE TABLE IF NOT EXISTS auditorias (
  id VARCHAR(40) PRIMARY KEY,
  sorteio_id VARCHAR(40) NOT NULL,
  data_hora DATETIME NOT NULL,
  usuario_id VARCHAR(40) NULL,
  usuario_nome VARCHAR(160) NULL,
  demanda_json JSON NULL,
  criterios_json JSON NULL,
  empresas_analisadas_json JSON NULL,
  empresas_eliminadas_json JSON NULL,
  empresas_elegiveis_json JSON NULL,
  empresas_participantes_json JSON NULL,
  resultado_final_json JSON NULL,
  eventos_json JSON NULL,
  hash_operacao VARCHAR(128) NULL,
  seed_referencia VARCHAR(128) NULL,
  versao_algoritmo VARCHAR(40) NOT NULL DEFAULT 'mock-v1',
  -- modalidade, sorteadas, ranking, rodízio, distância, orçamentos e log de execução
  extras_json JSON NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_aud_sorteio (sorteio_id),
  INDEX idx_aud_data (data_hora),
  CONSTRAINT fk_aud_sorteio FOREIGN KEY (sorteio_id) REFERENCES sorteios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seeds iniciais
INSERT INTO usuarios (id, nome, email, senha_hash, perfil, ativo) VALUES
('USR-001', 'Operador Demo', 'operador@empresa.local', NULL, 'administrador', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO atividades_catalogo (codigo, nome) VALUES
('A', 'Laudo Simplificado'),
('B', 'Laudo Completo'),
('C', 'Laudo Rural'),
('D', 'Levantamento Topográfico'),
('E', 'Georreferenciamento'),
('F', 'Vistoria de Obra')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO regioes_catalogo (codigo, nome, tipo) VALUES
('I', 'Central (MG)', 'municipio_mg'),
('II', 'Zona da Mata (MG)', 'municipio_mg'),
('III', 'Sul de Minas', 'municipio_mg'),
('IV', 'Triângulo (MG)', 'municipio_mg'),
('V', 'Alto Paranaíba (MG)', 'municipio_mg'),
('VI', 'Centro Oeste (MG)', 'municipio_mg'),
('VII', 'Noroeste de Minas', 'municipio_mg'),
('VIII', 'Norte de Minas', 'municipio_mg'),
('IX', 'Jequitinhonha/Mucuri', 'municipio_mg'),
('X', 'Rio Doce (MG)', 'municipio_mg'),
('XI', 'Acre', 'estado'),
('XII', 'Amazonas', 'estado'),
('XIII', 'Rondônia', 'estado'),
('XIV', 'Amapá', 'estado'),
('XV', 'Pará', 'estado'),
('XVI', 'Maranhão', 'estado'),
('XVII', 'Piauí', 'estado'),
('XVIII', 'Ceará', 'estado'),
('XIX', 'Rio Grande do Norte', 'estado'),
('XX', 'Paraíba', 'estado'),
('XXI', 'Pernambuco', 'estado'),
('XXII', 'Alagoas', 'estado'),
('XXIII', 'Sergipe', 'estado'),
('XXIV', 'Bahia', 'estado'),
('XXV', 'Espírito Santo', 'estado'),
('XXVI', 'Rio de Janeiro', 'estado'),
('XXVII', 'São Paulo', 'estado'),
('XXVIII', 'Paraná', 'estado'),
('XXIX', 'Santa Catarina', 'estado'),
('XXX', 'Rio Grande do Sul', 'estado'),
('XXXI', 'Mato Grosso do Sul', 'estado'),
('XXXII', 'Mato Grosso', 'estado'),
('XXXIII', 'Goiás/Distrito Federal', 'estado'),
('XXXIV', 'Tocantins', 'estado'),
('XXXV', 'Roraima', 'estado')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO criterios (codigo, nome, descricao, obrigatorio, tipo) VALUES
('servico_compativel', 'Atividade habilitada', 'A empresa deve estar habilitada em ao menos uma das atividades solicitadas na demanda.', 1, 'boolean'),
('convenio_ativo', 'Credenciado ativo', 'Somente empresas com credenciamento ativo participam do sorteio.', 1, 'boolean'),
('localizacao', 'Atende a região da demanda', 'A empresa deve ter no cadastro a região (ou estado) onde fica a demanda. A distância não elimina quem atende a região.', 1, 'region'),
('limite_100km', 'Limite de deslocamento (100 km ida e volta)', 'Empresa cadastrada para atender só até 100 km (50 km ida + 50 km volta) sai quando a rota de carro até a demanda passa desse limite.', 1, 'distance'),
('documentacao_regular', 'Documentação regular', 'Empresa sem pendências cadastrais ou documentais.', 1, 'boolean')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), tipo = VALUES(tipo);

DELETE FROM criterios WHERE codigo = 'mesma_cidade';

-- =============================================================================
-- Módulo Jurídico (Edital 01/2026): ver também database/schema_juridico.sql
-- Aplicar com: php database/migrate_juridico.php
-- =============================================================================
