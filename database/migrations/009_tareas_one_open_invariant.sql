USE `agenda_personal`;

-- R4-D5-C-R2
-- Unica ocurrencia abierta por serie.
-- serie_id se mantiene fuera de la expresion generada.

ALTER TABLE `tareas`

    ADD COLUMN `abierta_flag`
        TINYINT UNSIGNED

        GENERATED ALWAYS AS (

            CASE

                WHEN `estado` IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )

                THEN 1

                ELSE NULL

            END

        ) PERSISTENT,

    ADD UNIQUE KEY `uq_tareas_serie_abierta` (
        `serie_id`,
        `abierta_flag`
    );


UPDATE `configuracion`

SET `valor` = '9'

WHERE
    `clave` = 'schema_version'
    AND `valor` = '8';
