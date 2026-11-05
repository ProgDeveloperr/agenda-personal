<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/vida_repository.php';

iniciarSesion();


function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function fechaNacimientoValida(
    string $fecha
): bool {
    $zona = new DateTimeZone(
        'America/Argentina/Buenos_Aires'
    );

    $dt = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha,
        $zona
    );

    if (
        $dt === false
        || $dt->format('Y-m-d') !== $fecha
    ) {
        return false;
    }

    $hoy = new DateTimeImmutable(
        'today',
        $zona
    );

    return $dt <= $hoy;
}


function sumarMesesSeguro(
    DateTimeImmutable $fecha,
    int $meses
): DateTimeImmutable {
    $anio =
        (int) $fecha->format('Y');

    $mes =
        (int) $fecha->format('n');

    $dia =
        (int) $fecha->format('j');


    $total =
        ($anio * 12)
        + ($mes - 1)
        + $meses;


    $nuevoAnio =
        intdiv($total, 12);

    $nuevoMes =
        ($total % 12) + 1;


    $ultimoDiaMes =
        (int) (
            new DateTimeImmutable(
                sprintf(
                    '%04d-%02d-01',
                    $nuevoAnio,
                    $nuevoMes
                )
            )
        )
        ->modify('last day of this month')
        ->format('j');


    $nuevoDia =
        min(
            $dia,
            $ultimoDiaMes
        );


    return $fecha->setDate(
        $nuevoAnio,
        $nuevoMes,
        $nuevoDia
    );
}


function indiceMesVidaActual(
    DateTimeImmutable $nacimiento,
    DateTimeImmutable $hoy
): int {
    if ($hoy < $nacimiento) {
        return 0;
    }


    $meses =
        (
            (int) $hoy->format('Y')
            -
            (int) $nacimiento->format('Y')
        ) * 12
        +
        (
            (int) $hoy->format('n')
            -
            (int) $nacimiento->format('n')
        );


    $inicioMesActual =
        sumarMesesSeguro(
            $nacimiento,
            $meses
        );


    if ($inicioMesActual > $hoy) {
        $meses--;
    }


    return max(
        0,
        min(
            1079,
            $meses
        )
    );
}


function formatearFechaVida(
    DateTimeImmutable $fecha
): string {
    return $fecha->format(
        'd/m/Y'
    );
}


function textoEdadVida(
    int $indice
): string {
    $anios =
        intdiv(
            $indice,
            12
        );

    $meses =
        $indice % 12;


    if ($meses === 0) {
        return $anios
            . (
                $anios === 1
                    ? ' año'
                    : ' años'
            );
    }


    return $anios
        . (
            $anios === 1
                ? ' año'
                : ' años'
        )
        . ' y '
        . $meses
        . (
            $meses === 1
                ? ' mes'
                : ' meses'
        );
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCsrf()) {

        http_response_code(403);

        exit(
            'Solicitud rechazada: token CSRF inválido.'
        );
    }


    $accion = trim(
        (string) ($_POST['accion'] ?? '')
    );


    if (
        $accion
        === 'guardar_fecha_nacimiento'
    ) {

        $fecha = trim(
            (string) (
                $_POST['fecha_nacimiento']
                ?? ''
            )
        );


        if (!fechaNacimientoValida($fecha)) {

            guardarMensaje(
                'error',
                'Ingresá una fecha de nacimiento válida.'
            );

        } else {

            guardarFechaNacimientoVida(
                $tenantUserId,
                $fecha
            );

            guardarMensaje(
                'exito',
                'Fecha de nacimiento actualizada.'
            );

        }


        header(
            'Location: /vida.php'
        );

        exit;
    }
}


$fechaNacimiento =
    obtenerFechaNacimientoVida($tenantUserId);

$horizonteAnios =
    obtenerHorizonteVidaAnios($tenantUserId);

$totalMeses =
    $horizonteAnios * 12;

$token =
    csrfToken();

$mensaje =
    obtenerMensaje();


$estados = [];

$marcados = 0;
$sinMarcar = $totalMeses;

$porcentaje = 0.0;

$indiceActual = null;

$nacimiento = null;

$inicioPeriodoActual = null;
$finPeriodoActual = null;

$edadAniosActual = null;
$edadMesesActual = null;


$zona = new DateTimeZone(
    'America/Argentina/Buenos_Aires'
);

$hoy = new DateTimeImmutable(
    'today',
    $zona
);


if (
    $fechaNacimiento !== null
    && fechaNacimientoValida(
        $fechaNacimiento
    )
) {

    $nacimiento =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fechaNacimiento,
            $zona
        );


    if ($nacimiento !== false) {

        $estados =
            obtenerCalendarioVida($tenantUserId);

        $marcados =
            contarMesesVidaMarcados($tenantUserId);

        $sinMarcar =
            max(
                0,
                $totalMeses
                - $marcados
            );

        $porcentaje =
            round(
                ($marcados / $totalMeses)
                * 100,
                1
            );


        $indiceActual =
            indiceMesVidaActual(
                $nacimiento,
                $hoy
            );


        $inicioPeriodoActual =
            sumarMesesSeguro(
                $nacimiento,
                $indiceActual
            );

        $finPeriodoActual =
            sumarMesesSeguro(
                $nacimiento,
                $indiceActual + 1
            )
            ->modify('-1 day');


        $edadAniosActual =
            intdiv(
                $indiceActual,
                12
            );

        $edadMesesActual =
            $indiceActual % 12;
    }
}

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
        Calendario de Vida | Agenda Personal
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
        href="/assets/css/ui-v3.css?v=27"
    >

    <script
        src="/assets/js/vida.js?v=3"
        defer
    ></script>



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

    <link rel="stylesheet" href="/assets/css/vida-legibilidad.css?v=7">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/vida-mobile.css?v=2">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-vida.css?v=2">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-life-v3">


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

            <a
                class="active"
                href="/vida.php"
            >
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
                <?= $marcados ?>
                marcados
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="life-shell-v3">


    <section class="life-hero-v3">

        <div class="life-hero-copy-v3">

            <div class="hero-eyebrow">

                <span class="eyebrow-dot"></span>

                Calendario personal

            </div>


            <h1>
                Calendario de Vida
            </h1>


            <p>
                Noventa años convertidos en 1.080 meses.
                Una perspectiva visual de tu tiempo,
                marcada completamente por vos.
            </p>


            <?php if (
                $nacimiento instanceof DateTimeImmutable
            ): ?>

                <div class="life-hero-meta-v3">

                    <span>

                        <svg viewBox="0 0 24 24">
                            <path
                                d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                            />
                            <path
                                d="M8 2v4M16 2v4M4 9h16"
                            />
                        </svg>

                        Nacimiento
                        <?= h(
                            formatearFechaVida(
                                $nacimiento
                            )
                        ) ?>

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

                        Horizonte
                        <?= $horizonteAnios ?>
                        años

                    </span>

                </div>

            <?php endif; ?>

        </div>


        <div class="life-progress-orb-card-v3">

            <div
                class="life-progress-orb-v3"
                style="--life-progress: <?= h(
                    (string) $porcentaje
                ) ?>%;"
            >

                <div class="life-progress-orb-inner-v3">

                    <strong id="vidaPorcentaje">
                        <?= h(
                            str_replace(
                                '.',
                                ',',
                                (string) $porcentaje
                            )
                        ) ?>
                        %
                    </strong>

                    <span>
                        marcado
                    </span>

                </div>

            </div>


            <div class="life-progress-copy-v3">

                <strong>
                    <?= $marcados ?>
                    de
                    <?= $totalMeses ?>
                </strong>

                <span>
                    meses registrados
                </span>

            </div>

        </div>

    </section>


    <?php if ($mensaje !== null): ?>

        <div
            class="agenda-message-v3 <?= $mensaje['tipo'] === 'exito'
                ? 'agenda-message-success-v3'
                : 'agenda-message-error-v3' ?>"
        >

            <span class="agenda-message-icon-v3">
                <?= $mensaje['tipo'] === 'exito'
                    ? '✓'
                    : '!' ?>
            </span>

            <span>
                <?= h(
                    (string) $mensaje['texto']
                ) ?>
            </span>

        </div>

    <?php endif; ?>


    <?php if (
        $nacimiento instanceof DateTimeImmutable
    ): ?>


        <section class="life-metrics-v3">

            <article>

                <div class="life-metric-icon-v3">

                    <svg viewBox="0 0 24 24">
                        <path
                            d="M5 5h3v3H5zM10.5 5h3v3h-3zM16 5h3v3h-3zM5 10.5h3v3H5zM10.5 10.5h3v3h-3zM16 10.5h3v3h-3zM5 16h3v3H5zM10.5 16h3v3h-3z"
                        />
                    </svg>

                </div>

                <div>

                    <span>
                        Marcados
                    </span>

                    <strong id="vidaMarcados">
                        <?= $marcados ?>
                    </strong>

                    <small>
                        Meses manuales
                    </small>

                </div>

            </article>


            <article>

                <div class="life-metric-icon-v3">

                    <svg viewBox="0 0 24 24">
                        <path
                            d="M4 12h16"
                        />
                        <path
                            d="M12 4v16"
                        />
                    </svg>

                </div>

                <div>

                    <span>
                        Sin marcar
                    </span>

                    <strong id="vidaRestantes">
                        <?= $sinMarcar ?>
                    </strong>

                    <small>
                        Del horizonte
                    </small>

                </div>

            </article>


            <article>

                <div class="life-metric-icon-v3">

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
                        Mes actual
                    </span>

                    <strong>
                        #<?= (int) $indiceActual + 1 ?>
                    </strong>

                    <small>
                        De tu vida
                    </small>

                </div>

            </article>


            <article>

                <div class="life-metric-icon-v3">

                    <svg viewBox="0 0 24 24">
                        <path
                            d="M6 19 18 5"
                        />
                        <path
                            d="M7 5h4M13 19h4"
                        />
                    </svg>

                </div>

                <div>

                    <span>
                        Edad del período
                    </span>

                    <strong>
                        <?= h(
                            textoEdadVida(
                                (int) $indiceActual
                            )
                        ) ?>
                    </strong>

                    <small>
                        Al comenzar el mes
                    </small>

                </div>

            </article>

        </section>


        <section class="life-current-v3">

            <div class="life-current-primary-v3">

                <span class="section-kicker">
                    Ahora
                </span>

                <strong>
                    Mes
                    #<?= (int) $indiceActual + 1 ?>
                </strong>

                <p>
                    Estás transitando este período actualmente.
                </p>

            </div>


            <div class="life-current-period-v3">

                <span>
                    Período actual
                </span>

                <strong>
                    <?= h(
                        formatearFechaVida(
                            $inicioPeriodoActual
                        )
                    ) ?>

                    <span>→</span>

                    <?= h(
                        formatearFechaVida(
                            $finPeriodoActual
                        )
                    ) ?>
                </strong>

            </div>


            <div class="life-current-age-v3">

                <span>
                    Edad al comenzar
                </span>

                <strong>
                    <?= h(
                        textoEdadVida(
                            (int) $indiceActual
                        )
                    ) ?>
                </strong>

            </div>


            <div class="life-current-rule-v3">

                <i></i>

                <p>
                    El borde azul identifica
                    el mes actual.
                    Nunca se marca automáticamente.
                </p>

            </div>

        </section>


        <section class="life-main-panel-v3">

            <div class="life-main-header-v3">

                <div>

                    <span class="section-kicker">
                        Tu tiempo
                    </span>

                    <h2>
                        Mis 1.080 meses
                    </h2>

                    <p>
                        Cada cuadrado representa un mes.
                        Seleccioná un mes para ver su detalle, marcarlo o registrar un hito.
                    </p>

                </div>


                <div class="life-legend-v3">

                    <span>
                        <i class="life-legend-empty-v3"></i>
                        Vacío
                    </span>

                    <span>
                        <i class="life-legend-marked-v3"></i>
                        Marcado
                    </span>

                    <span>
                        <i class="life-legend-current-v3"></i>
                        Mes actual
                    </span>

                </div>

            </div>


            <div class="life-progress-track-wrap-v3">

                <div class="life-progress-track-labels-v3">

                    <span>
                        Inicio
                    </span>

                    <strong>
                        <?= h(
                            str_replace(
                                '.',
                                ',',
                                (string) $porcentaje
                            )
                        ) ?>
                        %
                    </strong>

                    <span>
                        90 años
                    </span>

                </div>


                <div
                    class="life-progress-track-v3"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?= h(
                        (string) $porcentaje
                    ) ?>"
                >

                    <span
                        id="vidaProgressBar"
                        style="width: <?= h(
                            (string) $porcentaje
                        ) ?>%;"
                    ></span>

                </div>

            </div>


            <div class="life-matrix-scroll-v3">

                <div class="life-matrix-v3">


                    <div class="life-ruler-v3">

                        <div></div>

                        <div class="life-ruler-years-v3">

                            <span>
                                Año 1
                            </span>

                            <span>
                                Año 2
                            </span>

                            <span>
                                Año 3
                            </span>

                        </div>

                    </div>


                    <div
                        class="life-grid-v3"
                        data-vida-root
                        data-current-index="<?= (int) $indiceActual ?>"
                        data-csrf="<?= h($token) ?>"
                    >

                    <?php for (
                        $fila = 0;
                        $fila < 30;
                        $fila++
                    ): ?>

                        <?php

                        $edadInicio =
                            $fila * 3;

                        $edadFin =
                            $edadInicio + 2;

                        $indiceInicioFila =
                            $fila * 36;

                        $indiceFinFila =
                            $indiceInicioFila + 35;

                        $filaActual =
                            $indiceActual >= $indiceInicioFila
                            && $indiceActual <= $indiceFinFila;

                        ?>


                        <div
                            class="life-row-v3 <?= $filaActual
                                ? 'life-row-current-v3'
                                : '' ?>"
                        >

                            <div class="life-range-v3">

                                <strong>
                                    <?= $edadInicio ?>
                                    – 
                                    <?= $edadFin ?>
                                </strong>

                                <span>
                                    años
                                </span>

                            </div>


                            <div class="life-cells-v3">

                            <?php for (
                                $col = 0;
                                $col < 36;
                                $col++
                            ): ?>

                                <?php

                                $indice =
                                    ($fila * 36)
                                    + $col;

                                $nivel =
                                    isset(
                                        $estados[$indice]
                                    )
                                        ? (int)
                                            $estados[$indice]['nivel']
                                        : 0;

                                $marcado =
                                    $nivel > 0;

                                $esActual =
                                    $indice ===
                                    $indiceActual;


                                $inicioPeriodo =
                                    sumarMesesSeguro(
                                        $nacimiento,
                                        $indice
                                    );

                                $finPeriodo =
                                    sumarMesesSeguro(
                                        $nacimiento,
                                        $indice + 1
                                    )
                                    ->modify('-1 day');


                                $detalle =
                                    'Mes #'
                                    . ($indice + 1)
                                    . ' · '
                                    . textoEdadVida($indice)
                                    . ' · '
                                    . formatearFechaVida(
                                        $inicioPeriodo
                                    )
                                    . ' → '
                                    . formatearFechaVida(
                                        $finPeriodo
                                    );

                                ?>


                                <button
                                    type="button"
                                    class="vida-celda life-cell-v3 <?= $marcado
                                        ? 'vida-celda-marcada'
                                        : '' ?> <?= $esActual
                                        ? 'vida-celda-actual'
                                        : '' ?>"
                                    data-vida-indice="<?= $indice ?>"
                                    data-vida-detalle="<?= h($detalle) ?>"
                                    aria-pressed="<?= $marcado
                                        ? 'true'
                                        : 'false' ?>"
                                    aria-label="<?= h($detalle) ?>"
                                    title="<?= h($detalle) ?>"
                                ></button>

                            <?php endfor; ?>

                            </div>

                        </div>

                    <?php endfor; ?>

                    </div>

                </div>

            </div>


            <div class="life-panel-footer-v3">

                <div class="life-cell-inspector-v3">

                    <span class="life-inspector-icon-v3">

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

                    </span>


                    <div>

                        <span>
                            Detalle
                        </span>

                        <strong id="vidaCellDetail">
                            Pasá el cursor sobre un mes
                            para ver su período.
                        </strong>

                    </div>

                </div>


                <div
                    id="vidaFeedback"
                    class="vida-feedback life-feedback-v3"
                    aria-live="polite"
                ></div>

            </div>

        </section>


        <details class="life-settings-v3">

            <summary>

                <span class="life-settings-summary-icon-v3">

                    <svg viewBox="0 0 24 24">
                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                        />
                        <path
                            d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1A7 7 0 0 0 14.8 6L14.5 3h-5l-.3 3a7 7 0 0 0-1.7 1.1l-2.4-1-2 3.4L5.1 11A7 7 0 0 0 5 12c0 .3 0 .7.1 1l-2 1.5 2 3.4 2.4-1A7 7 0 0 0 9.2 18l.3 3h5l.3-3a7 7 0 0 0 1.7-1.1l2.4 1 2-3.4-2-1.5c.1-.3.1-.7.1-1Z"
                        />
                    </svg>

                </span>


                <div>

                    <strong>
                        Configuración del calendario
                    </strong>

                    <span>
                        Fecha de nacimiento:
                        <?= h(
                            formatearFechaVida(
                                $nacimiento
                            )
                        ) ?>
                    </span>

                </div>


                <span class="life-settings-arrow-v3">
                    ↓
                </span>

            </summary>


            <div class="life-settings-content-v3">

                <div>

                    <h3>
                        Fecha de nacimiento
                    </h3>

                    <p>
                        Se utiliza únicamente
                        para determinar qué período
                        representa cada cuadrado.
                    </p>

                </div>


                <form
                    method="post"
                    action="/vida.php"
                    class="life-settings-form-v3"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="guardar_fecha_nacimiento"
                    >


                    <input
                        name="fecha_nacimiento"
                        type="date"
                        required
                        max="<?= h(
                            $hoy->format('Y-m-d')
                        ) ?>"
                        value="<?= h(
                            $fechaNacimiento
                        ) ?>"
                    >


                    <button type="submit">
                        Guardar fecha
                    </button>

                </form>


                <p class="life-settings-warning-v3">
                    Cambiar esta fecha no elimina
                    meses marcados, pero modifica
                    el período temporal asociado
                    a cada índice.
                </p>

            </div>

        </details>


    <?php else: ?>


        <section class="life-setup-v3">

            <div class="life-setup-icon-v3">

                <svg viewBox="0 0 24 24">
                    <path
                        d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                    />
                    <path
                        d="M8 2v4M16 2v4M4 9h16"
                    />
                </svg>

            </div>


            <div>

                <span class="section-kicker">
                    Primer paso
                </span>

                <h2>
                    Configurá tu fecha de nacimiento
                </h2>

                <p>
                    A partir de ella generamos los
                    1.080 períodos que forman
                    el calendario.
                </p>

            </div>


            <form
                method="post"
                action="/vida.php"
                class="life-setup-form-v3"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($token) ?>"
                >

                <input
                    type="hidden"
                    name="accion"
                    value="guardar_fecha_nacimiento"
                >

                <input
                    name="fecha_nacimiento"
                    type="date"
                    required
                    max="<?= h(
                        $hoy->format('Y-m-d')
                    ) ?>"
                >

                <button type="submit">
                    Crear calendario
                </button>

            </form>

        </section>


    <?php endif; ?>




<?php if (
    $nacimiento
    instanceof DateTimeImmutable
): ?>

    <!-- R3-H1 LIFE DETAIL WORKSPACE -->

    <section class="life-detail-workspace-v3">

        <div class="life-detail-editor-v3">

            <header class="life-detail-head-v3">

                <div>

                    <span class="section-kicker">
                        Mes seleccionado
                    </span>

                    <h2 id="vidaSelectedTitle">
                        Seleccioná un mes
                    </h2>

                    <p id="vidaSelectedDetail">
                        Elegí un cuadrado de la matriz para
                        ver su período y registrar un hito.
                    </p>

                </div>


                <span
                    id="vidaSelectedState"
                    class="life-selected-state-v3"
                >
                    —
                </span>

            </header>


            <div class="life-detail-body-v3">

                <div class="life-detail-meta-v3">

                    <article>

                        <span>
                            Índice
                        </span>

                        <strong id="vidaSelectedIndex">
                            —
                        </strong>

                    </article>


                    <article>

                        <span>
                            Estado
                        </span>

                        <strong id="vidaSelectedLevel">
                            —
                        </strong>

                    </article>


                    <article>

                        <span>
                            Nota
                        </span>

                        <strong id="vidaSelectedNoteState">
                            —
                        </strong>

                    </article>

                </div>


                <div class="life-note-editor-v3">

                    <div class="life-note-label-v3">

                        <div>

                            <span>
                                Hito / nota del mes
                            </span>

                            <small>
                                Un recuerdo, cambio o momento
                                importante de este período.
                            </small>

                        </div>


                        <span id="vidaNoteCounter">
                            0 / 500
                        </span>

                    </div>


                    <textarea
                        id="vidaNoteInput"
                        maxlength="500"
                        placeholder="Ej.: Empecé un proyecto importante, terminé una etapa, viajé, cambié de trabajo..."
                        disabled
                    ></textarea>


                    <div class="life-note-actions-v3">

                        <button
                            type="button"
                            id="vidaToggleSelected"
                            class="life-note-secondary-v3"
                            disabled
                        >
                            Marcar mes
                        </button>


                        <div>

                            <button
                                type="button"
                                id="vidaClearNote"
                                class="life-note-secondary-v3"
                                disabled
                            >
                                Borrar nota
                            </button>


                            <button
                                type="button"
                                id="vidaSaveNote"
                                class="life-note-primary-v3"
                                disabled
                            >
                                Guardar nota
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <aside class="life-notes-index-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Memoria
                    </span>

                    <h3>
                        Hitos y notas
                    </h3>

                </div>


                <strong id="vidaNotesCount">
                    0
                </strong>

            </header>


            <label class="life-notes-search-v3">

                <svg viewBox="0 0 24 24">

                    <circle
                        cx="11"
                        cy="11"
                        r="6"
                    />

                    <path
                        d="m16 16 4 4"
                    />

                </svg>


                <input
                    type="search"
                    id="vidaNotesSearch"
                    placeholder="Buscar por mes o texto..."
                    autocomplete="off"
                >

            </label>


            <div
                id="vidaNotesList"
                class="life-notes-list-v3"
            >

                <div class="life-notes-empty-v3">

                    <span>
                        ◇
                    </span>

                    <strong>
                        Sin hitos todavía
                    </strong>

                    <small>
                        Las notas que guardes aparecerán acá.
                    </small>

                </div>

            </div>

        </aside>

    </section>

<?php endif; ?>

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

    <script src="/assets/js/vida-mobile.js?v=2"></script>
</body>
</html>
