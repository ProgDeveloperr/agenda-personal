<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/materias_repository.php';

iniciarSesion();

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, max-age=0'
);

header(
    'X-Content-Type-Options: nosniff'
);


function quickAddResponder(
    array $payload,
    int $status = 200
): never {

    http_response_code(
        $status
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}


if (
    $_SERVER['REQUEST_METHOD']
    !== 'GET'
) {

    header(
        'Allow: GET'
    );

    quickAddResponder(
        [
            'ok' => false,
            'error' =>
                'Método no permitido.',
        ],
        405
    );
}


try {

    $materiasTodas =
        listarMaterias($tenantUserId);

    $materias =
        array_values(
            array_filter(
                $materiasTodas,
                static fn (
                    array $materia
                ): bool =>
                    (int) $materia['activa']
                    === 1
            )
        );


    $materiasPayload =
        array_map(
            static function (
                array $materia
            ): array {

                return [
                    'id' =>
                        (int) $materia['id'],

                    'nombre' =>
                        (string) $materia['nombre'],

                    'codigo' =>
                        $materia['codigo']
                            !== null
                            ? (string) $materia['codigo']
                            : null,

                    'color' =>
                        (string) $materia['color'],

                    'cuatrimestre' =>
                        $materia['cuatrimestre']
                            !== null
                            ? (int) $materia['cuatrimestre']
                            : null,
                ];
            },
            $materias
        );


    quickAddResponder(
        [
            'ok' => true,

            'csrf_token' =>
                csrfToken(),

            'materias' =>
                $materiasPayload,

            'tipos' => [
                'PARCIAL' =>
                    'Parcial',

                'FINAL' =>
                    'Final',

                'RECUPERATORIO' =>
                    'Recuperatorio',

                'TP' =>
                    'Trabajo práctico',

                'ENTREGA' =>
                    'Entrega',

                'OTRO' =>
                    'Otro',
            ],

            'estados' => [
                'PENDIENTE' =>
                    'Pendiente',

                'ENTREGADO' =>
                    'Entregado',

                'APROBADO' =>
                    'Aprobado',

                'DESAPROBADO' =>
                    'Desaprobado',

                'AUSENTE' =>
                    'Ausente',

                'CANCELADO' =>
                    'Cancelado',
            ],
        ]
    );


} catch (Throwable $e) {

    quickAddResponder(
        [
            'ok' => false,
            'error' =>
                'No se pudo cargar Quick Add.',
        ],
        500
    );
}
