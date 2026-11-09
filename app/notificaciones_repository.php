<?php

declare(strict_types=1);

require_once '/var/www/app/database.php';


function condicionFuenteNotificacionSql(): string
{
    return "
        (
            n.origen='EVENTO'
            AND n.evento_id IS NOT NULL
            AND n.tarea_id IS NULL
        )
        OR
        (
            n.origen='TAREA'
            AND n.tarea_id IS NOT NULL
            AND n.evento_id IS NULL
        )
    ";
}


function selectBaseNotificacionesSql(): string
{
    return "
        SELECT
            n.id,
            n.origen,
            n.evento_id,
            n.tarea_id,
            n.tipo,
            n.clave_unica,
            n.titulo,
            n.mensaje,
            n.fecha_objetivo,
            n.leida_at,
            n.descartada_at,
            n.resuelta_at,
            n.created_at,
            n.updated_at,

            e.materia_id
                AS evento_materia_id,

            e.titulo
                AS evento_titulo,

            e.tipo
                AS evento_tipo,

            e.estado
                AS evento_estado,

            e.fecha
                AS evento_fecha,

            e.hora
                AS evento_hora,

            t.materia_id
                AS tarea_materia_id,

            t.evento_id
                AS tarea_evento_id,

            t.titulo
                AS tarea_titulo,

            t.prioridad
                AS tarea_prioridad,

            t.estado
                AS tarea_estado,

            t.fecha_limite
                AS tarea_fecha_limite,

            t.hora_limite
                AS tarea_hora_limite,

            t.completada_at
                AS tarea_completada_at,

            te.titulo
                AS tarea_evento_titulo,

            te.fecha
                AS tarea_evento_fecha,

            te.hora
                AS tarea_evento_hora,

            m.id
                AS materia_id,

            m.codigo
                AS materia_codigo,

            m.nombre
                AS materia_nombre,

            m.color
                AS materia_color

        FROM notificaciones n

        LEFT JOIN eventos_academicos e
            ON e.id=n.evento_id
            AND e.user_id=n.user_id

        LEFT JOIN tareas t
            ON t.id=n.tarea_id
            AND t.user_id=n.user_id

        LEFT JOIN eventos_academicos te
            ON te.id=t.evento_id
            AND te.user_id=n.user_id

        LEFT JOIN materias m
            ON m.id=COALESCE(
                t.materia_id,
                e.materia_id,
                te.materia_id
            )
            AND m.user_id=n.user_id
    ";
}


function listarNotificacionesActivas(
    int $userId,
    int $limite = 6
): array {

    $limite =
        max(
            1,
            min(
                20,
                $limite
            )
        );


    $fuente =
        condicionFuenteNotificacionSql();


    $sql =
        selectBaseNotificacionesSql()
        . "
        WHERE
            n.user_id=:user_id
            AND
            n.resuelta_at IS NULL
            AND n.descartada_at IS NULL
            AND (
                {$fuente}
            )

        ORDER BY
            n.leida_at IS NULL DESC,
            n.created_at DESC,
            n.id DESC

        LIMIT :limite
        ";


    $stmt =
        db()->prepare(
            $sql
        );


    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );


    $stmt->bindValue(
        ':limite',
        $limite,
        PDO::PARAM_INT
    );


    $stmt->execute();


    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function contarNotificacionesActivas(int $userId): int
{
    $fuente =
        condicionFuenteNotificacionSql();


    $stmt =
        db()->prepare(

                "
                SELECT COUNT(*)

                FROM notificaciones n

                WHERE
                    n.user_id=:user_id
                    AND
                    n.resuelta_at IS NULL
                    AND n.descartada_at IS NULL
                    AND (
                        {$fuente}
                    )
                "
                    );


    $stmt->execute([
        'user_id' => $userId,
    ]);


    return
        (int) $stmt
            ->fetchColumn();
}


function contarNotificacionesNoLeidas(int $userId): int
{
    $fuente =
        condicionFuenteNotificacionSql();


    $stmt =
        db()->prepare(

                "
                SELECT COUNT(*)

                FROM notificaciones n

                WHERE
                    n.user_id=:user_id
                    AND
                    n.resuelta_at IS NULL
                    AND n.descartada_at IS NULL
                    AND n.leida_at IS NULL
                    AND (
                        {$fuente}
                    )
                "
                    );


    $stmt->execute([
        'user_id' => $userId,
    ]);


    return
        (int) $stmt
            ->fetchColumn();
}


function marcarNotificacionLeida(
    int $userId,
    int $id
): void {

    $stmt =
        db()->prepare(
            "
            UPDATE notificaciones

            SET
                leida_at =
                    COALESCE(
                        leida_at,
                        NOW()
                    )

            WHERE
                user_id=:user_id
                AND
                id=:id
                AND resuelta_at IS NULL
                AND descartada_at IS NULL
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'id' => $id,
    ]);
}


function marcarTodasNotificacionesLeidas(int $userId): int
{
    $fuente =
        condicionFuenteNotificacionSql();


    $stmt =
        db()->prepare(
            "
            UPDATE notificaciones n

            SET
                leida_at =
                    COALESCE(
                        leida_at,
                        NOW()
                    )

            WHERE
                n.user_id=:user_id
                AND
                n.resuelta_at IS NULL
                AND n.descartada_at IS NULL
                AND n.leida_at IS NULL
                AND (
                    {$fuente}
                )
            "
        );


    $stmt->execute([
        'user_id' => $userId,
    ]);


    return $stmt->rowCount();
}


function descartarNotificacion(
    int $userId,
    int $id
): void {

    $stmt =
        db()->prepare(
            "
            UPDATE notificaciones

            SET
                descartada_at =
                    COALESCE(
                        descartada_at,
                        NOW()
                    ),

                leida_at =
                    COALESCE(
                        leida_at,
                        NOW()
                    )

            WHERE
                user_id=:user_id
                AND
                id=:id
                AND resuelta_at IS NULL
                AND descartada_at IS NULL
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'id' => $id,
    ]);
}


function restaurarNotificacion(
    int $userId,
    int $id
): void {

    $stmt =
        db()->prepare(
            "
            UPDATE notificaciones

            SET
                descartada_at=NULL

            WHERE
                user_id=:user_id
                AND
                id=:id
                AND resuelta_at IS NULL
                AND descartada_at IS NOT NULL
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'id' => $id,
    ]);
}


function buscarNotificacionCentro(
    int $userId,
    int $id
): ?array {

    $fuente =
        condicionFuenteNotificacionSql();


    $stmt =
        db()->prepare(
            "
            SELECT
                n.id,
                n.origen,
                n.evento_id,
                n.tarea_id,
                n.leida_at,
                n.descartada_at,
                n.resuelta_at

            FROM notificaciones n

            WHERE
                n.user_id=:user_id
                AND
                n.id=:id
                AND (
                    {$fuente}
                )

            LIMIT 1
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'id' => $id,
    ]);


    $fila =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    return
        $fila !== false
            ? $fila
            : null;
}


function contarCentroNotificaciones(int $userId): array
{
    $fuente =
        condicionFuenteNotificacionSql();


    $stmt =
        db()->prepare(

            "
            SELECT
                COUNT(*) AS total,

                SUM(
                    n.resuelta_at IS NULL
                    AND n.descartada_at IS NULL
                ) AS activas,

                SUM(
                    n.resuelta_at IS NULL
                    AND n.descartada_at IS NULL
                    AND n.leida_at IS NULL
                ) AS no_leidas,

                SUM(
                    n.resuelta_at IS NULL
                    AND n.descartada_at IS NULL
                    AND n.leida_at IS NOT NULL
                ) AS leidas,

                SUM(
                    n.descartada_at IS NOT NULL
                ) AS descartadas,

                SUM(
                    n.resuelta_at IS NOT NULL
                ) AS resueltas

            FROM notificaciones n

            WHERE
                n.user_id=:user_id
                AND
(
                {$fuente}
            )
            "
                );


    $stmt->execute([
        'user_id' => $userId,
    ]);


    $fila =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    return [
        'total' =>
            (int) ($fila['total'] ?? 0),

        'activas' =>
            (int) ($fila['activas'] ?? 0),

        'no_leidas' =>
            (int) ($fila['no_leidas'] ?? 0),

        'leidas' =>
            (int) ($fila['leidas'] ?? 0),

        'descartadas' =>
            (int) ($fila['descartadas'] ?? 0),

        'resueltas' =>
            (int) ($fila['resueltas'] ?? 0),
    ];
}


function listarCentroNotificaciones(
    int $userId,
    string $filtro = 'todas',
    int $limite = 100
): array {

    $limite =
        max(
            1,
            min(
                300,
                $limite
            )
        );


    $where =
        match ($filtro) {

            'activas' =>
                "
                n.resuelta_at IS NULL
                AND n.descartada_at IS NULL
                ",

            'no-leidas' =>
                "
                n.resuelta_at IS NULL
                AND n.descartada_at IS NULL
                AND n.leida_at IS NULL
                ",

            'leidas' =>
                "
                n.resuelta_at IS NULL
                AND n.descartada_at IS NULL
                AND n.leida_at IS NOT NULL
                ",

            'descartadas' =>
                "
                n.descartada_at IS NOT NULL
                ",

            'resueltas' =>
                "
                n.resuelta_at IS NOT NULL
                ",

            default =>
                "1=1",
        };


    $fuente =
        condicionFuenteNotificacionSql();


    $sql =
        selectBaseNotificacionesSql()
        . "
        WHERE
            n.user_id=:user_id
            AND
            (
                {$where}
            )
            AND (
                {$fuente}
            )

        ORDER BY
            CASE
                WHEN
                    n.resuelta_at IS NULL
                    AND n.descartada_at IS NULL
                THEN 0

                WHEN
                    n.descartada_at IS NOT NULL
                THEN 1

                ELSE 2
            END,

            n.leida_at IS NULL DESC,
            n.created_at DESC,
            n.id DESC

        LIMIT :limite
        ";


    $stmt =
        db()->prepare(
            $sql
        );


    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );


    $stmt->bindValue(
        ':limite',
        $limite,
        PDO::PARAM_INT
    );


    $stmt->execute();


    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}

// ARK3360_R36J2_REPOSITORY_PERMANENT_DELETE
function r36j2Acquire(PDO $pdo): void {
    $s = $pdo->query("SELECT GET_LOCK('ark3360:r36j2:notificaciones', 30)");
    if ((int) $s->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo bloquear la bandeja. Reintentá.');
    }
}
function r36j2Release(PDO $pdo): void {
    $s = $pdo->query("SELECT RELEASE_LOCK('ark3360:r36j2:notificaciones')");
    if ((int) $s->fetchColumn() !== 1) {
        error_log('R36J2: fallo al liberar el bloqueo del centro.');
    }
}
function r36j2Exclude(PDO $pdo, int $uid, string $key): void {
    if ($key === '' || strlen($key) > 120) {
        throw new RuntimeException('Clave de notificación inválida.');
    }
    $s = $pdo->prepare(
        'INSERT IGNORE INTO notificaciones_exclusiones_r36j2(user_id,clave_hash) '
        . 'VALUES(:uid,:hash)'
    );
    $s->execute(['uid'=>$uid, 'hash'=>hash('sha256', $key)]);
}
function r36j2HistoryCount(int $uid): int {
    $s = db()->prepare('SELECT COUNT(*) FROM notificaciones WHERE user_id=:uid '
        . 'AND (resuelta_at IS NOT NULL OR descartada_at IS NOT NULL)');
    $s->execute(['uid'=>$uid]);
    return (int) $s->fetchColumn();
}
function r36j2DeleteOne(int $uid, int $id): void {
    if ($uid <= 0 || $id <= 0) throw new InvalidArgumentException('Identificador inválido.');
    $pdo = db();
    if ($pdo->inTransaction()) throw new RuntimeException('Transacción anidada no permitida.');
    r36j2Acquire($pdo);
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT clave_unica FROM notificaciones '
            . 'WHERE id=:id AND user_id=:uid FOR UPDATE');
        $s->execute(['id'=>$id, 'uid'=>$uid]);
        $key = $s->fetchColumn();
        if ($key === false) throw new InvalidArgumentException('Notificación inexistente o ajena.');
        r36j2Exclude($pdo, $uid, (string) $key);
        $d = $pdo->prepare('DELETE FROM notificaciones WHERE id=:id AND user_id=:uid');
        $d->execute(['id'=>$id, 'uid'=>$uid]);
        if ($d->rowCount() !== 1) throw new RuntimeException('Eliminación inconsistente.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally { r36j2Release($pdo); }
}
function r36j2ClearHistory(int $uid): int {
    if ($uid <= 0) throw new InvalidArgumentException('Usuario inválido.');
    $pdo = db();
    if ($pdo->inTransaction()) throw new RuntimeException('Transacción anidada no permitida.');
    r36j2Acquire($pdo);
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT id,clave_unica FROM notificaciones '
            . 'WHERE user_id=:uid AND (resuelta_at IS NOT NULL OR descartada_at IS NOT NULL) '
            . 'ORDER BY id FOR UPDATE');
        $s->execute(['uid'=>$uid]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 2000) throw new InvalidArgumentException('Historial demasiado grande; revisión requerida.');
        $d = $pdo->prepare('DELETE FROM notificaciones WHERE id=:id AND user_id=:uid '
            . 'AND (resuelta_at IS NOT NULL OR descartada_at IS NOT NULL)');
        foreach ($rows as $row) {
            r36j2Exclude($pdo, $uid, (string) $row['clave_unica']);
            $d->execute(['id'=>(int)$row['id'], 'uid'=>$uid]);
            if ($d->rowCount() !== 1) throw new RuntimeException('Limpieza inconsistente; revertida.');
        }
        $pdo->commit();
        return count($rows);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally { r36j2Release($pdo); }
}
