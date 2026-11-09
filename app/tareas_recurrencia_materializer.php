<?php

declare(strict_types=1);

require_once __DIR__
    . '/tareas_recurrencia_repository.php';


function listarOcurrenciasAbiertasSerieTarea(int $userId, 
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
                id,
                serie_id,
                estado,
                fecha_limite,
                hora_limite,
                fecha_ocurrencia

            FROM tareas

            WHERE
                user_id=:user_id
                AND serie_id=:serie_id
                AND estado IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )

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


function ultimaFechaMaterializadaSerieTarea(int $userId, 
    int $serieId
): ?string {

    if ($serieId <= 0) {

        throw new InvalidArgumentException(
            'La serie indicada no es válida.'
        );
    }


    $stmt =
        db()->prepare(
            "
            SELECT
                MAX(fecha_ocurrencia)

            FROM tareas

            WHERE user_id=:user_id
                AND serie_id=:serie_id
            "
        );


    $stmt->execute([
        'user_id' => $userId,
            'serie_id' =>
            $serieId,
    ]);


    $fecha =
        $stmt->fetchColumn();


    return
        $fecha !== false
        && $fecha !== null
        && $fecha !== ''
            ? (string) $fecha
            : null;
}


function procesarSerieTareaMaterializador(int $userId, 
    array $serie
): array {
    $serieOwnerId =
        (int) (
            $serie['user_id']
            ?? 0
        );

    if (
        $userId <= 0
        || $serieOwnerId <= 0
        || $serieOwnerId !== $userId
    ) {
        throw new RuntimeException(
            'La serie recurrente no pertenece al usuario indicado.'
        );
    }


    $serieId =
        (int) (
            $serie['id']
            ?? 0
        );


    if ($serieId <= 0) {

        throw new InvalidArgumentException(
            'La serie no posee un identificador válido.'
        );
    }


    if (
        (int) (
            $serie['activa']
            ?? 0
        ) !== 1
    ) {

        return [
            'estado' =>
                'INACTIVA',

            'serie_id' =>
                $serieId,
        ];
    }


    $abiertas =
        listarOcurrenciasAbiertasSerieTarea($userId, 
            $serieId
        );


    if (
        count($abiertas) > 1
    ) {

        throw new RuntimeException(
            'La serie posee más de una ocurrencia abierta.'
        );
    }


    if (
        count($abiertas) === 1
    ) {

        return [
            'estado' =>
                'ABIERTA_EXISTENTE',

            'serie_id' =>
                $serieId,

            'tarea_id' =>
                (int)
                    $abiertas[0]['id'],

            'fecha_ocurrencia' =>
                $abiertas[0][
                    'fecha_ocurrencia'
                ],
        ];
    }


    $ultima =
        ultimaFechaMaterializadaSerieTarea($userId, 
            $serieId
        );


    if ($ultima === null) {

        $candidata =
            validarFechaTarea(
                (string) (
                    $serie[
                        'fecha_inicio'
                    ]
                    ?? ''
                )
            );

    } else {

        $candidata =
            calcularSiguienteFechaSerieTarea(
                $serie,
                $ultima
            );
    }


    if ($candidata === null) {

        return [
            'estado' =>
                'AGOTADA',

            'serie_id' =>
                $serieId,

            'ultima_fecha' =>
                $ultima,
        ];
    }


    $tareaId =
        crearOcurrenciaSerieTarea($userId, 
            $serieId,
            $candidata
        );


    return [
        'estado' =>
            'CREADA',

        'serie_id' =>
            $serieId,

        'tarea_id' =>
            $tareaId,

        'fecha_ocurrencia' =>
            $candidata,
    ];
}


function ejecutarMaterializadorRecurrencias(): int
{
    $pdo =
        db();


    $lockName =
        'agenda:recurrence-materializer';


    $lock =
        $pdo->prepare(
            "
            SELECT GET_LOCK(
                :lock_name,
                0
            )
            "
        );


    $lock->execute([
        'lock_name' =>
            $lockName,
    ]);


    $locked =
        (int)
            $lock->fetchColumn();


    if ($locked !== 1) {

        echo
            "RECURRENCE_MATERIALIZER="
            . "SKIPPED_LOCK_BUSY"
            . PHP_EOL;


        return 0;
    }


    try {

        $series =
            listarSeriesTareasActivas();


        $created = 0;
        $open = 0;
        $exhausted = 0;
        $inactive = 0;
        $errors = 0;


        foreach (
            $series
            as $serie
        ) {

            try {

                $resultado =
                    procesarSerieTareaMaterializador((int) ($serie['user_id'] ?? 0), 
                        $serie
                    );


                $estado =
                    (string) (
                        $resultado['estado']
                        ?? ''
                    );


                if (
                    $estado === 'CREADA'
                ) {

                    $created++;

                } elseif (
                    $estado
                    === 'ABIERTA_EXISTENTE'
                ) {

                    $open++;

                } elseif (
                    $estado === 'AGOTADA'
                ) {

                    $exhausted++;

                } elseif (
                    $estado === 'INACTIVA'
                ) {

                    $inactive++;
                }


            } catch (Throwable $e) {

                $errors++;


                error_log(
                    'Agenda recurrence series '
                    . (
                        $serie['id']
                        ?? '?'
                    )
                    . ': '
                    . $e->getMessage()
                );
            }
        }


        echo
            'RECURRENCE_MATERIALIZER='
            . (
                $errors === 0
                    ? 'PASS'
                    : 'FAIL'
            )
            . PHP_EOL;


        echo
            'ACTIVE_SERIES='
            . count($series)
            . PHP_EOL;


        echo
            'SERIES_PROCESSED='
            . count($series)
            . PHP_EOL;


        echo
            'OCCURRENCES_CREATED='
            . $created
            . PHP_EOL;


        echo
            'OPEN_SERIES='
            . $open
            . PHP_EOL;


        echo
            'EXHAUSTED_SERIES='
            . $exhausted
            . PHP_EOL;


        echo
            'INACTIVE_SERIES='
            . $inactive
            . PHP_EOL;


        echo
            'ERRORS='
            . $errors
            . PHP_EOL;


        return
            $errors === 0
                ? 0
                : 1;


    } finally {

        try {

            $release =
                $pdo->prepare(
                    "
                    SELECT RELEASE_LOCK(
                        :lock_name
                    )
                    "
                );


            $release->execute([
                'lock_name' =>
                    $lockName,
            ]);

        } catch (Throwable) {
        }
    }
}


if (
    PHP_SAPI === 'cli'
    && isset(
        $_SERVER['SCRIPT_FILENAME']
    )
    && realpath(
        (string)
            $_SERVER['SCRIPT_FILENAME']
    ) === __FILE__
) {

    exit(
        ejecutarMaterializadorRecurrencias()
    );
}
