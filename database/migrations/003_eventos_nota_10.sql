
USE `agenda_personal`;

ALTER TABLE `eventos_academicos`
    DROP CONSTRAINT `chk_eventos_nota`;

ALTER TABLE `eventos_academicos`
    ADD CONSTRAINT `chk_eventos_nota`
        CHECK (
            `nota` IS NULL
            OR (`nota` >= 0 AND `nota` <= 10)
        );

INSERT INTO `configuracion` (`clave`, `valor`)
VALUES ('schema_version', '3')
ON DUPLICATE KEY UPDATE
    `valor` = VALUES(`valor`);

