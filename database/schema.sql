-- Agenda Personal: v10 canonical structural candidate.
-- Staging only; NOT installed and NOT a v9 upgrade script.
-- No user/password/fecha_nacimiento records are inserted.

/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `agenda_personal` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `agenda_personal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `calendario_vida` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `indice_mes` smallint(5) unsigned NOT NULL,
  `nivel` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `nota` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calendario_user_indice` (`user_id`,`indice_mes`),
  CONSTRAINT `fk_calendario_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_calendario_indice` CHECK (`indice_mes` between 0 and 1079),
  CONSTRAINT `chk_calendario_nivel` CHECK (`nivel` between 0 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` varchar(80) NOT NULL,
  `valor` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `eventos_academicos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `materia_id` int(10) unsigned NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `tipo` enum('PARCIAL','FINAL','RECUPERATORIO','TP','ENTREGA','OTRO') NOT NULL DEFAULT 'OTRO',
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `lugar` varchar(160) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('PENDIENTE','ENTREGADO','APROBADO','DESAPROBADO','AUSENTE','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  `nota` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eventos_materia` (`materia_id`),
  KEY `idx_eventos_fecha` (`fecha`),
  KEY `idx_eventos_estado` (`estado`),
  KEY `idx_eventos_tipo` (`tipo`),
  KEY `idx_eventos_fecha_estado` (`fecha`,`estado`),
  KEY `idx_eventos_user` (`user_id`),
  CONSTRAINT `fk_eventos_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_eventos_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_eventos_nota` CHECK (`nota` is null or `nota` >= 0 and `nota` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `materias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `cuatrimestre` tinyint(3) unsigned DEFAULT NULL,
  `turno` enum('MANANA','TARDE','NOCHE','OTRO') DEFAULT NULL,
  `comision` varchar(30) DEFAULT NULL,
  `docente` varchar(150) DEFAULT NULL,
  `color` char(7) NOT NULL DEFAULT '#39d353',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_materias_user_codigo` (`user_id`,`codigo`),
  KEY `idx_materias_nombre` (`nombre`),
  KEY `idx_materias_activa` (`activa`),
  KEY `idx_materias_cuatrimestre` (`cuatrimestre`),
  KEY `idx_materias_turno` (`turno`),
  CONSTRAINT `fk_materias_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_materias_color` CHECK (`color` regexp '^#[0-9A-Fa-f]{6}$'),
  CONSTRAINT `chk_materias_cuatrimestre` CHECK (`cuatrimestre` is null or `cuatrimestre` between 1 and 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `origen` enum('EVENTO','TAREA') NOT NULL DEFAULT 'EVENTO',
  `evento_id` bigint(20) unsigned DEFAULT NULL,
  `tarea_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(32) NOT NULL,
  `clave_unica` varchar(120) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `fecha_objetivo` date NOT NULL,
  `leida_at` datetime DEFAULT NULL,
  `descartada_at` datetime DEFAULT NULL,
  `resuelta_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notificaciones_clave` (`clave_unica`),
  KEY `idx_notificaciones_evento` (`evento_id`),
  KEY `idx_notificaciones_fecha` (`fecha_objetivo`),
  KEY `idx_notificaciones_bandeja` (`resuelta_at`,`descartada_at`,`leida_at`,`created_at`),
  KEY `idx_notificaciones_tarea` (`tarea_id`),
  KEY `idx_notificaciones_origen_fecha` (`origen`,`fecha_objetivo`),
  KEY `idx_notificaciones_user` (`user_id`),
  CONSTRAINT `fk_notificaciones_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos_academicos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notificaciones_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notificaciones_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `preferencias_usuario` (
  `user_id` bigint(20) unsigned NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `horizonte_vida_anios` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_preferencias_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `series_tareas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `materia_id` int(10) unsigned DEFAULT NULL,
  `evento_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `prioridad` enum('BAJA','MEDIA','ALTA','URGENTE') NOT NULL DEFAULT 'MEDIA',
  `frecuencia` enum('DIARIA','SEMANAL','MENSUAL') NOT NULL,
  `intervalo` smallint(5) unsigned NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `hora_limite` time DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_series_tareas_materia` (`materia_id`),
  KEY `idx_series_tareas_evento` (`evento_id`),
  KEY `idx_series_tareas_activa` (`activa`),
  KEY `idx_series_tareas_ventana` (`activa`,`fecha_inicio`,`fecha_fin`),
  KEY `idx_series_tareas_user` (`user_id`),
  CONSTRAINT `fk_series_tareas_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos_academicos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_series_tareas_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_series_tareas_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_series_tareas_titulo` CHECK (char_length(trim(`titulo`)) > 0),
  CONSTRAINT `chk_series_tareas_intervalo` CHECK (`intervalo` between 1 and 365),
  CONSTRAINT `chk_series_tareas_fechas` CHECK (`fecha_fin` is null or `fecha_fin` >= `fecha_inicio`),
  CONSTRAINT `chk_series_tareas_activa` CHECK (`activa` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tareas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `materia_id` int(10) unsigned DEFAULT NULL,
  `evento_id` bigint(20) unsigned DEFAULT NULL,
  `serie_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `prioridad` enum('BAJA','MEDIA','ALTA','URGENTE') NOT NULL DEFAULT 'MEDIA',
  `estado` enum('PENDIENTE','EN_PROGRESO','COMPLETADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_limite` date DEFAULT NULL,
  `hora_limite` time DEFAULT NULL,
  `fecha_ocurrencia` date DEFAULT NULL,
  `completada_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `abierta_flag` tinyint(3) unsigned GENERATED ALWAYS AS (case when `estado` in ('PENDIENTE','EN_PROGRESO') then 1 else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tareas_serie_ocurrencia` (`serie_id`,`fecha_ocurrencia`),
  UNIQUE KEY `uq_tareas_serie_abierta` (`serie_id`,`abierta_flag`),
  KEY `idx_tareas_materia` (`materia_id`),
  KEY `idx_tareas_evento` (`evento_id`),
  KEY `idx_tareas_estado` (`estado`),
  KEY `idx_tareas_prioridad` (`prioridad`),
  KEY `idx_tareas_fecha_limite` (`fecha_limite`),
  KEY `idx_tareas_estado_fecha` (`estado`,`fecha_limite`),
  KEY `idx_tareas_serie` (`serie_id`),
  KEY `idx_tareas_serie_fecha` (`serie_id`,`fecha_ocurrencia`),
  KEY `idx_tareas_user` (`user_id`),
  CONSTRAINT `fk_tareas_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos_academicos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tareas_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tareas_serie` FOREIGN KEY (`serie_id`) REFERENCES `series_tareas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tareas_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_tareas_titulo` CHECK (char_length(trim(`titulo`)) > 0),
  CONSTRAINT `chk_tareas_hora_fecha` CHECK (`hora_limite` is null or `fecha_limite` is not null),
  CONSTRAINT `chk_tareas_completada` CHECK (`estado` = 'COMPLETADA' and `completada_at` is not null or `estado` <> 'COMPLETADA' and `completada_at` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('ADMIN','USER') NOT NULL DEFAULT 'USER',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_username` (`username`),
  KEY `idx_usuarios_activo` (`activo`),
  KEY `idx_usuarios_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Only generic bootstrap configuration; no personal data.
INSERT IGNORE INTO `configuracion` (`clave`, `valor`) VALUES
  ('schema_version', '10'),
  ('horizonte_vida_anios', '90');
