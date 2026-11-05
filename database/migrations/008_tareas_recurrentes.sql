USE `agenda_personal`;


CREATE TABLE `series_tareas` (

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

    `frecuencia`
        ENUM(
            'DIARIA',
            'SEMANAL',
            'MENSUAL'
        )
        NOT NULL,

    `intervalo`
        SMALLINT UNSIGNED
        NOT NULL
        DEFAULT 1,

    `fecha_inicio`
        DATE
        NOT NULL,

    `fecha_fin`
        DATE
        NULL,

    `hora_limite`
        TIME
        NULL,

    `activa`
        TINYINT(1)
        NOT NULL
        DEFAULT 1,

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

    KEY `idx_series_tareas_materia`
        (`materia_id`),

    KEY `idx_series_tareas_evento`
        (`evento_id`),

    KEY `idx_series_tareas_activa`
        (`activa`),

    KEY `idx_series_tareas_ventana`
        (`activa`, `fecha_inicio`, `fecha_fin`),

    CONSTRAINT `fk_series_tareas_materia`
        FOREIGN KEY (`materia_id`)
        REFERENCES `materias` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `fk_series_tareas_evento`
        FOREIGN KEY (`evento_id`)
        REFERENCES `eventos_academicos` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `chk_series_tareas_titulo`
        CHECK (
            CHAR_LENGTH(
                TRIM(`titulo`)
            ) > 0
        ),

    CONSTRAINT `chk_series_tareas_intervalo`
        CHECK (
            `intervalo`
            BETWEEN 1 AND 365
        ),

    CONSTRAINT `chk_series_tareas_fechas`
        CHECK (
            `fecha_fin` IS NULL
            OR `fecha_fin` >= `fecha_inicio`
        ),

    CONSTRAINT `chk_series_tareas_activa`
        CHECK (
            `activa` IN (0, 1)
        )

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `tareas`

    ADD COLUMN `serie_id`
        BIGINT UNSIGNED
        NULL
        AFTER `evento_id`,

    ADD COLUMN `fecha_ocurrencia`
        DATE
        NULL
        AFTER `hora_limite`,

    ADD KEY `idx_tareas_serie`
        (`serie_id`),

    ADD KEY `idx_tareas_serie_fecha`
        (`serie_id`, `fecha_ocurrencia`),

    ADD UNIQUE KEY `uq_tareas_serie_ocurrencia`
        (`serie_id`, `fecha_ocurrencia`),

    ADD CONSTRAINT `fk_tareas_serie`
        FOREIGN KEY (`serie_id`)
        REFERENCES `series_tareas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;


UPDATE `configuracion`

SET
    `valor`='8'

WHERE
    `clave`='schema_version';
