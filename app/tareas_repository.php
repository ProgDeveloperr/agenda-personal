<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';


function estadosTareaValidos(): array
{
    return [
        'PENDIENTE',
        'EN_PROGRESO',
        'COMPLETADA',
        'CANCELADA',
    ];
}


function prioridadesTareaValidas(): array
{
    return [
        'BAJA',
        'MEDIA',
        'ALTA',
        'URGENTE',
    ];
}


function normalizarNullableTarea(
    ?string $valor
): ?string {

    if ($valor === null) {
        return null;
    }


    $valor = trim($valor);


    return $valor !== ''
        ? $valor
        : null;
}


function validarFechaTarea(
    ?string $fecha
): ?string {

    $fecha =
        normalizarNullableTarea(
            $fecha
        );


    if ($fecha === null) {
        return null;
    }


    $objeto =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );


    if (
        !(
            $objeto
            instanceof DateTimeImmutable
        )
        || $objeto->format('Y-m-d')
            !== $fecha
    ) {

        throw new InvalidArgumentException(
            'La fecha límite no es válida.'
        );
    }


    return $fecha;
}


function validarHoraTarea(
    ?string $hora,
    ?string $fecha
): ?string {

    $hora =
        normalizarNullableTarea(
            $hora
        );


    if ($hora === null) {
        return null;
    }


    if ($fecha === null) {

        throw new InvalidArgumentException(
            'No puede existir una hora límite sin fecha límite.'
        );
    }


    $objeto =
        DateTimeImmutable::createFromFormat(
            '!H:i',
            $hora
        );


    if (
        !(
            $objeto
            instanceof DateTimeImmutable
        )
        || $objeto->format('H:i')
            !== $hora
    ) {

        throw new InvalidArgumentException(
            'La hora límite no es válida.'
        );
    }


    return $hora;
}


function resolverVinculosTarea(
    ?int $materiaId,
    ?int $eventoId
): array {
    throw new LogicException(
        'Usar resolverVinculosTareaUsuario con usuario autenticado.'
    );
}

function resolverVinculosTareaUsuario(
    int $userId,
    ?int $materiaId,
    ?int $eventoId
): array {

    $pdo = db();


    if (
        $materiaId !== null
        && $materiaId <= 0
    ) {

        throw new InvalidArgumentException(
            'La materia seleccionada no es válida.'
        );
    }


    if (
        $eventoId !== null
        && $eventoId <= 0
    ) {

        throw new InvalidArgumentException(
            'El evento seleccionado no es válido.'
        );
    }


    if ($eventoId !== null) {

        $stmt =
            $pdo->prepare(
                "
                SELECT
                    id,
                    materia_id
                FROM eventos_academicos
                WHERE id = :id
                    AND user_id = :user_id
                LIMIT 1
                "
            );


        $stmt->execute([
            'id' => $eventoId,
            'user_id' => $userId,
        ]);


        $evento =
            $stmt->fetch();


        if ($evento === false) {

            throw new InvalidArgumentException(
                'El evento seleccionado no existe.'
            );
        }


        $materiaEvento =
            (int) $evento['materia_id'];


        if ($materiaId === null) {

            $materiaId =
                $materiaEvento;

        } elseif (
            $materiaId
            !== $materiaEvento
        ) {

            throw new InvalidArgumentException(
                'El evento no pertenece a la materia seleccionada.'
            );
        }
    }


    if ($materiaId !== null) {

        $stmt =
            $pdo->prepare(
                "
                SELECT id
                FROM materias
                WHERE id = :id
                    AND user_id = :user_id
                LIMIT 1
                "
            );


        $stmt->execute([
            'id' => $materiaId,
            'user_id' => $userId,
        ]);


        if ($stmt->fetchColumn() === false) {

            throw new InvalidArgumentException(
                'La materia seleccionada no existe.'
            );
        }
    }


    return [
        'materia_id' =>
            $materiaId,

        'evento_id' =>
            $eventoId,
    ];
}


function validarDatosTarea(
    int $userId,
    string $titulo,
    ?string $descripcion,
    string $prioridad,
    string $estado,
    ?string $fechaLimite,
    ?string $horaLimite,
    ?int $materiaId,
    ?int $eventoId
): array {

    $titulo =
        trim(
            $titulo
        );


    if ($titulo === '') {

        throw new InvalidArgumentException(
            'El título de la tarea es obligatorio.'
        );
    }


    if (
        mb_strlen(
            $titulo
        ) > 180
    ) {

        throw new InvalidArgumentException(
            'El título no puede superar 180 caracteres.'
        );
    }


    $descripcion =
        normalizarNullableTarea(
            $descripcion
        );


    if (
        !in_array(
            $prioridad,
            prioridadesTareaValidas(),
            true
        )
    ) {

        throw new InvalidArgumentException(
            'La prioridad seleccionada no es válida.'
        );
    }


    if (
        !in_array(
            $estado,
            estadosTareaValidos(),
            true
        )
    ) {

        throw new InvalidArgumentException(
            'El estado seleccionado no es válido.'
        );
    }


    $fechaLimite =
        validarFechaTarea(
            $fechaLimite
        );


    $horaLimite =
        validarHoraTarea(
            $horaLimite,
            $fechaLimite
        );


    $vinculos =
        resolverVinculosTareaUsuario(
            $userId,
            $materiaId,
            $eventoId
        );


    return [
        'titulo' =>
            $titulo,

        'descripcion' =>
            $descripcion,

        'prioridad' =>
            $prioridad,

        'estado' =>
            $estado,

        'fecha_limite' =>
            $fechaLimite,

        'hora_limite' =>
            $horaLimite,

        'materia_id' =>
            $vinculos['materia_id'],

        'evento_id' =>
            $vinculos['evento_id'],
    ];
}


function listarTareas(
    int $userId,
    ?string $estado = null,
    ?int $materiaId = null
): array {

    $where = [
        't.user_id = :user_id',
    ];
    $params = [
        'user_id' => $userId,
    ];


    if ($estado !== null) {

        if (
            !in_array(
                $estado,
                estadosTareaValidos(),
                true
            )
        ) {

            throw new InvalidArgumentException(
                'Filtro de estado inválido.'
            );
        }


        $where[] =
            't.estado = :estado';

        $params['estado'] =
            $estado;
    }


    if ($materiaId !== null) {

        $where[] =
            't.materia_id = :materia_id';

        $params['materia_id'] =
            $materiaId;
    }


    $sql = "
        SELECT
            t.id,
            t.materia_id,
            t.evento_id,
            t.serie_id,
            t.titulo,
            t.descripcion,
            t.prioridad,
            t.estado,
            t.fecha_limite,
            t.hora_limite,
            t.fecha_ocurrencia,
            t.completada_at,
            t.created_at,
            t.updated_at,

            m.nombre AS materia_nombre,
            m.codigo AS materia_codigo,
            m.color AS materia_color,

            e.titulo AS evento_titulo,
            e.fecha AS evento_fecha,
            e.hora AS evento_hora

        FROM tareas t

        LEFT JOIN materias m
            ON m.id = t.materia_id
           AND m.user_id = t.user_id

        LEFT JOIN eventos_academicos e
            ON e.id = t.evento_id
           AND e.user_id = t.user_id
    ";


    if ($where !== []) {

        $sql .=
            "
            WHERE "
            . implode(
                ' AND ',
                $where
            );
    }


    $sql .= "
        ORDER BY
            CASE t.estado
                WHEN 'EN_PROGRESO' THEN 1
                WHEN 'PENDIENTE' THEN 2
                WHEN 'COMPLETADA' THEN 3
                ELSE 4
            END,

            t.fecha_limite IS NULL,
            t.fecha_limite ASC,

            t.hora_limite IS NULL,
            t.hora_limite ASC,

            CASE t.prioridad
                WHEN 'URGENTE' THEN 1
                WHEN 'ALTA' THEN 2
                WHEN 'MEDIA' THEN 3
                ELSE 4
            END,

            t.id DESC
    ";


    $stmt =
        db()->prepare(
            $sql
        );


    $stmt->execute(
        $params
    );


    return $stmt->fetchAll();
}


function buscarTarea(
    int $userId,
    int $id
): ?array {

    $stmt =
        db()->prepare(
            "
            SELECT
                t.id,
                t.materia_id,
                t.evento_id,
            t.serie_id,
                t.titulo,
                t.descripcion,
                t.prioridad,
                t.estado,
                t.fecha_limite,
                t.hora_limite,
            t.fecha_ocurrencia,
                t.completada_at,
                t.created_at,
                t.updated_at,

                m.nombre AS materia_nombre,
                m.codigo AS materia_codigo,
                m.color AS materia_color,

                e.titulo AS evento_titulo,
                e.fecha AS evento_fecha,
                e.hora AS evento_hora

            FROM tareas t

            LEFT JOIN materias m
                ON m.id = t.materia_id
               AND m.user_id = t.user_id

            LEFT JOIN eventos_academicos e
                ON e.id = t.evento_id
               AND e.user_id = t.user_id

            WHERE t.id = :id
                AND t.user_id = :user_id

            LIMIT 1
            "
        );


    $stmt->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);


    $tarea =
        $stmt->fetch();


    return $tarea !== false
        ? $tarea
        : null;
}


function crearTarea(
    int $userId,
    string $titulo,
    ?string $descripcion = null,
    string $prioridad = 'MEDIA',
    string $estado = 'PENDIENTE',
    ?string $fechaLimite = null,
    ?string $horaLimite = null,
    ?int $materiaId = null,
    ?int $eventoId = null
): int {

    $datos =
        validarDatosTarea(
            $userId,
            $titulo,
            $descripcion,
            $prioridad,
            $estado,
            $fechaLimite,
            $horaLimite,
            $materiaId,
            $eventoId
        );


    $stmt =
        db()->prepare(
            "
            INSERT INTO tareas (
                user_id,
                materia_id,
                evento_id,
                titulo,
                descripcion,
                prioridad,
                estado,
                fecha_limite,
                hora_limite,
                completada_at
            )
            VALUES (
                :user_id,
                :materia_id,
                :evento_id,
                :titulo,
                :descripcion,
                :prioridad,
                :estado,
                :fecha_limite,
                :hora_limite,

                CASE
                    WHEN :estado_completion
                        = 'COMPLETADA'
                    THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END
            )
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'materia_id' =>
            $datos['materia_id'],

        'evento_id' =>
            $datos['evento_id'],

        'titulo' =>
            $datos['titulo'],

        'descripcion' =>
            $datos['descripcion'],

        'prioridad' =>
            $datos['prioridad'],

        'estado' =>
            $datos['estado'],

        'fecha_limite' =>
            $datos['fecha_limite'],

        'hora_limite' =>
            $datos['hora_limite'],

        'estado_completion' =>
            $datos['estado'],
    ]);


    return (int)
        db()->lastInsertId();
}


// R4-D5-C — one-open invariant

function validarAperturaOcurrenciaRecurrente(
    int $userId,
    int $id,
    string $estado
): void {

    if (!in_array(
        $estado,
        ['PENDIENTE', 'EN_PROGRESO'],
        true
    )) {
        return;
    }

    $stmt = db()->prepare(
        "
        SELECT serie_id
        FROM tareas
        WHERE id = :id
            AND user_id = :user_id
        LIMIT 1
        "
    );

    $stmt->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);

    $serieId = $stmt->fetchColumn();

    if ($serieId === false) {
        throw new InvalidArgumentException(
            'La tarea ya no existe.'
        );
    }

    if ($serieId === null) {
        return;
    }

    $otra = db()->prepare(
        "
        SELECT id
        FROM tareas
        WHERE
            serie_id = :serie_id
            AND user_id = :user_id
            AND id <> :id
            AND estado IN (
                'PENDIENTE',
                'EN_PROGRESO'
            )
        LIMIT 1
        "
    );

    $otra->execute([
        'user_id' => $userId,
        'serie_id' => (int) $serieId,
        'id' => $id,
    ]);

    if ($otra->fetchColumn() !== false) {
        throw new InvalidArgumentException(
            'No se puede abrir esta ocurrencia porque la serie ya tiene otra ocurrencia abierta.'
        );
    }
}


function actualizarTarea(
    int $userId,
    int $id,
    string $titulo,
    ?string $descripcion,
    string $prioridad,
    string $estado,
    ?string $fechaLimite,
    ?string $horaLimite,
    ?int $materiaId,
    ?int $eventoId
): bool {

    if ($id <= 0) {

        throw new InvalidArgumentException(
            'La tarea indicada no es válida.'
        );
    }


    $datos =
        validarDatosTarea(
            $userId,
            $titulo,
            $descripcion,
            $prioridad,
            $estado,
            $fechaLimite,
            $horaLimite,
            $materiaId,
            $eventoId
        );


    validarAperturaOcurrenciaRecurrente(
        $userId,
        $id,
        $datos['estado']
    );



    $stmt =
        db()->prepare(
            "
            UPDATE tareas
            SET
                materia_id =
                    :materia_id,

                evento_id =
                    :evento_id,

                titulo =
                    :titulo,

                descripcion =
                    :descripcion,

                prioridad =
                    :prioridad,

                estado =
                    :estado,

                fecha_limite =
                    :fecha_limite,

                hora_limite =
                    :hora_limite,

                completada_at =
                    CASE
                        WHEN :estado_completion
                            = 'COMPLETADA'
                        THEN COALESCE(
                            completada_at,
                            CURRENT_TIMESTAMP
                        )
                        ELSE NULL
                    END

            WHERE id = :id
                AND user_id = :user_id
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'materia_id' =>
            $datos['materia_id'],

        'evento_id' =>
            $datos['evento_id'],

        'titulo' =>
            $datos['titulo'],

        'descripcion' =>
            $datos['descripcion'],

        'prioridad' =>
            $datos['prioridad'],

        'estado' =>
            $datos['estado'],

        'fecha_limite' =>
            $datos['fecha_limite'],

        'hora_limite' =>
            $datos['hora_limite'],

        'estado_completion' =>
            $datos['estado'],

        'id' =>
            $id,
    ]);


    return $stmt->rowCount() > 0;
}


function cambiarEstadoTarea(
    int $userId,
    int $id,
    string $estado
): bool {

    if (
        $id <= 0
        || !in_array(
            $estado,
            estadosTareaValidos(),
            true
        )
    ) {

        throw new InvalidArgumentException(
            'Estado de tarea inválido.'
        );
    }


    validarAperturaOcurrenciaRecurrente(
        $userId,
        $id,
        $estado
    );



    $stmt =
        db()->prepare(
            "
            UPDATE tareas
            SET
                estado =
                    :estado,

                completada_at =
                    CASE
                        WHEN :estado_completion
                            = 'COMPLETADA'
                        THEN COALESCE(
                            completada_at,
                            CURRENT_TIMESTAMP
                        )
                        ELSE NULL
                    END

            WHERE id = :id
                AND user_id = :user_id
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'estado' =>
            $estado,

        'estado_completion' =>
            $estado,

        'id' =>
            $id,
    ]);


    return $stmt->rowCount() > 0;
}


function contarTareasAbiertas(
    int $userId
): int {
    $stmt =
        db()->prepare(
            "
            SELECT COUNT(*)
            FROM tareas
            WHERE
                user_id = :user_id
                AND estado IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )
            "
        );

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return (int)
        $stmt->fetchColumn();
}


function listarTareasPorRango(
    int $userId,
    string $desde,
    string $hasta
): array {

    $stmt =
        db()->prepare(
            "
            SELECT
                t.id,
                t.materia_id,
                t.evento_id,
            t.serie_id,
                t.titulo,
                t.descripcion,
                t.prioridad,
                t.estado,
                t.fecha_limite,
                t.hora_limite,
            t.fecha_ocurrencia,
                t.completada_at,

                m.nombre AS materia_nombre,
                m.codigo AS materia_codigo,
                m.color AS materia_color,

                e.titulo AS evento_titulo

            FROM tareas t

            LEFT JOIN materias m
                ON m.id = t.materia_id
               AND m.user_id = t.user_id

            LEFT JOIN eventos_academicos e
                ON e.id = t.evento_id
               AND e.user_id = t.user_id

            WHERE
                t.user_id = :user_id
                AND t.fecha_limite >= :desde
                AND t.fecha_limite < :hasta

            ORDER BY
                t.fecha_limite ASC,
                t.hora_limite IS NULL,
                t.hora_limite ASC,

                CASE t.prioridad
                    WHEN 'URGENTE' THEN 1
                    WHEN 'ALTA' THEN 2
                    WHEN 'MEDIA' THEN 3
                    ELSE 4
                END,

                t.id ASC
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'desde' => $desde,
        'hasta' => $hasta,
    ]);


    return $stmt->fetchAll();
}


function listarTareasVencidas(
    int $userId,
    ?string $hoy = null
): array {

    $hoy =
        $hoy
        ?? (new DateTimeImmutable(
            'today',
            new DateTimeZone(
                'America/Argentina/Buenos_Aires'
            )
        ))->format('Y-m-d');


    $stmt =
        db()->prepare(
            "
            SELECT
                t.id,
                t.materia_id,
                t.evento_id,
            t.serie_id,
                t.titulo,
                t.prioridad,
                t.estado,
                t.fecha_limite,
                t.hora_limite,
            t.fecha_ocurrencia,

                m.nombre AS materia_nombre,
                m.codigo AS materia_codigo,
                m.color AS materia_color

            FROM tareas t

            LEFT JOIN materias m
                ON m.id = t.materia_id
               AND m.user_id = t.user_id

            WHERE
                t.user_id = :user_id
                AND t.fecha_limite < :hoy

                AND t.estado IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )

            ORDER BY
                t.fecha_limite ASC,

                CASE t.prioridad
                    WHEN 'URGENTE' THEN 1
                    WHEN 'ALTA' THEN 2
                    WHEN 'MEDIA' THEN 3
                    ELSE 4
                END,

                t.id ASC
            "
        );


    $stmt->execute([
        'user_id' => $userId,
        'hoy' => $hoy,
    ]);


    return $stmt->fetchAll();
}


// ARK3360_R36F_TASK_DELETE_PILOT_REPOSITORY
function eliminarTareaIndividualR36F(int $userId, int $id): void
{
    if ($userId <= 0 || $id <= 0) {
        throw new InvalidArgumentException('Identificador inválido.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT serie_id FROM tareas '
            . 'WHERE id = :id AND user_id = :user_id FOR UPDATE'
        );
        $consulta->execute(['id' => $id, 'user_id' => $userId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!is_array($fila)) {
            throw new InvalidArgumentException('La tarea no existe o no te pertenece.');
        }
        if ($fila['serie_id'] !== null) {
            throw new InvalidArgumentException(
                'La eliminación de tareas recurrentes todavía no está habilitada.'
            );
        }

        $eliminar = $pdo->prepare(
            'DELETE FROM tareas '
            . 'WHERE id = :id AND user_id = :user_id AND serie_id IS NULL'
        );
        $eliminar->execute(['id' => $id, 'user_id' => $userId]);
        if ($eliminar->rowCount() !== 1) {
            throw new RuntimeException('No se pudo verificar la eliminación.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
