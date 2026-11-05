<?php

declare(strict_types=1);

require_once '/var/www/app/database.php';


date_default_timezone_set(
    'America/Argentina/Buenos_Aires'
);


/*
 * ==========================================================
 * Shared helpers
 * ==========================================================
 */


function etiquetaMateriaNotificacion(
    array $fila
): string {

    $codigo =
        trim(
            (string) (
                $fila['materia_codigo']
                ?? ''
            )
        );


    if ($codigo !== '') {
        return $codigo;
    }


    $nombre =
        trim(
            (string) (
                $fila['materia_nombre']
                ?? ''
            )
        );


    return
        $nombre !== ''
            ? $nombre
            : 'General';
}


function diasHastaNotificacion(
    ?string $fecha,
    DateTimeImmutable $hoy
): ?int {

    if (
        $fecha === null
        || trim($fecha) === ''
    ) {
        return null;
    }


    $objetivo =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $hoy->getTimezone()
        );


    if ($objetivo === false) {
        return null;
    }


    return
        (int) $hoy
            ->diff(
                $objetivo
            )
            ->format('%r%a');
}


/*
 * ==========================================================
 * Evento — comportamiento legado preservado
 * ==========================================================
 */


function especificacionNotificacion(
    array $evento,
    DateTimeImmutable $hoy
): ?array {

    $codigo =
        etiquetaMateriaNotificacion(
            $evento
        );


    $tituloEvento =
        trim(
            (string) (
                $evento['titulo']
                ?? ''
            )
        );


    if (
        ($evento['estado'] ?? '')
        === 'ENTREGADO'
    ) {

        return [
            'tipo' =>
                'ENTREGADO',

            'titulo' =>
                'Entrega registrada',

            'mensaje' =>
                $codigo
                . ' · '
                . $tituloEvento
                . ' fue marcado como entregado y espera resolución.',
        ];
    }


    if (
        ($evento['estado'] ?? '')
        !== 'PENDIENTE'
    ) {
        return null;
    }


    $dias =
        diasHastaNotificacion(
            $evento['fecha']
            ?? null,
            $hoy
        );


    if ($dias === null) {
        return null;
    }


    if (
        $dias >= 4
        && $dias <= 7
    ) {

        return [
            'tipo' =>
                'VENCE_7D',

            'titulo' =>
                'Próxima fecha',

            'mensaje' =>
                'Faltan '
                . $dias
                . ' días para '
                . $codigo
                . ' · '
                . $tituloEvento
                . '.',
        ];
    }


    if (
        $dias >= 2
        && $dias <= 3
    ) {

        return [
            'tipo' =>
                'VENCE_3D',

            'titulo' =>
                'Fecha cercana',

            'mensaje' =>
                'Faltan '
                . $dias
                . ' días para '
                . $codigo
                . ' · '
                . $tituloEvento
                . '.',
        ];
    }


    if ($dias === 1) {

        return [
            'tipo' =>
                'VENCE_1D',

            'titulo' =>
                'Vence mañana',

            'mensaje' =>
                $codigo
                . ' · '
                . $tituloEvento
                . ' está programado para mañana.',
        ];
    }


    if ($dias === 0) {

        return [
            'tipo' =>
                'VENCE_HOY',

            'titulo' =>
                'Vence hoy',

            'mensaje' =>
                $codigo
                . ' · '
                . $tituloEvento
                . ' está programado para hoy.',
        ];
    }


    return null;
}


/*
 * ==========================================================
 * Tarea — R4-C2
 * ==========================================================
 */


function especificacionNotificacionTarea(
    array $tarea,
    DateTimeImmutable $hoy
): ?array {

    $estado =
        (string) (
            $tarea['estado']
            ?? ''
        );


    if (
        !in_array(
            $estado,
            [
                'PENDIENTE',
                'EN_PROGRESO',
            ],
            true
        )
    ) {

        return null;
    }


    $dias =
        diasHastaNotificacion(
            $tarea['fecha_limite']
            ?? null,
            $hoy
        );


    if ($dias === null) {
        return null;
    }


    $codigo =
        etiquetaMateriaNotificacion(
            $tarea
        );


    $tituloTarea =
        trim(
            (string) (
                $tarea['titulo']
                ?? 'Tarea'
            )
        );


    $referencia =
        $codigo
        . ' · '
        . $tituloTarea;


    if (
        $dias >= 4
        && $dias <= 7
    ) {

        return [
            'tipo' =>
                'VENCE_7D',

            'titulo' =>
                'Tarea próxima',

            'mensaje' =>
                'Faltan '
                . $dias
                . ' días para completar '
                . $referencia
                . '.',
        ];
    }


    if (
        $dias >= 2
        && $dias <= 3
    ) {

        return [
            'tipo' =>
                'VENCE_3D',

            'titulo' =>
                'Tarea cercana',

            'mensaje' =>
                'Faltan '
                . $dias
                . ' días para completar '
                . $referencia
                . '.',
        ];
    }


    if ($dias === 1) {

        return [
            'tipo' =>
                'VENCE_1D',

            'titulo' =>
                'Tarea para mañana',

            'mensaje' =>
                $referencia
                . ' vence mañana.',
        ];
    }


    if ($dias === 0) {

        return [
            'tipo' =>
                'VENCE_HOY',

            'titulo' =>
                'Tarea vence hoy',

            'mensaje' =>
                $referencia
                . ' vence hoy.',
        ];
    }


    if ($dias < 0) {

        $atraso =
            abs(
                $dias
            );


        return [
            'tipo' =>
                'VENCIDA',

            'titulo' =>
                'Tarea vencida',

            'mensaje' =>
                $referencia
                . ' venció hace '
                . $atraso
                . (
                    $atraso === 1
                        ? ' día.'
                        : ' días.'
                ),
        ];
    }


    return null;
}


/*
 * ==========================================================
 * Data loaders
 * ==========================================================
 */


function cargarEventosNotificaciones(
    PDO $pdo
): array {

    return
        $pdo
            ->query(
                "
                SELECT
                    e.id,
                    e.user_id,
                    e.titulo,
                    e.fecha,
                    e.estado,

                    m.codigo
                        AS materia_codigo,

                    m.nombre
                        AS materia_nombre

                FROM eventos_academicos e

                INNER JOIN materias m
                    ON m.id=e.materia_id
                    AND m.user_id=e.user_id

                ORDER BY
                    e.id
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );
}


function cargarTareasNotificaciones(
    PDO $pdo
): array {

    return
        $pdo
            ->query(
                "
                SELECT
                    t.id,
                    t.user_id,
                    t.titulo,
                    t.prioridad,
                    t.estado,
                    t.fecha_limite,
                    t.hora_limite,
                    t.materia_id,
                    t.evento_id,

                    m.codigo
                        AS materia_codigo,

                    m.nombre
                        AS materia_nombre

                FROM tareas t

                LEFT JOIN materias m
                    ON m.id=t.materia_id
                    AND m.user_id=t.user_id

                WHERE
                    (
                        t.materia_id IS NULL
                        OR EXISTS (
                            SELECT 1
                            FROM materias mx
                            WHERE mx.id=t.materia_id
                              AND mx.user_id=t.user_id
                        )
                    )
                    AND
                    (
                        t.evento_id IS NULL
                        OR EXISTS (
                            SELECT 1
                            FROM eventos_academicos ex
                            WHERE ex.id=t.evento_id
                              AND ex.user_id=t.user_id
                        )
                    )

                ORDER BY
                    t.id
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );
}


/*
 * ==========================================================
 * Event processing
 * ==========================================================
 */


function procesarNotificacionesEventos(
    PDO $pdo,
    DateTimeImmutable $hoy
): array {

    $eventos =
        cargarEventosNotificaciones(
            $pdo
        );


    $resolverTodas =
        $pdo->prepare(
            "
            UPDATE notificaciones

            SET
                resuelta_at =
                    COALESCE(
                        resuelta_at,
                        NOW()
                    )

            WHERE
                origen='EVENTO'
                AND user_id=:user_id
                AND evento_id=:evento_id
                AND resuelta_at IS NULL
            "
        );


    $resolverOtras =
        $pdo->prepare(
            "
            UPDATE notificaciones

            SET
                resuelta_at =
                    COALESCE(
                        resuelta_at,
                        NOW()
                    )

            WHERE
                origen='EVENTO'
                AND user_id=:user_id
                AND evento_id=:evento_id
                AND tipo<>:tipo
                AND resuelta_at IS NULL
            "
        );


    $upsert =
        $pdo->prepare(
            "
            INSERT INTO notificaciones
            (
                origen,
                user_id,
                evento_id,
                tarea_id,
                tipo,
                clave_unica,
                titulo,
                mensaje,
                fecha_objetivo,
                resuelta_at
            )

            VALUES
            (
                'EVENTO',
                :user_id,
                :evento_id,
                NULL,
                :tipo,
                :clave_unica,
                :titulo,
                :mensaje,
                :fecha_objetivo,
                NULL
            )

            ON DUPLICATE KEY UPDATE

                origen='EVENTO',

                evento_id=
                    VALUES(evento_id),

                tarea_id=NULL,

                tipo=
                    VALUES(tipo),

                titulo=
                    VALUES(titulo),

                mensaje=
                    VALUES(mensaje),

                fecha_objetivo=
                    VALUES(fecha_objetivo),

                resuelta_at=NULL,

                updated_at=
                    CURRENT_TIMESTAMP
            "
        );


    $relevantes = 0;


    foreach (
        $eventos
        as $evento
    ) {

        $userId =
            (int) (
                $evento['user_id']
                ?? 0
            );


        if ($userId <= 0) {
            continue;
        }

        $spec =
            especificacionNotificacion(
                $evento,
                $hoy
            );


        if ($spec === null) {

            $resolverTodas->execute([
            'user_id' =>
                $userId,

                'evento_id' =>
                    (int) $evento['id'],
            ]);

            continue;
        }


        $resolverOtras->execute([
            'user_id' =>
                $userId,

            'evento_id' =>
                (int) $evento['id'],

            'tipo' =>
                $spec['tipo'],
        ]);


        $clave =
            'evento:'
            . (int) $evento['id']
            . ':'
            . $spec['tipo'];


        $upsert->execute([
            'user_id' =>
                $userId,

            'evento_id' =>
                (int) $evento['id'],

            'tipo' =>
                $spec['tipo'],

            'clave_unica' =>
                $clave,

            'titulo' =>
                $spec['titulo'],

            'mensaje' =>
                $spec['mensaje'],

            'fecha_objetivo' =>
                $evento['fecha'],
        ]);


        $relevantes++;
    }


    return [
        'procesados' =>
            count(
                $eventos
            ),

        'relevantes' =>
            $relevantes,
    ];
}


/*
 * ==========================================================
 * Task processing
 * ==========================================================
 */


function procesarNotificacionesTareas(
    PDO $pdo,
    DateTimeImmutable $hoy
): array {

    $tareas =
        cargarTareasNotificaciones(
            $pdo
        );


    $resolverTodas =
        $pdo->prepare(
            "
            UPDATE notificaciones

            SET
                resuelta_at =
                    COALESCE(
                        resuelta_at,
                        NOW()
                    )

            WHERE
                origen='TAREA'
                AND user_id=:user_id
                AND tarea_id=:tarea_id
                AND resuelta_at IS NULL
            "
        );


    $resolverOtras =
        $pdo->prepare(
            "
            UPDATE notificaciones

            SET
                resuelta_at =
                    COALESCE(
                        resuelta_at,
                        NOW()
                    )

            WHERE
                origen='TAREA'
                AND user_id=:user_id
                AND tarea_id=:tarea_id
                AND tipo<>:tipo
                AND resuelta_at IS NULL
            "
        );


    $upsert =
        $pdo->prepare(
            "
            INSERT INTO notificaciones
            (
                origen,
                user_id,
                evento_id,
                tarea_id,
                tipo,
                clave_unica,
                titulo,
                mensaje,
                fecha_objetivo,
                resuelta_at
            )

            VALUES
            (
                'TAREA',
                :user_id,
                NULL,
                :tarea_id,
                :tipo,
                :clave_unica,
                :titulo,
                :mensaje,
                :fecha_objetivo,
                NULL
            )

            ON DUPLICATE KEY UPDATE

                origen='TAREA',

                evento_id=NULL,

                tarea_id=
                    VALUES(tarea_id),

                tipo=
                    VALUES(tipo),

                titulo=
                    VALUES(titulo),

                mensaje=
                    VALUES(mensaje),

                fecha_objetivo=
                    VALUES(fecha_objetivo),

                resuelta_at=NULL,

                updated_at=
                    CURRENT_TIMESTAMP
            "
        );


    $relevantes = 0;


    foreach (
        $tareas
        as $tarea
    ) {

        $userId =
            (int) (
                $tarea['user_id']
                ?? 0
            );


        if ($userId <= 0) {
            continue;
        }

        $spec =
            especificacionNotificacionTarea(
                $tarea,
                $hoy
            );


        if ($spec === null) {

            $resolverTodas->execute([
            'user_id' =>
                $userId,

                'tarea_id' =>
                    (int) $tarea['id'],
            ]);

            continue;
        }


        $resolverOtras->execute([
            'user_id' =>
                $userId,

            'tarea_id' =>
                (int) $tarea['id'],

            'tipo' =>
                $spec['tipo'],
        ]);


        $clave =
            'tarea:'
            . (int) $tarea['id']
            . ':'
            . $spec['tipo'];


        $upsert->execute([
            'user_id' =>
                $userId,

            'tarea_id' =>
                (int) $tarea['id'],

            'tipo' =>
                $spec['tipo'],

            'clave_unica' =>
                $clave,

            'titulo' =>
                $spec['titulo'],

            'mensaje' =>
                $spec['mensaje'],

            'fecha_objetivo' =>
                $tarea['fecha_limite'],
        ]);


        $relevantes++;
    }


    return [
        'procesados' =>
            count(
                $tareas
            ),

        'relevantes' =>
            $relevantes,
    ];
}


/*
 * ==========================================================
 * Main
 * ==========================================================
 */


function ejecutarGeneradorNotificaciones(): void
{
    $pdo = db();


    $zona =
        new DateTimeZone(
            'America/Argentina/Buenos_Aires'
        );


    $hoy =
        new DateTimeImmutable(
            'today',
            $zona
        );


    $pdo->beginTransaction();


    try {

        $eventos =
            procesarNotificacionesEventos(
                $pdo,
                $hoy
            );


        $tareas =
            procesarNotificacionesTareas(
                $pdo,
                $hoy
            );


        $pdo->commit();


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        throw $e;
    }


    $total =
        (int) $pdo
            ->query(
                "
                SELECT COUNT(*)
                FROM notificaciones
                "
            )
            ->fetchColumn();


    $activas =
        (int) $pdo
            ->query(
                "
                SELECT COUNT(*)

                FROM notificaciones

                WHERE
                    resuelta_at IS NULL
                    AND descartada_at IS NULL
                "
            )
            ->fetchColumn();


    $noLeidas =
        (int) $pdo
            ->query(
                "
                SELECT COUNT(*)

                FROM notificaciones

                WHERE
                    resuelta_at IS NULL
                    AND descartada_at IS NULL
                    AND leida_at IS NULL
                "
            )
            ->fetchColumn();


    $eventRows =
        (int) $pdo
            ->query(
                "
                SELECT COUNT(*)
                FROM notificaciones
                WHERE origen='EVENTO'
                "
            )
            ->fetchColumn();


    $taskRows =
        (int) $pdo
            ->query(
                "
                SELECT COUNT(*)
                FROM notificaciones
                WHERE origen='TAREA'
                "
            )
            ->fetchColumn();


    echo
        'NOTIFICATIONS_GENERATOR=PASS'
        . PHP_EOL;


    echo
        'EVENTS_PROCESSED='
        . $eventos['procesados']
        . PHP_EOL;


    echo
        'EVENT_RELEVANT_RULES='
        . $eventos['relevantes']
        . PHP_EOL;


    echo
        'TASKS_PROCESSED='
        . $tareas['procesados']
        . PHP_EOL;


    echo
        'TASK_RELEVANT_RULES='
        . $tareas['relevantes']
        . PHP_EOL;


    echo
        'EVENT_NOTIFICATION_ROWS='
        . $eventRows
        . PHP_EOL;


    echo
        'TASK_NOTIFICATION_ROWS='
        . $taskRows
        . PHP_EOL;


    echo
        'NOTIFICATIONS_TOTAL='
        . $total
        . PHP_EOL;


    echo
        'NOTIFICATIONS_ACTIVE='
        . $activas
        . PHP_EOL;


    echo
        'NOTIFICATIONS_UNREAD='
        . $noLeidas
        . PHP_EOL;
}


function ejecutarGeneradorNotificacionesCli(): void
{
    try {

        ejecutarGeneradorNotificaciones();


    } catch (Throwable $e) {

        fwrite(
            STDERR,
            'NOTIFICATIONS_GENERATOR=FAIL'
            . PHP_EOL
        );


        fwrite(
            STDERR,
            get_class($e)
            . ': '
            . $e->getMessage()
            . PHP_EOL
        );


        exit(1);
    }
}


/*
 * Al incluir este archivo desde un test, sólo se cargan
 * las funciones. La ejecución productiva ocurre únicamente
 * cuando este archivo es el script CLI principal.
 */

$scriptActual =
    realpath(
        (string) (
            $_SERVER['SCRIPT_FILENAME']
            ?? ''
        )
    );


$archivoActual =
    realpath(
        __FILE__
    );


if (
    PHP_SAPI === 'cli'
    && $scriptActual !== false
    && $archivoActual !== false
    && $scriptActual === $archivoActual
) {

    ejecutarGeneradorNotificacionesCli();
}
