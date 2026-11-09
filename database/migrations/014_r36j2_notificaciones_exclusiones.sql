-- R36-J2: huellas de avisos eliminados; sin titulos ni mensajes.
-- No se modifica configuracion.schema_version (=11).
CREATE TABLE `notificaciones_exclusiones_r36j2` (
 `user_id` bigint(20) unsigned NOT NULL,
 `clave_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(`user_id`,`clave_hash`),
 CONSTRAINT `fk_r36j2_exclusiones_user` FOREIGN KEY(`user_id`)
 REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
