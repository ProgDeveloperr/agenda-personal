<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/materias_repository.php';
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


function materiaTurno(
    ?string $turno
): string {

    return match ($turno) {
        'MANANA' => 'Mañana',
        'TARDE' => 'Tarde',
        'NOCHE' => 'Noche',
        'OTRO' => 'Otro',
        default => 'Sin especificar',
    };
}


function materiaTipo(
    string $tipo
): string {

    return match ($tipo) {
        'PARCIAL' => 'Parcial',
        'FINAL' => 'Final',
        'RECUPERATORIO' =>
            'Recuperatorio',
        'TP' =>
            'Trabajo práctico',
        'ENTREGA' => 'Entrega',
        'OTRO' => 'Otro',
        default => $tipo,
    };
}


function materiaEstado(
    string $estado
): string {

    return match ($estado) {
        'PENDIENTE' => 'Pendiente',
        'ENTREGADO' => 'Entregado',
        'APROBADO' => 'Aprobado',
        'DESAPROBADO' =>
            'Desaprobado',
        'AUSENTE' => 'Ausente',
        'CANCELADO' => 'Cancelado',
        default => $estado,
    };
}


function materiaEstadoClase(
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


function materiaMesCorto(
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


function materiaFecha(
    string $fecha
): string {

    $dt =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );

    return $dt !== false
        ? $dt->format('d/m/Y')
        : $fecha;
}


function materiaHora(
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


function materiaNota(
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


$materiaId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );


$errorPagina = null;
$materia = null;
$eventos = [];
$tareas = [];


if (
    $materiaId === null
    || $materiaId === false
) {

    http_response_code(400);

    $errorPagina =
        'El identificador de materia no es válido.';

} else {

    $materia =
        buscarMateria($tenantUserId, 
            (int) $materiaId
        );


    if ($materia === null) {

        http_response_code(404);

        $errorPagina =
            'La materia solicitada no existe.';

    } else {

        $eventos =
            listarEventosPorMateria($tenantUserId, 
                (int) $materiaId
            );


        $tareas =
            listarTareas($tenantUserId,
                null,
                (int) $materiaId
            );
    }
}


$totalEventos =
    count($eventos);


$totalTareas =
    count($tareas);


$tareasAbiertas =
    count(
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


$tareasCompletadas =
    count(
        array_filter(
            $tareas,
            static fn (
                array $tarea
            ): bool =>
                $tarea['estado']
                === 'COMPLETADA'
        )
    );


$pendientes = 0;
$entregados = 0;
$aprobados = 0;
$desaprobados = 0;
$ausentes = 0;
$cancelados = 0;

$notas = [];

$proximos = [];
$historial = [];


$zona =
    new DateTimeZone(
        'America/Argentina/Buenos_Aires'
    );

$hoy =
    new DateTimeImmutable(
        'today',
        $zona
    );


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


    $fechaEvento =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) $evento['fecha'],
            $zona
        );


    $esFuturo =
        $fechaEvento !== false
        && $fechaEvento >= $hoy
        && $evento['estado']
            !== 'CANCELADO';


    if ($esFuturo) {
        $proximos[] = $evento;
    } else {
        $historial[] = $evento;
    }
}


$historial =
    array_reverse(
        $historial
    );


$proximoEvento =
    $proximos[0]
    ?? null;


$promedio =
    $notas !== []
        ? array_sum($notas)
            / count($notas)
        : null;


$resueltos =
    $aprobados
    + $desaprobados
    + $ausentes
    + $cancelados;


$porcentajeAprobado =
    $totalEventos > 0
        ? round(
            (
                $aprobados
                / $totalEventos
            )
            * 100,
            1
        )
        : 0;


$porcentajeEntregado =
    $totalEventos > 0
        ? round(
            (
                $entregados
                / $totalEventos
            )
            * 100,
            1
        )
        : 0;


$porcentajePendiente =
    $totalEventos > 0
        ? round(
            (
                $pendientes
                / $totalEventos
            )
            * 100,
            1
        )
        : 0;


$porcentajeOtros =
    max(
        0,
        100
        - $porcentajeAprobado
        - $porcentajeEntregado
        - $porcentajePendiente
    );


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
        <?= $materia
            ? h($materia['nombre'])
                . ' | Agenda Personal'
            : 'Materia | Agenda Personal' ?>
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

    <link rel="stylesheet" href="/assets/css/materia-workspace-legibilidad.css?v=4">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-materia.css?v=2">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-subject-workspace-v3">


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

<a href="/calendario.php">
                Calendario
            </a>

            <a href="/vida.php">
                Vida
            </a>

            <a
                class="active"
                href="/materias.php"
            >
                Materias
            </a>

        
            <a href="/rendimiento.php">
                Rendimiento
            </a>
</nav>


        <div class="app-header-status">

            <span
                class="status-dot <?= $materia
                    && (int) $materia['activa'] === 1
                    ? 'status-dot-online'
                    : '' ?>"
            ></span>

            <span>
                <?= $materia
                    ? (
                        (int) $materia['activa'] === 1
                            ? 'Materia activa'
                            : 'Materia inactiva'
                    )
                    : 'Materia' ?>
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="subject-workspace-shell-v3">


<?php if ($errorPagina !== null): ?>

    <section class="subject-workspace-error-v3">

        <div class="subject-workspace-error-icon-v3">
            !
        </div>

        <span class="section-kicker">
            Materias
        </span>

        <h1>
            No pudimos abrir esta materia
        </h1>

        <p>
            <?= h($errorPagina) ?>
        </p>

        <a href="/materias.php">
            ← Volver a Materias
        </a>

    </section>


<?php else: ?>


    <a
        href="/materias.php"
        class="subject-back-v3"
    >
        <span>←</span>
        Todas las materias
    </a>


    <section
        class="subject-workspace-hero-v3"
        style="--subject-color: <?= h(
            $materia['color']
        ) ?>;"
    >

        <div class="subject-workspace-identity-v3">

            <div class="subject-workspace-eyebrow-v3">

                <span></span>

                <?= $materia['codigo']
                    ? '[' . h(
                        $materia['codigo']
                    ) . ']'
                    : 'SIN CÓDIGO' ?>

                <i>·</i>

                <?= (int) $materia['activa'] === 1
                    ? 'ACTIVA'
                    : 'INACTIVA' ?>

            </div>


            <h1>
                <?= h(
                    $materia['nombre']
                ) ?>
            </h1>


            <p>
                Workspace académico de la materia:
                próximos eventos, historial,
                rendimiento y accesos rápidos.
            </p>


            <div class="subject-workspace-tags-v3">

                <span>

                    <svg viewBox="0 0 24 24">
                        <path
                            d="M4 7h16M7 4v6M17 4v6M5 12h14M5 17h14"
                        />
                    </svg>

                    <?= $materia['cuatrimestre'] !== null
                        ? (
                            (int) $materia['cuatrimestre']
                            . '° cuatrimestre'
                        )
                        : 'Sin cuatrimestre' ?>

                </span>


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
                        materiaTurno(
                            $materia['turno']
                        )
                    ) ?>

                </span>


                <span>

                    <svg viewBox="0 0 24 24">
                        <path
                            d="M5 5h14v14H5z"
                        />
                        <path
                            d="M8 9h8M8 13h5"
                        />
                    </svg>

                    <?= h(
                        $materia['comision']
                        ?? 'Sin comisión'
                    ) ?>

                </span>

            </div>

        </div>


        <div class="subject-workspace-actions-v3">

            <a
                href="/agenda.php#nuevo-evento"
                data-quick-materia-id="<?= (int) $materia['id'] ?>"
                class="subject-primary-action-v3"
            >

                <svg viewBox="0 0 24 24">
                    <path
                        d="M12 5v14M5 12h14"
                    />
                </svg>

                Nuevo evento

            </a>


            <a
                href="/materias.php?editar=<?= (int) $materia['id'] ?>"
                class="subject-secondary-action-v3"
            >
                Editar materia
            </a>

        </div>

    </section>


    <section class="subject-workspace-metrics-v3">

        <article>

            <span>
                Eventos
            </span>

            <strong>
                <?= $totalEventos ?>
            </strong>

            <small>
                Historial total
            </small>

        </article>


        <article>

            <span>
                Pendientes
            </span>

            <strong>
                <?= $pendientes ?>
            </strong>

            <small>
                Por completar
            </small>

        </article>


        <article>

            <span>
                Entregados
            </span>

            <strong>
                <?= $entregados ?>
            </strong>

            <small>
                Esperando resolución
            </small>

        </article>


        <article>

            <span>
                Promedio
            </span>

            <strong>
                <?= $promedio !== null
                    ? h(
                        materiaNota(
                            $promedio
                        )
                    )
                    : '—' ?>
            </strong>

            <small>
                Sólo notas cargadas
            </small>

        </article>

    </section>


    <section class="subject-workspace-layout-v3">


        <div class="subject-workspace-main-v3">


            <section class="subject-next-v3">

                <div class="subject-section-heading-v3">

                    <div>

                        <span class="section-kicker">
                            Lo próximo
                        </span>

                        <h2>
                            Próximo evento
                        </h2>

                    </div>


                    <a
                        href="/agenda.php?materia=<?= (int) $materia['id'] ?>"
                    >
                        Ver en Agenda
                        <span>→</span>
                    </a>

                </div>


                <?php if ($proximoEvento): ?>

                    <?php

                    $fechaProximo =
                        DateTimeImmutable::createFromFormat(
                            '!Y-m-d',
                            (string) $proximoEvento['fecha']
                        );

                    ?>

                    <a
                        href="/agenda.php#evento-<?= (int) $proximoEvento['id'] ?>"
                        class="subject-next-card-v3"
                        style="--subject-color: <?= h(
                            $materia['color']
                        ) ?>;"
                    >

                        <div class="subject-event-date-v3">

                            <span>
                                <?= $fechaProximo
                                    ? materiaMesCorto(
                                        (int) $fechaProximo->format('n')
                                    )
                                    : '' ?>
                            </span>

                            <strong>
                                <?= $fechaProximo
                                    ? $fechaProximo->format('d')
                                    : '--' ?>
                            </strong>

                            <small>
                                <?= $fechaProximo
                                    ? $fechaProximo->format('Y')
                                    : '' ?>
                            </small>

                        </div>


                        <div class="subject-next-content-v3">

                            <div class="subject-event-meta-v3">

                                <span>
                                    <?= h(
                                        materiaTipo(
                                            $proximoEvento['tipo']
                                        )
                                    ) ?>
                                </span>

                                <span>·</span>

                                <span>
                                    <?= h(
                                        materiaHora(
                                            $proximoEvento['hora']
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <h3>
                                <?= h(
                                    $proximoEvento['titulo']
                                ) ?>
                            </h3>


                            <p>
                                <?= h(
                                    $proximoEvento['lugar']
                                    ?: 'Lugar sin especificar'
                                ) ?>
                            </p>

                        </div>


                        <div class="subject-next-state-v3">

                            <span
                                class="subject-event-status-v3 subject-event-status-<?= h(
                                    materiaEstadoClase(
                                        $proximoEvento['estado']
                                    )
                                ) ?>"
                            >
                                <?= h(
                                    materiaEstado(
                                        $proximoEvento['estado']
                                    )
                                ) ?>
                            </span>

                            <i>→</i>

                        </div>

                    </a>

                <?php else: ?>

                    <div class="subject-empty-panel-v3">

                        <div>✓</div>

                        <strong>
                            No hay próximos eventos
                        </strong>

                        <p>
                            Cuando agregues una nueva fecha
                            aparecerá acá automáticamente.
                        </p>

                    </div>

                <?php endif; ?>

            </section>



            <section class="subject-tasks-v4">

                <div class="subject-section-heading-v3">

                    <div>

                        <span class="section-kicker">
                            Ejecución
                        </span>

                        <h2>
                            Tareas
                        </h2>

                    </div>


                    <div class="subject-task-heading-v4">

                        <span class="subject-section-count-v3">
                            <?= $tareasAbiertas ?>
                            abiertas
                            ·
                            <?= $tareasCompletadas ?>
                            completadas
                        </span>

                        <a href="/tareas.php">
                            Ver workspace
                            <i>→</i>
                        </a>

                    </div>

                </div>


                <?php if (
                    $tareas === []
                ): ?>

                    <div class="subject-empty-panel-v3">

                        <div>✓</div>

                        <strong>
                            Sin tareas asociadas
                        </strong>

                        <p>
                            Las tareas vinculadas a esta materia
                            aparecerán acá.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="subject-task-list-v4">


                    <?php foreach (
                        $tareas
                        as $tarea
                    ): ?>

                        <?php

                        $fechaTarea =
                            !empty(
                                $tarea[
                                    'fecha_limite'
                                ]
                            )
                                ? DateTimeImmutable::createFromFormat(
                                    '!Y-m-d',
                                    (string)
                                        $tarea[
                                            'fecha_limite'
                                        ]
                                )
                                : false;

                        ?>


                        <a
                            href="/tareas.php?editar=<?= (int)
                                $tarea['id'] ?>#task-form"
                            class="
                                subject-task-item-v4
                                priority-<?= h(
                                    strtolower(
                                        $tarea[
                                            'prioridad'
                                        ]
                                    )
                                ) ?>
                            "
                        >

                            <span
                                class="subject-task-accent-v4"
                                style="background: <?= h(
                                    $materia[
                                        'color'
                                    ]
                                ) ?>;"
                            ></span>


                            <div class="subject-task-date-v4">

                                <strong>
                                    <?= $fechaTarea
                                        ? $fechaTarea
                                            ->format('d')
                                        : '—' ?>
                                </strong>

                                <span>
                                    <?= $fechaTarea
                                        ? materiaMesCorto(
                                            (int)
                                                $fechaTarea
                                                    ->format(
                                                        'n'
                                                    )
                                        )
                                        : 'S/F' ?>
                                </span>

                            </div>


                            <div class="subject-task-copy-v4">

                                <div>

                                    <span>
                                        <?= h(
                                            ucfirst(
                                                strtolower(
                                                    $tarea[
                                                        'prioridad'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </span>

                                    <i>·</i>

                                    <span>
                                        <?= h(
                                            materiaHora(
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
                                                    : (
                                                        $tarea[
                                                            'estado'
                                                        ]
                                                        === 'CANCELADA'
                                                            ? 'Cancelada'
                                                            : 'Pendiente'
                                                    )
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


                            <span class="subject-task-arrow-v4">
                                →
                            </span>

                        </a>


                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>


            <section class="subject-timeline-v3">

                <div class="subject-section-heading-v3">

                    <div>

                        <span class="section-kicker">
                            Actividad académica
                        </span>

                        <h2>
                            Historial y próximos
                        </h2>

                    </div>


                    <span class="subject-section-count-v3">
                        <?= $totalEventos ?>
                        <?= $totalEventos === 1
                            ? 'evento'
                            : 'eventos' ?>
                    </span>

                </div>


                <?php if ($eventos === []): ?>

                    <div class="subject-empty-panel-v3">

                        <div>+</div>

                        <strong>
                            Todavía no hay actividad
                        </strong>

                        <p>
                            Creá el primer evento de esta materia
                            utilizando Quick Add.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="subject-timeline-list-v3">


                    <?php foreach (
                        $eventos
                        as $evento
                    ): ?>

                        <?php

                        $fechaEvento =
                            DateTimeImmutable::createFromFormat(
                                '!Y-m-d',
                                (string) $evento['fecha']
                            );

                        ?>


                        <a
                            href="/agenda.php#evento-<?= (int) $evento['id'] ?>"
                            class="subject-timeline-item-v3"
                        >

                            <span
                                class="subject-timeline-dot-v3"
                                style="background: <?= h(
                                    $materia['color']
                                ) ?>;"
                            ></span>


                            <div class="subject-timeline-date-v3">

                                <strong>
                                    <?= $fechaEvento
                                        ? $fechaEvento->format('d')
                                        : '--' ?>
                                </strong>

                                <span>
                                    <?= $fechaEvento
                                        ? materiaMesCorto(
                                            (int) $fechaEvento->format('n')
                                        )
                                        : '' ?>
                                </span>

                            </div>


                            <div class="subject-timeline-copy-v3">

                                <div>

                                    <span>
                                        <?= h(
                                            materiaTipo(
                                                $evento['tipo']
                                            )
                                        ) ?>
                                    </span>

                                    <span>·</span>

                                    <span>
                                        <?= h(
                                            materiaHora(
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
                                        $evento['lugar']
                                        ?: 'Lugar sin especificar'
                                    ) ?>
                                </small>

                            </div>


                            <div class="subject-timeline-result-v3">

                                <?php if (
                                    $evento['nota']
                                    !== null
                                ): ?>

                                    <strong>
                                        <?= h(
                                            materiaNota(
                                                $evento['nota']
                                            )
                                        ) ?>
                                    </strong>

                                    <small>
                                        Nota
                                    </small>

                                <?php else: ?>

                                    <span
                                        class="subject-event-status-v3 subject-event-status-<?= h(
                                            materiaEstadoClase(
                                                $evento['estado']
                                            )
                                        ) ?>"
                                    >
                                        <?= h(
                                            materiaEstado(
                                                $evento['estado']
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </a>

                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>

        </div>


        <aside class="subject-workspace-side-v3">


            <section class="subject-profile-card-v3">

                <div class="subject-profile-heading-v3">

                    <span
                        style="background: <?= h(
                            $materia['color']
                        ) ?>;"
                    ></span>

                    <div>

                        <small>
                            Información
                        </small>

                        <strong>
                            Datos académicos
                        </strong>

                    </div>

                </div>


                <dl>

                    <div>
                        <dt>
                            Docente
                        </dt>

                        <dd>
                            <?= h(
                                $materia['docente']
                                ?? 'Sin especificar'
                            ) ?>
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Comisión
                        </dt>

                        <dd>
                            <?= h(
                                $materia['comision']
                                ?? 'Sin especificar'
                            ) ?>
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Turno
                        </dt>

                        <dd>
                            <?= h(
                                materiaTurno(
                                    $materia['turno']
                                )
                            ) ?>
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Cuatrimestre
                        </dt>

                        <dd>
                            <?= $materia['cuatrimestre'] !== null
                                ? (
                                    (int) $materia['cuatrimestre']
                                    . '°'
                                )
                                : '—' ?>
                        </dd>
                    </div>


                    <div>
                        <dt>
                            Código
                        </dt>

                        <dd>
                            <?= h(
                                $materia['codigo']
                                ?? 'Sin código'
                            ) ?>
                        </dd>
                    </div>

                </dl>

            </section>


            <section class="subject-performance-v3">

                <div>

                    <span class="section-kicker">
                        Rendimiento
                    </span>

                    <h3>
                        Estado académico
                    </h3>

                </div>


                <div class="subject-performance-main-v3">

                    <strong>
                        <?= $promedio !== null
                            ? h(
                                materiaNota(
                                    $promedio
                                )
                            )
                            : '—' ?>
                    </strong>

                    <div>

                        <span>
                            Promedio actual
                        </span>

                        <small>
                            <?= count($notas) ?>
                            <?= count($notas) === 1
                                ? 'nota cargada'
                                : 'notas cargadas' ?>
                        </small>

                    </div>

                </div>


                <div class="subject-performance-track-v3">

                    <?php if ($porcentajeAprobado > 0): ?>
                        <span
                            class="approved"
                            style="width: <?= h(
                                (string) $porcentajeAprobado
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>

                    <?php if ($porcentajeEntregado > 0): ?>
                        <span
                            class="delivered"
                            style="width: <?= h(
                                (string) $porcentajeEntregado
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>

                    <?php if ($porcentajePendiente > 0): ?>
                        <span
                            class="pending"
                            style="width: <?= h(
                                (string) $porcentajePendiente
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>

                    <?php if ($porcentajeOtros > 0): ?>
                        <span
                            class="other"
                            style="width: <?= h(
                                (string) $porcentajeOtros
                            ) ?>%;"
                        ></span>
                    <?php endif; ?>

                </div>


                <div class="subject-performance-legend-v3">

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


            <section class="subject-actions-card-v3">

                <span class="section-kicker">
                    Accesos
                </span>

                <h3>
                    Acciones rápidas
                </h3>


                <a
                    href="/agenda.php#nuevo-evento"
                    data-quick-materia-id="<?= (int) $materia['id'] ?>"
                >
                    <span>+</span>

                    <div>
                        <strong>
                            Nuevo evento
                        </strong>

                        <small>
                            Quick Add preseleccionado
                        </small>
                    </div>

                    <i>→</i>
                </a>


                <a
                    href="/agenda.php?materia=<?= (int) $materia['id'] ?>"
                >
                    <span>⌁</span>

                    <div>
                        <strong>
                            Ver en Agenda
                        </strong>

                        <small>
                            Filtrar esta materia
                        </small>
                    </div>

                    <i>→</i>
                </a>


                <a
                    href="/materias.php?editar=<?= (int) $materia['id'] ?>"
                >
                    <span>✎</span>

                    <div>
                        <strong>
                            Editar materia
                        </strong>

                        <small>
                            Datos académicos
                        </small>
                    </div>

                    <i>→</i>
                </a>

            </section>

        </aside>

    </section>


<?php endif; ?>


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
