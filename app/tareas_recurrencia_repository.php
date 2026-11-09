<?php

declare(strict_types=1);

require_once __DIR__ . '/tareas_repository.php';


function frecuenciasTareaRecurrenteValidas(): array
{
    return [
        'DIARIA',
        'SEMANAL',
        'MENSUAL',
    ];
}


function validarIntervaloTareaRecurrente(
    int $intervalo
): int {

    if (
        $intervalo < 1
        || $intervalo > 365
    ) {

        throw new InvalidArgumentException(
            'El intervalo de recurrencia debe estar entre 1 y 365.'
        );
    }


    return $intervalo;
}


function validarDatosSerieTarea(int $userId, 
    string $titulo,
    ?string $descripcion,
    string $prioridad,
    string $frecuencia,
    int $intervalo,
    string $fechaInicio,
    ?string $fechaFin,
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
            'El título de la serie es obligatorio.'
        );
    }


    if (
        mb_strlen(
            $titulo
        ) > 180
    ) {

        throw new InvalidArgumentException(
            'El título de la serie no puede superar 180 caracteres.'
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
            'La prioridad de la serie no es válida.'
        );
    }


    $frecuencia =
        strtoupper(
            trim(
                $frecuencia
            )
        );


    if (
        !in_array(
            $frecuencia,
            frecuenciasTareaRecurrenteValidas(),
            true
        )
    ) {

        throw new InvalidArgumentException(
            'La frecuencia seleccionada no es válida.'
        );
    }


    $intervalo =
        validarIntervaloTareaRecurrente(
            $intervalo
        );


    $fechaInicio =
        validarFechaTarea(
            $fechaInicio
        );


    if ($fechaInicio === null) {

        throw new InvalidArgumentException(
            'La fecha de inicio es obligatoria.'
        );
    }


    $fechaFin =
        validarFechaTarea(
            $fechaFin
        );


    if (
        $fechaFin !== null
        && $fechaFin < $fechaInicio
    ) {

        throw new InvalidArgumentException(
            'La fecha de fin no puede ser anterior a la fecha de inicio.'
        );
    }


    $horaLimite =
        validarHoraTarea(
            $horaLimite,
            $fechaInicio
        );


    $vinculos =
        resolverVinculosTareaUsuario($userId, 
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

        'frecuencia' =>
            $frecuencia,

        'intervalo' =>
            $intervalo,

        'fecha_inicio' =>
            $fechaInicio,

        'fecha_fin' =>
            $fechaFin,

        'hora_limite' =>
            $horaLimite,

        'materia_id' =>
            $vinculos['materia_id'],

        'evento_id' =>
            $vinculos['evento_id'],
    ];
}


function crearSerieTarea(int $userId, 
    string $titulo,
    ?string $descripcion,
    string $prioridad,
    string $frecuencia,
    int $intervalo,
    string $fechaInicio,
    ?string $fechaFin = null,
    ?string $horaLimite = null,
    ?int $materiaId = null,
    ?int $eventoId = null
): int {

    $datos =
        validarDatosSerieTarea($userId, 
            $titulo,
            $descripcion,
            $prioridad,
            $frecuencia,
            $intervalo,
            $fechaInicio,
            $fechaFin,
            $horaLimite,
            $materiaId,
            $eventoId
        );


    $stmt =
        db()->prepare(
            "
            INSERT INTO series_tareas (
                user_id,
                materia_id,
                evento_id,
                titulo,
                descripcion,
                prioridad,
                frecuencia,
                intervalo,
                fecha_inicio,
                fecha_fin,
                hora_limite,
                activa
            )

            VALUES (
                :user_id,
                :materia_id,
                :evento_id,
                :titulo,
                :descripcion,
                :prioridad,
                :frecuencia,
                :intervalo,
                :fecha_inicio,
                :fecha_fin,
                :hora_limite,
                1
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

        'frecuencia' =>
            $datos['frecuencia'],

        'intervalo' =>
            $datos['intervalo'],

        'fecha_inicio' =>
            $datos['fecha_inicio'],

        'fecha_fin' =>
            $datos['fecha_fin'],

        'hora_limite' =>
            $datos['hora_limite'],
    ]);


    return (int)
        db()->lastInsertId();
}


function buscarSerieTarea(int $userId, 
    int $id
): ?array {

    if ($id <= 0) {

        throw new InvalidArgumentException(
            'La serie indicada no es válida.'
        );
    }


    $stmt =
        db()->prepare(
            "
            SELECT
                s.user_id,
                s.id,
                s.materia_id,
                s.evento_id,
                s.titulo,
                s.descripcion,
                s.prioridad,
                s.frecuencia,
                s.intervalo,
                s.fecha_inicio,
                s.fecha_fin,
                s.hora_limite,
                s.activa,
                s.created_at,
                s.updated_at,

                m.codigo
                    AS materia_codigo,

                m.nombre
                    AS materia_nombre,

                m.color
                    AS materia_color,

                e.titulo
                    AS evento_titulo,

                e.fecha
                    AS evento_fecha,

                e.hora
                    AS evento_hora

            FROM series_tareas s

            LEFT JOIN materias m
                ON m.id=s.materia_id
                AND m.user_id=s.user_id

            LEFT JOIN eventos_academicos e
                ON e.id=s.evento_id
                AND e.user_id=s.user_id

            WHERE s.id=:id
                AND s.user_id=:user_id

            LIMIT 1
            "
        );


    $stmt->execute([
        'user_id' => $userId,
            'id' => $id,
    ]);


    $serie =
        $stmt->fetch();


    return
        $serie !== false
            ? $serie
            : null;
}


function listarSeriesTareasActivas(): array
{
    return
        db()
            ->query(
                "
                SELECT
                s.user_id,
                    s.id,
                    s.materia_id,
                    s.evento_id,
                    s.titulo,
                    s.descripcion,
                    s.prioridad,
                    s.frecuencia,
                    s.intervalo,
                    s.fecha_inicio,
                    s.fecha_fin,
                    s.hora_limite,
                    s.activa,
                    s.created_at,
                    s.updated_at,

                    m.codigo
                        AS materia_codigo,

                    m.nombre
                        AS materia_nombre,

                    m.color
                        AS materia_color,

                    e.titulo
                        AS evento_titulo,

                    e.fecha
                        AS evento_fecha,

                    e.hora
                        AS evento_hora

                FROM series_tareas s

                LEFT JOIN materias m
                    ON m.id=s.materia_id
                AND m.user_id=s.user_id

                LEFT JOIN eventos_academicos e
                    ON e.id=s.evento_id
                AND e.user_id=s.user_id

                WHERE s.activa=1

                ORDER BY
                    s.fecha_inicio ASC,
                    s.id ASC
                "
            )
            ->fetchAll();
}


function cambiarActivaSerieTarea(int $userId, 
    int $id,
    bool $activa
): bool {

    if ($id <= 0) {

        throw new InvalidArgumentException(
            'La serie indicada no es válida.'
        );
    }


    $stmt =
        db()->prepare(
            "
            UPDATE series_tareas

            SET
                activa=:activa

            WHERE id=:id
                AND user_id=:user_id
            "
        );


    $stmt->execute([
        'user_id' => $userId,
            'activa' =>
            $activa
                ? 1
                : 0,

        'id' =>
            $id,
    ]);


    return
        $stmt->rowCount() > 0;
}


function calcularSiguienteFechaSerieTarea(
    array $serie,
    string $fechaActual
): ?string {

    $fechaActual =
        validarFechaTarea(
            $fechaActual
        );


    if ($fechaActual === null) {

        throw new InvalidArgumentException(
            'La fecha actual de la serie es obligatoria.'
        );
    }


    $fechaInicio =
        validarFechaTarea(
            (string) (
                $serie['fecha_inicio']
                ?? ''
            )
        );


    if ($fechaInicio === null) {

        throw new InvalidArgumentException(
            'La serie no posee una fecha de inicio válida.'
        );
    }


    $frecuencia =
        strtoupper(
            trim(
                (string) (
                    $serie['frecuencia']
                    ?? ''
                )
            )
        );


    if (
        !in_array(
            $frecuencia,
            frecuenciasTareaRecurrenteValidas(),
            true
        )
    ) {

        throw new InvalidArgumentException(
            'La frecuencia de la serie no es válida.'
        );
    }


    $intervalo =
        validarIntervaloTareaRecurrente(
            (int) (
                $serie['intervalo']
                ?? 0
            )
        );


    $zona =
        new DateTimeZone(
            'America/Argentina/Buenos_Aires'
        );


    $actual =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fechaActual,
            $zona
        );


    $inicio =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fechaInicio,
            $zona
        );


    if (
        $actual === false
        || $inicio === false
    ) {

        throw new InvalidArgumentException(
            'No se pudo interpretar la fecha de recurrencia.'
        );
    }


    if ($frecuencia === 'DIARIA') {

        $siguiente =
            $actual->modify(
                '+'
                . $intervalo
                . ' days'
            );

    } elseif (
        $frecuencia === 'SEMANAL'
    ) {

        $siguiente =
            $actual->modify(
                '+'
                . $intervalo
                . ' weeks'
            );

    } else {

        /*
         * La recurrencia mensual mantiene el día
         * de fecha_inicio como ancla.
         *
         * 31/01 -> 28/02 -> 31/03
         * en un año no bisiesto.
         */

        $mesDestino =
            $actual
                ->modify(
                    'first day of this month'
                )
                ->modify(
                    '+'
                    . $intervalo
                    . ' months'
                );


        $diaAncla =
            (int)
                $inicio->format(
                    'd'
                );


        $ultimoDia =
            (int)
                $mesDestino->format(
                    't'
                );


        $diaDestino =
            min(
                $diaAncla,
                $ultimoDia
            );


        $siguiente =
            $mesDestino->setDate(
                (int)
                    $mesDestino->format(
                        'Y'
                    ),

                (int)
                    $mesDestino->format(
                        'm'
                    ),

                $diaDestino
            );
    }


    $siguienteSql =
        $siguiente->format(
            'Y-m-d'
        );


    $fechaFin =
        validarFechaTarea(
            $serie['fecha_fin']
            ?? null
        );


    if (
        $fechaFin !== null
        && $siguienteSql > $fechaFin
    ) {

        return null;
    }


    return $siguienteSql;
}


function crearOcurrenciaSerieTarea(int $userId, 
    int $serieId,
    string $fechaOcurrencia
): int {

    if ($serieId <= 0) {

        throw new InvalidArgumentException(
            'La serie indicada no es válida.'
        );
    }


    $fechaOcurrencia =
        validarFechaTarea(
            $fechaOcurrencia
        );


    if ($fechaOcurrencia === null) {

        throw new InvalidArgumentException(
            'La fecha de ocurrencia es obligatoria.'
        );
    }


    $serie =
        buscarSerieTarea($userId, 
            $serieId
        );


    if ($serie === null) {

        throw new InvalidArgumentException(
            'La serie ya no existe.'
        );
    }


    if (
        (int) $serie['activa']
        !== 1
    ) {

        throw new InvalidArgumentException(
            'La serie está detenida.'
        );
    }


    if (
        $fechaOcurrencia
        < (string)
            $serie['fecha_inicio']
    ) {

        throw new InvalidArgumentException(
            'La ocurrencia no puede ser anterior al inicio de la serie.'
        );
    }


    if (
        $serie['fecha_fin'] !== null
        && $fechaOcurrencia
            > (string)
                $serie['fecha_fin']
    ) {

        throw new InvalidArgumentException(
            'La ocurrencia supera la fecha de fin de la serie.'
        );
    }


    $pdo =
        db();


    $stmt =
        $pdo->prepare(
            "
            INSERT INTO tareas (
                user_id,
                materia_id,
                evento_id,
                serie_id,
                titulo,
                descripcion,
                prioridad,
                estado,
                fecha_limite,
                hora_limite,
                fecha_ocurrencia,
                completada_at
            )

            VALUES (
                :user_id,
                :materia_id,
                :evento_id,
                :serie_id,
                :titulo,
                :descripcion,
                :prioridad,
                'PENDIENTE',
                :fecha_limite,
                :hora_limite,
                :fecha_ocurrencia,
                NULL
            )

            ON DUPLICATE KEY UPDATE
                id=LAST_INSERT_ID(id)
            "
        );


    $stmt->execute([
        'user_id' => $userId,
            'materia_id' =>
            $serie['materia_id'],

        'evento_id' =>
            $serie['evento_id'],

        'serie_id' =>
            $serieId,

        'titulo' =>
            $serie['titulo'],

        'descripcion' =>
            $serie['descripcion'],

        'prioridad' =>
            $serie['prioridad'],

        'fecha_limite' =>
            $fechaOcurrencia,

        'hora_limite' =>
            $serie['hora_limite'],

        'fecha_ocurrencia' =>
            $fechaOcurrencia,
    ]);


    $id =
        (int)
            $pdo->lastInsertId();


    if ($id > 0) {
        return $id;
    }


    $lookup =
        $pdo->prepare(
            "
            SELECT id

            FROM tareas

            WHERE
                user_id=:user_id
                AND serie_id=:serie_id
                AND fecha_ocurrencia=
                    :fecha_ocurrencia

            LIMIT 1
            "
        );


    $lookup->execute([
        'user_id' => $userId,
            'serie_id' =>
            $serieId,

        'fecha_ocurrencia' =>
            $fechaOcurrencia,
    ]);


    $existente =
        $lookup->fetchColumn();


    if ($existente === false) {

        throw new RuntimeException(
            'No se pudo materializar la ocurrencia.'
        );
    }


    return (int) $existente;
}


function listarOcurrenciasSerieTarea(int $userId, 
    int $serieId
): array {

    if ($serieId <= 0) {

        throw new InvalidArgumentException(
            'La serie indicada no es válida.'
        );
    }


    $stmt =
        db()->prepare(
            "
            SELECT
                user_id,
                id,
                materia_id,
                evento_id,
                serie_id,
                titulo,
                descripcion,
                prioridad,
                estado,
                fecha_limite,
                hora_limite,
                fecha_ocurrencia,
                completada_at,
                created_at,
                updated_at

            FROM tareas

            WHERE user_id=:user_id
                AND serie_id=:serie_id

            ORDER BY
                fecha_ocurrencia ASC,
                id ASC
            "
        );


    $stmt->execute([
        'user_id' => $userId,
            'serie_id' =>
            $serieId,
    ]);


    return
        $stmt->fetchAll();
}


function contarSeriesTareasActivas(int $userId): int
{
    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'El usuario indicado no es válido.'
        );
    }

    $stmt =
        db()->prepare(
            "
            SELECT COUNT(*)
            FROM series_tareas
            WHERE user_id=:user_id
              AND activa=1
            "
        );

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return (int) $stmt->fetchColumn();
}



function listarSeriesTareas(int $userId, 
    ?bool $activa = null
): array {

    $sql = "
        SELECT
                s.user_id,
            s.id,
            s.materia_id,
            s.evento_id,
            s.titulo,
            s.descripcion,
            s.prioridad,
            s.frecuencia,
            s.intervalo,
            s.fecha_inicio,
            s.fecha_fin,
            s.hora_limite,
            s.activa,
            s.created_at,
            s.updated_at,

            m.codigo
                AS materia_codigo,

            m.nombre
                AS materia_nombre,

            m.color
                AS materia_color,

            e.titulo
                AS evento_titulo,

            e.fecha
                AS evento_fecha,

            e.hora
                AS evento_hora

        FROM series_tareas s

        LEFT JOIN materias m
            ON m.id=s.materia_id
                AND m.user_id=s.user_id

        LEFT JOIN eventos_academicos e
            ON e.id=s.evento_id
                AND e.user_id=s.user_id
            WHERE s.user_id=:user_id
    ";


    $params = [
        'user_id' => $userId,
    ];


    if ($activa !== null) {

        $sql .= "
            AND s.activa=:activa
        ";


        $params['activa'] =
            $activa
                ? 1
                : 0;
    }


    $sql .= "
        ORDER BY
            s.activa DESC,
            s.fecha_inicio ASC,
            s.id ASC
    ";


    $stmt =
        db()->prepare(
            $sql
        );


    $stmt->execute(
        $params
    );


    return
        $stmt->fetchAll();
}


// R4-D5 — edición de plantilla de serie

function actualizarSerieTarea(int $userId, 
    int $id,
    string $titulo,
    ?string $descripcion,
    string $prioridad,
    string $frecuencia,
    int $intervalo,
    ?string $fechaFin = null,
    ?string $horaLimite = null,
    ?int $materiaId = null,
    ?int $eventoId = null
): bool {

    if ($id <= 0) {

        throw new InvalidArgumentException(
            'La serie indicada no es válida.'
        );
    }


    $actual =
        buscarSerieTarea($userId, 
            $id
        );


    if ($actual === null) {

        throw new InvalidArgumentException(
            'La serie ya no existe.'
        );
    }


    $fechaInicio =
        trim(
            (string) (
                $actual['fecha_inicio']
                ?? ''
            )
        );


    if ($fechaInicio === '') {

        throw new RuntimeException(
            'La serie no tiene una fecha de inicio válida.'
        );
    }


    $datos =
        validarDatosSerieTarea($userId, 
            $titulo,
            $descripcion,
            $prioridad,
            $frecuencia,
            $intervalo,
            $fechaInicio,
            $fechaFin,
            $horaLimite,
            $materiaId,
            $eventoId
        );


    $stmtUltima =
        db()->prepare(
            "
            SELECT
                MAX(fecha_ocurrencia)

            FROM tareas

            WHERE user_id=:user_id
                AND serie_id = :serie_id
            "
        );


    $stmtUltima->execute([
        'user_id' => $userId,
            'serie_id' =>
            $id,
    ]);


    $ultimaOcurrencia =
        $stmtUltima->fetchColumn();


    if (
        $datos['fecha_fin'] !== null
        && $ultimaOcurrencia !== false
        && $ultimaOcurrencia !== null
        && $ultimaOcurrencia !== ''
        && $datos['fecha_fin']
            < (string) $ultimaOcurrencia
    ) {

        throw new InvalidArgumentException(
            'La fecha final no puede quedar antes de una ocurrencia ya materializada.'
        );
    }


    $stmt =
        db()->prepare(
            "
            UPDATE series_tareas

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

                frecuencia =
                    :frecuencia,

                intervalo =
                    :intervalo,

                fecha_fin =
                    :fecha_fin,

                hora_limite =
                    :hora_limite

            WHERE id = :id
                AND user_id=:user_id
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

        'frecuencia' =>
            $datos['frecuencia'],

        'intervalo' =>
            $datos['intervalo'],

        'fecha_fin' =>
            $datos['fecha_fin'],

        'hora_limite' =>
            $datos['hora_limite'],

        'id' =>
            $id,
    ]);


    return true;
}

