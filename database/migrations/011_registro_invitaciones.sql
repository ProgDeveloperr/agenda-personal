-- R9-R1A: candidato, NO ejecutar en produccion.
CREATE TABLE invitaciones_registro (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    creado_por_user_id BIGINT UNSIGNED NOT NULL,
    utilizado_por_user_id BIGINT UNSIGNED NULL,
    vence_at DATETIME NOT NULL,
    utilizado_at DATETIME NULL,
    revocada_at DATETIME NULL,
    revocada_por_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_reg_token_hash (token_hash),
    KEY idx_inv_reg_vencimiento (vence_at, utilizado_at),
    KEY idx_inv_reg_creador (creado_por_user_id),
    KEY idx_inv_reg_creador_tiempo (creado_por_user_id, created_at),
    KEY idx_inv_reg_utilizado (utilizado_por_user_id),
    KEY idx_inv_reg_revocador (revocada_por_user_id),
    CONSTRAINT fk_inv_reg_creador
        FOREIGN KEY (creado_por_user_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inv_reg_utilizado
        FOREIGN KEY (utilizado_por_user_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inv_reg_revocador
        FOREIGN KEY (revocada_por_user_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- R9-R2D2: control atomico de intentos de registro.
CREATE TABLE registro_rate_limits (
    bucket_hash CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin NOT NULL,
    ventana_slot BIGINT UNSIGNED NOT NULL,
    intentos INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket_hash),
    KEY idx_r9_rate_window (ventana_slot)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- schema_version=11 se aplicara solo tras validar el canary.
