-- Esquema inicial do SCA (MySQL 8+). Não insere dados de demonstração.

CREATE TABLE unidade (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unidade_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidade_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    email VARCHAR(254) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_email (email),
    KEY idx_usuario_unidade_ativo (unidade_id, ativo),
    CONSTRAINT fk_usuario_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissao (
    id VARCHAR(60) PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    grupo ENUM('operacao','cadastro','gestao','consulta','administracao') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_permissao (
    usuario_id BIGINT UNSIGNED NOT NULL,
    permissao_id VARCHAR(60) NOT NULL,
    concedido_por BIGINT UNSIGNED NULL,
    concedido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, permissao_id),
    CONSTRAINT fk_usuario_permissao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_permissao_permissao FOREIGN KEY (permissao_id) REFERENCES permissao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_permissao_concedido_por FOREIGN KEY (concedido_por) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_permissao_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    permissao_id VARCHAR(60) NOT NULL,
    acao ENUM('concedida','revogada') NOT NULL,
    realizado_por BIGINT UNSIGNED NULL,
    realizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_permissao_historico_usuario (usuario_id, realizado_em),
    CONSTRAINT fk_permissao_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_permissao_historico_permissao FOREIGN KEY (permissao_id) REFERENCES permissao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_permissao_historico_autor FOREIGN KEY (realizado_por) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracao_catalogo (
    chave VARCHAR(80) PRIMARY KEY,
    nome VARCHAR(140) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    tipo_valor ENUM('opcao','inteiro','decimal','booleano','texto') NOT NULL,
    valor_padrao VARCHAR(255) NULL,
    valores_permitidos JSON NULL,
    editavel_pela_unidade TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracao_unidade (
    unidade_id BIGINT UNSIGNED NOT NULL,
    chave VARCHAR(80) NOT NULL,
    valor VARCHAR(255) NOT NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (unidade_id, chave),
    CONSTRAINT fk_config_unidade_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_config_unidade_chave FOREIGN KEY (chave) REFERENCES configuracao_catalogo(chave)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_config_unidade_usuario FOREIGN KEY (atualizado_por) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fornecedor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidade_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(220) NOT NULL,
    cpf_cnpj VARCHAR(20) NULL,
    telefone VARCHAR(40) NULL,
    email VARCHAR(254) NULL,
    prazo_reposicao_dias SMALLINT UNSIGNED NULL,
    agricultura_familiar TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fornecedor_documento_unidade (unidade_id, cpf_cnpj),
    KEY idx_fornecedor_unidade_ativo (unidade_id, ativo),
    CONSTRAINT fk_fornecedor_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    janela_alerta_validade_dias SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    validade_padrao_dias SMALLINT UNSIGNED NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produto (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidade_id BIGINT UNSIGNED NOT NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    unidade_medida ENUM('kg','L','un','pct') NOT NULL,
    aceita_fracao TINYINT(1) NOT NULL DEFAULT 0,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    grau_processamento ENUM('in_natura_minimamente_processado','ingrediente_culinario','processado','ultraprocessado','nao_classificado') NOT NULL DEFAULT 'nao_classificado',
    vedado_pnae TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    arquivado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_produto_unidade_ativo (unidade_id, arquivado_em),
    KEY idx_produto_categoria (categoria_id),
    CONSTRAINT fk_produto_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id) REFERENCES categoria(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_produto_estoque_minimo CHECK (estoque_minimo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regra_pnae (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vigente_desde DATE NOT NULL,
    vigente_ate DATE NULL,
    min_in_natura DECIMAL(5,2) NOT NULL,
    max_proc_ultra DECIMAL(5,2) NOT NULL,
    max_ingr_culinario DECIMAL(5,2) NOT NULL,
    min_agric_familiar DECIMAL(5,2) NOT NULL,
    referencia_normativa VARCHAR(500) NOT NULL,
    UNIQUE KEY uq_regra_pnae_vigencia_inicio (vigente_desde),
    KEY idx_regra_pnae_vigencia (vigente_desde, vigente_ate),
    CONSTRAINT chk_regra_pnae_vigencia CHECK (vigente_ate IS NULL OR vigente_ate >= vigente_desde),
    CONSTRAINT chk_regra_pnae_percentuais CHECK (
        min_in_natura BETWEEN 0 AND 100 AND max_proc_ultra BETWEEN 0 AND 100
        AND max_ingr_culinario BETWEEN 0 AND 100 AND min_agric_familiar BETWEEN 0 AND 100
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documento_fiscal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidade_id BIGINT UNSIGNED NOT NULL,
    fornecedor_id BIGINT UNSIGNED NULL,
    tipo ENUM('nota_fiscal_eletronica','nota_produtor_rural','termo_doacao','outro') NOT NULL,
    numero VARCHAR(80) NULL,
    serie VARCHAR(40) NULL,
    chave_acesso VARCHAR(60) NULL,
    emitido_em DATE NULL,
    valor_total DECIMAL(13,2) NULL,
    doador_nome VARCHAR(220) NULL,
    destinatario_conferido TINYINT(1) NOT NULL DEFAULT 0,
    identificada_pnae TINYINT(1) NOT NULL DEFAULT 0,
    anexo VARCHAR(500) NULL,
    status ENUM('ativo','cancelado') NOT NULL DEFAULT 'ativo',
    substitui_id BIGINT UNSIGNED NULL,
    cancelado_por BIGINT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    motivo_cancelamento VARCHAR(1000) NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_documento_unidade_emissao (unidade_id, emitido_em),
    UNIQUE KEY uq_documento_fornecedor_numero (unidade_id, fornecedor_id, tipo, numero, serie),
    UNIQUE KEY uq_documento_chave_acesso (chave_acesso),
    CONSTRAINT fk_documento_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedor(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_substitui FOREIGN KEY (substitui_id) REFERENCES documento_fiscal(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_usuario FOREIGN KEY (registrado_por) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documento_cancelado_por FOREIGN KEY (cancelado_por) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_documento_valor CHECK (valor_total IS NULL OR valor_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidade_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('ENTRADA','SAIDA','AJUSTE','ESTORNO') NOT NULL,
    motivo VARCHAR(60) NOT NULL,
    documento_fiscal_id BIGINT UNSIGNED NULL,
    estorna_operacao_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    observacao TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_operacao_estorno (estorna_operacao_id),
    KEY idx_operacao_unidade_data (unidade_id, criado_em),
    CONSTRAINT fk_operacao_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_operacao_documento FOREIGN KEY (documento_fiscal_id) REFERENCES documento_fiscal(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_operacao_estorna FOREIGN KEY (estorna_operacao_id) REFERENCES operacao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_operacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lote (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id BIGINT UNSIGNED NOT NULL,
    documento_fiscal_id BIGINT UNSIGNED NULL,
    fornecedor_id BIGINT UNSIGNED NULL,
    origem_recurso ENUM('pnae_federal','recurso_proprio','doacao') NOT NULL,
    codigo_lote VARCHAR(100) NULL,
    validade DATE NOT NULL,
    validade_estimada TINYINT(1) NOT NULL DEFAULT 0,
    valor_item DECIMAL(13,2) NULL,
    recebido_em DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lote_produto_validade (produto_id, validade),
    KEY idx_lote_documento (documento_fiscal_id),
    KEY idx_lote_recurso_data (origem_recurso, recebido_em),
    CONSTRAINT fk_lote_produto FOREIGN KEY (produto_id) REFERENCES produto(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lote_documento FOREIGN KEY (documento_fiscal_id) REFERENCES documento_fiscal(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lote_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedor(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_lote_valor CHECK (valor_item IS NULL OR valor_item >= 0),
    CONSTRAINT chk_lote_doacao_sem_valor CHECK (origem_recurso <> 'doacao' OR valor_item IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movimentacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operacao_id BIGINT UNSIGNED NOT NULL,
    lote_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('ENTRADA','SAIDA','AJUSTE','ESTORNO') NOT NULL,
    motivo VARCHAR(60) NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estorno_id BIGINT UNSIGNED NULL,
    observacao TEXT NULL,
    UNIQUE KEY uq_movimentacao_estorno (estorno_id),
    KEY idx_movimentacao_lote_data (lote_id, criado_em),
    KEY idx_movimentacao_operacao (operacao_id),
    CONSTRAINT fk_movimentacao_operacao FOREIGN KEY (operacao_id) REFERENCES operacao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_movimentacao_lote FOREIGN KEY (lote_id) REFERENCES lote(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_movimentacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_movimentacao_estorno FOREIGN KEY (estorno_id) REFERENCES movimentacao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_movimentacao_quantidade CHECK (quantidade <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pendencia_revisao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operacao_id BIGINT UNSIGNED NOT NULL,
    movimentacao_id BIGINT UNSIGNED NULL,
    observacao TEXT NOT NULL,
    status ENUM('aberta','revisada') NOT NULL DEFAULT 'aberta',
    revisado_por BIGINT UNSIGNED NULL,
    revisado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pendencia_status_data (status, criado_em),
    CONSTRAINT fk_pendencia_operacao FOREIGN KEY (operacao_id) REFERENCES operacao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pendencia_movimentacao FOREIGN KEY (movimentacao_id) REFERENCES movimentacao(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pendencia_revisor FOREIGN KEY (revisado_por) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
