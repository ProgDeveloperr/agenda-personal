CREATE TABLE IF NOT EXISTS `notificaciones` (

    `id`
        BIGINT UNSIGNED
        NOT NULL
        AUTO_INCREMENT,

    `evento_id`
        BIGINT UNSIGNED
        NOT NULL,

    `tipo`
        VARCHAR(32)
        NOT NULL,

    `clave_unica`
        VARCHAR(120)
        NOT NULL,

    `titulo`
        VARCHAR(180)
        NOT NULL,

    `mensaje`
        VARCHAR(500)
        NOT NULL,

    `fecha_objetivo`
        DATE
        NOT NULL,

    `leida_at`
        DATETIME
        NULL,

    `descartada_at`
        DATETIME
        NULL,

    `resuelta_at`
        DATETIME
        NULL,

    `created_at`
        TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    `updated_at`
        TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_notificaciones_clave`
        (`clave_unica`),

    KEY `idx_notificaciones_evento`
        (`evento_id`),

    KEY `idx_notificaciones_fecha`
        (`fecha_objetivo`),

    KEY `idx_notificaciones_bandeja`
        (
            `resuelta_at`,
            `descartada_at`,
            `leida_at`,
            `created_at`
        ),

    CONSTRAINT `fk_notificaciones_evento`

        FOREIGN KEY (`evento_id`)

        REFERENCES `eventos_academicos`
            (`id`)

        ON DELETE CASCADE
        ON UPDATE CASCADE

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


INSERT INTO `configuracion`
    (`clave`, `valor`)

VALUES
    ('schema_version', '5')

ON DUPLICATE KEY UPDATE

    `valor` = VALUES(`valor`);
