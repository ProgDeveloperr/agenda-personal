
USE `agenda_personal`;

ALTER TABLE `materias`
    DROP CONSTRAINT `chk_materias_anio`;

ALTER TABLE `materias`
    DROP COLUMN `anio_carrera`,

    ADD COLUMN `cuatrimestre`
        TINYINT UNSIGNED NULL
        AFTER `codigo`,

    ADD COLUMN `turno`
        ENUM(
            'MANANA',
            'TARDE',
            'NOCHE',
            'OTRO'
        )
        NULL
        AFTER `cuatrimestre`,

    ADD COLUMN `comision`
        VARCHAR(30) NULL
        AFTER `turno`,

    ADD COLUMN `docente`
        VARCHAR(150) NULL
        AFTER `comision`,

    ADD KEY `idx_materias_cuatrimestre`
        (`cuatrimestre`),

    ADD KEY `idx_materias_turno`
        (`turno`),

    ADD CONSTRAINT `chk_materias_cuatrimestre`
        CHECK (
            `cuatrimestre` IS NULL
            OR `cuatrimestre` BETWEEN 1 AND 10
        );

INSERT INTO `configuracion` (`clave`, `valor`)
VALUES ('schema_version', '2')
ON DUPLICATE KEY UPDATE
    `valor` = VALUES(`valor`);

