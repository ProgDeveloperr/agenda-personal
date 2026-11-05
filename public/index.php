<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/materias_repository.php';
require_once '/var/www/app/eventos_repository.php';
require_once '/var/www/app/tareas_repository.php';

iniciarSesion();

// R8-WELCOME-CONSUME-START
$r8MostrarBienvenida = ($_SESSION['r8_welcome_pending'] ?? false) === true;
unset($_SESSION['r8_welcome_pending']);

$r8NombreBienvenida = '';

if ($r8MostrarBienvenida) {
    $r8Cuenta = contextoTenantWebActual();

    $r8NombreBienvenida = htmlspecialchars(
        (string) ($r8Cuenta['username'] ?? 'Usuario'),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
// R8-WELCOME-CONSUME-END



function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function dashboardEstado(
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


function dashboardEstadoClase(
    string $estado
): string {

    return match ($estado) {
        'PENDIENTE' => 'pending',
        'ENTREGADO' => 'delivered',
        'APROBADO' => 'approved',
        'DESAPROBADO' => 'failed',
        'AUSENTE' => 'absent',
        'CANCELADO' => 'cancelled',
        default => 'neutral',
    };
}


function dashboardTipo(
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


function dashboardHora(
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


function dashboardNota(
    mixed $nota
): string {

    if ($nota === null) {
        return '—';
    }

    $texto =
        number_format(
            (float) $nota,
            2,
            ',',
            '.'
        );

    return preg_replace(
        '/,00$/',
        '',
        $texto
    ) ?? $texto;
}


function dashboardMes(
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


function dashboardDiaSemana(
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


function dashboardMesLargo(
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


function dashboardRelativo(
    DateTimeImmutable $fecha,
    DateTimeImmutable $hoy
): string {

    $dias =
        (int) $hoy
            ->diff($fecha)
            ->format('%r%a');


    if ($dias === 0) {
        return 'Hoy';
    }


    if ($dias === 1) {
        return 'Mañana';
    }


    if ($dias > 1) {
        return 'En ' . $dias . ' días';
    }


    if ($dias === -1) {
        return 'Ayer';
    }


    return 'Hace '
        . abs($dias)
        . ' días';
}


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

$fin14 =
    $hoy->modify(
        '+14 days'
    );


$materias =
    listarMaterias($tenantUserId);

$eventos =
    listarEventos($tenantUserId);


$tareas =
    listarTareas($tenantUserId);


$tareasAbiertas =
    array_values(
        array_filter(
            $tareas,
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


$tareasVencidas =
    array_values(
        array_filter(
            $tareasAbiertas,
            static function (
                array $tarea
            ) use ($hoy): bool {

                if (
                    empty(
                        $tarea['fecha_limite']
                    )
                ) {
                    return false;
                }


                return
                    $tarea['fecha_limite']
                    < $hoy->format('Y-m-d');
            }
        )
    );


$tareasEnFoco =
    array_values(
        array_filter(
            $tareasAbiertas,
            static function (
                array $tarea
            ) use ($fin14): bool {

                if (
                    empty(
                        $tarea['fecha_limite']
                    )
                ) {
                    return true;
                }


                return
                    $tarea['fecha_limite']
                    <= $fin14->format('Y-m-d');
            }
        )
    );


$prioridadOrden = [
    'URGENTE' => 1,
    'ALTA' => 2,
    'MEDIA' => 3,
    'BAJA' => 4,
];


usort(
    $tareasEnFoco,
    static function (
        array $a,
        array $b
    ) use (
        $prioridadOrden
    ): int {

        $fechaA =
            $a['fecha_limite']
            ?: '9999-12-31';

        $fechaB =
            $b['fecha_limite']
            ?: '9999-12-31';


        if ($fechaA !== $fechaB) {
            return $fechaA <=> $fechaB;
        }


        $horaA =
            $a['hora_limite']
            ?: '23:59:59';

        $horaB =
            $b['hora_limite']
            ?: '23:59:59';


        if ($horaA !== $horaB) {
            return $horaA <=> $horaB;
        }


        return (
            $prioridadOrden[
                $a['prioridad']
            ]
            ?? 99
        )
        <=>
        (
            $prioridadOrden[
                $b['prioridad']
            ]
            ?? 99
        );
    }
);


$tareasEnFoco =
    array_slice(
        $tareasEnFoco,
        0,
        5
    );


$materiasActivas =
    array_values(
        array_filter(
            $materias,
            static fn (array $materia): bool =>
                (int) $materia['activa'] === 1
        )
    );


$pendientes = 0;
$entregados = 0;
$aprobados = 0;
$desaprobados = 0;
$ausentes = 0;
$cancelados = 0;

$notas = [];

$eventosHoy = [];
$proximos14 = [];
$futuros = [];
$actividad = [];


foreach ($eventos as $evento) {

    switch ($evento['estado']) {

        case 'PENDIENTE':
            $pendientes++;
            break;

        case 'ENTREGADO':
            $entregados++;
            break;

        case 'APROBADO':
            $aprobados++;
            break;

        case 'DESAPROBADO':
            $desaprobados++;
            break;

        case 'AUSENTE':
            $ausentes++;
            break;

        case 'CANCELADO':
            $cancelados++;
            break;
    }


    if ($evento['nota'] !== null) {

        $notas[] =
            (float) $evento['nota'];
    }


    $fecha =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) $evento['fecha'],
            $zona
        );


    if ($fecha !== false) {

        if (
            $fecha == $hoy
            && $evento['estado']
                !== 'CANCELADO'
        ) {

            $eventosHoy[] =
                $evento;
        }


        if (
            $fecha >= $hoy
            && $fecha <= $fin14
            && $evento['estado']
                !== 'CANCELADO'
        ) {

            $proximos14[] =
                $evento;
        }


        if (
            $fecha >= $hoy
            && $evento['estado']
                !== 'CANCELADO'
        ) {

            $futuros[] =
                $evento;
        }
    }


    if (
        in_array(
            $evento['estado'],
            [
                'APROBADO',
                'DESAPROBADO',
                'ENTREGADO',
            ],
            true
        )
    ) {

        $actividad[] =
            $evento;
    }
}


usort(
    $actividad,
    static function (
        array $a,
        array $b
    ): int {

        $aTime =
            strtotime(
                (string) $a['updated_at']
            ) ?: 0;

        $bTime =
            strtotime(
                (string) $b['updated_at']
            ) ?: 0;


        if ($aTime === $bTime) {
            return (int) $b['id']
                <=> (int) $a['id'];
        }


        return $bTime <=> $aTime;
    }
);


$actividad =
    array_slice(
        $actividad,
        0,
        5
    );


$promedio =
    $notas !== []
        ? array_sum($notas)
            / count($notas)
        : null;


$eventoFoco =
    $eventosHoy[0]
    ?? $futuros[0]
    ?? null;


$fechaFoco = null;
$relativoFoco = null;


if ($eventoFoco !== null) {

    $fechaFoco =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) $eventoFoco['fecha'],
            $zona
        );


    if ($fechaFoco !== false) {

        $relativoFoco =
            dashboardRelativo(
                $fechaFoco,
                $hoy
            );
    }
}


$saludo =
    match (true) {
        (int) $ahora->format('G') < 12 =>
            'Buenos días',

        (int) $ahora->format('G') < 20 =>
            'Buenas tardes',

        default =>
            'Buenas noches',
    };


$fechaLarga =
    ucfirst(
        dashboardDiaSemana(
            (int) $ahora->format('N')
        )
    )
    . ' '
    . $ahora->format('j')
    . ' de '
    . dashboardMesLargo(
        (int) $ahora->format('n')
    );


$totalEventos =
    count($eventos);

$totalMaterias =
    count($materiasActivas);


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
        Inicio | Agenda Personal
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
    <link rel="stylesheet" href="/assets/css/dashboard-legibilidad.css?v=3.1">



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

    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-light.css?v=3">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
<?php if ($r8MostrarBienvenida): ?>
<link rel="stylesheet" href="/assets/css/r8-welcome-curtain.css?v=1">
<?php endif; ?>
</head>


<body class="ui-v3 page-dashboard-v3">
<?php if ($r8MostrarBienvenida): ?>
<div class="r8-welcome-curtain" aria-hidden="true">
    <div class="r8-welcome-message">Hola, <?= $r8NombreBienvenida ?>.</div>
</div>
<?php endif; ?>


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

            <a
                class="active"
                href="/"
            >
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
                Agenda al día
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="dashboard-shell-v3">


    <section class="dashboard-hero-v3">

        <div>

            <span class="section-kicker">
                <?= h($saludo) ?>
            </span>


            <h1>
                Tu carrera,
                <span>organizada.</span>
            </h1>


            <p>
                <?= h($fechaLarga) ?>.
                Lo importante de hoy y de las próximas dos semanas,
                en un solo lugar.
            </p>

        </div>


        <div class="dashboard-hero-status-v3">

            <div>

                <span>
                    PRÓXIMOS 14 DÍAS
                </span>

                <strong>
                    <?= count($proximos14) ?>
                </strong>

                <small>
                    <?= count($proximos14) === 1
                        ? 'evento programado'
                        : 'eventos programados' ?>
                </small>

            </div>


            <div>

                <span>
                    PENDIENTES
                </span>

                <strong>
                    <?= $pendientes ?>
                </strong>

                <small>
                    requieren seguimiento
                </small>

            </div>

        </div>

    </section>


    <section class="dashboard-metrics-v3">

        <article>

            <span>
                Materias activas
            </span>

            <strong>
                <?= $totalMaterias ?>
            </strong>

            <small>
                catálogo académico
            </small>

        </article>


        <article>

            <span>
                Eventos
            </span>

            <strong>
                <?= $totalEventos ?>
            </strong>

            <small>
                historial total
            </small>

        </article>


        <article class="dashboard-metric-delivered-v3">

            <span>
                Entregados
            </span>

            <strong>
                <?= $entregados ?>
            </strong>

            <small>
                esperando resolución
            </small>

        </article>


        <article class="dashboard-metric-average-v3">

            <span>
                Promedio
            </span>

            <strong>
                <?= $promedio !== null
                    ? h(
                        dashboardNota(
                            $promedio
                        )
                    )
                    : '—' ?>
            </strong>

            <small>
                <?= count($notas) ?>
                <?= count($notas) === 1
                    ? 'nota cargada'
                    : 'notas cargadas' ?>
            </small>

        </article>

    </section>


    <section class="dashboard-grid-v3">


        <div class="dashboard-main-v3">


            <section class="dashboard-focus-v3">

                <div class="dashboard-section-head-v3">

                    <div>

                        <span class="section-kicker">
                            Ahora
                        </span>

                        <h2>
                            En foco
                        </h2>

                    </div>


                    <a href="/hoy.php">
                        Abrir Hoy
                        <span>→</span>
                    </a>

                </div>


                <?php if ($eventoFoco !== null): ?>

                    <article
                        class="dashboard-focus-card-v3"
                        style="--event-color: <?= h(
                            $eventoFoco['materia_color']
                        ) ?>;"
                    >

                        <div class="dashboard-focus-date-v3">

                            <span>
                                <?= $fechaFoco
                                    ? dashboardMes(
                                        (int) $fechaFoco->format('n')
                                    )
                                    : '' ?>
                            </span>

                            <strong>
                                <?= $fechaFoco
                                    ? $fechaFoco->format('d')
                                    : '--' ?>
                            </strong>

                            <small>
                                <?= h(
                                    $relativoFoco
                                    ?? ''
                                ) ?>
                            </small>

                        </div>


                        <div class="dashboard-focus-copy-v3">

                            <div class="dashboard-event-meta-v3">

                                <span
                                    class="dashboard-subject-dot-v3"
                                ></span>

                                <a
                                    href="/materia.php?id=<?= (int) $eventoFoco['materia_id'] ?>"
                                >
                                    <?= h(
                                        $eventoFoco['materia_codigo']
                                        ?: $eventoFoco['materia_nombre']
                                    ) ?>
                                </a>

                                <i>·</i>

                                <span>
                                    <?= h(
                                        dashboardTipo(
                                            $eventoFoco['tipo']
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <h3>
                                <?= h(
                                    $eventoFoco['titulo']
                                ) ?>
                            </h3>


                            <p>
                                <?= h(
                                    $eventoFoco['materia_nombre']
                                ) ?>
                            </p>


                            <div class="dashboard-focus-details-v3">

                                <span>

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

                                    <?= h(
                                        dashboardHora(
                                            $eventoFoco['hora']
                                        )
                                    ) ?>

                                </span>


                                <span>

                                    <svg viewBox="0 0 24 24">
                                        <path
                                            d="M5 4h14v16H5z"
                                        />
                                        <path
                                            d="M8 8h8M8 12h6"
                                        />
                                    </svg>

                                    <?= h(
                                        $eventoFoco['lugar']
                                        ?: 'Lugar sin especificar'
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="dashboard-focus-action-v3">

                            <span
                                class="dashboard-status-v3 dashboard-status-<?= h(
                                    dashboardEstadoClase(
                                        $eventoFoco['estado']
                                    )
                                ) ?>"
                            >
                                <?= h(
                                    dashboardEstado(
                                        $eventoFoco['estado']
                                    )
                                ) ?>
                            </span>


                            <a
                                href="/agenda.php#evento-<?= (int) $eventoFoco['id'] ?>"
                            >
                                Ver evento
                                <span>→</span>
                            </a>

                        </div>

                    </article>

                <?php else: ?>

                    <div class="dashboard-empty-v3">

                        <span>✓</span>

                        <strong>
                            No hay eventos próximos
                        </strong>

                        <p>
                            Tu agenda no tiene fechas futuras registradas.
                        </p>

                    </div>

                <?php endif; ?>

            </section>


            <section class="dashboard-upcoming-v3">

                <div class="dashboard-section-head-v3">

                    <div>

                        <span class="section-kicker">
                            Horizonte
                        </span>

                        <h2>
                            Próximos 14 días
                        </h2>

                    </div>


                    <span class="dashboard-section-count-v3">
                        <?= count($proximos14) ?>
                        <?= count($proximos14) === 1
                            ? 'evento'
                            : 'eventos' ?>
                    </span>

                </div>


                <?php if ($proximos14 === []): ?>

                    <div class="dashboard-empty-v3 dashboard-empty-compact-v3">

                        <span>✓</span>

                        <strong>
                            Dos semanas despejadas
                        </strong>

                        <p>
                            No hay eventos registrados en esta ventana.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="dashboard-upcoming-list-v3">


                    <?php foreach (
                        $proximos14
                        as $evento
                    ): ?>

                        <?php

                        $fechaEvento =
                            DateTimeImmutable::createFromFormat(
                                '!Y-m-d',
                                (string) $evento['fecha'],
                                $zona
                            );

                        $relativo =
                            $fechaEvento !== false
                                ? dashboardRelativo(
                                    $fechaEvento,
                                    $hoy
                                )
                                : '';

                        ?>


                        <a
                            class="dashboard-upcoming-item-v3"
                            href="/agenda.php#evento-<?= (int) $evento['id'] ?>"
                            style="--event-color: <?= h(
                                $evento['materia_color']
                            ) ?>;"
                        >

                            <span
                                class="dashboard-upcoming-accent-v3"
                            ></span>


                            <div class="dashboard-upcoming-date-v3">

                                <strong>
                                    <?= $fechaEvento
                                        ? $fechaEvento->format('d')
                                        : '--' ?>
                                </strong>

                                <span>
                                    <?= $fechaEvento
                                        ? dashboardMes(
                                            (int) $fechaEvento->format('n')
                                        )
                                        : '' ?>
                                </span>

                            </div>


                            <div class="dashboard-upcoming-copy-v3">

                                <div>

                                    <span>
                                        <?= h(
                                            $relativo
                                        ) ?>
                                    </span>

                                    <i>·</i>

                                    <span>
                                        <?= h(
                                            dashboardHora(
                                                $evento['hora']
                                            )
                                        ) ?>
                                    </span>

                                </div>


                                <strong>
                                    <?= h(
                                        $evento['titulo']
                                    ) ?>
                                </strong>


                                <small>
                                    <?= h(
                                        $evento['materia_codigo']
                                        ?: $evento['materia_nombre']
                                    ) ?>

                                    ·

                                    <?= h(
                                        $evento['materia_nombre']
                                    ) ?>
                                </small>

                            </div>


                            <span
                                class="dashboard-status-v3 dashboard-status-<?= h(
                                    dashboardEstadoClase(
                                        $evento['estado']
                                    )
                                ) ?>"
                            >
                                <?= h(
                                    dashboardEstado(
                                        $evento['estado']
                                    )
                                ) ?>
                            </span>


                            <i class="dashboard-row-arrow-v3">
                                →
                            </i>

                        </a>


                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>

        


            <section class="dashboard-actions-v4">

                <div class="dashboard-section-head-v3">

                    <div>

                        <span class="section-kicker">
                            Ejecución
                        </span>

                        <h2>
                            Próximas acciones
                        </h2>

                    </div>


                    <div class="dashboard-actions-head-v4">

                        <span>
                            <?= count(
                                $tareasAbiertas
                            ) ?>
                            abiertas
                        </span>

                        <?php if (
                            count(
                                $tareasVencidas
                            ) > 0
                        ): ?>

                            <strong>
                                <?= count(
                                    $tareasVencidas
                                ) ?>
                                vencidas
                            </strong>

                        <?php endif; ?>


                        <a href="/tareas.php">
                            Ver todas
                            <i>→</i>
                        </a>

                    </div>

                </div>


                <?php if (
                    $tareasEnFoco === []
                ): ?>

                    <div class="dashboard-empty-v3 dashboard-empty-compact-v3">

                        <span>✓</span>

                        <strong>
                            Sin acciones pendientes
                        </strong>

                        <p>
                            No hay tareas abiertas en el horizonte actual.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="dashboard-actions-list-v4">


                    <?php foreach (
                        $tareasEnFoco
                        as $tarea
                    ): ?>

                        <?php

                        $fechaTarea = null;
                        $relativoTarea = 'Sin fecha';


                        if (
                            !empty(
                                $tarea[
                                    'fecha_limite'
                                ]
                            )
                        ) {

                            $fechaTarea =
                                DateTimeImmutable::createFromFormat(
                                    '!Y-m-d',
                                    (string)
                                        $tarea[
                                            'fecha_limite'
                                        ],
                                    $zona
                                );


                            if (
                                $fechaTarea
                                !== false
                            ) {

                                $relativoTarea =
                                    dashboardRelativo(
                                        $fechaTarea,
                                        $hoy
                                    );
                            }
                        }


                        $prioridadTarea =
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

                                'BAJA' =>
                                    'Baja',

                                default =>
                                    $tarea[
                                        'prioridad'
                                    ],
                            };


                        $estadoTarea =
                            match (
                                $tarea[
                                    'estado'
                                ]
                            ) {

                                'EN_PROGRESO' =>
                                    'En progreso',

                                default =>
                                    'Pendiente',
                            };

                        ?>


                        <a
                            href="/tareas.php?editar=<?= (int)
                                $tarea['id'] ?>#task-form"
                            class="dashboard-action-item-v4"
                            style="--task-color: <?= h(
                                $tarea[
                                    'materia_color'
                                ]
                                ?? '#587089'
                            ) ?>;"
                        >

                            <span class="dashboard-action-accent-v4"></span>


                            <div class="dashboard-action-date-v4">

                                <strong>
                                    <?= $fechaTarea
                                        ? $fechaTarea
                                            ->format('d')
                                        : '—' ?>
                                </strong>

                                <span>
                                    <?= $fechaTarea
                                        ? dashboardMes(
                                            (int)
                                                $fechaTarea
                                                    ->format(
                                                        'n'
                                                    )
                                        )
                                        : 'S/F' ?>
                                </span>

                            </div>


                            <div class="dashboard-action-copy-v4">

                                <div>

                                    <span>
                                        <?= h(
                                            $relativoTarea
                                        ) ?>
                                    </span>

                                    <i>·</i>

                                    <span>
                                        <?= h(
                                            dashboardHora(
                                                $tarea[
                                                    'hora_limite'
                                                ]
                                            )
                                        ) ?>
                                    </span>

                                </div>


                                <strong>
                                    <?= h(
                                        $tarea[
                                            'titulo'
                                        ]
                                    ) ?>
                                </strong>


                                <small>

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

                                    <?php if (
                                        !empty(
                                            $tarea[
                                                'evento_titulo'
                                            ]
                                        )
                                    ): ?>

                                        ·
                                        <?= h(
                                            $tarea[
                                                'evento_titulo'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </small>

                            </div>


                            <div class="dashboard-action-state-v4">

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
                                        $prioridadTarea
                                    ) ?>
                                </span>

                                <small>
                                    <?= h(
                                        $estadoTarea
                                    ) ?>
                                </small>

                            </div>


                            <i class="dashboard-row-arrow-v3">
                                →
                            </i>

                        </a>


                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>


<section class="dashboard-subjects-v3">

        <div class="dashboard-section-head-v3">

            <div>

                <span class="section-kicker">
                    Cursada
                </span>

                <h2>
                    Materias activas
                </h2>

            </div>


            <a href="/materias.php">
                Ver catálogo
                <span>→</span>
            </a>

        </div>


        <div class="dashboard-subject-grid-v3">

        <?php foreach (
            $materiasActivas
            as $materia
        ): ?>

            <?php

            $eventosMateria =
                array_values(
                    array_filter(
                        $eventos,
                        static fn (
                            array $evento
                        ): bool =>
                            (int) $evento['materia_id']
                            === (int) $materia['id']
                    )
                );

            $pendientesMateria =
                count(
                    array_filter(
                        $eventosMateria,
                        static fn (
                            array $evento
                        ): bool =>
                            $evento['estado']
                            === 'PENDIENTE'
                    )
                );

            ?>


            <a
                href="/materia.php?id=<?= (int) $materia['id'] ?>"
                class="dashboard-subject-v3"
                style="--subject-color: <?= h(
                    $materia['color']
                ) ?>;"
            >

                <span
                    class="dashboard-subject-accent-v3"
                ></span>


                <div>

                    <small>
                        <?= h(
                            $materia['codigo']
                            ?: 'SIN CÓDIGO'
                        ) ?>
                    </small>

                    <strong>
                        <?= h(
                            $materia['nombre']
                        ) ?>
                    </strong>

                    <span>
                        <?= count($eventosMateria) ?>
                        <?= count($eventosMateria) === 1
                            ? 'evento'
                            : 'eventos' ?>

                        ·

                        <?= $pendientesMateria ?>
                        pendientes
                    </span>

                </div>


                <i>→</i>

            </a>

        <?php endforeach; ?>

        </div>

    </section>

        </div>


        <aside class="dashboard-side-v3">


            <section class="dashboard-state-card-v3">

                <span class="section-kicker">
                    Estado académico
                </span>

                <h3>
                    Panorama general
                </h3>


                <div class="dashboard-state-total-v3">

                    <strong>
                        <?= $totalEventos ?>
                    </strong>

                    <div>
                        <span>
                            eventos registrados
                        </span>

                        <small>
                            <?= $totalMaterias ?>
                            materias activas
                        </small>
                    </div>

                </div>


                <?php

                $denominador =
                    max(
                        1,
                        $totalEventos
                    );

                $pctAprobados =
                    ($aprobados / $denominador)
                    * 100;

                $pctEntregados =
                    ($entregados / $denominador)
                    * 100;

                $pctPendientes =
                    ($pendientes / $denominador)
                    * 100;

                $pctOtros =
                    max(
                        0,
                        100
                        - $pctAprobados
                        - $pctEntregados
                        - $pctPendientes
                    );

                ?>


                <div class="dashboard-state-track-v3">

                    <?php if ($pctAprobados > 0): ?>
                        <span
                            class="approved"
                            style="width: <?= h(
                                (string) $pctAprobados
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>


                    <?php if ($pctEntregados > 0): ?>
                        <span
                            class="delivered"
                            style="width: <?= h(
                                (string) $pctEntregados
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>


                    <?php if ($pctPendientes > 0): ?>
                        <span
                            class="pending"
                            style="width: <?= h(
                                (string) $pctPendientes
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>


                    <?php if ($pctOtros > 0): ?>
                        <span
                            class="other"
                            style="width: <?= h(
                                (string) $pctOtros
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>

                </div>


                <div class="dashboard-state-list-v3">

                    <div>

                        <i class="approved"></i>

                        <span>
                            Aprobados
                        </span>

                        <strong>
                            <?= $aprobados ?>
                        </strong>

                    </div>


                    <div>

                        <i class="delivered"></i>

                        <span>
                            Entregados
                        </span>

                        <strong>
                            <?= $entregados ?>
                        </strong>

                    </div>


                    <div>

                        <i class="pending"></i>

                        <span>
                            Pendientes
                        </span>

                        <strong>
                            <?= $pendientes ?>
                        </strong>

                    </div>


                    <?php if (
                        $desaprobados
                        + $ausentes
                        + $cancelados
                        > 0
                    ): ?>

                        <div>

                            <i class="other"></i>

                            <span>
                                Otros
                            </span>

                            <strong>
                                <?= $desaprobados
                                    + $ausentes
                                    + $cancelados ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <section class="dashboard-activity-v3">

                <div class="dashboard-section-head-v3">

                    <div>

                        <span class="section-kicker">
                            Movimiento
                        </span>

                        <h3>
                            Actividad reciente
                        </h3>

                    </div>

                </div>


                <?php if ($actividad === []): ?>

                    <div class="dashboard-mini-empty-v3">
                        Sin actividad reciente.
                    </div>

                <?php else: ?>

                    <div class="dashboard-activity-list-v3">


                    <?php foreach (
                        $actividad
                        as $evento
                    ): ?>

                        <a
                            href="/materia.php?id=<?= (int) $evento['materia_id'] ?>"
                            class="dashboard-activity-item-v3"
                        >

                            <span
                                class="dashboard-activity-icon-v3 dashboard-activity-<?= h(
                                    dashboardEstadoClase(
                                        $evento['estado']
                                    )
                                ) ?>"
                            >

                                <?= $evento['estado'] === 'APROBADO'
                                    ? '✓'
                                    : (
                                        $evento['estado'] === 'ENTREGADO'
                                            ? '↑'
                                            : '!'
                                    ) ?>

                            </span>


                            <div>

                                <strong>
                                    <?= h(
                                        $evento['materia_codigo']
                                        ?: $evento['materia_nombre']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= h(
                                        $evento['titulo']
                                    ) ?>

                                    ·

                                    <?= h(
                                        dashboardEstado(
                                            $evento['estado']
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <?php if (
                                $evento['nota']
                                !== null
                            ): ?>

                                <b>
                                    <?= h(
                                        dashboardNota(
                                            $evento['nota']
                                        )
                                    ) ?>
                                </b>

                            <?php else: ?>

                                <i>→</i>

                            <?php endif; ?>

                        </a>

                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>


            <section class="dashboard-quick-v3">

                <span class="section-kicker">
                    Accesos
                </span>

                <h3>
                    Atajos
                </h3>


                <a
                    href="/agenda.php#nuevo-evento"
                >

                    <span>+</span>

                    <div>
                        <strong>
                            Nuevo evento
                        </strong>
                        <small>
                            Abrir Quick Add
                        </small>
                    </div>

                    <i>N</i>

                </a>


                <a href="/tareas.php">

                    <span>✓</span>

                    <div>
                        <strong>
                            Tareas
                        </strong>
                        <small>
                            Próximas acciones
                        </small>
                    </div>

                    <i>→</i>

                </a>


                <a href="/calendario.php">

                    <span>□</span>

                    <div>
                        <strong>
                            Calendario
                        </strong>
                        <small>
                            Vista mensual
                        </small>
                    </div>

                    <i>→</i>

                </a>


                <a href="/materias.php">

                    <span>▦</span>

                    <div>
                        <strong>
                            Materias
                        </strong>
                        <small>
                            Workspaces académicos
                        </small>
                    </div>

                    <i>→</i>

                </a>

            </section>

        </aside>

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
