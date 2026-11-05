<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';


function listarEventos(
    int $userId
): array {
    $stmt = db()->prepare("
        SELECT
            e.id,
            e.materia_id,
            e.titulo,
            e.tipo,
            e.fecha,
            e.hora,
            e.lugar,
            e.descripcion,
            e.estado,
            e.nota,
            e.created_at,
            e.updated_at,

            m.nombre AS materia_nombre,
            m.codigo AS materia_codigo,
            m.color AS materia_color,
            m.activa AS materia_activa

        FROM eventos_academicos e

        INNER JOIN materias m
            ON m.id = e.materia_id
           AND m.user_id = e.user_id

        WHERE e.user_id = :user_id

        ORDER BY
            e.fecha ASC,
            e.hora IS NULL,
            e.hora ASC,
            e.id ASC
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return $stmt->fetchAll();
}


function buscarEvento(
    int $userId,
    int $id
): ?array {
    $stmt = db()->prepare("
        SELECT
            id,
            materia_id,
            titulo,
            tipo,
            fecha,
            hora,
            lugar,
            descripcion,
            estado,
            nota
        FROM eventos_academicos
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);

    $evento = $stmt->fetch();

    return $evento !== false
        ? $evento
        : null;
}


function crearEvento(
    int $userId,
    int $materiaId,
    string $titulo,
    string $tipo,
    string $fecha,
    ?string $hora,
    ?string $lugar,
    ?string $descripcion,
    string $estado,
    ?float $nota
): int {
    $stmt = db()->prepare("
        INSERT INTO eventos_academicos (
            user_id,
            materia_id,
            titulo,
            tipo,
            fecha,
            hora,
            lugar,
            descripcion,
            estado,
            nota
        )
        SELECT
            :event_user_id,
            m.id,
            :titulo,
            :tipo,
            :fecha,
            :hora,
            :lugar,
            :descripcion,
            :estado,
            :nota
        FROM materias m
        WHERE m.id = :materia_id
          AND m.user_id = :materia_user_id
        LIMIT 1
    ");

    $stmt->execute([
        'event_user_id' => $userId,
        'materia_id' => $materiaId,
        'materia_user_id' => $userId,
        'titulo' => $titulo,
        'tipo' => $tipo,
        'fecha' => $fecha,
        'hora' => $hora,
        'lugar' => $lugar,
        'descripcion' => $descripcion,
        'estado' => $estado,
        'nota' => $nota,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'La materia indicada no pertenece al usuario actual.'
        );
    }

    return (int) db()->lastInsertId();
}


function actualizarEvento(
    int $userId,
    int $id,
    int $materiaId,
    string $titulo,
    string $tipo,
    string $fecha,
    ?string $hora,
    ?string $lugar,
    ?string $descripcion,
    string $estado,
    ?float $nota
): bool {
    $stmt = db()->prepare("
        UPDATE eventos_academicos e

        INNER JOIN materias m
            ON m.id = :materia_id
           AND m.user_id = :materia_user_id

        SET
            e.materia_id = m.id,
            e.titulo = :titulo,
            e.tipo = :tipo,
            e.fecha = :fecha,
            e.hora = :hora,
            e.lugar = :lugar,
            e.descripcion = :descripcion,
            e.estado = :estado,
            e.nota = :nota

        WHERE e.id = :id
          AND e.user_id = :event_user_id
    ");

    $stmt->execute([
        'id' => $id,
        'event_user_id' => $userId,
        'materia_id' => $materiaId,
        'materia_user_id' => $userId,
        'titulo' => $titulo,
        'tipo' => $tipo,
        'fecha' => $fecha,
        'hora' => $hora,
        'lugar' => $lugar,
        'descripcion' => $descripcion,
        'estado' => $estado,
        'nota' => $nota,
    ]);

    if ($stmt->rowCount() === 1) {
        return true;
    }

    /*
     * MySQL may report rowCount()=0 for a valid no-op UPDATE.
     * Verify ownership + same-tenant materia before returning true.
     */
    $verify = db()->prepare("
        SELECT 1
        FROM eventos_academicos e

        INNER JOIN materias m
            ON m.id = :materia_id
           AND m.user_id = :materia_user_id

        WHERE e.id = :id
          AND e.user_id = :event_user_id

        LIMIT 1
    ");

    $verify->execute([
        'id' => $id,
        'event_user_id' => $userId,
        'materia_id' => $materiaId,
        'materia_user_id' => $userId,
    ]);

    return $verify->fetchColumn() !== false;
}


function contarEventosPendientes(
    int $userId
): int {
    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM eventos_academicos
        WHERE user_id = :user_id
          AND estado = 'PENDIENTE'
          AND fecha >= CURRENT_DATE
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return (int) $stmt->fetchColumn();
}


function proximoEvento(
    int $userId
): ?array {
    $stmt = db()->prepare("
        SELECT
            e.id,
            e.titulo,
            e.tipo,
            e.fecha,
            e.hora,

            m.nombre AS materia_nombre,
            m.codigo AS materia_codigo,
            m.color AS materia_color

        FROM eventos_academicos e

        INNER JOIN materias m
            ON m.id = e.materia_id
           AND m.user_id = e.user_id

        WHERE e.user_id = :user_id
          AND e.estado = 'PENDIENTE'
          AND e.fecha >= CURRENT_DATE

        ORDER BY
            e.fecha ASC,
            e.hora IS NULL,
            e.hora ASC,
            e.id ASC

        LIMIT 1
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    $evento = $stmt->fetch();

    return $evento !== false
        ? $evento
        : null;
}


function listarEventosPorRango(
    int $userId,
    string $desde,
    string $hasta
): array {
    $stmt = db()->prepare("
        SELECT
            e.id,
            e.materia_id,
            e.titulo,
            e.tipo,
            e.fecha,
            e.hora,
            e.lugar,
            e.descripcion,
            e.estado,
            e.nota,

            m.nombre AS materia_nombre,
            m.codigo AS materia_codigo,
            m.color AS materia_color

        FROM eventos_academicos e

        INNER JOIN materias m
            ON m.id = e.materia_id
           AND m.user_id = e.user_id

        WHERE e.user_id = :user_id
          AND e.fecha >= :desde
          AND e.fecha < :hasta

        ORDER BY
            e.fecha ASC,
            e.hora IS NULL,
            e.hora ASC,
            e.id ASC
    ");

    $stmt->execute([
        'user_id' => $userId,
        'desde' => $desde,
        'hasta' => $hasta,
    ]);

    return $stmt->fetchAll();
}


function listarEventosPorMateria(
    int $userId,
    int $materiaId
): array {
    $stmt = db()->prepare("
        SELECT
            e.id,
            e.materia_id,
            e.titulo,
            e.tipo,
            e.fecha,
            e.hora,
            e.lugar,
            e.descripcion,
            e.estado,
            e.nota,
            e.created_at,
            e.updated_at,

            m.nombre AS materia_nombre,
            m.codigo AS materia_codigo,
            m.color AS materia_color,
            m.activa AS materia_activa

        FROM eventos_academicos e

        INNER JOIN materias m
            ON m.id = e.materia_id
           AND m.user_id = e.user_id

        WHERE e.user_id = :user_id
          AND e.materia_id = :materia_id

        ORDER BY
            e.fecha ASC,
            e.hora IS NULL,
            e.hora ASC,
            e.id ASC
    ");

    $stmt->execute([
        'user_id' => $userId,
        'materia_id' => $materiaId,
    ]);

    return $stmt->fetchAll();
}
