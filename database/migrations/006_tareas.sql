CREATE TABLE `tareas` (
    `id`
        BIGINT UNSIGNED
        NOT NULL
        AUTO_INCREMENT,

    `materia_id`
        INT UNSIGNED
        NULL,

    `evento_id`
        BIGINT UNSIGNED
        NULL,

    `titulo`
        VARCHAR(180)
        NOT NULL,

    `descripcion`
        TEXT
        NULL,

    `prioridad`
        ENUM(
            'BAJA',
            'MEDIA',
            'ALTA',
            'URGENTE'
        )
        NOT NULL
        DEFAULT 'MEDIA',

    `estado`
        ENUM(
            'PENDIENTE',
            'EN_PROGRESO',
            'COMPLETADA',
            'CANCELADA'
        )
        NOT NULL
        DEFAULT 'PENDIENTE',

    `fecha_limite`
        DATE
        NULL,

    `hora_limite`
        TIME
        NULL,

    `completada_at`
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

    KEY `idx_tareas_materia`
        (`materia_id`),

    KEY `idx_tareas_evento`
        (`evento_id`),

    KEY `idx_tareas_estado`
        (`estado`),

    KEY `idx_tareas_prioridad`
        (`prioridad`),

    KEY `idx_tareas_fecha_limite`
        (`fecha_limite`),

    KEY `idx_tareas_estado_fecha`
        (`estado`, `fecha_limite`),

    CONSTRAINT `fk_tareas_materia`
        FOREIGN KEY (`materia_id`)
        REFERENCES `materias` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `fk_tareas_evento`
        FOREIGN KEY (`evento_id`)
        REFERENCES `eventos_academicos` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `chk_tareas_titulo`
        CHECK (
            CHAR_LENGTH(
                TRIM(`titulo`)
            ) > 0
        ),

    CONSTRAINT `chk_tareas_hora_fecha`
        CHECK (
            `hora_limite` IS NULL
            OR `fecha_limite` IS NOT NULL
        ),

    CONSTRAINT `chk_tareas_completada`
        CHECK (
            (
                `estado` = 'COMPLETADA'
                AND `completada_at`
                    IS NOT NULL
            )
            OR
            (
                `estado` <> 'COMPLETADA'
                AND `completada_at`
                    IS NULL
            )
        )
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


INSERT INTO `configuracion` (
    `clave`,
    `valor`
)
VALUES (
    'schema_version',
    '6'
)
ON DUPLICATE KEY UPDATE
    `valor` = VALUES(`valor`);
