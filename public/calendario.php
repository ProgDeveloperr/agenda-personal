<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();

require_once '/var/www/app/eventos_repository.php';


function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function nombreMes(int $mes): string
{
    return match ($mes) {
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
        default => '',
    };
}


function nombreMesCorto(int $mes): string
{
    return match ($mes) {
        1 => 'ENE',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'ABR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AGO',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DIC',
        default => '',
    };
}


function etiquetaTipoCalendario(
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


function etiquetaEstadoCalendario(
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


$zona = new DateTimeZone(
    'America/Argentina/Buenos_Aires'
);

$hoy = new DateTimeImmutable(
    'today',
    $zona
);


$mesParam = trim(
    (string) ($_GET['mes'] ?? '')
);


if (
    !preg_match(
        '/^\d{4}-(0[1-9]|1[0-2])$/',
        $mesParam
    )
) {
    $mesParam =
        $hoy->format('Y-m');
}


$inicioMes =
    DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $mesParam . '-01',
        $zona
    );


if ($inicioMes === false) {
    $inicioMes =
        $hoy->modify(
            'first day of this month'
        );
}


$finMes =
    $inicioMes->modify(
        'last day of this month'
    );

$inicioMesSiguiente =
    $inicioMes->modify(
        'first day of next month'
    );


$mesAnterior =
    $inicioMes
        ->modify('-1 month')
        ->format('Y-m');

$mesSiguiente =
    $inicioMes
        ->modify('+1 month')
        ->format('Y-m');

$mesActual =
    $hoy->format('Y-m');


$eventos =
    listarEventosPorRango($tenantUserId, 
        $inicioMes->format('Y-m-d'),
        $inicioMesSiguiente->format('Y-m-d')
    );


$eventosPorDia = [];
$materiasMes = [];

$totalPendientes = 0;
$totalEvaluaciones = 0;
$totalEntregas = 0;


foreach ($eventos as $evento) {

    $eventosPorDia[
        $evento['fecha']
    ][] = $evento;


    $materiasMes[
        (int) $evento['materia_id']
    ] = [
        'codigo' =>
            $evento['materia_codigo'],

        'nombre' =>
            $evento['materia_nombre'],

        'color' =>
            $evento['materia_color'],
    ];


    if (
        $evento['estado']
        === 'PENDIENTE'
    ) {
        $totalPendientes++;
    }


    if (
        in_array(
            $evento['tipo'],
            [
                'PARCIAL',
                'FINAL',
                'RECUPERATORIO',
            ],
            true
        )
    ) {
        $totalEvaluaciones++;
    }


    if (
        in_array(
            $evento['tipo'],
            [
                'TP',
                'ENTREGA',
            ],
            true
        )
    ) {
        $totalEntregas++;
    }
}


uasort(
    $materiasMes,
    static function (
        array $a,
        array $b
    ): int {
        return strcasecmp(
            $a['nombre'],
            $b['nombre']
        );
    }
);


$primerDiaSemana =
    (int) $inicioMes->format('N');

$espaciosIniciales =
    $primerDiaSemana - 1;

$totalDias =
    (int) $finMes->format('j');

$totalCeldas =
    $espaciosIniciales
    + $totalDias;

$espaciosFinales =
    (7 - ($totalCeldas % 7)) % 7;


$tituloMes =
    nombreMes(
        (int) $inicioMes->format('n')
    )
    . ' '
    . $inicioMes->format('Y');


$esMesActual =
    $inicioMes->format('Y-m')
    === $hoy->format('Y-m');

?><!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#070b11"
    >

    <title>
        <?= h($tituloMes) ?>
        | Agenda Personal
    </title>

        <!-- ARK3360_R35J_C_FLATPICKR_BASE_CSS -->
    <link rel="stylesheet" href="/assets/vendor/flatpickr-4.6.13/flatpickr.min.css?v=1b34a42552c96f10">
    <!-- /ARK3360_R35J_C_FLATPICKR_BASE_CSS -->
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



    <!-- R3-I1 PWA META -->
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
<link rel="stylesheet" href="/assets/css/calendario-legibilidad.css?v=4">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/calendario-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-calendario.css?v=2">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
    <!-- ARK3360_R35B3_NOTIFICATION_BADGE -->
    <link rel="stylesheet" href="/assets/css/r35b-notification-badge.css?v=1">
<!-- ARK3360_R35I_D2_QUICK_ADD_PARITY -->
<link rel="stylesheet" href="/assets/css/r35i-d2-quick-add-parity-canary.css?v=1">
<!-- /ARK3360_R35I_D2_QUICK_ADD_PARITY -->
<!-- ARK3360_R35I_E2_QUICK_DATE_PARITY -->
<link rel="stylesheet" href="/assets/css/r35i-e2-quick-date-parity.css?v=1">
<!-- /ARK3360_R35I_E2_QUICK_DATE_PARITY -->
    <!-- ARK3360_R35K_B_SEARCH_DARK_PARITY -->
    <link rel="stylesheet" href="/assets/css/r35k-b-search-dark-parity.css?v=r35kb-674398ee8d57edad">
    <!-- /ARK3360_R35K_B_SEARCH_DARK_PARITY -->

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

    <!-- ARK3360_R35R_B_LIGHT_MOBILE_ONLY -->
    <link rel="stylesheet" href="/assets/css/r35r-b-light-mobile-vida-calendario.css?v=09f11b35aac1b8c9">
    <!-- /ARK3360_R35R_B_LIGHT_MOBILE_ONLY -->

    <!-- ARK3360_R35S_D_PROGRESSIVE_RESPONSIVE -->
    <script src="/assets/js/r35s-c-viewport-cssom-pilot.js?v=a1b051a863df6f0f"></script>
    <!-- /ARK3360_R35S_D_PROGRESSIVE_RESPONSIVE -->
</head>


<body class="ui-v3 page-calendar-v3">


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


            <a href="/hoy.php">
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

<a
                class="active"
                href="/calendario.php"
            >
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
                Vista mensual
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="calendar-shell-v3">


    <section class="calendar-hero-v3">

        <div>

            <div class="hero-eyebrow">

                <span class="eyebrow-dot"></span>

                Agenda académica

            </div>


            <h1>
                Calendario
            </h1>


            <p>
                Mirá tu carrera mes a mes y encontrá
                cada parcial, final y entrega de un vistazo.
            </p>

        </div>


        <div class="calendar-hero-actions-v3">

            <a
                href="/agenda.php"
                class="agenda-outline-action-v3"
            >

                <svg viewBox="0 0 24 24">

                    <path
                        d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                    />

                    <path
                        d="M8 2v4M16 2v4M4 9h16"
                    />

                    <path
                        d="M8 13h3M8 16h6"
                    />

                </svg>

                Abrir agenda

            </a>


            <div class="calendar-event-total-v3">

                <strong>
                    <?= count($eventos) ?>
                </strong>

                <span>
                    <?= count($eventos) === 1
                        ? 'evento'
                        : 'eventos' ?>
                </span>

            </div>

        </div>

    </section>


    <section class="calendar-metrics-v3">

        <article>

            <div class="calendar-metric-icon-v3">

                <svg viewBox="0 0 24 24">

                    <rect
                        x="4"
                        y="5"
                        width="16"
                        height="15"
                        rx="2"
                    />

                    <path
                        d="M8 2.5v5M16 2.5v5M4 9h16"
                    />

                </svg>

            </div>

            <div>

                <span>
                    Eventos
                </span>

                <strong>
                    <?= count($eventos) ?>
                </strong>

                <small>
                    Este mes
                </small>

            </div>

        </article>


        <article>

            <div class="calendar-metric-icon-v3">

                <svg viewBox="0 0 24 24">

                    <circle
                        cx="12"
                        cy="12"
                        r="8"
                    />

                    <path
                        d="M12 7v5l3 2"
                    />

                </svg>

            </div>

            <div>

                <span>
                    Pendientes
                </span>

                <strong>
                    <?= $totalPendientes ?>
                </strong>

                <small>
                    Por completar
                </small>

            </div>

        </article>


        <article>

            <div class="calendar-metric-icon-v3">

                <svg viewBox="0 0 24 24">

                    <path
                        d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                    />

                    <path
                        d="m8.5 12 2 2 5-5"
                    />

                </svg>

            </div>

            <div>

                <span>
                    Evaluaciones
                </span>

                <strong>
                    <?= $totalEvaluaciones ?>
                </strong>

                <small>
                    Parciales y finales
                </small>

            </div>

        </article>


        <article>

            <div class="calendar-metric-icon-v3">

                <svg viewBox="0 0 24 24">

                    <path
                        d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                    />

                    <path
                        d="M9 8h6M9 12h6M9 16h4"
                    />

                </svg>

            </div>

            <div>

                <span>
                    Materias
                </span>

                <strong>
                    <?= count($materiasMes) ?>
                </strong>

                <small>
                    Con actividad
                </small>

            </div>

        </article>

    </section>


    <section class="calendar-workspace-v3">


        <div class="calendar-toolbar-v3">


            <div class="calendar-navigation-v3">

                <a
                    href="/calendario.php?mes=<?= h(
                        $mesAnterior
                    ) ?>"
                    class="calendar-nav-button-v3"
                    aria-label="Mes anterior"
                >

                    <svg viewBox="0 0 24 24">
                        <path d="m15 6-6 6 6 6" />
                    </svg>

                </a>


                <a
                    href="/calendario.php?mes=<?= h(
                        $mesSiguiente
                    ) ?>"
                    class="calendar-nav-button-v3"
                    aria-label="Mes siguiente"
                >

                    <svg viewBox="0 0 24 24">
                        <path d="m9 6 6 6-6 6" />
                    </svg>

                </a>

            </div>


            <div class="calendar-month-title-v3">

                <span>
                    <?= $inicioMes->format('Y') ?>
                </span>

                <h2>
                    <?= h(
                        nombreMes(
                            (int) $inicioMes->format('n')
                        )
                    ) ?>
                </h2>

                <p>

                    <?= count($eventos) ?>

                    <?= count($eventos) === 1
                        ? 'evento académico'
                        : 'eventos académicos' ?>

                </p>

            </div>


            <div class="calendar-toolbar-actions-v3">

                <?php if (!$esMesActual): ?>

                    <a
                        href="/calendario.php?mes=<?= h(
                            $mesActual
                        ) ?>"
                        class="calendar-today-button-v3"
                    >

                        <svg viewBox="0 0 24 24">

                            <circle
                                cx="12"
                                cy="12"
                                r="8"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="2"
                            />

                        </svg>

                        Ir a hoy

                    </a>

                <?php else: ?>

                    <span class="calendar-current-month-v3">

                        <span></span>

                        Mes actual

                    </span>

                <?php endif; ?>

            </div>

        </div>


        <?php if ($materiasMes !== []): ?>

            <div class="calendar-legend-v3">

                <span class="calendar-legend-title-v3">
                    Materias del mes
                </span>


                <div class="calendar-legend-items-v3">

                    <?php foreach (
                        $materiasMes
                        as $materia
                    ): ?>

                        <span class="calendar-legend-chip-v3">

                            <i
                                style="background: <?= h(
                                    $materia['color']
                                ) ?>"
                            ></i>

                            <?php if (
                                $materia['codigo']
                            ): ?>

                                <small>
                                    [<?= h(
                                        $materia['codigo']
                                    ) ?>]
                                </small>

                            <?php endif; ?>


                            <strong>
                                <?= h(
                                    $materia['nombre']
                                ) ?>
                            </strong>

                        </span>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php else: ?>

            <div class="calendar-legend-v3 calendar-legend-empty-v3">

                <span>
                    Este mes todavía no tiene materias con eventos.
                </span>

            </div>

        <?php endif; ?>


        <div class="calendar-board-scroll-v3">

            <div class="calendar-board-v3">


                <div class="calendar-weekdays-v3">

                    <?php foreach (
                        [
                            'Lunes',
                            'Martes',
                            'Miércoles',
                            'Jueves',
                            'Viernes',
                            'Sábado',
                            'Domingo',
                        ]
                        as $indice => $dia
                    ): ?>

                        <div
                            class="<?= $indice >= 5
                                ? 'calendar-weekend-heading-v3'
                                : '' ?>"
                        >
                            <?= h($dia) ?>
                        </div>

                    <?php endforeach; ?>

                </div>


                <div class="calendar-days-v3">


                    <?php for (
                        $i = 0;
                        $i < $espaciosIniciales;
                        $i++
                    ): ?>

                        <div
                            class="calendar-day-v3 calendar-day-empty-v3"
                            aria-hidden="true"
                        ></div>

                    <?php endfor; ?>


                    <?php for (
                        $dia = 1;
                        $dia <= $totalDias;
                        $dia++
                    ): ?>

                        <?php

                        $fechaDia = sprintf(
                            '%s-%02d',
                            $inicioMes->format('Y-m'),
                            $dia
                        );

                        $fechaObj =
                            DateTimeImmutable::createFromFormat(
                                '!Y-m-d',
                                $fechaDia,
                                $zona
                            );

                        $esHoy =
                            $fechaDia
                            === $hoy->format('Y-m-d');

                        $diaSemana =
                            $fechaObj instanceof DateTimeImmutable
                                ? (int) $fechaObj->format('N')
                                : 1;

                        $esFinSemana =
                            $diaSemana >= 6;

                        $eventosDia =
                            $eventosPorDia[$fechaDia]
                            ?? [];

                        ?>


                        <div
                            class="calendar-day-v3 <?= $esHoy
                                ? 'calendar-day-today-v3'
                                : '' ?> <?= $esFinSemana
                                ? 'calendar-day-weekend-v3'
                                : '' ?>"
                        >

                            <div class="calendar-day-header-v3">

                                <span class="calendar-day-number-v3">
                                    <?= $dia ?>
                                </span>


                                <?php if ($esHoy): ?>

                                    <span class="calendar-today-label-v3">
                                        Hoy
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    count($eventosDia) > 1
                                ): ?>

                                    <span class="calendar-day-count-v3">
                                        <?= count($eventosDia) ?>
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="calendar-events-v3">

                                <?php foreach (
                                    $eventosDia
                                    as $evento
                                ): ?>

                                    <a
                                        href="/agenda.php?editar=<?= (int) $evento['id'] ?>"
                                        class="calendar-event-v3 <?= $evento['estado'] === 'CANCELADO'
                                            ? 'calendar-event-cancelled-v3'
                                            : '' ?>"
                                        style="--event-color: <?= h(
                                            $evento['materia_color']
                                        ) ?>"
                                        title="<?= h(
                                            $evento['titulo']
                                            . ' · '
                                            . $evento['materia_nombre']
                                        ) ?>"
                                    >

                                        <span class="calendar-event-stripe-v3"></span>


                                        <div class="calendar-event-top-v3">

                                            <span>
                                                <?= h(
                                                    etiquetaTipoCalendario(
                                                        $evento['tipo']
                                                    )
                                                ) ?>
                                            </span>

                                            <?php if (
                                                $evento['hora']
                                            ): ?>

                                                <small>
                                                    <?= h(
                                                        substr(
                                                            $evento['hora'],
                                                            0,
                                                            5
                                                        )
                                                    ) ?>
                                                </small>

                                            <?php endif; ?>

                                        </div>


                                        <strong>
                                            <?= h(
                                                $evento['titulo']
                                            ) ?>
                                        </strong>


                                        <p>

                                            <?php if (
                                                $evento['materia_codigo']
                                            ): ?>

                                                <span>
                                                    [<?= h(
                                                        $evento['materia_codigo']
                                                    ) ?>]
                                                </span>

                                            <?php endif; ?>

                                            <?= h(
                                                $evento['materia_nombre']
                                            ) ?>

                                        </p>


                                        <div class="calendar-event-footer-v3">

                                            <span
                                                class="calendar-event-state-v3 calendar-event-state-<?= strtolower(
                                                    $evento['estado']
                                                ) ?>"
                                            >
                                                <?= h(
                                                    etiquetaEstadoCalendario(
                                                        $evento['estado']
                                                    )
                                                ) ?>
                                            </span>


                                            <span
                                                class="calendar-event-open-v3"
                                                aria-hidden="true"
                                            >
                                                →
                                            </span>

                                        </div>

                                    </a>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endfor; ?>


                    <?php for (
                        $i = 0;
                        $i < $espaciosFinales;
                        $i++
                    ): ?>

                        <div
                            class="calendar-day-v3 calendar-day-empty-v3"
                            aria-hidden="true"
                        ></div>

                    <?php endfor; ?>


                </div>

            </div>

        </div>

    </section>


    <section class="calendar-bottom-v3">

        <div class="calendar-bottom-icon-v3">

            <svg viewBox="0 0 24 24">

                <circle
                    cx="12"
                    cy="12"
                    r="8"
                />

                <path
                    d="M12 11v5M12 8h.01"
                />

            </svg>

        </div>


        <div>

            <strong>
                Colores de tus materias
            </strong>

            <p>
                Cada evento conserva el color que asignaste
                a su materia. Hacé clic sobre cualquiera
                para abrirlo directamente en modo edición.
            </p>

        </div>

    </section>


</main>

    <script src="/assets/js/command-palette.js?v=r35ie1-c8ba12954f606e8f" defer></script>
    <script src="/assets/js/quick-add.js?v=r35ie3-ec2ed63bd623c25f60b6" defer></script>
<script
    src="/assets/js/notifications.js?v=3"
    defer
></script>



    <!-- R3-I1 MOBILE SHELL -->

    <script
        src="/assets/js/mobile-shell.js?v=5"
        defer
    ></script>



    <!-- R3-K2 PWA -->

    <script
        src="/assets/js/pwa.js?v=1"
        defer
    ></script>

    <script src="/assets/js/r35s-d-calendario-mobile.js?v=725b14bd9d2322d9" defer></script>
</body>
</html>
