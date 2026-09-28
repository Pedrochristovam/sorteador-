-- =============================================================================
-- Módulo Jurídico — Credenciamento Edital 01/2026 (Sociedades de Advogados)
-- Banco: sorteador_convenios (mesmo sistema, tabelas isoladas)
-- =============================================================================

USE sorteador_convenios;

-- Catálogo de atividades jurídicas (serviços do Anexo / TR)
CREATE TABLE IF NOT EXISTS juridico_atividades_catalogo (
  codigo VARCHAR(20) PRIMARY KEY,
  nome VARCHAR(180) NOT NULL,
  descricao VARCHAR(400) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Polos / comarcas / municípios de interesse (Anexo X)
CREATE TABLE IF NOT EXISTS juridico_polos_catalogo (
  codigo VARCHAR(40) PRIMARY KEY,
  nome VARCHAR(180) NOT NULL,
  tipo ENUM('comarca','municipio','regiao','polo') NOT NULL DEFAULT 'comarca',
  uf CHAR(2) NOT NULL DEFAULT 'MG',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_jur_polo_tipo (tipo),
  INDEX idx_jur_polo_uf (uf)
) ENGINE=InnoDB;

-- Critérios de elegibilidade do módulo jurídico
CREATE TABLE IF NOT EXISTS juridico_criterios (
  codigo VARCHAR(60) PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  descricao VARCHAR(500) NOT NULL DEFAULT '',
  obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
  tipo VARCHAR(40) NOT NULL DEFAULT 'boolean',
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Sociedades de Advogados credenciadas (Edital 01/2026)
CREATE TABLE IF NOT EXISTS sociedades_advogados (
  id VARCHAR(40) PRIMARY KEY,
  razao_social VARCHAR(255) NOT NULL,
  nome_fantasia VARCHAR(255) NOT NULL DEFAULT '',
  cnpj VARCHAR(20) NOT NULL DEFAULT '',
  oab_sociedade VARCHAR(60) NOT NULL DEFAULT '',
  cidade_sede VARCHAR(120) NOT NULL,
  uf_sede CHAR(2) NOT NULL DEFAULT 'MG',
  endereco VARCHAR(255) NOT NULL DEFAULT '',
  bairro VARCHAR(120) NOT NULL DEFAULT '',
  cep VARCHAR(20) NOT NULL DEFAULT '',
  telefone VARCHAR(180) NULL,
  email VARCHAR(180) NULL,
  responsavel VARCHAR(160) NULL,
  oab_responsavel VARCHAR(60) NOT NULL DEFAULT '',
  status ENUM('ativa','inativa','suspensa') NOT NULL DEFAULT 'ativa',
  -- Apto a sorteio | Indisponível (demanda em andamento) | Contemplado recente
  status_operacional ENUM('apto','indisponivel_demanda','contemplado') NOT NULL DEFAULT 'apto',
  data_credenciamento DATE NOT NULL,
  numero_contrato VARCHAR(60) NOT NULL DEFAULT '',
  ordem_credenciamento INT NOT NULL DEFAULT 0,
  processo_sei VARCHAR(80) NOT NULL DEFAULT '',
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_soc_status (status),
  INDEX idx_soc_operacional (status_operacional),
  INDEX idx_soc_cidade (cidade_sede),
  INDEX idx_soc_nome (razao_social),
  INDEX idx_soc_cnpj (cnpj)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sociedade_atividades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sociedade_id VARCHAR(40) NOT NULL,
  codigo_atividade VARCHAR(20) NOT NULL,
  CONSTRAINT fk_soc_ativ_soc FOREIGN KEY (sociedade_id) REFERENCES sociedades_advogados(id) ON DELETE CASCADE,
  CONSTRAINT fk_soc_ativ_cat FOREIGN KEY (codigo_atividade) REFERENCES juridico_atividades_catalogo(codigo),
  UNIQUE KEY uq_soc_ativ (sociedade_id, codigo_atividade),
  INDEX idx_soc_ativ_codigo (codigo_atividade)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sociedade_polos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sociedade_id VARCHAR(40) NOT NULL,
  codigo_polo VARCHAR(40) NOT NULL,
  CONSTRAINT fk_soc_polo_soc FOREIGN KEY (sociedade_id) REFERENCES sociedades_advogados(id) ON DELETE CASCADE,
  CONSTRAINT fk_soc_polo_cat FOREIGN KEY (codigo_polo) REFERENCES juridico_polos_catalogo(codigo),
  UNIQUE KEY uq_soc_polo (sociedade_id, codigo_polo),
  INDEX idx_soc_polo_codigo (codigo_polo)
) ENGINE=InnoDB;

-- Demandas / OS em andamento (bloqueia participação no sorteio)
CREATE TABLE IF NOT EXISTS juridico_demandas_ativas (
  id VARCHAR(40) PRIMARY KEY,
  sociedade_id VARCHAR(40) NOT NULL,
  processo_sei VARCHAR(80) NOT NULL DEFAULT '',
  objeto VARCHAR(255) NOT NULL,
  atividade_codigo VARCHAR(20) NULL,
  polo_codigo VARCHAR(40) NULL,
  setor_demandante VARCHAR(80) NOT NULL DEFAULT '',
  status ENUM('em_andamento','aguardando_aceite','concluida','cancelada') NOT NULL DEFAULT 'em_andamento',
  data_inicio DATETIME NOT NULL,
  data_conclusao DATETIME NULL,
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_jur_dem_soc FOREIGN KEY (sociedade_id) REFERENCES sociedades_advogados(id) ON DELETE CASCADE,
  INDEX idx_jur_dem_status (status),
  INDEX idx_jur_dem_soc (sociedade_id)
) ENGINE=InnoDB;

-- Sorteios do módulo jurídico
CREATE TABLE IF NOT EXISTS juridico_sorteios (
  id VARCHAR(40) PRIMARY KEY,
  data_hora DATETIME NOT NULL,
  status ENUM('agendado','concluido','cancelado','aguardando_aceite') NOT NULL DEFAULT 'concluido',
  usuario_id VARCHAR(40) NULL,
  usuario_nome VARCHAR(160) NULL,
  processo_sei VARCHAR(80) NOT NULL DEFAULT '',
  setor_demandante VARCHAR(80) NOT NULL DEFAULT '',
  demanda_objeto VARCHAR(255) NOT NULL DEFAULT '',
  demanda_atividade VARCHAR(120) NOT NULL DEFAULT '',
  demanda_polo VARCHAR(120) NOT NULL DEFAULT '',
  demanda_json JSON NULL,
  criterios_json JSON NULL,
  amostra_json JSON NOT NULL,
  excluidos_demanda_json JSON NULL,
  participantes_json JSON NOT NULL,
  sociedade_selecionada_id VARCHAR(40) NULL,
  sociedade_selecionada_json JSON NULL,
  total_analisadas INT NOT NULL DEFAULT 0,
  total_excluidos_demanda INT NOT NULL DEFAULT 0,
  total_eliminadas INT NOT NULL DEFAULT 0,
  total_elegiveis INT NOT NULL DEFAULT 0,
  modo_sorteio VARCHAR(40) NOT NULL DEFAULT 'secure_random',
  hash_integridade VARCHAR(128) NULL,
  seed_referencia VARCHAR(128) NULL,
  aceite_prazo_dias INT NOT NULL DEFAULT 5,
  aceite_status ENUM('pendente','aceito','recusado','expirado','nao_aplicavel') NOT NULL DEFAULT 'nao_aplicavel',
  aceite_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_jur_srt_data (data_hora),
  INDEX idx_jur_srt_status (status),
  CONSTRAINT fk_jur_srt_soc FOREIGN KEY (sociedade_selecionada_id) REFERENCES sociedades_advogados(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS juridico_auditorias (
  id VARCHAR(40) PRIMARY KEY,
  sorteio_id VARCHAR(40) NOT NULL,
  data_hora DATETIME NOT NULL,
  usuario_id VARCHAR(40) NULL,
  usuario_nome VARCHAR(160) NULL,
  demanda_json JSON NULL,
  criterios_json JSON NULL,
  analisadas_json JSON NULL,
  eliminadas_json JSON NULL,
  excluidos_demanda_json JSON NULL,
  elegiveis_json JSON NULL,
  participantes_json JSON NULL,
  resultado_final_json JSON NULL,
  eventos_json JSON NULL,
  hash_operacao VARCHAR(128) NULL,
  seed_referencia VARCHAR(128) NULL,
  versao_algoritmo VARCHAR(40) NOT NULL DEFAULT 'juridico-v1',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_jur_aud_srt (sorteio_id),
  INDEX idx_jur_aud_data (data_hora),
  CONSTRAINT fk_jur_aud_srt FOREIGN KEY (sorteio_id) REFERENCES juridico_sorteios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seeds — atividades jurídicas (exemplos alinhados a serviços sob demanda)
INSERT INTO juridico_atividades_catalogo (codigo, nome, descricao) VALUES
('JUR-CONT', 'Contencioso judicial', 'Atuação contenciosa em processos judiciais'),
('JUR-CONS', 'Consultivo', 'Pareceres e consultoria jurídica'),
('JUR-EXTRA', 'Extrajudicial', 'Atuação extrajudicial e administrativos'),
('JUR-TRAB', 'Trabalhista', 'Demandas trabalhistas'),
('JUR-CIV', 'Cível', 'Demandas cíveis em geral'),
('JUR-ADM', 'Administrativo', 'Direito administrativo e regulatório'),
('JUR-TRIB', 'Tributário', 'Demandas tributárias'),
('JUR-REC', 'Recuperação / Falência', 'Recuperação judicial e falência')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Seeds — polos / comarcas MG (amostra; expandível via Anexo X)
INSERT INTO juridico_polos_catalogo (codigo, nome, tipo, uf) VALUES
('COM-BH', 'Comarca de Belo Horizonte', 'comarca', 'MG'),
('COM-UBERLANDIA', 'Comarca de Uberlândia', 'comarca', 'MG'),
('COM-JUIZ-DE-FORA', 'Comarca de Juiz de Fora', 'comarca', 'MG'),
('COM-MONTES-CLAROS', 'Comarca de Montes Claros', 'comarca', 'MG'),
('COM-UBERABA', 'Comarca de Uberaba', 'comarca', 'MG'),
('COM-GOVERNADOR-VALADARES', 'Comarca de Governador Valadares', 'comarca', 'MG'),
('COM-POUSO-ALEGRE', 'Comarca de Pouso Alegre', 'comarca', 'MG'),
('COM-DIVINOPOLIS', 'Comarca de Divinópolis', 'comarca', 'MG'),
('COM-IPATINGA', 'Comarca de Ipatinga', 'comarca', 'MG'),
('COM-SETES-LAGOAS', 'Comarca de Sete Lagoas', 'comarca', 'MG'),
('COM-VARGINHA', 'Comarca de Varginha', 'comarca', 'MG'),
('COM-PATOS-DE-MINAS', 'Comarca de Patos de Minas', 'comarca', 'MG'),
('POLO-CENTRAL', 'Polo Central (MG)', 'polo', 'MG'),
('POLO-SUL', 'Polo Sul de Minas', 'polo', 'MG'),
('POLO-TRIANGULO', 'Polo Triângulo Mineiro', 'polo', 'MG'),
('POLO-NORTE', 'Polo Norte de Minas', 'polo', 'MG'),
('POLO-ZONA-MATA', 'Polo Zona da Mata', 'polo', 'MG')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO juridico_criterios (codigo, nome, descricao, obrigatorio, tipo) VALUES
('atividade_compativel', 'Atividade jurídica compatível', 'A sociedade deve ter optado pela atividade demandada na Ficha de Opção.', 1, 'boolean'),
('polo_abrangencia', 'Polo / comarca de abrangência', 'A sociedade deve ter indicado o polo/comarca da demanda no Anexo X.', 1, 'boolean'),
('credenciamento_ativo', 'Credenciamento ativo', 'Somente sociedades ativas e contratadas participam.', 1, 'boolean'),
('sem_demanda_aberta', 'Sem demanda em andamento', 'Sociedade com OS/processo sob seus cuidados fica temporariamente fora da amostra.', 1, 'boolean'),
('documentacao_regular', 'Documentação regular', 'Sociedade sem pendências cadastrais ou documentais.', 1, 'boolean')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);
