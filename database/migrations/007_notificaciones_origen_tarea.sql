USE `agenda_personal`;


ALTER TABLE `notificaciones`

    MODIFY COLUMN `evento_id`
        BIGINT UNSIGNED
        NULL,

    ADD COLUMN `origen`
        ENUM(
            'EVENTO',
            'TAREA'
        )
        NOT NULL
        DEFAULT 'EVENTO'
        AFTER `id`,

    ADD COLUMN `tarea_id`
        BIGINT UNSIGNED
        NULL
        AFTER `evento_id`,

    ADD KEY `idx_notificaciones_tarea`
        (`tarea_id`),

    ADD KEY `idx_notificaciones_origen_fecha`
        (`origen`, `fecha_objetivo`),

    ADD CONSTRAINT `fk_notificaciones_tarea`
        FOREIGN KEY (`tarea_id`)
        REFERENCES `tareas` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE;


UPDATE `notificaciones`

SET
    `origen`='EVENTO',
    `tarea_id`=NULL

WHERE
    `evento_id` IS NOT NULL;


UPDATE `configuracion`

SET
    `valor`='7'

WHERE
    `clave`='schema_version';
