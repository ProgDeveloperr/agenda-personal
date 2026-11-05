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

    <link
        rel="manifest"
        href="/manifest.webmanifest"
    >

    <link
        rel="icon"
        href="/assets/icons/agenda-personal.svg"
        type="image/svg+xml"
    >

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



    <link
        rel="apple-touch-icon"
        href="/assets/icons/icon-180.png"
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
</head>


<body class="ui-v3 page-calendar-v3">


<header class="app-header">

    <div class="app-header-inner">

        <a
            class="app-brand"
            href="/"
        >

            <span class="app-brand-mark">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M7 3.75h8.5A2.5 2.5 0 0 1 18 6.25v11.5a2.5 2.5 0 0 1-2.5 2.5H7a2.5 2.5 0 0 1-2.5-2.5V6.25A2.5 2.5 0 0 1 7 3.75Z"
                    />

                    <path
                        d="M8.5 2v3.5M14 2v3.5M4.5 8.5H18"
                    />

                    <path
                        d="m8.5 13 2 2 4-4"
                    />

                </svg>

            </span>


            <span class="app-brand-copy">

                <strong>
                    Agenda Personal
                </strong>

                <small>
                    Workspace
                </small>

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

    <script src="/assets/js/command-palette.js?v=6" defer></script>
    <script src="/assets/js/quick-add.js?v=2" defer></script>
<script
    src="/assets/js/notifications.js?v=2"
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

    <script src="/assets/js/calendario-mobile.js?v=1" defer></script>
</body>
</html>
