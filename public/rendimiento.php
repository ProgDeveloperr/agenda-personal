<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/materias_repository.php';
require_once '/var/www/app/eventos_repository.php';

iniciarSesion();


function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function rendimientoEstado(
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


function rendimientoEstadoClase(
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


function rendimientoTipo(
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


function rendimientoNota(
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


function rendimientoFecha(
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


$materias =
    listarMaterias($tenantUserId);

$eventos =
    listarEventos($tenantUserId);


$totalMaterias =
    count(
        array_filter(
            $materias,
            static fn (
                array $materia
            ): bool =>
                (int) $materia['activa']
                === 1
        )
    );


$totalEventos =
    count($eventos);


$pendientes = 0;
$entregados = 0;
$aprobados = 0;
$desaprobados = 0;
$ausentes = 0;
$cancelados = 0;

$notas = [];
$historialNotas = [];
$cargaActual = [];

$statsMateria = [];


foreach ($materias as $materia) {

    $id =
        (int) $materia['id'];


    $statsMateria[$id] = [
        'materia' =>
            $materia,

        'eventos' =>
            0,

        'pendientes' =>
            0,

        'entregados' =>
            0,

        'aprobados' =>
            0,

        'desaprobados' =>
            0,

        'notas' =>
            [],
    ];
}


foreach ($eventos as $evento) {

    $materiaId =
        (int) $evento['materia_id'];


    if (isset(
        $statsMateria[$materiaId]
    )) {

        $statsMateria[$materiaId][
            'eventos'
        ]++;
    }


    switch ($evento['estado']) {

        case 'PENDIENTE':

            $pendientes++;

            if (isset(
                $statsMateria[$materiaId]
            )) {
                $statsMateria[
                    $materiaId
                ]['pendientes']++;
            }

            $cargaActual[] =
                $evento;

            break;


        case 'ENTREGADO':

            $entregados++;

            if (isset(
                $statsMateria[$materiaId]
            )) {
                $statsMateria[
                    $materiaId
                ]['entregados']++;
            }

            $cargaActual[] =
                $evento;

            break;


        case 'APROBADO':

            $aprobados++;

            if (isset(
                $statsMateria[$materiaId]
            )) {
                $statsMateria[
                    $materiaId
                ]['aprobados']++;
            }

            break;


        case 'DESAPROBADO':

            $desaprobados++;

            if (isset(
                $statsMateria[$materiaId]
            )) {
                $statsMateria[
                    $materiaId
                ]['desaprobados']++;
            }

            break;


        case 'AUSENTE':
            $ausentes++;
            break;


        case 'CANCELADO':
            $cancelados++;
            break;
    }


    if ($evento['nota'] !== null) {

        $nota =
            (float) $evento['nota'];


        $notas[] =
            $nota;


        if (isset(
            $statsMateria[$materiaId]
        )) {

            $statsMateria[
                $materiaId
            ]['notas'][] =
                $nota;
        }


        $historialNotas[] =
            $evento;
    }
}


usort(
    $historialNotas,
    static fn (
        array $a,
        array $b
    ): int =>
        strcmp(
            (string) $b['fecha'],
            (string) $a['fecha']
        )
);


usort(
    $cargaActual,
    static function (
        array $a,
        array $b
    ): int {

        $fecha =
            strcmp(
                (string) $a['fecha'],
                (string) $b['fecha']
            );


        if ($fecha !== 0) {
            return $fecha;
        }


        return
            ((string) ($a['hora'] ?? '99:99'))
            <=>
            ((string) ($b['hora'] ?? '99:99'));
    }
);


$promedio =
    $notas !== []
        ? array_sum($notas)
            / count($notas)
        : null;


$totalCalificadas =
    $aprobados
    + $desaprobados;


$tasaAprobacion =
    $totalCalificadas > 0
        ? (
            $aprobados
            / $totalCalificadas
        ) * 100
        : null;


$materiasConNota =
    count(
        array_filter(
            $statsMateria,
            static fn (
                array $stats
            ): bool =>
                $stats['notas']
                !== []
        )
    );


$denominadorEstados =
    max(
        1,
        $totalEventos
    );


$pctAprobados =
    ($aprobados / $denominadorEstados)
    * 100;

$pctEntregados =
    ($entregados / $denominadorEstados)
    * 100;

$pctPendientes =
    ($pendientes / $denominadorEstados)
    * 100;

$pctOtros =
    max(
        0,
        100
        - $pctAprobados
        - $pctEntregados
        - $pctPendientes
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
        Rendimiento | Agenda Personal
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

    <link rel="stylesheet" href="/assets/css/rendimiento-legibilidad.css?v=6">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-rendimiento.css?v=3">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-performance-v3">


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

            <a
                class="active"
                href="/rendimiento.php"
            >
                Rendimiento
            </a>

        </nav>


        <div class="app-header-status">

            <span
                class="status-dot status-dot-online"
            ></span>

            <span>
                Métricas
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="performance-shell-v3">


    <section class="performance-hero-v3">

        <div>

            <span class="section-kicker">
                Análisis académico
            </span>


            <h1>
                Tu rendimiento,
                <span>con contexto.</span>
            </h1>


            <p>
                Notas, estados y carga académica
                sin inflar estadísticas con datos que todavía
                no existen.
            </p>

        </div>


        <div class="performance-sample-v3">

            <span>
                MUESTRA ACTUAL
            </span>

            <strong>
                <?= count($notas) ?>
                /
                <?= $totalEventos ?>
            </strong>

            <small>
                eventos con nota
            </small>

        </div>

    </section>


    <section class="performance-metrics-v3">


        <article>

            <span>
                Promedio general
            </span>

            <strong>
                <?= $promedio !== null
                    ? h(
                        rendimientoNota(
                            $promedio
                        )
                    )
                    : '—' ?>
            </strong>

            <small>
                <?= count($notas) ?>
                <?= count($notas) === 1
                    ? 'calificación'
                    : 'calificaciones' ?>
            </small>

        </article>


        <article>

            <span>
                Aprobación calificada
            </span>

            <strong>
                <?= $tasaAprobacion !== null
                    ? number_format(
                        $tasaAprobacion,
                        0,
                        ',',
                        '.'
                    ) . '%'
                    : '—' ?>
            </strong>

            <small>
                <?= $totalCalificadas ?>
                evaluaciones resueltas
            </small>

        </article>


        <article class="performance-metric-delivered-v3">

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


        <article class="performance-metric-pending-v3">

            <span>
                Pendientes
            </span>

            <strong>
                <?= $pendientes ?>
            </strong>

            <small>
                por completar
            </small>

        </article>

    </section>


    <section class="performance-layout-v3">


        <div class="performance-main-v3">


            <section class="performance-subjects-v3">

                <div class="performance-section-head-v3">

                    <div>

                        <span class="section-kicker">
                            Materias
                        </span>

                        <h2>
                            Rendimiento por materia
                        </h2>

                    </div>


                    <span>
                        <?= $totalMaterias ?>
                        activas
                    </span>

                </div>


                <div class="performance-subject-grid-v3">


                <?php foreach (
                    $statsMateria
                    as $stats
                ): ?>

                    <?php

                    $materia =
                        $stats['materia'];


                    if (
                        (int) $materia['activa']
                        !== 1
                    ) {
                        continue;
                    }


                    $promedioMateria =
                        $stats['notas'] !== []
                            ? array_sum(
                                $stats['notas']
                            ) / count(
                                $stats['notas']
                            )
                            : null;

                    ?>


                    <a
                        href="/materia.php?id=<?= (int) $materia['id'] ?>"
                        class="performance-subject-card-v3"
                        style="--subject-color: <?= h(
                            $materia['color']
                        ) ?>;"
                    >

                        <span
                            class="performance-subject-accent-v3"
                        ></span>


                        <div class="performance-subject-head-v3">

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

                            </div>


                            <span>
                                <?= $promedioMateria !== null
                                    ? h(
                                        rendimientoNota(
                                            $promedioMateria
                                        )
                                    )
                                    : '—' ?>
                            </span>

                        </div>


                        <div class="performance-subject-stats-v3">

                            <span>
                                <?= $stats['eventos'] ?>
                                eventos
                            </span>

                            <span>
                                <?= $stats['aprobados'] ?>
                                aprobados
                            </span>

                            <span>
                                <?= $stats['entregados'] ?>
                                entregados
                            </span>

                            <span>
                                <?= $stats['pendientes'] ?>
                                pendientes
                            </span>

                        </div>


                        <div class="performance-subject-foot-v3">

                            <?php if (
                                $promedioMateria !== null
                            ): ?>

                                <span class="with-grade">
                                    <?= count(
                                        $stats['notas']
                                    ) ?>
                                    <?= count(
                                        $stats['notas']
                                    ) === 1
                                        ? 'nota cargada'
                                        : 'notas cargadas' ?>
                                </span>

                            <?php else: ?>

                                <span>
                                    Sin calificaciones todavía
                                </span>

                            <?php endif; ?>


                            <i>
                                →
                            </i>

                        </div>

                    </a>

                <?php endforeach; ?>


                </div>

            </section>


            <section class="performance-history-v3">

                <div class="performance-section-head-v3">

                    <div>

                        <span class="section-kicker">
                            Calificaciones
                        </span>

                        <h2>
                            Historial de notas
                        </h2>

                    </div>


                    <span>
                        <?= count($historialNotas) ?>
                        registros
                    </span>

                </div>


                <?php if (
                    $historialNotas === []
                ): ?>

                    <div class="performance-empty-v3">

                        <span>—</span>

                        <strong>
                            Todavía no hay notas
                        </strong>

                        <p>
                            Las calificaciones aparecerán acá
                            cuando cierres evaluaciones.
                        </p>

                    </div>


                <?php else: ?>

                    <div class="performance-grade-list-v3">


                    <?php foreach (
                        $historialNotas
                        as $evento
                    ): ?>


                        <a
                            href="/materia.php?id=<?= (int) $evento['materia_id'] ?>"
                            class="performance-grade-item-v3"
                            style="--subject-color: <?= h(
                                $evento['materia_color']
                            ) ?>;"
                        >

                            <span
                                class="performance-grade-accent-v3"
                            ></span>


                            <div class="performance-grade-date-v3">

                                <span>
                                    <?= h(
                                        rendimientoFecha(
                                            $evento['fecha']
                                        )
                                    ) ?>
                                </span>

                            </div>


                            <div class="performance-grade-copy-v3">

                                <div>

                                    <span>
                                        <?= h(
                                            $evento['materia_codigo']
                                            ?: $evento['materia_nombre']
                                        ) ?>
                                    </span>

                                    <i>·</i>

                                    <span>
                                        <?= h(
                                            rendimientoTipo(
                                                $evento['tipo']
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
                                        $evento['materia_nombre']
                                    ) ?>
                                </small>

                            </div>


                            <span
                                class="performance-status-v3 performance-status-<?= h(
                                    rendimientoEstadoClase(
                                        $evento['estado']
                                    )
                                ) ?>"
                            >
                                <?= h(
                                    rendimientoEstado(
                                        $evento['estado']
                                    )
                                ) ?>
                            </span>


                            <div class="performance-grade-value-v3">

                                <strong>
                                    <?= h(
                                        rendimientoNota(
                                            $evento['nota']
                                        )
                                    ) ?>
                                </strong>

                                <small>
                                    nota
                                </small>

                            </div>

                        </a>


                    <?php endforeach; ?>


                    </div>

                <?php endif; ?>

            </section>

        </div>


        <aside class="performance-side-v3">


            <section class="performance-overview-v3">

                <span class="section-kicker">
                    Panorama
                </span>

                <h3>
                    Estado académico
                </h3>


                <div class="performance-overview-main-v3">

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


                <div class="performance-track-v3">

                    <?php if (
                        $pctAprobados > 0
                    ): ?>

                        <span
                            class="approved"
                            style="width: <?= h(
                                (string) $pctAprobados
                            ) ?>%;"
                        ></span>

                    <?php endif; ?>


                    <?php if (
                        $pctEntregados > 0
                    ): ?>

                        <span
                            class="delivered"
                            style="width: <?= h(
                                (string) $pctEntregados
                            ) ?>%;"
                        ></span>

                    <?php endif; ?>


                    <?php if (
                        $pctPendientes > 0
                    ): ?>

                        <span
                            class="pending"
                            style="width: <?= h(
                                (string) $pctPendientes
                            ) ?>%;"
                        ></span>

                    <?php endif; ?>


                    <?php if (
                        $pctOtros > 0
                    ): ?>

                        <span
                            class="other"
                            style="width: <?= h(
                                (string) $pctOtros
                            ) ?>%;"
                        ></span>

                    <?php endif; ?>

                </div>


                <div class="performance-overview-list-v3">

                    <div>
                        <i class="approved"></i>
                        <span>Aprobados</span>
                        <strong><?= $aprobados ?></strong>
                    </div>

                    <div>
                        <i class="delivered"></i>
                        <span>Entregados</span>
                        <strong><?= $entregados ?></strong>
                    </div>

                    <div>
                        <i class="pending"></i>
                        <span>Pendientes</span>
                        <strong><?= $pendientes ?></strong>
                    </div>

                    <?php if (
                        $desaprobados
                        + $ausentes
                        + $cancelados
                        > 0
                    ): ?>

                        <div>
                            <i class="other"></i>
                            <span>Otros</span>

                            <strong>
                                <?= $desaprobados
                                    + $ausentes
                                    + $cancelados ?>
                            </strong>
                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <section class="performance-sample-card-v3">

                <span class="section-kicker">
                    Calidad de muestra
                </span>

                <h3>
                    Contexto estadístico
                </h3>


                <div class="performance-sample-grid-v3">

                    <div>

                        <strong>
                            <?= count($notas) ?>
                        </strong>

                        <span>
                            notas
                        </span>

                    </div>


                    <div>

                        <strong>
                            <?= $materiasConNota ?>
                        </strong>

                        <span>
                            materias con nota
                        </span>

                    </div>

                </div>


                <?php if (
                    count($notas) < 3
                ): ?>

                    <p>
                        El promedio actual es válido para
                        las notas registradas, pero todavía
                        hay poco historial para interpretar
                        una tendencia.
                    </p>

                <?php else: ?>

                    <p>
                        Ya existe suficiente historial para
                        empezar a observar una tendencia
                        académica.
                    </p>

                <?php endif; ?>

            </section>


            <section class="performance-open-v3">

                <span class="section-kicker">
                    En curso
                </span>

                <h3>
                    Carga actual
                </h3>


                <?php if (
                    $cargaActual === []
                ): ?>

                    <div class="performance-mini-empty-v3">
                        No hay eventos pendientes ni entregados.
                    </div>


                <?php else: ?>


                    <?php foreach (
                        $cargaActual
                        as $evento
                    ): ?>

                        <a
                            href="/agenda.php#evento-<?= (int) $evento['id'] ?>"
                            class="performance-open-item-v3"
                            style="--subject-color: <?= h(
                                $evento['materia_color']
                            ) ?>;"
                        >

                            <span></span>


                            <div>

                                <strong>
                                    <?= h(
                                        $evento['materia_codigo']
                                        ?: $evento['materia_nombre']
                                    ) ?>
                                </strong>

                                <small>
                                    <?= h(
                                        $evento['titulo']
                                    ) ?>

                                    ·

                                    <?= h(
                                        rendimientoFecha(
                                            $evento['fecha']
                                        )
                                    ) ?>
                                </small>

                            </div>


                            <b
                                class="performance-status-v3 performance-status-<?= h(
                                    rendimientoEstadoClase(
                                        $evento['estado']
                                    )
                                ) ?>"
                            >
                                <?= h(
                                    rendimientoEstado(
                                        $evento['estado']
                                    )
                                ) ?>
                            </b>

                        </a>

                    <?php endforeach; ?>


                <?php endif; ?>

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
