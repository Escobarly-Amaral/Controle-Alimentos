-- Migração aditiva do esquema anterior para os campos usados pelas telas atuais.
-- Não apaga tabelas nem registros. Pode ser executada mais de uma vez.

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='categoria' AND COLUMN_NAME='ativo')=0,
    'ALTER TABLE categoria ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fornecedor' AND COLUMN_NAME='cpf_cnpj')=0,
    'ALTER TABLE fornecedor ADD COLUMN cpf_cnpj VARCHAR(20) NULL AFTER nome',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

-- Preserva o valor da coluna antiga usada pelo cadastro de fornecedor.
SET @sca_copy_cnpj = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fornecedor' AND COLUMN_NAME='cnpj')>0,
    'UPDATE fornecedor SET cpf_cnpj=NULLIF(cnpj,'''') WHERE cpf_cnpj IS NULL',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_copy_cnpj; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

-- Corrige índice legado que acidentalmente permitia apenas um fornecedor por unidade.
SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fornecedor' AND INDEX_NAME='uq_fornecedor_unidade_cnpj')>0,
    'ALTER TABLE fornecedor DROP INDEX uq_fornecedor_unidade_cnpj',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fornecedor' AND INDEX_NAME='uq_fornecedor_documento_unidade')=0,
    'ALTER TABLE fornecedor ADD UNIQUE KEY uq_fornecedor_documento_unidade (unidade_id,cpf_cnpj)',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documento_fiscal' AND COLUMN_NAME='cancelado_por')=0,
    'ALTER TABLE documento_fiscal ADD COLUMN cancelado_por BIGINT UNSIGNED NULL AFTER substitui_id',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documento_fiscal' AND COLUMN_NAME='cancelado_em')=0,
    'ALTER TABLE documento_fiscal ADD COLUMN cancelado_em DATETIME NULL AFTER cancelado_por',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documento_fiscal' AND COLUMN_NAME='motivo_cancelamento')=0,
    'ALTER TABLE documento_fiscal ADD COLUMN motivo_cancelamento VARCHAR(1000) NULL AFTER cancelado_em',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_documento_cancelado_por')=0,
    'ALTER TABLE documento_fiscal ADD CONSTRAINT fk_documento_cancelado_por FOREIGN KEY (cancelado_por) REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

CREATE TABLE IF NOT EXISTS operacao (
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
    CONSTRAINT fk_operacao_unidade FOREIGN KEY (unidade_id) REFERENCES unidade(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_operacao_documento FOREIGN KEY (documento_fiscal_id) REFERENCES documento_fiscal(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_operacao_estorna FOREIGN KEY (estorna_operacao_id) REFERENCES operacao(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_operacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_permissao_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    permissao_id VARCHAR(60) NOT NULL,
    acao ENUM('concedida','revogada') NOT NULL,
    realizado_por BIGINT UNSIGNED NULL,
    realizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_permissao_historico_usuario (usuario_id, realizado_em),
    CONSTRAINT fk_permissao_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_permissao_historico_permissao FOREIGN KEY (permissao_id) REFERENCES permissao(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_permissao_historico_autor FOREIGN KEY (realizado_por) REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendencia_revisao' AND COLUMN_NAME='operacao_id')=0,
    'ALTER TABLE pendencia_revisao ADD COLUMN operacao_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_backfill = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendencia_revisao' AND COLUMN_NAME='movimentacao_id')>0,
    'UPDATE pendencia_revisao pr JOIN movimentacao m ON m.id=pr.movimentacao_id SET pr.operacao_id=m.operacao_id WHERE pr.operacao_id IS NULL',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_backfill; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendencia_revisao' AND INDEX_NAME='idx_pendencia_operacao')=0,
    'ALTER TABLE pendencia_revisao ADD KEY idx_pendencia_operacao (operacao_id)',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;

SET @sca_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_pendencia_operacao')=0,
    'ALTER TABLE pendencia_revisao ADD CONSTRAINT fk_pendencia_operacao FOREIGN KEY (operacao_id) REFERENCES operacao(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE sca_stmt FROM @sca_ddl; EXECUTE sca_stmt; DEALLOCATE PREPARE sca_stmt;
