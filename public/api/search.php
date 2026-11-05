<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();

require_once '/var/www/app/database.php';


header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, max-age=0'
);

header(
    'X-Content-Type-Options: nosniff'
);


function responder(
    array $datos,
    int $status = 200
): never {

    http_response_code(
        $status
    );

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}


function etiquetaTipoBusqueda(
    string $tipo
): string {

    return match ($tipo) {
        'PARCIAL' => 'Parcial',
        'FINAL' => 'Final',
        'RECUPERATORIO' => 'Recuperatorio',
        'TP' => 'Trabajo práctico',
        'ENTREGA' => 'Entrega',
        'OTRO' => 'Otro',
        default => $tipo,
    };
}


function etiquetaEstadoBusqueda(
    string $estado
): string {

    return match ($estado) {
        'PENDIENTE' => 'Pendiente',
        'ENTREGADO' => 'Entregado',
        'APROBADO' => 'Aprobado',
        'DESAPROBADO' => 'Desaprobado',
        'AUSENTE' => 'Ausente',
        'CANCELADO' => 'Cancelado',
        default => $estado,
    };
}


function etiquetaEstadoTareaBusqueda(
    string $estado
): string {

    return match ($estado) {
        'PENDIENTE' =>
            'Pendiente',

        'EN_PROGRESO' =>
            'En progreso',

        'COMPLETADA' =>
            'Completada',

        'CANCELADA' =>
            'Cancelada',

        default =>
            $estado,
    };
}


function etiquetaPrioridadTareaBusqueda(
    string $prioridad
): string {

    return match ($prioridad) {
        'BAJA' =>
            'Baja',

        'MEDIA' =>
            'Media',

        'ALTA' =>
            'Alta',

        'URGENTE' =>
            'Urgente',

        default =>
            $prioridad,
    };
}


function etiquetaTurnoBusqueda(
    ?string $turno
): string {

    return match ($turno) {
        'MANANA' => 'Mañana',
        'TARDE' => 'Tarde',
        'NOCHE' => 'Noche',
        'OTRO' => 'Otro',
        default => '',
    };
}


if (
    $_SERVER['REQUEST_METHOD']
    !== 'GET'
) {

    header(
        'Allow: GET'
    );

    responder(
        [
            'ok' => false,
            'error' =>
                'Método no permitido.',
        ],
        405
    );
}


$query = trim(
    (string) (
        $_GET['q']
        ?? ''
    )
);


if ($query === '') {

    responder(
        [
            'ok' => true,
            'query' => '',
            'results' => [],
        ]
    );
}


if (mb_strlen($query) < 2) {

    responder(
        [
            'ok' => true,
            'query' => $query,
            'results' => [],
        ]
    );
}


if (mb_strlen($query) > 80) {

    responder(
        [
            'ok' => false,
            'error' =>
                'La búsqueda es demasiado larga.',
        ],
        400
    );
}


$like =
    '%' . $query . '%';


try {

    $pdo = db();


    /*
     * ------------------------------------------------------
     * Materias
     * ------------------------------------------------------
     */

    $sqlMaterias = '
        SELECT
            id,
            nombre,
            codigo,
            cuatrimestre,
            turno,
            comision,
            docente,
            color,
            activa
        FROM materias
        WHERE
            user_id = ?
            AND (
                nombre LIKE ?
                OR codigo LIKE ?
                OR docente LIKE ?
                OR comision LIKE ?
            )
        ORDER BY
            activa DESC,
            cuatrimestre IS NULL,
            cuatrimestre,
            nombre
        LIMIT 6
    ';


    $stmtMaterias =
        $pdo->prepare(
            $sqlMaterias
        );


    $stmtMaterias->execute(
        [
            $tenantUserId,
            $like,
            $like,
            $like,
            $like,
        ]
    );


    $materias =
        $stmtMaterias->fetchAll();


    /*
     * ------------------------------------------------------
     * Eventos
     * ------------------------------------------------------
     */

    $sqlEventos = '
        SELECT
            e.id,
            e.titulo,
            e.tipo,
            e.fecha,
            e.hora,
            e.lugar,
            e.estado,
            e.nota,

            m.nombre
                AS materia_nombre,

            m.codigo
                AS materia_codigo,

            m.color
                AS materia_color

        FROM eventos_academicos e

        INNER JOIN materias m
            ON m.id = e.materia_id
           AND m.user_id = e.user_id

        WHERE
            e.user_id = ?
            AND (
                e.titulo LIKE ?
                OR e.tipo LIKE ?
                OR e.estado LIKE ?
                OR e.lugar LIKE ?
                OR m.nombre LIKE ?
                OR m.codigo LIKE ?
                OR DATE_FORMAT(
                    e.fecha,
                    "%d/%m/%Y"
                ) LIKE ?
                OR DATE_FORMAT(
                    e.fecha,
                    "%Y-%m-%d"
                ) LIKE ?
            )

        ORDER BY
            CASE
                WHEN e.fecha >= CURDATE()
                THEN 0
                ELSE 1
            END,
            e.fecha ASC,
            e.id DESC

        LIMIT 8
    ';


    $stmtEventos =
        $pdo->prepare(
            $sqlEventos
        );


    $stmtEventos->execute(
        [
            $tenantUserId,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
        ]
    );


    $eventos =
        $stmtEventos->fetchAll();


    /*
     * ------------------------------------------------------
     * Tareas
     * ------------------------------------------------------
     */

    $sqlTareas = '
        SELECT
            t.id,
            t.titulo,
            t.descripcion,
            t.prioridad,
            t.estado,
            t.fecha_limite,
            t.hora_limite,

            m.nombre
                AS materia_nombre,

            m.codigo
                AS materia_codigo,

            m.color
                AS materia_color,

            e.titulo
                AS evento_titulo

        FROM tareas t

        LEFT JOIN materias m
            ON m.id = t.materia_id
           AND m.user_id = t.user_id

        LEFT JOIN eventos_academicos e
            ON e.id = t.evento_id
           AND e.user_id = t.user_id

        WHERE
            t.user_id = ?
            AND (
                t.titulo LIKE ?
                OR t.descripcion LIKE ?
                OR t.prioridad LIKE ?
                OR t.estado LIKE ?
                OR m.nombre LIKE ?
                OR m.codigo LIKE ?
                OR e.titulo LIKE ?
                OR DATE_FORMAT(
                    t.fecha_limite,
                    "%d/%m/%Y"
                ) LIKE ?
                OR DATE_FORMAT(
                    t.fecha_limite,
                    "%Y-%m-%d"
                ) LIKE ?
            )

        ORDER BY
            CASE t.estado
                WHEN "EN_PROGRESO"
                    THEN 0
                WHEN "PENDIENTE"
                    THEN 1
                WHEN "COMPLETADA"
                    THEN 2
                ELSE 3
            END,

            t.fecha_limite IS NULL,
            t.fecha_limite ASC,

            CASE t.prioridad
                WHEN "URGENTE"
                    THEN 1
                WHEN "ALTA"
                    THEN 2
                WHEN "MEDIA"
                    THEN 3
                ELSE 4
            END,

            t.id DESC

        LIMIT 8
    ';


    $stmtTareas =
        $pdo->prepare(
            $sqlTareas
        );


    $stmtTareas->execute(
        [
            $tenantUserId,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
        ]
    );


    $tareas =
        $stmtTareas->fetchAll();


    /*
     * ------------------------------------------------------
     * Respuesta normalizada
     * ------------------------------------------------------
     */

    $results = [];


    foreach (
        $materias
        as $materia
    ) {

        $meta = [];


        if (
            $materia['cuatrimestre']
            !== null
        ) {

            $meta[] =
                (int) $materia['cuatrimestre']
                . '° cuatrimestre';
        }


        $turno =
            etiquetaTurnoBusqueda(
                $materia['turno']
            );


        if ($turno !== '') {
            $meta[] = $turno;
        }


        if (
            $materia['comision']
            !== null
        ) {

            $meta[] =
                $materia['comision'];
        }


        if (
            $materia['docente']
            !== null
        ) {

            $meta[] =
                $materia['docente'];
        }


        $titulo =
            $materia['nombre'];


        if (
            $materia['codigo']
            !== null
        ) {

            $titulo =
                '['
                . $materia['codigo']
                . '] '
                . $titulo;
        }


        $results[] = [
            'type' => 'materia',

            'title' =>
                $titulo,

            'subtitle' =>
                implode(
                    ' · ',
                    $meta
                ),

            'badge' =>
                (int) $materia['activa']
                    === 1
                    ? 'Materia activa'
                    : 'Materia inactiva',

            'url' =>
                '/materia.php?id='
                . (int) $materia['id'],

            'color' =>
                $materia['color'],
        ];
    }


    foreach (
        $eventos
        as $evento
    ) {

        $fecha =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $evento['fecha']
            );


        $fechaTexto =
            $fecha !== false
                ? $fecha->format(
                    'd/m/Y'
                )
                : $evento['fecha'];


        if (
            $evento['hora']
            !== null
        ) {

            $fechaTexto .=
                ' · '
                . substr(
                    $evento['hora'],
                    0,
                    5
                );
        }


        $materiaTexto =
            $evento['materia_nombre'];


        if (
            $evento['materia_codigo']
            !== null
        ) {

            $materiaTexto =
                '['
                . $evento['materia_codigo']
                . '] '
                . $materiaTexto;
        }


        $results[] = [
            'type' => 'evento',

            'title' =>
                $evento['titulo'],

            'subtitle' =>
                $materiaTexto
                . ' · '
                . $fechaTexto,

            'badge' =>
                etiquetaTipoBusqueda(
                    $evento['tipo']
                )
                . ' · '
                . etiquetaEstadoBusqueda(
                    $evento['estado']
                ),

            'url' =>
                '/agenda.php#evento-'
                . (int) $evento['id'],

            'color' =>
                $evento['materia_color'],
        ];
    }





    foreach (
        $tareas
        as $tarea
    ) {

        $fechaTexto =
            'Sin fecha';


        if (
            !empty(
                $tarea[
                    'fecha_limite'
                ]
            )
        ) {

            $fecha =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $tarea[
                        'fecha_limite'
                    ]
                );


            $fechaTexto =
                $fecha !== false
                    ? $fecha->format(
                        'd/m/Y'
                    )
                    : $tarea[
                        'fecha_limite'
                    ];


            if (
                $tarea[
                    'hora_limite'
                ]
                !== null
            ) {

                $fechaTexto .=
                    ' · '
                    . substr(
                        $tarea[
                            'hora_limite'
                        ],
                        0,
                        5
                    );
            }
        }


        $materiaTexto =
            'Sin materia';


        if (
            !empty(
                $tarea[
                    'materia_nombre'
                ]
            )
        ) {

            $materiaTexto =
                $tarea[
                    'materia_nombre'
                ];


            if (
                !empty(
                    $tarea[
                        'materia_codigo'
                    ]
                )
            ) {

                $materiaTexto =
                    '['
                    . $tarea[
                        'materia_codigo'
                    ]
                    . '] '
                    . $materiaTexto;
            }
        }


        $subtitle =
            $materiaTexto
            . ' · '
            . $fechaTexto;


        if (
            !empty(
                $tarea[
                    'evento_titulo'
                ]
            )
        ) {

            $subtitle .=
                ' · '
                . $tarea[
                    'evento_titulo'
                ];
        }


        $results[] = [
            'type' =>
                'tarea',

            'title' =>
                $tarea[
                    'titulo'
                ],

            'subtitle' =>
                $subtitle,

            'badge' =>
                'Tarea · '
                . etiquetaPrioridadTareaBusqueda(
                    $tarea[
                        'prioridad'
                    ]
                )
                . ' · '
                . etiquetaEstadoTareaBusqueda(
                    $tarea[
                        'estado'
                    ]
                ),

            'url' =>
                '/tareas.php?editar='
                . (int) $tarea['id']
                . '#task-form',

            'color' =>
                $tarea[
                    'materia_color'
                ]
                ?? '#587089',
        ];
    }


    responder(
        [
            'ok' => true,
            'query' => $query,
            'results' => $results,
        ]
    );


} catch (Throwable $e) {

    responder(
        [
            'ok' => false,
            'error' =>
                'No se pudo realizar la búsqueda.',
        ],
        500
    );
}
