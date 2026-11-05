-- R7-R3B2D1 CANDIDATE ONLY; NOT DEPLOYED.
-- Requires an approved maintenance window.
-- The six ALTER statements are frozen R7-R1 DDL.
-- ============================================================================
-- AGENDA PERSONAL — R7 SCHEMA HARDENING
-- INERT PROPOSAL ONLY
-- DO NOT EXECUTE ON PRODUCTION FROM THIS EVIDENCE DIRECTORY.
-- ============================================================================

-- --------------------------------------------------------------------------
-- 1. materias
-- --------------------------------------------------------------------------

ALTER TABLE materias
    MODIFY COLUMN user_id
        BIGINT UNSIGNED NOT NULL,
    DROP INDEX uq_materias_codigo,
    ADD UNIQUE KEY uq_materias_user_codigo
        (user_id, codigo),
    ADD CONSTRAINT fk_materias_user
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;


-- --------------------------------------------------------------------------
-- 2. eventos_academicos
-- --------------------------------------------------------------------------

ALTER TABLE eventos_academicos
    MODIFY COLUMN user_id
        BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_eventos_user
        (user_id),
    ADD CONSTRAINT fk_eventos_user
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;


-- --------------------------------------------------------------------------
-- 3. series_tareas
-- --------------------------------------------------------------------------

ALTER TABLE series_tareas
    MODIFY COLUMN user_id
        BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_series_tareas_user
        (user_id),
    ADD CONSTRAINT fk_series_tareas_user
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;


-- --------------------------------------------------------------------------
-- 4. tareas
-- --------------------------------------------------------------------------

ALTER TABLE tareas
    MODIFY COLUMN user_id
        BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_tareas_user
        (user_id),
    ADD CONSTRAINT fk_tareas_user
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;


-- --------------------------------------------------------------------------
-- 5. notificaciones
--
-- uq_notificaciones_clave(clave_unica) is intentionally preserved.
-- --------------------------------------------------------------------------

ALTER TABLE notificaciones
    MODIFY COLUMN user_id
        BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_notificaciones_user
        (user_id),
    ADD CONSTRAINT fk_notificaciones_user
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;


-- --------------------------------------------------------------------------
-- 6. calendario_vida
-- --------------------------------------------------------------------------

ALTER TABLE calendario_vida
    MODIFY COLUMN user_id
        BIGINT UNSIGNED NOT NULL,
    DROP INDEX uq_calendario_indice_mes,
    ADD UNIQUE KEY uq_calendario_user_indice
        (user_id, indice_mes),
    ADD CONSTRAINT fk_calendario_user
        FOREIGN KEY (user_id)
        REFERENCES usuarios(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;


-- ============================================================================
-- INTENTIONALLY UNCHANGED
-- ============================================================================
--
-- notificaciones:
--     uq_notificaciones_clave(clave_unica)
--
-- tareas:
--     uq_tareas_serie_abierta(serie_id, abierta_flag)
--     uq_tareas_serie_ocurrencia(serie_id, fecha_ocurrencia)
--
-- existing functional relation FKs:
--     unchanged
--
-- preferencias_usuario:
--     already strict and already FK-backed
--
-- configuracion:
--     no structural change in this DDL proposal
--
-- schema_version:
--     migration artifact/version update is resolved separately before
--     productive deployment.
-- ============================================================================

-- Deployment runner must verify version 9 before execution.
UPDATE `configuracion` SET `valor`='10' WHERE `clave`='schema_version' AND `valor`='9';
