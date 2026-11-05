<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/vida_repository.php';


header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);


iniciarSesion();


function responderVida(
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


function validarIndiceVidaApi(
    mixed $valor
): ?int {

    $indice =
        filter_var(
            $valor,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' =>
                        0,

                    'max_range' =>
                        1079,
                ],
            ]
        );


    if ($indice === false) {
        return null;
    }


    return
        (int) $indice;
}


function longitudVidaUtf8(
    string $texto
): int {

    if (
        function_exists(
            'mb_strlen'
        )
    ) {

        return
            mb_strlen(
                $texto,
                'UTF-8'
            );
    }


    $resultado =
        preg_match_all(
            '/./us',
            $texto,
            $matches
        );


    return
        $resultado !== false
            ? $resultado
            : strlen($texto);
}


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {

    responderVida(
        [
            'ok' => false,
            'error' => 'Método no permitido.',
        ],
        405
    );
}


if (!validarCsrf()) {

    responderVida(
        [
            'ok' => false,
            'error' => 'Token CSRF inválido.',
        ],
        403
    );
}


if (
    obtenerFechaNacimientoVida($tenantUserId)
    === null
) {

    responderVida(
        [
            'ok' => false,

            'error' =>
                'Primero configurá la fecha de nacimiento.',
        ],
        409
    );
}


$accion =
    trim(
        (string) (
            $_POST['accion']
            ?? ''
        )
    );


if ($accion === 'list_notes') {

    responderVida(
        [
            'ok' =>
                true,

            'notes_count' =>
                contarNotasVida($tenantUserId),

            'notes' =>
                listarNotasVida($tenantUserId),
        ]
    );
}


$indice =
    validarIndiceVidaApi(
        $_POST['indice']
        ?? null
    );


if ($indice === null) {

    responderVida(
        [
            'ok' =>
                false,

            'error' =>
                'Índice inválido.',
        ],
        422
    );
}


try {

    switch ($accion) {

        case 'detail':

            $detalle =
                buscarMesVidaDetalle(
                    $tenantUserId,
                    $indice
                );


            responderVida(
                [
                    'ok' =>
                        true,

                    'indice' =>
                        $indice,

                    'nivel' =>
                        (int) $detalle['nivel'],

                    'nota' =>
                        $detalle['nota'],

                    'updated_at' =>
                        $detalle['updated_at'],

                    'has_note' =>
                        $detalle['nota'] !== null
                        && trim(
                            (string) $detalle['nota']
                        ) !== '',
                ]
            );


        case 'toggle':

            $nivel =
                alternarMesVidaPreservandoNota(
                    $tenantUserId,
                    $indice
                );


            $detalle =
                buscarMesVidaDetalle(
                    $tenantUserId,
                    $indice
                );


            $marcados =
                contarMesesVidaMarcados($tenantUserId);


            $porcentaje =
                round(
                    (
                        $marcados
                        / 1080
                    )
                    * 100,
                    1
                );


            responderVida(
                [
                    'ok' =>
                        true,

                    'indice' =>
                        $indice,

                    'nivel' =>
                        $nivel,

                    'nota' =>
                        $detalle['nota'],

                    'has_note' =>
                        $detalle['nota'] !== null
                        && trim(
                            (string) $detalle['nota']
                        ) !== '',

                    'marcados' =>
                        $marcados,

                    'porcentaje' =>
                        $porcentaje,
                ]
            );


        case 'save_note':

            $nota =
                trim(
                    (string) (
                        $_POST['nota']
                        ?? ''
                    )
                );


            if (
                longitudVidaUtf8(
                    $nota
                ) > 500
            ) {

                responderVida(
                    [
                        'ok' =>
                            false,

                        'error' =>
                            'La nota puede tener como máximo 500 caracteres.',
                    ],
                    422
                );
            }


            $detalle =
                guardarNotaMesVida(
                    $tenantUserId,
                    $indice,
                    $nota !== ''
                        ? $nota
                        : null
                );


            responderVida(
                [
                    'ok' =>
                        true,

                    'indice' =>
                        $indice,

                    'nivel' =>
                        (int) $detalle['nivel'],

                    'nota' =>
                        $detalle['nota'],

                    'has_note' =>
                        $detalle['nota'] !== null
                        && trim(
                            (string) $detalle['nota']
                        ) !== '',

                    'notes_count' =>
                        contarNotasVida($tenantUserId),
                ]
            );


        default:

            responderVida(
                [
                    'ok' =>
                        false,

                    'error' =>
                        'Acción no reconocida.',
                ],
                400
            );
    }


} catch (Throwable $e) {

    responderVida(
        [
            'ok' =>
                false,

            'error' =>
                'No se pudo guardar el cambio.',
        ],
        500
    );
}
