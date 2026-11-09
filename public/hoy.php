<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/tareas_repository.php';
require_once '/var/www/app/eventos_repository.php';


iniciarSesion();


function h(
    mixed $valor
): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function hoyFlash(
    string $tipo,
    string $mensaje
): void {

    $_SESSION['hoy_flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];
}


function consumirHoyFlash(): ?array
{
    $flash =
        $_SESSION['hoy_flash']
        ?? null;


    unset(
        $_SESSION['hoy_flash']
    );


    return is_array($flash)
        ? $flash
        : null;
}


function hoyRedirect(): never
{
    header(
        'Location: /hoy.php',
        true,
        303
    );

    exit;
}


function hoyPrioridadLabel(
    string $prioridad
): string {

    return match ($prioridad) {
        'URGENTE' => 'Urgente',
        'ALTA' => 'Alta',
        'MEDIA' => 'Media',
        'BAJA' => 'Baja',
        default => $prioridad,
    };
}


function hoyEstadoTareaLabel(
    string $estado
): string {

    return match ($estado) {
        'PENDIENTE' => 'Pendiente',
        'EN_PROGRESO' => 'En progreso',
        'COMPLETADA' => 'Completada',
        'CANCELADA' => 'Cancelada',
        default => $estado,
    };
}


function hoyEstadoEventoLabel(
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


function hoyTipoEventoLabel(
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


function hoyHora(
    ?string $hora
): string {

    if (
        $hora === null
        || $hora === ''
    ) {
        return 'Sin horario';
    }


    return substr(
        $hora,
        0,
        5
    );
}


function hoyMes(
    int $mes
): string {

    return [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ][$mes] ?? '';
}


function hoyDiaSemana(
    int $dia
): string {

    return [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
        7 => 'domingo',
    ][$dia] ?? '';
}


function hoyFechaLarga(
    DateTimeImmutable $fecha
): string {

    return ucfirst(
        hoyDiaSemana(
            (int) $fecha->format('N')
        )
    )
    . ' '
    . $fecha->format('j')
    . ' de '
    . hoyMes(
        (int) $fecha->format('n')
    );
}


function hoyDiasHasta(
    ?string $fecha,
    DateTimeImmutable $hoy,
    DateTimeZone $zona
): ?int {

    if (
        $fecha === null
        || $fecha === ''
    ) {
        return null;
    }


    $objeto =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $zona
        );


    if ($objeto === false) {
        return null;
    }


    return (int)
        $hoy
            ->diff($objeto)
            ->format('%r%a');
}


function hoyClaveFoco(
    array $tarea,
    DateTimeImmutable $hoy,
    DateTimeZone $zona
): array {

    $dias =
        hoyDiasHasta(
            $tarea['fecha_limite']
            ?? null,
            $hoy,
            $zona
        );


    $prioridad =
        [
            'URGENTE' => 1,
            'ALTA' => 2,
            'MEDIA' => 3,
            'BAJA' => 4,
        ][$tarea['prioridad'] ?? '']
        ?? 9;


    $estado =
        (string) (
            $tarea['estado']
            ?? ''
        );


    /*
     * Orden determinista:
     *
     * 10 vencidas
     * 20 vencen hoy
     * 30 ya están en progreso
     * 40 alta/urgente dentro de 3 días
     * 50 próxima por fecha
     * 60 sin fecha
     */

    if (
        $dias !== null
        && $dias < 0
    ) {
        $tier = 10;

    } elseif ($dias === 0) {
        $tier = 20;

    } elseif (
        $estado === 'EN_PROGRESO'
    ) {
        $tier = 30;

    } elseif (
        $dias !== null
        && $dias >= 1
        && $dias <= 3
        && in_array(
            $tarea['prioridad'],
            [
                'URGENTE',
                'ALTA',
            ],
            true
        )
    ) {
        $tier = 40;

    } elseif ($dias !== null) {
        $tier = 50;

    } else {
        $tier = 60;
    }


    $fechaOrden =
        $dias === null
            ? 99999
            : $dias;


    $hora =
        $tarea['hora_limite']
        ?: '23:59:59';


    return [
        $tier,
        $tier === 50
            ? $fechaOrden
            : $prioridad,
        $prioridad,
        $fechaOrden,
        $hora,
        (int) $tarea['id'],
    ];
}


function hoyGrupoTarea(
    array $tarea,
    DateTimeImmutable $hoy,
    DateTimeZone $zona
): string {

    $dias =
        hoyDiasHasta(
            $tarea['fecha_limite']
            ?? null,
            $hoy,
            $zona
        );


    if (
        $dias !== null
        && $dias < 0
    ) {
        return 'VENCIDA';
    }


    if ($dias === 0) {
        return 'HOY';
    }


    if (
        $tarea['estado']
        === 'EN_PROGRESO'
    ) {
        return 'EN PROGRESO';
    }


    if (
        $dias !== null
        && $dias >= 1
        && $dias <= 3
        && in_array(
            $tarea['prioridad'],
            [
                'URGENTE',
                'ALTA',
            ],
            true
        )
    ) {
        return 'PRIORIDAD';
    }


    if ($dias !== null) {
        return 'PRÓXIMA';
    }


    return 'SIN FECHA';
}


function hoyMotivoTarea(
    array $tarea,
    DateTimeImmutable $hoy,
    DateTimeZone $zona
): string {

    $dias =
        hoyDiasHasta(
            $tarea['fecha_limite']
            ?? null,
            $hoy,
            $zona
        );


    if (
        $dias !== null
        && $dias < 0
    ) {

        $cantidad =
            abs($dias);

        return
            'Vencida hace '
            . $cantidad
            . (
                $cantidad === 1
                    ? ' día'
                    : ' días'
            );
    }


    if ($dias === 0) {
        return 'Vence hoy';
    }


    if (
        $tarea['estado']
        === 'EN_PROGRESO'
    ) {
        return 'Ya está en progreso';
    }


    if ($dias === 1) {
        return 'Vence mañana';
    }


    if (
        $dias !== null
        && $dias > 1
    ) {
        return
            'Vence en '
            . $dias
            . ' días';
    }


    return 'Sin fecha límite';
}


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {

    if (!validarCsrf()) {

        http_response_code(
            403
        );

        echo 'Token CSRF inválido.';

        exit;
    }


    $accion =
        trim(
            (string) (
                $_POST['accion']
                ?? ''
            )
        );


    try {

        if ($accion !== 'estado') {

            throw new InvalidArgumentException(
                'Acción no reconocida.'
            );
        }


        $idRaw =
            trim(
                (string) (
                    $_POST['id']
                    ?? ''
                )
            );


        if (
            !ctype_digit($idRaw)
            || (int) $idRaw <= 0
        ) {

            throw new InvalidArgumentException(
                'La tarea indicada no es válida.'
            );
        }


        $id =
            (int) $idRaw;


        $estado =
            trim(
                (string) (
                    $_POST['estado']
                    ?? ''
                )
            );


        if (
            !in_array(
                $estado,
                [
                    'PENDIENTE',
                    'EN_PROGRESO',
                    'COMPLETADA',
                ],
                true
            )
        ) {

            throw new InvalidArgumentException(
                'Estado operativo no permitido.'
            );
        }


        if (
            buscarTarea($tenantUserId,
                $id
            ) === null
        ) {

            throw new InvalidArgumentException(
                'La tarea ya no existe.'
            );
        }


        cambiarEstadoTarea($tenantUserId,
            $id,
            $estado
        );


        $mensaje =
            match ($estado) {

                'EN_PROGRESO' =>
                    'Tarea iniciada.',

                'PENDIENTE' =>
                    'Tarea pausada.',

                'COMPLETADA' =>
                    'Tarea completada.',

                default =>
                    'Estado actualizado.',
            };


        hoyFlash(
            'success',
            $mensaje
        );


        hoyRedirect();

    } catch (
        InvalidArgumentException $e
    ) {

        hoyFlash(
            'error',
            $e->getMessage()
        );


        hoyRedirect();

    } catch (Throwable $e) {

        error_log(
            'Agenda Hoy: '
            . $e->getMessage()
        );


        hoyFlash(
            'error',
            'No se pudo completar la operación.'
        );


        hoyRedirect();
    }
}


$csrf =
    csrfToken();


$flash =
    consumirHoyFlash();


$zona =
    new DateTimeZone(
        'America/Argentina/Buenos_Aires'
    );


$ahora =
    new DateTimeImmutable(
        'now',
        $zona
    );


$hoy =
    new DateTimeImmutable(
        'today',
        $zona
    );


$manana =
    $hoy->modify(
        '+1 day'
    );


$fin14Exclusivo =
    $hoy->modify(
        '+15 days'
    );


$hoySql =
    $hoy->format(
        'Y-m-d'
    );


$todasTareas =
    listarTareas($tenantUserId);


$tareasAbiertas =
    array_values(
        array_filter(
            $todasTareas,
            static fn (
                array $tarea
            ): bool =>
                in_array(
                    $tarea['estado'],
                    [
                        'PENDIENTE',
                        'EN_PROGRESO',
                    ],
                    true
                )
        )
    );


$tareasVencidas = [];
$tareasHoy = [];
$tareasProximas14 = [];
$tareasSinFecha = [];
$requiereAtencion = [];


foreach (
    $tareasAbiertas
    as $tarea
) {

    $dias =
        hoyDiasHasta(
            $tarea['fecha_limite']
            ?? null,
            $hoy,
            $zona
        );


    if ($dias === null) {

        $tareasSinFecha[] =
            $tarea;

    } elseif ($dias < 0) {

        $tareasVencidas[] =
            $tarea;

    } elseif ($dias === 0) {

        $tareasHoy[] =
            $tarea;

    } elseif (
        $dias >= 1
        && $dias <= 14
    ) {

        $tareasProximas14[] =
            $tarea;
    }


    $esAltaCercana =
        $dias !== null
        && $dias >= 0
        && $dias <= 3
        && in_array(
            $tarea['prioridad'],
            [
                'URGENTE',
                'ALTA',
            ],
            true
        );


    if (
        (
            $dias !== null
            && $dias < 0
        )
        || $tarea['estado']
            === 'EN_PROGRESO'
        || $esAltaCercana
    ) {

        $requiereAtencion[] =
            $tarea;
    }
}


$tareasOrdenadas =
    $tareasAbiertas;


usort(
    $tareasOrdenadas,
    static function (
        array $a,
        array $b
    ) use (
        $hoy,
        $zona
    ): int {

        return
            hoyClaveFoco(
                $a,
                $hoy,
                $zona
            )
            <=>
            hoyClaveFoco(
                $b,
                $hoy,
                $zona
            );
    }
);


$siguienteTarea =
    $tareasOrdenadas[0]
    ?? null;


usort(
    $tareasProximas14,
    static function (
        array $a,
        array $b
    ) use (
        $hoy,
        $zona
    ): int {

        return
            hoyClaveFoco(
                $a,
                $hoy,
                $zona
            )
            <=>
            hoyClaveFoco(
                $b,
                $hoy,
                $zona
            );
    }
);


usort(
    $requiereAtencion,
    static function (
        array $a,
        array $b
    ) use (
        $hoy,
        $zona
    ): int {

        return
            hoyClaveFoco(
                $a,
                $hoy,
                $zona
            )
            <=>
            hoyClaveFoco(
                $b,
                $hoy,
                $zona
            );
    }
);


$eventosHoy =
    array_values(
        array_filter(
            listarEventosPorRango($tenantUserId, 
                $hoySql,
                $manana->format(
                    'Y-m-d'
                )
            ),
            static fn (
                array $evento
            ): bool =>
                $evento['estado']
                !== 'CANCELADO'
        )
    );


$eventos14 =
    array_values(
        array_filter(
            listarEventosPorRango($tenantUserId, 
                $hoySql,
                $fin14Exclusivo->format(
                    'Y-m-d'
                )
            ),
            static fn (
                array $evento
            ): bool =>
                $evento['estado']
                !== 'CANCELADO'
        )
    );


$eventosPendientesHoy =
    array_values(
        array_filter(
            $eventosHoy,
            static fn (
                array $evento
            ): bool =>
                $evento['estado']
                === 'PENDIENTE'
        )
    );


$proximoEventoPendiente = null;


foreach (
    $eventos14
    as $evento
) {

    if (
        $evento['estado']
        === 'PENDIENTE'
    ) {

        $proximoEventoPendiente =
            $evento;

        break;
    }
}


$cargaAccionable =
    count($tareasVencidas)
    + count($tareasHoy)
    + count($eventosPendientesHoy);


$estadoDia =
    match (true) {

        count(
            $tareasVencidas
        ) > 0 =>
            'Hay tareas vencidas',

        $cargaAccionable > 0 =>
            'Hay acciones para hoy',

        default =>
            'Día bajo control',
    };


$horaActual =
    (int)
        $ahora->format('G');


$saludo =
    match (true) {

        $horaActual < 12 =>
            'Buenos días',

        $horaActual < 20 =>
            'Buenas tardes',

        default =>
            'Buenas noches',
    };

?>
<!doctype html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#07111b"
    >

    <title>
        Hoy | Agenda Personal
    </title>


        <!-- ARK3360_R35J_D_FLATPICKR_BASE_CSS -->
    <link rel="stylesheet" href="/assets/vendor/flatpickr-4.6.13/flatpickr.min.css?v=1b34a42552c96f10">
    <!-- /ARK3360_R35J_D_FLATPICKR_BASE_CSS -->
<link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/ui-v2.css?v=1"
    >

    <link
        rel="stylesheet"
        href="/assets/css/ui-v3.css?v=26"
    >
<!-- ARK3360_R35N_B_AGIL_ICON -->

<!-- /ARK3360_R35N_B_AGIL_ICON -->
<meta
        name="application-name"
        content="Agenda Personal"
    >

    <meta
        name="mobile-web-app-capable"
        content="yes"
    >

    <meta
        name="apple-mobile-web-app-capable"
        content="yes"
    >

    <meta
        name="apple-mobile-web-app-status-bar-style"
        content="black-translucent"
    >

    <meta
        name="apple-mobile-web-app-title"
        content="Agenda Personal"
    >

    <link rel="stylesheet" href="/assets/css/hoy-legibilidad.css?v=4">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-hoy.css?v=3">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
    <!-- ARK3360_R35B3_NOTIFICATION_BADGE -->
    <link rel="stylesheet" href="/assets/css/r35b-notification-badge.css?v=1">

<!-- ARK3360_R35C2_TODAY_EMPTY -->
<link rel="stylesheet" href="/assets/css/r35c-today-empty.css?v=2">

<!-- ARK3360_R35D_ACCOUNT_LIGHT -->
<link rel="stylesheet" href="/assets/css/r35d-account-light-fix.css?v=1">
<!-- ARK3360_R35I_E2_QUICK_DATE_PARITY -->
<link rel="stylesheet" href="/assets/css/r35i-e2-quick-date-parity.css?v=1">
<!-- /ARK3360_R35I_E2_QUICK_DATE_PARITY -->

<!-- ARK3360_R35M_D_NAVBAR_BRAND_GLOBAL -->
<link rel="stylesheet" href="/assets/css/r35m-d-navbar-brand-global.css?v=5905e4aebab7f230">
<!-- /ARK3360_R35M_D_NAVBAR_BRAND_GLOBAL -->

    <!-- ARK3360_R35N_C_AGIL_TASKBAR_BOOST -->
    <link rel="icon" href="/assets/icons/agil-hola-taskbar-32.png?v=3e4f3c6e8665eba2" sizes="32x32" type="image/png">
    <link rel="shortcut icon" href="/assets/icons/agil-hola-taskbar.ico?v=3e4f3c6e8665eba2">
    <link rel="apple-touch-icon" href="/assets/icons/agil-hola-taskbar-180.png?v=3e4f3c6e8665eba2">
    <link rel="manifest" href="/manifest.webmanifest?v=cba488cbbc0c086a">
    <!-- /ARK3360_R35N_C_AGIL_TASKBAR_BOOST -->

    <!-- ARK3360_R35Q_D_MOBILE_THEME_GLOBAL -->
    <link rel="stylesheet" href="/assets/css/r35q-d-mobile-theme-global.css?v=bb89cc627441c62d">
    <script src="/assets/js/r35s-d-mobile-theme-global.js?v=2abb38c899b16f70" defer></script>
    <!-- /ARK3360_R35Q_D_MOBILE_THEME_GLOBAL -->

    <!-- ARK3360_R35S_D_PROGRESSIVE_RESPONSIVE -->
    <script src="/assets/js/r35s-c-viewport-cssom-pilot.js?v=a1b051a863df6f0f"></script>
    <!-- /ARK3360_R35S_D_PROGRESSIVE_RESPONSIVE -->
</head>


<body class="ui-v3 page-today-v4">


<header class="app-header">

    <div class="app-header-inner">

        <a
            class="app-brand r35m-brand"
            href="/"
        >
      <span class="r35m-brand-lockup">
        <img class="r35m-brand-image r35m-brand-light" src="/assets/brand/agenda-personal-premium.svg?v=f471ddad7269fdfd" alt="Agenda Personal" width="259" height="76" decoding="async">
        <img class="r35m-brand-image r35m-brand-dark" src="/assets/brand/agenda-personal-premium-oscuro.svg?v=f20f6bada98c57b3" alt="Agenda Personal" width="259" height="76" decoding="async">
      </span>
    </a>


        <nav
            class="app-nav"
            aria-label="Navegación principal"
        >

            <a href="/">
                Inicio
            </a>

            <a
                class="active"
                href="/hoy.php"
            >
                Hoy
            </a>

            <a href="/agenda.php">
                Agenda
            </a>

            <a href="/tareas.php">
                Tareas
            </a>

            <a href="/semana.php">
                Semana
            </a>

            <a href="/calendario.php">
                Calendario
            </a>

            <a href="/vida.php">
                Vida
            </a>

            <a href="/materias.php">
                Materias
            </a>

            <a href="/rendimiento.php">
                Rendimiento
            </a>

        </nav>


        <div class="app-header-status">

            <span
                class="status-dot status-dot-online"
            ></span>

            <span>
                Focus diario
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<?php

function hoyRazonFoco(
    array $tarea,
    DateTimeImmutable $hoy,
    DateTimeZone $zona
): array {

    $tier =
        (int) (
            hoyClaveFoco(
                $tarea,
                $hoy,
                $zona
            )[0]
            ?? 99
        );


    return match ($tier) {

        10 => [
            'codigo' =>
                'VENCIDA',

            'titulo' =>
                'Tarea vencida',

            'detalle' =>
                'Una fecha límite vencida desplaza trabajo futuro.',
        ],

        20 => [
            'codigo' =>
                'VENCE_HOY',

            'titulo' =>
                'Vence hoy',

            'detalle' =>
                'Una tarea con límite hoy entra antes que trabajo futuro.',
        ],

        30 => [
            'codigo' =>
                'EN_PROGRESO',

            'titulo' =>
                'Continuidad de trabajo',

            'detalle' =>
                'Ya está en progreso, por eso conserva prioridad operativa.',
        ],

        40 => [
            'codigo' =>
                'PRIORIDAD_CERCANA',

            'titulo' =>
                'Prioridad cercana',

            'detalle' =>
                'Es alta o urgente y vence dentro de los próximos 3 días.',
        ],

        50 => [
            'codigo' =>
                'PROXIMA_FECHA',

            'titulo' =>
                'Próxima tarea por fecha',

            'detalle' =>
                'No hay presión inmediata; es la próxima fecha límite disponible.',
        ],

        default => [
            'codigo' =>
                'SIN_FECHA',

            'titulo' =>
                'Tarea abierta sin fecha',

            'detalle' =>
                'No quedan tareas fechadas con mayor prioridad operativa.',
        ],
    };
}


function hoyFechaCorta(
    ?string $fecha,
    DateTimeZone $zona
): string {

    if (
        $fecha === null
        || $fecha === ''
    ) {
        return 'Sin fecha';
    }


    $objeto =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $zona
        );


    return $objeto !== false
        ? $objeto->format(
            'd/m/Y'
        )
        : $fecha;
}


function hoyDistanciaLabel(
    ?int $dias
): string {

    if ($dias === null) {
        return 'Sin fecha';
    }


    if ($dias === 0) {
        return 'Hoy';
    }


    if ($dias === 1) {
        return 'Mañana';
    }


    if ($dias > 1) {

        return
            'En '
            . $dias
            . ' días';
    }


    $cantidad =
        abs(
            $dias
        );


    return
        'Hace '
        . $cantidad
        . (
            $cantidad === 1
                ? ' día'
                : ' días'
        );
}


function hoyMargenLabel(
    ?int $dias
): string {

    if ($dias === null) {
        return 'Sin relación temporal';
    }


    if ($dias === 0) {
        return 'Mismo día';
    }


    $cantidad =
        abs(
            $dias
        );


    if ($dias > 0) {

        return
            $cantidad
            . (
                $cantidad === 1
                    ? ' día después'
                    : ' días después'
            );
    }


    return
        $cantidad
        . (
            $cantidad === 1
                ? ' día antes'
                : ' días antes'
        );
}


$tareasProximos3 = [];


foreach (
    $tareasAbiertas
    as $tareaCarga
) {

    $diasCarga =
        hoyDiasHasta(
            $tareaCarga[
                'fecha_limite'
            ]
            ?? null,
            $hoy,
            $zona
        );


    if (
        $diasCarga !== null
        && $diasCarga >= 1
        && $diasCarga <= 3
    ) {

        $tareasProximos3[] =
            $tareaCarga;
    }
}


$eventosPendientes7 = [];


foreach (
    $eventos14
    as $eventoCarga
) {

    if (
        $eventoCarga[
            'estado'
        ] !== 'PENDIENTE'
    ) {
        continue;
    }


    $diasEventoCarga =
        hoyDiasHasta(
            $eventoCarga[
                'fecha'
            ]
            ?? null,
            $hoy,
            $zona
        );


    if (
        $diasEventoCarga !== null
        && $diasEventoCarga >= 0
        && $diasEventoCarga <= 7
    ) {

        $eventosPendientes7[] =
            $eventoCarga;
    }
}


$vencidasUrgentes = 0;


foreach (
    $tareasVencidas
    as $tareaVencida
) {

    if (
        $tareaVencida[
            'prioridad'
        ] === 'URGENTE'
    ) {

        $vencidasUrgentes++;
    }
}


/*
 * Presión diaria determinista:
 *
 * CRITICO:
 * - URGENTE vencida
 * - 2+ vencidas
 * - 4+ acciones inmediatas
 *
 * ALTO:
 * - alguna vencida
 * - 3+ acciones inmediatas
 * - 3+ tareas dentro de 3 días
 *
 * MODERADO:
 * - alguna acción inmediata
 * - 2+ tareas dentro de 3 días
 * - 2+ eventos pendientes dentro de 7 días
 *
 * CONTROLADO:
 * - ningún criterio anterior
 */

$presionCodigo =
    match (true) {

        $vencidasUrgentes > 0
        || count(
            $tareasVencidas
        ) >= 2
        || $cargaAccionable >= 4 =>
            'CRITICO',

        count(
            $tareasVencidas
        ) > 0
        || $cargaAccionable >= 3
        || count(
            $tareasProximos3
        ) >= 3 =>
            'ALTO',

        $cargaAccionable >= 1
        || count(
            $tareasProximos3
        ) >= 2
        || count(
            $eventosPendientes7
        ) >= 2 =>
            'MODERADO',

        default =>
            'CONTROLADO',
    };


$presionNivel =
    match (
        $presionCodigo
    ) {

        'CRITICO' =>
            'CRÍTICO',

        'ALTO' =>
            'ALTO',

        'MODERADO' =>
            'MODERADO',

        default =>
            'CONTROLADO',
    };


$presionClase =
    match (
        $presionCodigo
    ) {

        'CRITICO' =>
            'is-critical',

        'ALTO' =>
            'is-high',

        'MODERADO' =>
            'is-moderate',

        default =>
            'is-controlled',
    };


$presionDetalle =
    match (
        $presionCodigo
    ) {

        'CRITICO' =>
            'Hay acumulación crítica o trabajo urgente vencido.',

        'ALTO' =>
            'Existe presión operativa que requiere atención prioritaria.',

        'MODERADO' =>
            'Hay carga inmediata o concentración próxima de trabajo.',

        default =>
            'Sin vencidas ni concentración de trabajo inmediato.',
    };


$atencionConteos = [
    'VENCIDAS' => 0,
    'HOY' => 0,
    'EN_PROGRESO' => 0,
    'PRIORIDAD' => 0,
];


foreach (
    $requiereAtencion
    as $tareaAtencion
) {

    $grupoAtencion =
        hoyGrupoTarea(
            $tareaAtencion,
            $hoy,
            $zona
        );


    if (
        $grupoAtencion
        === 'VENCIDA'
    ) {

        $atencionConteos[
            'VENCIDAS'
        ]++;

    } elseif (
        $grupoAtencion
        === 'HOY'
    ) {

        $atencionConteos[
            'HOY'
        ]++;

    } elseif (
        $grupoAtencion
        === 'EN PROGRESO'
    ) {

        $atencionConteos[
            'EN_PROGRESO'
        ]++;

    } elseif (
        $grupoAtencion
        === 'PRIORIDAD'
    ) {

        $atencionConteos[
            'PRIORIDAD'
        ]++;
    }
}


$razonFoco =
    $siguienteTarea !== null
        ? hoyRazonFoco(
            $siguienteTarea,
            $hoy,
            $zona
        )
        : null;


$diasEventoFoco =
    $siguienteTarea !== null
    && !empty(
        $siguienteTarea[
            'evento_fecha'
        ]
    )
        ? hoyDiasHasta(
            $siguienteTarea[
                'evento_fecha'
            ],
            $hoy,
            $zona
        )
        : null;


$margenEventoFoco =
    null;


if (
    $siguienteTarea !== null
    && !empty(
        $siguienteTarea[
            'fecha_limite'
        ]
    )
    && !empty(
        $siguienteTarea[
            'evento_fecha'
        ]
    )
) {

    $fechaTareaFoco =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string)
                $siguienteTarea[
                    'fecha_limite'
                ],
            $zona
        );


    $fechaEventoFoco =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string)
                $siguienteTarea[
                    'evento_fecha'
                ],
            $zona
        );


    if (
        $fechaTareaFoco !== false
        && $fechaEventoFoco !== false
    ) {

        $margenEventoFoco =
            (int)
                $fechaTareaFoco
                    ->diff(
                        $fechaEventoFoco
                    )
                    ->format(
                        '%r%a'
                    );
    }
}

?>


<main class="today-shell-v4">


    <section class="today-hero-v4">

        <div>

            <span class="section-kicker">
                <?= h($saludo) ?>
            </span>

            <h1>
                Elegí lo que
                <span>sigue.</span>
            </h1>

            <p>
                <?= h(
                    hoyFechaLarga(
                        $hoy
                    )
                ) ?>.
                El foco se calcula con estado,
                fecha y prioridad; sin decisiones ocultas.
            </p>

        </div>


        <div
            class="
                today-hero-state-v4
                <?= h(
                    $presionClase
                ) ?>
            "
            data-pressure-level="<?= h(
                $presionCodigo
            ) ?>"
        >

            <span>
                PRESIÓN DEL DÍA
            </span>

            <strong>
                <?= h(
                    $presionNivel
                ) ?>
            </strong>

            <small>
                <?= h(
                    $presionDetalle
                ) ?>
            </small>

            <em>
                <?= h(
                    $estadoDia
                ) ?>
                ·
                <?= $cargaAccionable ?>
                <?= $cargaAccionable === 1
                    ? 'acción inmediata'
                    : 'acciones inmediatas' ?>
            </em>

        </div>

    </section>


    <?php if (
        $flash !== null
    ): ?>

        <div
            class="
                today-flash-v4
                <?= ($flash['tipo'] ?? '')
                    === 'error'
                        ? 'is-error'
                        : 'is-success' ?>
            "
        >

            <span>
                <?= ($flash['tipo'] ?? '')
                    === 'error'
                        ? '!'
                        : '✓' ?>
            </span>

            <strong>
                <?= h(
                    $flash['mensaje']
                    ?? ''
                ) ?>
            </strong>

        </div>

    <?php endif; ?>


    <section class="today-metrics-v4">

        <article>

            <span>
                Abiertas
            </span>

            <strong>
                <?= count(
                    $tareasAbiertas
                ) ?>
            </strong>

            <small>
                pendientes o en progreso
            </small>

        </article>


        <article class="<?= count(
            $tareasVencidas
        ) > 0
            ? 'is-danger'
            : '' ?>">

            <span>
                Vencidas
            </span>

            <strong>
                <?= count(
                    $tareasVencidas
                ) ?>
            </strong>

            <small>
                requieren recuperación
            </small>

        </article>


        <article>

            <span>
                Para hoy
            </span>

            <strong>
                <?= count(
                    $tareasHoy
                ) ?>
            </strong>

            <small>
                tareas con límite hoy
            </small>

        </article>


        <article>

            <span>
                Eventos hoy
            </span>

            <strong>
                <?= count(
                    $eventosHoy
                ) ?>
            </strong>

            <small>
                actividad académica
            </small>

        </article>

    </section>


    <section class="today-layout-v4">


        <div class="today-main-v4">


            <section class="today-next-v4">

                <div class="today-section-head-v4">

                    <div>

                        <span class="section-kicker">
                            Prioridad operativa
                        </span>

                        <h2>
                            Siguiente acción
                        </h2>

                    </div>


                    <a href="/tareas.php">
                        Abrir Tareas
                        <span>→</span>
                    </a>

                </div>


                <?php if (
                    $siguienteTarea !== null
                ): ?>

                    <?php

                    $diasSiguiente =
                        hoyDiasHasta(
                            $siguienteTarea[
                                'fecha_limite'
                            ],
                            $hoy,
                            $zona
                        );

                    ?>


                    <article
                        class="
                            today-focus-task-v4
                            priority-<?= h(
                                strtolower(
                                    $siguienteTarea[
                                        'prioridad'
                                    ]
                                )
                            ) ?>
                        "
                        style="--focus-color: <?= h(
                            $siguienteTarea[
                                'materia_color'
                            ]
                            ?? '#587089'
                        ) ?>;"
                        data-next-task-id="<?= (int)
                            $siguienteTarea['id'] ?>"
                        data-focus-reason="<?= h(
                            $razonFoco[
                                'codigo'
                            ]
                            ?? ''
                        ) ?>"
                        data-focus-task-days="<?= $diasSiguiente
                            ?? '' ?>"
                        data-focus-event-days="<?= $diasEventoFoco
                            ?? '' ?>"
                        data-focus-event-gap="<?= $margenEventoFoco
                            ?? '' ?>"
                    >

                        <span
                            class="today-focus-accent-v4"
                        ></span>


                        <div class="today-focus-mark-v4">

                            <span>
                                <?= h(
                                    hoyGrupoTarea(
                                        $siguienteTarea,
                                        $hoy,
                                        $zona
                                    )
                                ) ?>
                            </span>

                            <strong>
                                <?= $diasSiguiente !== null
                                    ? (
                                        $diasSiguiente >= 0
                                            ? $diasSiguiente
                                            : abs(
                                                $diasSiguiente
                                            )
                                    )
                                    : '—' ?>
                            </strong>

                            <small>
                                <?= $diasSiguiente === null
                                    ? 'sin fecha'
                                    : (
                                        $diasSiguiente < 0
                                            ? 'días vencida'
                                            : (
                                                $diasSiguiente === 0
                                                    ? 'hoy'
                                                    : 'días'
                                            )
                                    ) ?>
                            </small>

                        </div>


                        <div class="today-focus-copy-v4">

                            <div class="today-focus-meta-v4">

                                <span>
                                    <?= h(
                                        $siguienteTarea[
                                            'materia_codigo'
                                        ]
                                        ?: (
                                            $siguienteTarea[
                                                'materia_nombre'
                                            ]
                                            ?: 'Sin materia'
                                        )
                                    ) ?>
                                </span>

                                <i>·</i>

                                <span>
                                    <?= h(
                                        hoyPrioridadLabel(
                                            $siguienteTarea[
                                                'prioridad'
                                            ]
                                        )
                                    ) ?>
                                </span>

                                <i>·</i>

                                <span>
                                    <?= h(
                                        hoyEstadoTareaLabel(
                                            $siguienteTarea[
                                                'estado'
                                            ]
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <h3>
                                <?= h(
                                    $siguienteTarea[
                                        'titulo'
                                    ]
                                ) ?>
                            </h3>


                            <p>
                                <?= h(
                                    hoyMotivoTarea(
                                        $siguienteTarea,
                                        $hoy,
                                        $zona
                                    )
                                ) ?>
                                · Prioridad
                                <?= h(
                                    hoyPrioridadLabel(
                                        $siguienteTarea[
                                            'prioridad'
                                        ]
                                    )
                                ) ?>
                            </p>


                            <?php if (
                                $razonFoco !== null
                            ): ?>

                                <div class="today-focus-reason-v4">

                                    <span>
                                        POR QUÉ ESTA TAREA
                                    </span>

                                    <strong>
                                        <?= h(
                                            $razonFoco[
                                                'titulo'
                                            ]
                                        ) ?>
                                    </strong>

                                    <p>
                                        <?= h(
                                            $razonFoco[
                                                'detalle'
                                            ]
                                        ) ?>
                                    </p>

                                </div>

                            <?php endif; ?>


                            <div class="today-focus-details-v4">

                                <span>
                                    <?= h(
                                        $siguienteTarea[
                                            'fecha_limite'
                                        ]
                                        ?: 'Sin fecha'
                                    ) ?>
                                </span>

                                <span>
                                    <?= h(
                                        hoyHora(
                                            $siguienteTarea[
                                                'hora_limite'
                                            ]
                                        )
                                    ) ?>
                                </span>

                                <?php if (
                                    !empty(
                                        $siguienteTarea[
                                            'evento_titulo'
                                        ]
                                    )
                                ): ?>

                                    <span>
                                        <?= h(
                                            $siguienteTarea[
                                                'evento_titulo'
                                            ]
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>


                            <?php if (
                                !empty(
                                    $siguienteTarea[
                                        'evento_titulo'
                                    ]
                                )
                                && !empty(
                                    $siguienteTarea[
                                        'evento_fecha'
                                    ]
                                )
                            ): ?>

                                <div class="today-event-context-v4">

                                    <div class="today-event-context-copy-v4">

                                        <span>
                                            EVENTO RELACIONADO
                                        </span>

                                        <strong>
                                            <?= h(
                                                $siguienteTarea[
                                                    'evento_titulo'
                                                ]
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= h(
                                                $siguienteTarea[
                                                    'materia_codigo'
                                                ]
                                                ?: (
                                                    $siguienteTarea[
                                                        'materia_nombre'
                                                    ]
                                                    ?: 'Sin materia'
                                                )
                                            ) ?>
                                        </small>

                                    </div>


                                    <dl>

                                        <div>

                                            <dt>
                                                Fecha
                                            </dt>

                                            <dd>
                                                <?= h(
                                                    hoyFechaCorta(
                                                        $siguienteTarea[
                                                            'evento_fecha'
                                                        ],
                                                        $zona
                                                    )
                                                ) ?>
                                                ·
                                                <?= h(
                                                    hoyHora(
                                                        $siguienteTarea[
                                                            'evento_hora'
                                                        ]
                                                    )
                                                ) ?>
                                            </dd>

                                        </div>


                                        <div>

                                            <dt>
                                                Desde hoy
                                            </dt>

                                            <dd>
                                                <?= h(
                                                    hoyDistanciaLabel(
                                                        $diasEventoFoco
                                                    )
                                                ) ?>
                                            </dd>

                                        </div>


                                        <div>

                                            <dt>
                                                Margen tarea → evento
                                            </dt>

                                            <dd>
                                                <?= h(
                                                    hoyMargenLabel(
                                                        $margenEventoFoco
                                                    )
                                                ) ?>
                                            </dd>

                                        </div>

                                    </dl>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="today-focus-actions-v4">

                            <span>
                                <?= h(
                                    hoyGrupoTarea(
                                        $siguienteTarea,
                                        $hoy,
                                        $zona
                                    )
                                ) ?>
                            </span>


                            <div class="today-focus-buttons-v4">


                                <?php if (
                                    $siguienteTarea[
                                        'estado'
                                    ] === 'PENDIENTE'
                                ): ?>

                                    <form
                                        method="post"
                                        action="/hoy.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h(
                                                $csrf
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $siguienteTarea[
                                                    'id'
                                                ] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="EN_PROGRESO"
                                        >

                                        <button
                                            type="submit"
                                            class="is-primary"
                                        >
                                            Iniciar
                                        </button>

                                    </form>


                                <?php elseif (
                                    $siguienteTarea[
                                        'estado'
                                    ] === 'EN_PROGRESO'
                                ): ?>

                                    <form
                                        method="post"
                                        action="/hoy.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h(
                                                $csrf
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $siguienteTarea[
                                                    'id'
                                                ] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="PENDIENTE"
                                        >

                                        <button
                                            type="submit"
                                        >
                                            Pausar
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <form
                                    method="post"
                                    action="/hoy.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h(
                                            $csrf
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="estado"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)
                                            $siguienteTarea[
                                                'id'
                                            ] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="estado"
                                        value="COMPLETADA"
                                    >

                                    <button
                                        type="submit"
                                        class="is-complete"
                                    >
                                        Completar
                                    </button>

                                </form>


                                <a
                                    href="/tareas.php?editar=<?= (int)
                                        $siguienteTarea[
                                            'id'
                                        ] ?>#task-form"
                                >
                                    Editar
                                </a>

                            </div>

                        </div>

                    </article>


                <?php elseif (
                    $proximoEventoPendiente
                    !== null
                ): ?>


                    <article class="today-focus-event-v4">

                        <div class="today-focus-copy-v4">

                            <span class="section-kicker">
                                Próximo compromiso
                            </span>

                            <h3>
                                <?= h(
                                    $proximoEventoPendiente[
                                        'titulo'
                                    ]
                                ) ?>
                            </h3>

                            <p>
                                <?= h(
                                    $proximoEventoPendiente[
                                        'materia_codigo'
                                    ]
                                ) ?>
                                ·
                                <?= h(
                                    $proximoEventoPendiente[
                                        'fecha'
                                    ]
                                ) ?>
                                ·
                                <?= h(
                                    hoyHora(
                                        $proximoEventoPendiente[
                                            'hora'
                                        ]
                                    )
                                ) ?>
                            </p>

                        </div>

                        <a
                            href="/agenda.php#evento-<?= (int)
                                $proximoEventoPendiente[
                                    'id'
                                ] ?>"
                        >
                            Ver evento →
                        </a>

                    </article>


                <?php else: ?>


                    <div class="today-empty-v4">

                        <span>✓</span>

                        <strong>
                            No hay una siguiente acción
                        </strong>

                        <p>
                            No existen tareas abiertas ni eventos
                            pendientes en el horizonte.
                        </p>

                    </div>


                <?php endif; ?>

            </section>


            <section
                class="today-attention-v4"
                data-attention-total="<?= count(
                    $requiereAtencion
                ) ?>"
            >

                <div class="today-section-head-v4">

                    <div>

                        <span class="section-kicker">
                            Atención
                        </span>

                        <h2>
                            Requiere atención
                        </h2>

                    </div>

                    <span>
                        <?= count(
                            $requiereAtencion
                        ) ?>
                    </span>

                </div>


                <div class="today-attention-groups-v4">

                    <article data-attention-group="VENCIDAS">
                        <span>Vencidas</span>
                        <strong><?= $atencionConteos['VENCIDAS'] ?></strong>
                        <small>recuperar primero</small>
                    </article>

                    <article data-attention-group="HOY">
                        <span>Hoy</span>
                        <strong><?= $atencionConteos['HOY'] ?></strong>
                        <small>vencen hoy</small>
                    </article>

                    <article data-attention-group="EN_PROGRESO">
                        <span>En progreso</span>
                        <strong><?= $atencionConteos['EN_PROGRESO'] ?></strong>
                        <small>conservar continuidad</small>
                    </article>

                    <article data-attention-group="PRIORIDAD">
                        <span>Alta / urgente</span>
                        <strong><?= $atencionConteos['PRIORIDAD'] ?></strong>
                        <small>dentro de 3 días</small>
                    </article>

                </div>


                <?php if (
                    $requiereAtencion === []
                ): ?>

                    <div class="today-empty-v4 today-empty-compact-v4">

                        <span>✓</span>

                        <strong>
                            Sin alertas críticas
                        </strong>

                        <p>
                            No hay tareas vencidas, en progreso
                            o de prioridad alta dentro de 3 días.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="today-task-list-v4">


                    <?php foreach (
                        $requiereAtencion
                        as $tarea
                    ): ?>


                        <a
                            href="/tareas.php?editar=<?= (int)
                                $tarea['id'] ?>#task-form"
                            class="today-task-row-v4"
                        >

                            <span
                                class="today-task-dot-v4"
                                style="background: <?= h(
                                    $tarea[
                                        'materia_color'
                                    ]
                                    ?? '#587089'
                                ) ?>;"
                            ></span>


                            <div>

                                <strong>
                                    <?= h(
                                        $tarea[
                                            'titulo'
                                        ]
                                    ) ?>
                                </strong>

                                <small>
                                    <?= h(
                                        hoyMotivoTarea(
                                            $tarea,
                                            $hoy,
                                            $zona
                                        )
                                    ) ?>
                                    ·
                                    <?= h(
                                        hoyPrioridadLabel(
                                            $tarea[
                                                'prioridad'
                                            ]
                                        )
                                    ) ?>
                                </small>

                            </div>

                            <i>→</i>

                        </a>


                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>


            <section class="today-agenda-v4">

                <div class="today-section-head-v4">

                    <div>

                        <span class="section-kicker">
                            <?= strtoupper(
                                hoyDiaSemana(
                                    (int)
                                        $hoy->format('N')
                                )
                            ) ?>
                        </span>

                        <h2>
                            Agenda de hoy
                        </h2>

                    </div>


                    <span>
                        <?= count(
                            $tareasHoy
                        )
                        + count(
                            $eventosHoy
                        ) ?>
                        elementos
                    </span>

                </div>


                <div class="today-day-grid-v4">


                    <article>

                        <header>

                            <span>
                                Tareas
                            </span>

                            <strong>
                                <?= count(
                                    $tareasHoy
                                ) ?>
                            </strong>

                        </header>


                        <?php if (
                            $tareasHoy === []
                        ): ?>

                            <div class="today-mini-empty-v4">
                                Sin tareas con límite hoy.
                            </div>

                        <?php else: ?>


                            <?php foreach (
                                $tareasHoy
                                as $tarea
                            ): ?>

                                <a
                                    href="/tareas.php?editar=<?= (int)
                                        $tarea[
                                            'id'
                                        ] ?>#task-form"
                                    class="today-day-item-v4"
                                >

                                    <span>
                                        <?= h(
                                            hoyHora(
                                                $tarea[
                                                    'hora_limite'
                                                ]
                                            )
                                        ) ?>
                                    </span>

                                    <div>

                                        <strong>
                                            <?= h(
                                                $tarea[
                                                    'titulo'
                                                ]
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= h(
                                                hoyPrioridadLabel(
                                                    $tarea[
                                                        'prioridad'
                                                    ]
                                                )
                                            ) ?>
                                        </small>

                                    </div>

                                </a>

                            <?php endforeach; ?>


                        <?php endif; ?>

                    </article>


                    <article>

                        <header>

                            <span>
                                Eventos
                            </span>

                            <strong>
                                <?= count(
                                    $eventosHoy
                                ) ?>
                            </strong>

                        </header>


                        <?php if (
                            $eventosHoy === []
                        ): ?>

                            <div class="today-mini-empty-v4">
                                Sin eventos académicos hoy.
                            </div>

                        <?php else: ?>


                            <?php foreach (
                                $eventosHoy
                                as $evento
                            ): ?>

                                <a
                                    href="/agenda.php#evento-<?= (int)
                                        $evento[
                                            'id'
                                        ] ?>"
                                    class="today-day-item-v4"
                                >

                                    <span>
                                        <?= h(
                                            hoyHora(
                                                $evento[
                                                    'hora'
                                                ]
                                            )
                                        ) ?>
                                    </span>

                                    <div>

                                        <strong>
                                            <?= h(
                                                $evento[
                                                    'titulo'
                                                ]
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= h(
                                                $evento[
                                                    'materia_codigo'
                                                ]
                                            ) ?>
                                            ·
                                            <?= h(
                                                hoyTipoEventoLabel(
                                                    $evento[
                                                        'tipo'
                                                    ]
                                                )
                                            ) ?>
                                            ·
                                            <?= h(
                                                hoyEstadoEventoLabel(
                                                    $evento[
                                                        'estado'
                                                    ]
                                                )
                                            ) ?>
                                        </small>

                                    </div>

                                </a>

                            <?php endforeach; ?>


                        <?php endif; ?>

                    </article>

                </div>

            </section>


            <section class="today-upcoming-v4">

                <div class="today-section-head-v4">

                    <div>

                        <span class="section-kicker">
                            Horizonte
                        </span>

                        <h2>
                            Próximas tareas
                        </h2>

                    </div>

                    <span>
                        14 días
                    </span>

                </div>


                <?php if (
                    $tareasProximas14 === []
                ): ?>

                    <div class="today-empty-v4 today-empty-compact-v4 r35c-upcoming-empty">

                        <span>✓</span>

                        <strong>
                            Horizonte despejado
                        </strong>

                        <p>
                            No hay tareas abiertas con vencimiento
                            durante los próximos 14 días.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="today-upcoming-list-v4">


                    <?php foreach (
                        $tareasProximas14
                        as $tarea
                    ): ?>

                        <?php

                        $dias =
                            hoyDiasHasta(
                                $tarea[
                                    'fecha_limite'
                                ],
                                $hoy,
                                $zona
                            );

                        ?>


                        <a
                            href="/tareas.php?editar=<?= (int)
                                $tarea['id'] ?>#task-form"
                            class="today-upcoming-item-v4"
                        >

                            <div class="today-upcoming-date-v4">

                                <strong>
                                    <?= h(
                                        substr(
                                            (string)
                                                $tarea[
                                                    'fecha_limite'
                                                ],
                                            8,
                                            2
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= h(
                                        substr(
                                            (string)
                                                $tarea[
                                                    'fecha_limite'
                                                ],
                                            5,
                                            2
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <div>

                                <small>
                                    <?= $dias === 1
                                        ? 'Mañana'
                                        : (
                                            'En '
                                            . (int) $dias
                                            . ' días'
                                        ) ?>
                                    ·
                                    <?= h(
                                        hoyHora(
                                            $tarea[
                                                'hora_limite'
                                            ]
                                        )
                                    ) ?>
                                </small>

                                <strong>
                                    <?= h(
                                        $tarea[
                                            'titulo'
                                        ]
                                    ) ?>
                                </strong>

                                <span>
                                    <?= h(
                                        $tarea[
                                            'materia_codigo'
                                        ]
                                        ?: (
                                            $tarea[
                                                'materia_nombre'
                                            ]
                                            ?: 'Sin materia'
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <b class="priority-<?= h(
                                strtolower(
                                    $tarea[
                                        'prioridad'
                                    ]
                                )
                            ) ?>">
                                <?= h(
                                    hoyPrioridadLabel(
                                        $tarea[
                                            'prioridad'
                                        ]
                                    )
                                ) ?>
                            </b>

                            <i>→</i>

                        </a>


                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>

        </div>


        <aside class="today-side-v4">


            <section class="today-side-card-v4">

                <span class="section-kicker">
                    Operación
                </span>

                <h3>
                    Resumen diario
                </h3>


                <div
                    class="
                        today-load-pressure-v4
                        <?= h(
                            $presionClase
                        ) ?>
                    "
                >
                    <span>
                        Presión operativa
                    </span>

                    <strong>
                        <?= h(
                            $presionNivel
                        ) ?>
                    </strong>
                </div>


                <dl>

                    <div>
                        <dt>Abiertas</dt>
                        <dd>
                            <?= count(
                                $tareasAbiertas
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Atención</dt>
                        <dd>
                            <?= count(
                                $requiereAtencion
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Sin fecha</dt>
                        <dd>
                            <?= count(
                                $tareasSinFecha
                            ) ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Eventos hoy</dt>
                        <dd>
                            <?= count(
                                $eventosHoy
                            ) ?>
                        </dd>
                    </div>

                </dl>

            </section>


            <section class="today-side-card-v4">

                <span class="section-kicker">
                    Motor de foco
                </span>

                <h3>
                    Orden determinista
                </h3>

                <div class="today-rule-v4">

                    <span>
                        1
                    </span>

                    <p>
                        Vencidas antes que futuras.
                    </p>

                </div>

                <div class="today-rule-v4">

                    <span>
                        2
                    </span>

                    <p>
                        Después, lo que vence hoy.
                    </p>

                </div>

                <div class="today-rule-v4">

                    <span>
                        3
                    </span>

                    <p>
                        En progreso y prioridades cercanas.
                    </p>

                </div>

                <div class="today-rule-v4">

                    <span>
                        4
                    </span>

                    <p>
                        Finalmente, próxima fecha disponible.
                    </p>

                </div>

            </section>


            <section class="today-side-card-v4 today-shortcuts-v4">

                <span class="section-kicker">
                    Accesos
                </span>

                <h3>
                    Continuar trabajando
                </h3>


                <a href="/tareas.php">
                    <span>✓</span>

                    <div>
                        <strong>
                            Tareas
                        </strong>
                        <small>
                            Workspace completo
                        </small>
                    </div>

                    <i>→</i>
                </a>


                <a href="/semana.php">
                    <span>□</span>

                    <div>
                        <strong>
                            Semana
                        </strong>
                        <small>
                            Planificación de 7 días
                        </small>
                    </div>

                    <i>→</i>
                </a>


                <a href="/agenda.php">
                    <span>▦</span>

                    <div>
                        <strong>
                            Agenda
                        </strong>
                        <small>
                            Eventos académicos
                        </small>
                    </div>

                    <i>→</i>
                </a>

            </section>

        </aside>

    </section>


</main>


<script
    src="/assets/js/command-palette.js?v=r35ie1-c8ba12954f606e8f"
    defer
></script>

<script
    src="/assets/js/quick-add.js?v=r35ie3-ec2ed63bd623c25f60b6"
    defer
></script>

<script
    src="/assets/js/notifications.js?v=3"
    defer
></script>

<script
    src="/assets/js/mobile-shell.js?v=5"
    defer
></script>

<script
    src="/assets/js/pwa.js?v=1"
    defer
></script>


</body>
</html>
