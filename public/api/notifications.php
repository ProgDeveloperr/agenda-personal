<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/notificaciones_repository.php';

iniciarSesion();


header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);


function responderNotificaciones(
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


function payloadNotificaciones(
    int $tenantUserId
): array
{
    $items = [];


    foreach (
        listarNotificacionesActivas($tenantUserId, 6)
        as $notificacion
    ) {

        $origen =
            (string) (
                $notificacion['origen']
                ?? 'EVENTO'
            );


        $eventoId =
            $notificacion['evento_id']
            !== null
                ? (int) $notificacion['evento_id']
                : null;


        $tareaId =
            $notificacion['tarea_id']
            !== null
                ? (int) $notificacion['tarea_id']
                : null;


        $materiaId =
            $notificacion['materia_id']
            !== null
                ? (int) $notificacion['materia_id']
                : null;


        $url =
            $origen === 'TAREA'
            && $tareaId !== null
                ? '/tareas.php?editar='
                    . $tareaId
                    . '#task-form'

                : '/agenda.php#evento-'
                    . (int) $eventoId;


        $materiaCodigo =
            trim(
                (string) (
                    $notificacion['materia_codigo']
                    ?? ''
                )
            );


        $materiaNombre =
            trim(
                (string) (
                    $notificacion['materia_nombre']
                    ?? ''
                )
            );


        if (
            $materiaCodigo === ''
            && $materiaNombre === ''
        ) {

            $materiaNombre =
                $origen === 'TAREA'
                    ? 'Tarea'
                    : 'Evento';
        }


        $items[] = [
            'id' =>
                (int) $notificacion['id'],

            'origen' =>
                $origen,

            'evento_id' =>
                $eventoId,

            'tarea_id' =>
                $tareaId,

            'materia_id' =>
                $materiaId,

            'tipo' =>
                (string) $notificacion['tipo'],

            'titulo' =>
                (string) $notificacion['titulo'],

            'mensaje' =>
                (string) $notificacion['mensaje'],

            'fecha_objetivo' =>
                (string) $notificacion['fecha_objetivo'],

            'leida' =>
                $notificacion['leida_at']
                !== null,

            'materia_codigo' =>
                $materiaCodigo,

            'materia_nombre' =>
                $materiaNombre,

            'materia_color' =>
                (string) (
                    $notificacion['materia_color']
                    ?: '#5794ff'
                ),

            'evento_titulo' =>
                $notificacion['evento_titulo'],

            'evento_estado' =>
                $notificacion['evento_estado'],

            'evento_fecha' =>
                $notificacion['evento_fecha'],

            'evento_hora' =>
                $notificacion['evento_hora'],

            'tarea_titulo' =>
                $notificacion['tarea_titulo'],

            'tarea_prioridad' =>
                $notificacion['tarea_prioridad'],

            'tarea_estado' =>
                $notificacion['tarea_estado'],

            'tarea_fecha_limite' =>
                $notificacion['tarea_fecha_limite'],

            'tarea_hora_limite' =>
                $notificacion['tarea_hora_limite'],

            'url' =>
                $url,
        ];
    }


    return [
        'ok' =>
            true,

        'csrf_token' =>
            csrfToken(),

        'active_count' =>
            contarNotificacionesActivas($tenantUserId),

        'unread_count' =>
            contarNotificacionesNoLeidas($tenantUserId),

        'notifications' =>
            $items,
    ];
}


$method =
    strtoupper(
        (string) (
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        )
    );


if ($method === 'GET') {

    responderNotificaciones(
        payloadNotificaciones(
        $tenantUserId
    )
    );
}


if ($method !== 'POST') {

    responderNotificaciones(
        [
            'ok' => false,
            'error' => 'Método no permitido.',
        ],
        405
    );
}


if (!validarCsrf()) {

    responderNotificaciones(
        [
            'ok' => false,
            'error' => 'Token CSRF inválido.',
        ],
        403
    );
}


$action =
    trim(
        (string) (
            $_POST['action']
            ?? ''
        )
    );


switch ($action) {

    case 'mark_read':

        $id =
            filter_input(
                INPUT_POST,
                'id',
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );


        if (
            $id === false
            || $id === null
        ) {

            responderNotificaciones(
                [
                    'ok' => false,
                    'error' => 'Notificación inválida.',
                ],
                422
            );
        }


        marcarNotificacionLeida($tenantUserId, 
            (int) $id
        );

        break;


    case 'mark_all_read':

        marcarTodasNotificacionesLeidas($tenantUserId);

        break;


    case 'dismiss':

        $id =
            filter_input(
                INPUT_POST,
                'id',
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );


        if (
            $id === false
            || $id === null
        ) {

            responderNotificaciones(
                [
                    'ok' => false,
                    'error' => 'Notificación inválida.',
                ],
                422
            );
        }


        descartarNotificacion($tenantUserId, 
            (int) $id
        );

        break;


    default:

        responderNotificaciones(
            [
                'ok' => false,
                'error' => 'Acción inválida.',
            ],
            422
        );
}


responderNotificaciones(
    payloadNotificaciones(
        $tenantUserId
    )
);
