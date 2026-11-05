<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/eventos_repository.php';
require_once '/var/www/app/tareas_repository.php';

iniciarSesion();


function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function semanaDia(
    int $dia
): string {

    return [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ][$dia] ?? '';
}


function semanaDiaCorto(
    int $dia
): string {

    return [
        1 => 'LUN',
        2 => 'MAR',
        3 => 'MIÉ',
        4 => 'JUE',
        5 => 'VIE',
        6 => 'SÁB',
        7 => 'DOM',
    ][$dia] ?? '';
}


function semanaMes(
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


function semanaMesCorto(
    int $mes
): string {

    return [
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
    ][$mes] ?? '';
}


function semanaTipo(
    string $tipo
): string {

    return match ($tipo) {
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

        default =>
            $tipo,
    };
}


function semanaEstado(
    string $estado
): string {

    return match ($estado) {
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

        default =>
            $estado,
    };
}


function semanaEstadoClase(
    string $estado
): string {

    return match ($estado) {
        'PENDIENTE' =>
            'pending',

        'ENTREGADO' =>
            'delivered',

        'APROBADO' =>
            'approved',

        'DESAPROBADO' =>
            'failed',

        'AUSENTE' =>
            'absent',

        'CANCELADO' =>
            'cancelled',

        default =>
            'neutral',
    };
}


function semanaHora(
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


function semanaNota(
    mixed $nota
): string {

    if ($nota === null) {
        return '—';
    }


    $valor =
        number_format(
            (float) $nota,
            2,
            ',',
            '.'
        );


    return preg_replace(
        '/,00$/',
        '',
        $valor
    ) ?? $valor;
}


function semanaRangoTitulo(
    DateTimeImmutable $inicio,
    DateTimeImmutable $fin
): string {

    $mismoMes =
        $inicio->format('Y-m')
        === $fin->format('Y-m');


    $mismoAnio =
        $inicio->format('Y')
        === $fin->format('Y');


    if ($mismoMes) {

        return
            $inicio->format('j')
            . ' – '
            . $fin->format('j')
            . ' de '
            . semanaMes(
                (int) $inicio->format('n')
            )
            . ' '
            . $inicio->format('Y');
    }


    if ($mismoAnio) {

        return
            $inicio->format('j')
            . ' '
            . semanaMesCorto(
                (int) $inicio->format('n')
            )
            . ' – '
            . $fin->format('j')
            . ' '
            . semanaMesCorto(
                (int) $fin->format('n')
            )
            . ' '
            . $inicio->format('Y');
    }


    return
        $inicio->format('j')
        . ' '
        . semanaMesCorto(
            (int) $inicio->format('n')
        )
        . ' '
        . $inicio->format('Y')
        . ' – '
        . $fin->format('j')
        . ' '
        . semanaMesCorto(
            (int) $fin->format('n')
        )
        . ' '
        . $fin->format('Y');
}



/*
 * ----------------------------------------------------------
 * R1.4 - Etiqueta completa de materia para Semana
 * ----------------------------------------------------------
 */

function semanaMateriaCompleta(
    array $item
): string {

    $codigo = trim(
        (string) (
            $item['materia_codigo']
            ?? ''
        )
    );

    $nombre = trim(
        (string) (
            $item['materia_nombre']
            ?? ''
        )
    );


    if (
        $codigo !== ''
        && $nombre !== ''
    ) {
        return
            $codigo
            . ' · '
            . $nombre;
    }


    if ($nombre !== '') {
        return $nombre;
    }


    if ($codigo !== '') {
        return $codigo;
    }


    return 'Sin materia';
}


$zona =
    new DateTimeZone(
        'America/Argentina/Buenos_Aires'
    );


$hoy =
    new DateTimeImmutable(
        'today',
        $zona
    );


$weekParam =
    trim(
        (string) (
            $_GET['week']
            ?? ''
        )
    );


$fechaBase = null;


if (
    preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $weekParam
    ) === 1
) {

    $candidata =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $weekParam,
            $zona
        );


    if (
        $candidata !== false
        && $candidata->format('Y-m-d')
            === $weekParam
    ) {

        $fechaBase =
            $candidata;
    }
}


if ($fechaBase === null) {

    $fechaBase =
        $hoy;
}


$diasDesdeLunes =
    (int) $fechaBase->format('N')
    - 1;


$inicioSemana =
    $fechaBase->modify(
        '-' . $diasDesdeLunes . ' days'
    );


$finSemanaExclusivo =
    $inicioSemana->modify(
        '+7 days'
    );


$finSemana =
    $inicioSemana->modify(
        '+6 days'
    );


$semanaAnterior =
    $inicioSemana
        ->modify('-7 days')
        ->format('Y-m-d');


$semanaSiguiente =
    $inicioSemana
        ->modify('+7 days')
        ->format('Y-m-d');


$inicioSemanaActual =
    $hoy->modify(
        '-'
        . (
            (int) $hoy->format('N')
            - 1
        )
        . ' days'
    );


$esSemanaActual =
    $inicioSemana->format('Y-m-d')
    === $inicioSemanaActual->format('Y-m-d');


$eventos =
    listarEventosPorRango($tenantUserId, 
        $inicioSemana->format('Y-m-d'),
        $finSemanaExclusivo->format('Y-m-d')
    );


$tareasSemana =
    array_values(
        array_filter(
            listarTareasPorRango($tenantUserId,
                $inicioSemana->format('Y-m-d'),
                $finSemanaExclusivo->format('Y-m-d')
            ),
            static fn (
                array $tarea
            ): bool =>
                $tarea['estado']
                !== 'CANCELADA'
        )
    );


$porDia = [];
$tareasPorDia = [];

$pendientes = 0;
$entregados = 0;
$aprobados = 0;

$materiasSemana = [];


foreach ($eventos as $evento) {

    $fecha =
        (string) $evento['fecha'];


    if (!isset($porDia[$fecha])) {
        $porDia[$fecha] = [];
    }


    $porDia[$fecha][] =
        $evento;


    if (
        $evento['estado']
        === 'PENDIENTE'
    ) {
        $pendientes++;
    }


    if (
        $evento['estado']
        === 'ENTREGADO'
    ) {
        $entregados++;
    }


    if (
        $evento['estado']
        === 'APROBADO'
    ) {
        $aprobados++;
    }


    $materiasSemana[
        (int) $evento['materia_id']
    ] = true;
}


foreach ($tareasSemana as $tarea) {

    $fecha =
        (string)
            $tarea['fecha_limite'];


    if (
        !isset(
            $tareasPorDia[
                $fecha
            ]
        )
    ) {

        $tareasPorDia[
            $fecha
        ] = [];
    }


    $tareasPorDia[
        $fecha
    ][] = $tarea;


    if (
        !empty(
            $tarea[
                'materia_id'
            ]
        )
    ) {

        $materiasSemana[
            (int)
                $tarea[
                    'materia_id'
                ]
        ] = true;
    }
}


$dias = [];


for ($i = 0; $i < 7; $i++) {

    $fecha =
        $inicioSemana->modify(
            '+' . $i . ' days'
        );


    $clave =
        $fecha->format('Y-m-d');


    $dias[] = [
        'fecha' =>
            $fecha,

        'clave' =>
            $clave,

        'eventos' =>
            $porDia[$clave]
            ?? [],

        'tareas' =>
            $tareasPorDia[$clave]
            ?? [],
    ];
}


$tituloRango =
    semanaRangoTitulo(
        $inicioSemana,
        $finSemana
    );


$totalEventos =
    count($eventos);


$totalTareasSemana =
    count($tareasSemana);


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
        Semana | Agenda Personal
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

    <link rel="stylesheet" href="/assets/css/semana-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-semana.css?v=6">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-week-v3">


<header class="app-header">

    <div class="app-header-inner">

        <a
            class="app-brand"
            href="/"
        >

            <span class="app-brand-mark">

                <svg viewBox="0 0 24 24">

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

            <a
                class="active"
                href="/semana.php"
            >
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
                Vista semanal
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="week-shell-v3">


    <section class="week-hero-v3">

        <div>

            <span class="section-kicker">
                Planificación
            </span>


            <h1>
                Agenda semanal
            </h1>


            <p>
                De lunes a domingo.
                Fechas, estados y materias organizadas
                en una única vista operativa.
            </p>

        </div>


        <div class="week-hero-metrics-v3">

            <article>

                <span>
                    EVENTOS
                </span>

                <strong>
                    <?= $totalEventos ?>
                </strong>

                <small>
                    esta semana
                </small>

            </article>


            <article>

                <span>
                    PENDIENTES
                </span>

                <strong>
                    <?= $pendientes ?>
                </strong>

                <small>
                    por completar
                </small>

            </article>


            <article>

                <span>
                    ENTREGADOS
                </span>

                <strong>
                    <?= $entregados ?>
                </strong>

                <small>
                    esperando resolución
                </small>

            </article>

        </div>

    </section>


    <section class="week-toolbar-v3">

        <a
            href="/semana.php?week=<?= h(
                $semanaAnterior
            ) ?>"
            class="week-nav-v3"
        >
            <span>←</span>
            Semana anterior
        </a>


        <div class="week-current-v3">

            <span>
                <?= $esSemanaActual
                    ? 'SEMANA ACTUAL'
                    : 'SEMANA' ?>
            </span>

            <strong>
                <?= h(
                    $tituloRango
                ) ?>
            </strong>

            <small>
                <?= $totalEventos ?>
                <?= $totalEventos === 1
                    ? 'evento'
                    : 'eventos' ?>

                ·

                <?= $totalTareasSemana ?>
                <?= $totalTareasSemana === 1
                    ? 'tarea'
                    : 'tareas' ?>

                ·

                <?= count(
                    $materiasSemana
                ) ?>
                <?= count(
                    $materiasSemana
                ) === 1
                    ? 'materia'
                    : 'materias' ?>
            </small>

        </div>


        <div class="week-toolbar-right-v3">

            <?php if (!$esSemanaActual): ?>

                <a
                    href="/semana.php"
                    class="week-today-v3"
                >
                    Hoy
                </a>

            <?php endif; ?>


            <a
                href="/semana.php?week=<?= h(
                    $semanaSiguiente
                ) ?>"
                class="week-nav-v3"
            >
                Semana siguiente
                <span>→</span>
            </a>

        </div>

    </section>


    <section class="week-board-panel-v3">

        <div class="week-board-header-v3">

            <div>

                <span class="section-kicker">
                    Semana
                </span>

                <h2>
                    Vista de 7 días
                </h2>

            </div>


            <a
                href="/agenda.php#nuevo-evento"
                class="week-global-add-v3"
            >

                <svg viewBox="0 0 24 24">
                    <path
                        d="M12 5v14M5 12h14"
                    />
                </svg>

                Nuevo evento

            </a>

        </div>


        <div class="week-board-scroll-v3">

            <div class="week-board-v3">


            <?php foreach (
                $dias
                as $dia
            ): ?>

                <?php

                /** @var DateTimeImmutable $fecha */
                $fecha =
                    $dia['fecha'];

                $clave =
                    (string) $dia['clave'];

                $eventosDia =
                    $dia['eventos'];

                $tareasDia =
                    $dia['tareas'];

                $esHoy =
                    $clave
                    === $hoy->format('Y-m-d');

                $esPasado =
                    $fecha < $hoy;

                ?>


                <article
                    class="week-day-v3 <?= $esHoy
                        ? 'week-day-today-v3'
                        : '' ?> <?= $esPasado
                            ? 'week-day-past-v3'
                            : '' ?>"
                >

                    <header class="week-day-header-v3">

                        <div>

                            <span>
                                <?= h(
                                    semanaDiaCorto(
                                        (int) $fecha->format('N')
                                    )
                                ) ?>
                            </span>

                            <strong>
                                <?= $fecha->format('d') ?>
                            </strong>

                            <small>
                                <?= h(
                                    semanaMesCorto(
                                        (int) $fecha->format('n')
                                    )
                                ) ?>
                            </small>

                        </div>


                        <div class="week-day-actions-v3">

                            <?php if ($esHoy): ?>

                                <span class="week-today-badge-v3">
                                    HOY
                                </span>

                            <?php endif; ?>


                            <a
                                href="/agenda.php#nuevo-evento"
                                data-quick-date="<?= h(
                                    $clave
                                ) ?>"
                                class="week-day-add-v3"
                                title="Agregar evento el <?= h(
                                    semanaDia(
                                        (int) $fecha->format('N')
                                    )
                                ) ?>"
                                aria-label="Agregar evento"
                            >
                                +
                            </a>

                        </div>

                    </header>


                    <div class="week-day-body-v3">


                    <?php if (
                        $eventosDia === []
                        && $tareasDia === []
                    ): ?>

                        <div class="week-day-empty-v3">

                            <span></span>

                            <small>
                                Sin actividad
                            </small>

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $eventosDia
                            as $evento
                        ): ?>

                            <article
                                class="week-event-card-v3"
                                style="--event-color: <?= h(
                                    $evento['materia_color']
                                ) ?>;"
                            >

                                <span
                                    class="week-event-accent-v3"
                                ></span>


                                <div class="week-event-top-v3">

                                    <span>
                                        <?= h(
                                            semanaHora(
                                                $evento['hora']
                                            )
                                        ) ?>
                                    </span>


                                    <span
                                        class="week-status-v3 week-status-<?= h(
                                            semanaEstadoClase(
                                                $evento['estado']
                                            )
                                        ) ?>"
                                    >
                                        <?= h(
                                            semanaEstado(
                                                $evento['estado']
                                            )
                                        ) ?>
                                    </span>

                                </div>


                                <a
                                    href="/agenda.php#evento-<?= (int) $evento['id'] ?>"
                                    class="week-event-title-v3"
                                >
                                    <?= h(
                                        $evento['titulo']
                                    ) ?>
                                </a>


                                <a
                                    href="/materia.php?id=<?= (int) $evento['materia_id'] ?>"
                                    class="week-event-subject-v3"
                                >
                                    <?= h(semanaMateriaCompleta($evento)) ?>
                                </a>


                                <span class="week-event-type-v3">

                                    <?= h(
                                        semanaTipo(
                                            $evento['tipo']
                                        )
                                    ) ?>

                                    <?php if (
                                        trim(
                                            (string) (
                                                $evento['lugar']
                                                ?? ''
                                            )
                                        )
                                        !== ''
                                    ): ?>

                                        ·
                                        <?= h(
                                            (string)
                                                $evento['lugar']
                                        ) ?>

                                    <?php endif; ?>


                                    <?php if (
                                        $evento['nota']
                                        !== null
                                    ): ?>

                                        · Nota
                                        <?= h(
                                            semanaNota(
                                                $evento['nota']
                                            )
                                        ) ?>

                                    <?php endif; ?>

                                </span>

                                <?php if (
                                    trim(
                                        (string) (
                                            $evento['descripcion']
                                            ?? ''
                                        )
                                    )
                                    !== ''
                                ): ?>

                                    <p
                                        class="week-event-description-v4"
                                    ><?= nl2br(
                                        h(
                                            (string)
                                                $evento['descripcion']
                                        )
                                    ) ?></p>

                                <?php endif; ?>


                            </article>

                        <?php endforeach; ?>


                        <?php foreach (
                            $tareasDia
                            as $tarea
                        ): ?>

                            <article
                                class="
                                    week-task-card-v4
                                    priority-<?= h(
                                        strtolower(
                                            $tarea[
                                                'prioridad'
                                            ]
                                        )
                                    ) ?>
                                "
                                style="--task-color: <?= h(
                                    $tarea[
                                        'materia_color'
                                    ]
                                    ?? '#587089'
                                ) ?>;"
                            >

                                <span
                                    class="week-task-accent-v4"
                                ></span>


                                <div class="week-task-top-v4">

                                    <span>
                                        <?= h(
                                            semanaHora(
                                                $tarea[
                                                    'hora_limite'
                                                ]
                                            )
                                        ) ?>
                                    </span>


                                    <span class="week-task-kind-v4">
                                        TAREA
                                    </span>

                                </div>


                                <a
                                    href="/tareas.php?editar=<?= (int)
                                        $tarea['id'] ?>#task-form"
                                    class="week-task-title-v4"
                                >
                                    <?= h(
                                        $tarea[
                                            'titulo'
                                        ]
                                    ) ?>
                                </a>


                                <?php if (
                                    !empty(
                                        $tarea[
                                            'materia_id'
                                        ]
                                    )
                                ): ?>

                                    <a
                                        href="/materia.php?id=<?= (int)
                                            $tarea[
                                                'materia_id'
                                            ] ?>"
                                        class="week-task-subject-v4"
                                    >
                                        <?= h(
                                            $tarea[
                                                'materia_codigo'
                                            ]
                                            ?: $tarea[
                                                'materia_nombre'
                                            ]
                                        ) ?>
                                    </a>

                                <?php else: ?>

                                    <span class="week-task-subject-v4">
                                        Sin materia
                                    </span>

                                <?php endif; ?>


                                <div class="week-task-footer-v4">

                                    <span
                                        class="priority-<?= h(
                                            strtolower(
                                                $tarea[
                                                    'prioridad'
                                                ]
                                            )
                                        ) ?>"
                                    >
                                        <?= h(
                                            match (
                                                $tarea[
                                                    'prioridad'
                                                ]
                                            ) {
                                                'URGENTE' =>
                                                    'Urgente',
                                                'ALTA' =>
                                                    'Alta',
                                                'MEDIA' =>
                                                    'Media',
                                                default =>
                                                    'Baja',
                                            }
                                        ) ?>
                                    </span>


                                    <small>
                                        <?= h(
                                            $tarea[
                                                'estado'
                                            ]
                                            === 'EN_PROGRESO'
                                                ? 'En progreso'
                                                : (
                                                    $tarea[
                                                        'estado'
                                                    ]
                                                    === 'COMPLETADA'
                                                        ? 'Completada'
                                                        : 'Pendiente'
                                                )
                                        ) ?>
                                    </small>

                                </div>

                            </article>

                        <?php endforeach; ?>



                    <?php endif; ?>


                    </div>

                </article>

            <?php endforeach; ?>


            </div>

        </div>

    </section>


    <section class="week-detail-v3">

        <div class="week-section-heading-v3">

            <div>

                <span class="section-kicker">
                    Detalle
                </span>

                <h2>
                    Agenda de la semana
                </h2>

            </div>


            <span>
                <?= $totalEventos ?>
                <?= $totalEventos === 1
                    ? 'evento'
                    : 'eventos' ?>

                ·

                <?= $totalTareasSemana ?>
                <?= $totalTareasSemana === 1
                    ? 'tarea'
                    : 'tareas' ?>
            </span>

        </div>


        <?php if (
            $eventos === []
            && $tareasSemana === []
        ): ?>

            <div class="week-detail-empty-v3">

                <div>✓</div>

                <strong>
                    Semana despejada
                </strong>

                <p>
                    No hay eventos ni tareas con fecha
                    entre lunes y domingo.
                </p>

            </div>


        <?php else: ?>


            <div class="week-detail-list-v3">


            <?php foreach (
                $eventos
                as $evento
            ): ?>

                <?php

                $fechaEvento =
                    DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        (string) $evento['fecha'],
                        $zona
                    );

                ?>


                <article
                    class="week-detail-item-v3"
                    style="--event-color: <?= h(
                        $evento['materia_color']
                    ) ?>;"
                >

                    <span
                        class="week-detail-accent-v3"
                    ></span>


                    <div class="week-detail-date-v3">

                        <span>
                            <?= $fechaEvento
                                ? h(
                                    semanaDiaCorto(
                                        (int) $fechaEvento->format('N')
                                    )
                                )
                                : '' ?>
                        </span>

                        <strong>
                            <?= $fechaEvento
                                ? $fechaEvento->format('d')
                                : '--' ?>
                        </strong>

                    </div>


                    <div class="week-detail-copy-v3">

                        <div>

                            <a
                                href="/materia.php?id=<?= (int) $evento['materia_id'] ?>"
                            >
                                <?= h(semanaMateriaCompleta($evento)) ?>
                            </a>

                            <i>·</i>

                            <span>
                                <?= h(
                                    semanaTipo(
                                        $evento['tipo']
                                    )
                                ) ?>
                            </span>

                        </div>


                        <a
                            href="/agenda.php#evento-<?= (int) $evento['id'] ?>"
                        >
                            <?= h(
                                $evento['titulo']
                            ) ?>
                        </a>


                        <small>

                            <?= h(
                                semanaHora(
                                    $evento['hora']
                                )
                            ) ?>

                            ·

                            <?= h(
                                $evento['lugar']
                                ?: 'Lugar sin especificar'
                            ) ?>

                        </small>


                        <?php if (
                            trim(
                                (string) (
                                    $evento['descripcion']
                                    ?? ''
                                )
                            )
                            !== ''
                        ): ?>

                            <p
                                class="week-detail-description-v4"
                            ><?= nl2br(
                                h(
                                    (string)
                                        $evento['descripcion']
                                )
                            ) ?></p>

                        <?php endif; ?>


                    </div>


                    <span
                        class="week-status-v3 week-status-<?= h(
                            semanaEstadoClase(
                                $evento['estado']
                            )
                        ) ?>"
                    >
                        <?= h(
                            semanaEstado(
                                $evento['estado']
                            )
                        ) ?>
                    </span>


                    <?php if (
                        $evento['nota']
                        !== null
                    ): ?>

                        <div class="week-detail-grade-v3">

                            <strong>
                                <?= h(
                                    semanaNota(
                                        $evento['nota']
                                    )
                                ) ?>
                            </strong>

                            <small>
                                nota
                            </small>

                        </div>

                    <?php else: ?>

                        <a
                            href="/agenda.php#evento-<?= (int) $evento['id'] ?>"
                            class="week-detail-arrow-v3"
                        >
                            →
                        </a>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>


            <?php foreach (
                $tareasSemana
                as $tarea
            ): ?>

                <?php

                $fechaTarea =
                    DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        (string)
                            $tarea[
                                'fecha_limite'
                            ],
                        $zona
                    );

                ?>


                <article
                    class="
                        week-detail-item-v3
                        week-detail-task-v4
                    "
                    style="--event-color: <?= h(
                        $tarea[
                            'materia_color'
                        ]
                        ?? '#587089'
                    ) ?>;"
                >

                    <span
                        class="week-detail-accent-v3"
                    ></span>


                    <div class="week-detail-date-v3">

                        <span>
                            <?= $fechaTarea
                                ? h(
                                    semanaDiaCorto(
                                        (int)
                                            $fechaTarea
                                                ->format('N')
                                    )
                                )
                                : '' ?>
                        </span>

                        <strong>
                            <?= $fechaTarea
                                ? $fechaTarea
                                    ->format('d')
                                : '--' ?>
                        </strong>

                    </div>


                    <div class="week-detail-copy-v3">

                        <div>

                            <?php if (
                                !empty(
                                    $tarea[
                                        'materia_id'
                                    ]
                                )
                            ): ?>

                                <a
                                    href="/materia.php?id=<?= (int)
                                        $tarea[
                                            'materia_id'
                                        ] ?>"
                                >
                                    <?= h(
                                        $tarea[
                                            'materia_codigo'
                                        ]
                                        ?: $tarea[
                                            'materia_nombre'
                                        ]
                                    ) ?>
                                </a>

                            <?php else: ?>

                                <span>
                                    Sin materia
                                </span>

                            <?php endif; ?>

                            <i>·</i>

                            <span>
                                Tarea
                            </span>

                        </div>


                        <a
                            href="/tareas.php?editar=<?= (int)
                                $tarea['id'] ?>#task-form"
                        >
                            <?= h(
                                $tarea[
                                    'titulo'
                                ]
                            ) ?>
                        </a>


                        <small>

                            <?= h(
                                semanaHora(
                                    $tarea[
                                        'hora_limite'
                                    ]
                                )
                            ) ?>

                            ·

                            Prioridad
                            <?= h(
                                ucfirst(
                                    strtolower(
                                        $tarea[
                                            'prioridad'
                                        ]
                                    )
                                )
                            ) ?>

                        </small>

                    </div>


                    <span class="week-task-kind-v4">
                        TAREA
                    </span>


                    <a
                        href="/tareas.php?editar=<?= (int)
                            $tarea['id'] ?>#task-form"
                        class="week-detail-arrow-v3"
                    >
                        →
                    </a>

                </article>

            <?php endforeach; ?>



            </div>

        <?php endif; ?>

    </section>


</main>


<script
    src="/assets/js/command-palette.js?v=6"
    defer
></script>

<script
    src="/assets/js/quick-add.js?v=2"
    defer
></script>

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

</body>
</html>
