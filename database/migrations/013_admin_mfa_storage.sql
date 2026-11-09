-- Agenda Personal: almacenamiento MFA.
-- Migracion aditiva. No activa MFA.
-- Schema funcional v11 permanece hasta el corte MFA.

CREATE TABLE usuario_mfa (
    user_id BIGINT UNSIGNED NOT NULL,
    secret_nonce BINARY(24) NOT NULL,
    secret_cipher VARBINARY(160) NOT NULL,
    enabled_at DATETIME NULL,
    last_used_step BIGINT NOT NULL DEFAULT -1,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id),

    CONSTRAINT fk_usuario_mfa_usuario
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_mfa_recuperacion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    code_hash CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_mfa_recovery_user_code
        (user_id, code_hash),

    KEY idx_mfa_recovery_unused
        (user_id, used_at),

    CONSTRAINT fk_mfa_recovery_usuario
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
