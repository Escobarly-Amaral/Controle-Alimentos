CREATE TABLE senha_redefinicao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expira_em DATETIME NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado_em DATETIME NULL,
    invalidado_em DATETIME NULL,
    UNIQUE KEY uq_senha_redefinicao_token (token_hash),
    KEY idx_senha_redefinicao_usuario (usuario_id, criado_em),
    KEY idx_senha_redefinicao_expiracao (expira_em),
    CONSTRAINT fk_senha_redefinicao_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
