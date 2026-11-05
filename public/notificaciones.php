<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/notificaciones_repository.php';

iniciarSesion();


function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function centroFecha(
    ?string $fecha
): string {

    if (
        $fecha === null
        || $fecha === ''
    ) {
        return '—';
    }


    $dt =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );


    return $dt !== false
        ? $dt->format('d/m/Y')
        : $fecha;
}


function centroHora(
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


function centroEstadoEvento(
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


function centroEstadoTarea(
    string $estado
): string {

    return match ($estado) {

        'PENDIENTE' =>
            'Pendiente',

        'EN_PROGRESO' =>
            'En progreso',

        'COMPLETADA' =>
            'Completada',

        'CANCELADA' =>
            'Cancelada',

        default =>
            $estado,
    };
}


function redirigirCentro(
    string $filtro
): never {

    header(
        'Location: /notificaciones.php?filtro='
        . rawurlencode($filtro),
        true,
        303
    );

    exit;
}


$filtrosValidos = [
    'todas',
    'activas',
    'no-leidas',
    'leidas',
    'descartadas',
    'resueltas',
];


$filtro =
    trim(
        (string) (
            $_GET['filtro']
            ?? $_POST['filtro']
            ?? 'todas'
        )
    );


if (
    !in_array(
        $filtro,
        $filtrosValidos,
        true
    )
) {
    $filtro =
        'todas';
}


$error = null;


if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'POST'
) {

    if (!validarCsrf()) {

        http_response_code(403);

        $error =
            'La sesión de seguridad no es válida. Recargá la página antes de continuar.';

    } else {

        $accion =
            trim(
                (string) (
                    $_POST['action']
                    ?? ''
                )
            );


        $id =
            filter_input(
                INPUT_POST,
                'id',
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );


        switch ($accion) {

            case 'mark_all_read':

                marcarTodasNotificacionesLeidas($tenantUserId);

                redirigirCentro(
                    $filtro
                );


            case 'mark_read':

                if (
                    $id === false
                    || $id === null
                ) {
                    http_response_code(422);
                    $error =
                        'La notificación indicada no es válida.';
                    break;
                }

                marcarNotificacionLeida($tenantUserId, 
                    (int) $id
                );

                redirigirCentro(
                    $filtro
                );


            case 'dismiss':

                if (
                    $id === false
                    || $id === null
                ) {
                    http_response_code(422);
                    $error =
                        'La notificación indicada no es válida.';
                    break;
                }

                descartarNotificacion($tenantUserId, 
                    (int) $id
                );

                redirigirCentro(
                    $filtro
                );


            case 'restore':

                if (
                    $id === false
                    || $id === null
                ) {
                    http_response_code(422);
                    $error =
                        'La notificación indicada no es válida.';
                    break;
                }

                restaurarNotificacion($tenantUserId, 
                    (int) $id
                );

                redirigirCentro(
                    $filtro
                );


            case 'open':

                if (
                    $id === false
                    || $id === null
                ) {
                    http_response_code(422);
                    $error =
                        'La notificación indicada no es válida.';
                    break;
                }


                $notificacion =
                    buscarNotificacionCentro($tenantUserId, 
                        (int) $id
                    );


                if ($notificacion === null) {

                    http_response_code(404);

                    $error =
                        'La notificación ya no existe.';

                    break;
                }


                marcarNotificacionLeida($tenantUserId, 
                    (int) $id
                );


                $destino =
                    (
                        $notificacion['origen']
                        ?? 'EVENTO'
                    ) === 'TAREA'
                    && !empty(
                        $notificacion['tarea_id']
                    )
                        ? '/tareas.php?editar='
                            . (int) $notificacion['tarea_id']
                            . '#task-form'

                        : '/agenda.php#evento-'
                            . (int) $notificacion['evento_id'];


                header(
                    'Location: '
                    . $destino,
                    true,
                    303
                );

                exit;


            default:

                http_response_code(422);

                $error =
                    'La acción indicada no es válida.';
        }
    }
}


$conteos =
    contarCentroNotificaciones($tenantUserId);


$notificaciones =
    listarCentroNotificaciones($tenantUserId, 
        $filtro
    );


$csrf =
    csrfToken();


$etiquetasFiltro = [
    'todas' =>
        'Todas',

    'activas' =>
        'Activas',

    'no-leidas' =>
        'No leídas',

    'leidas' =>
        'Leídas',

    'descartadas' =>
        'Descartadas',

    'resueltas' =>
        'Resueltas',
];


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
        Notificaciones | Agenda Personal
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

    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/notificaciones-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-notificaciones.css?v=3">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-notifications-v3">


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

            <a href="/rendimiento.php">
                Rendimiento
            </a>

        </nav>


        <div class="app-header-status">

            <span
                class="status-dot status-dot-online"
            ></span>

            <span>
                Centro de avisos
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="notifications-shell-v3">


    <section class="notifications-hero-v3">

        <div>

            <span class="section-kicker">
                Centro de avisos
            </span>


            <h1>
                Tus recordatorios,
                <span>bajo control.</span>
            </h1>


            <p>
                Revisá lo pendiente, conservá el historial
                y descartá avisos sin perder trazabilidad.
            </p>

        </div>


        <div class="notifications-hero-state-v3">

            <span>
                NO LEÍDAS
            </span>

            <strong>
                <?= $conteos['no_leidas'] ?>
            </strong>

            <small>
                de
                <?= $conteos['activas'] ?>
                activas
            </small>

        </div>

    </section>


    <section class="notifications-metrics-v3">

        <article>

            <span>
                Activas
            </span>

            <strong>
                <?= $conteos['activas'] ?>
            </strong>

            <small>
                requieren seguimiento
            </small>

        </article>


        <article>

            <span>
                No leídas
            </span>

            <strong>
                <?= $conteos['no_leidas'] ?>
            </strong>

            <small>
                todavía sin revisar
            </small>

        </article>


        <article>

            <span>
                Descartadas
            </span>

            <strong>
                <?= $conteos['descartadas'] ?>
            </strong>

            <small>
                ocultadas manualmente
            </small>

        </article>


        <article>

            <span>
                Historial
            </span>

            <strong>
                <?= $conteos['total'] ?>
            </strong>

            <small>
                avisos generados
            </small>

        </article>

    </section>


    <?php if ($error !== null): ?>

        <div class="notifications-error-v3">
            <?= h($error) ?>
        </div>

    <?php endif; ?>


    <section class="notifications-panel-v3">

        <header class="notifications-panel-head-v3">

            <div>

                <span class="section-kicker">
                    Bandeja
                </span>

                <h2>
                    Notificaciones
                </h2>

                <p>
                    <?= count($notificaciones) ?>
                    <?= count($notificaciones) === 1
                        ? 'registro visible'
                        : 'registros visibles' ?>
                </p>

            </div>


            <?php if (
                $conteos['no_leidas']
                > 0
            ): ?>

                <form
                    method="post"
                    action="/notificaciones.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >

                    <input
                        type="hidden"
                        name="filtro"
                        value="<?= h($filtro) ?>"
                    >

                    <button
                        type="submit"
                        class="notifications-read-all-v3"
                    >
                        Marcar todo leído
                    </button>

                </form>

            <?php endif; ?>

        </header>


        <nav
            class="notifications-filters-v3"
            aria-label="Filtros de notificaciones"
        >

            <?php foreach (
                $etiquetasFiltro
                as $clave => $etiqueta
            ): ?>

                <?php

                $cantidad =
                    match ($clave) {
                        'todas' =>
                            $conteos['total'],

                        'activas' =>
                            $conteos['activas'],

                        'no-leidas' =>
                            $conteos['no_leidas'],

                        'leidas' =>
                            $conteos['leidas'],

                        'descartadas' =>
                            $conteos['descartadas'],

                        'resueltas' =>
                            $conteos['resueltas'],

                        default =>
                            0,
                    };

                ?>


                <a
                    href="/notificaciones.php?filtro=<?= h(
                        $clave
                    ) ?>"
                    class="<?= $filtro === $clave
                        ? 'active'
                        : '' ?>"
                >

                    <?= h($etiqueta) ?>

                    <span>
                        <?= $cantidad ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </nav>


        <?php if (
            $notificaciones === []
        ): ?>

            <div class="notifications-empty-center-v3">

                <div>✓</div>

                <strong>
                    No hay avisos en esta vista
                </strong>

                <p>
                    El motor seguirá actualizando
                    automáticamente tus recordatorios.
                </p>

            </div>


        <?php else: ?>

            <div class="notifications-list-center-v3">


            <?php foreach (
                $notificaciones
                as $notificacion
            ): ?>

                <?php

                $esResuelta =
                    $notificacion['resuelta_at']
                    !== null;


                $esDescartada =
                    $notificacion['descartada_at']
                    !== null;


                $esLeida =
                    $notificacion['leida_at']
                    !== null;


                $esTarea =
                    (
                        $notificacion['origen']
                        ?? 'EVENTO'
                    ) === 'TAREA';


                if ($esResuelta) {

                    $estadoClase =
                        'resolved';

                    $estadoTexto =
                        'Resuelta';

                } elseif ($esDescartada) {

                    $estadoClase =
                        'dismissed';

                    $estadoTexto =
                        'Descartada';

                } elseif (!$esLeida) {

                    $estadoClase =
                        'unread';

                    $estadoTexto =
                        'No leída';

                } else {

                    $estadoClase =
                        'read';

                    $estadoTexto =
                        'Leída';
                }

                ?>


                <article
                    class="notifications-center-item-v3 notifications-center-item-<?= h(
                        $estadoClase
                    ) ?>"
                    data-notification-origin="<?= h(
                        $esTarea
                            ? 'TAREA'
                            : 'EVENTO'
                    ) ?>"
                    style="--notification-color: <?= h(
                        $notificacion['materia_color']
                        ?: '#5794ff'
                    ) ?>;"
                >

                    <span
                        class="notifications-center-accent-v3"
                    ></span>


                    <div class="notifications-center-icon-v3">

                        <svg viewBox="0 0 24 24">
                            <path
                                d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                            />
                            <path
                                d="M10 21h4"
                            />
                        </svg>

                    </div>


                    <div class="notifications-center-copy-v3">

                        <div class="notifications-center-meta-v3">

                            <?php if (
                                !empty(
                                    $notificacion[
                                        'materia_id'
                                    ]
                                )
                            ): ?>

                                <a
                                    href="/materia.php?id=<?= (int)
                                        $notificacion[
                                            'materia_id'
                                        ] ?>"
                                >
                                    <?= h(
                                        $notificacion[
                                            'materia_codigo'
                                        ]
                                        ?: $notificacion[
                                            'materia_nombre'
                                        ]
                                    ) ?>
                                </a>

                            <?php else: ?>

                                <span>
                                    <?= $esTarea
                                        ? 'Tarea'
                                        : 'Evento' ?>
                                </span>

                            <?php endif; ?>

                            <i>·</i>

                            <span>
                                <?= h(
                                    centroFecha(
                                        $notificacion['fecha_objetivo']
                                    )
                                ) ?>
                            </span>

                            <i>·</i>

                            <span>
                                <?= h(
                                    centroHora(
                                        $esTarea
                                            ? $notificacion[
                                                'tarea_hora_limite'
                                            ]
                                            : $notificacion[
                                                'evento_hora'
                                            ]
                                    )
                                ) ?>
                            </span>

                        </div>


                        <strong>
                            <?= h(
                                $notificacion['titulo']
                            ) ?>
                        </strong>


                        <p>
                            <?= h(
                                $notificacion['mensaje']
                            ) ?>
                        </p>


                        <small>

                            <?php if ($esTarea): ?>

                                Tarea ·

                                <?= h(
                                    centroEstadoTarea(
                                        (string)
                                            $notificacion[
                                                'tarea_estado'
                                            ]
                                    )
                                ) ?>

                            <?php else: ?>

                                <?= h(
                                    $notificacion[
                                        'evento_titulo'
                                    ]
                                ) ?>

                                ·

                                <?= h(
                                    centroEstadoEvento(
                                        (string)
                                            $notificacion[
                                                'evento_estado'
                                            ]
                                    )
                                ) ?>

                            <?php endif; ?>

                        </small>

                    </div>


                    <div class="notifications-center-state-v3">

                        <span class="<?= h(
                            $estadoClase
                        ) ?>">
                            <?= h(
                                $estadoTexto
                            ) ?>
                        </span>

                    </div>


                    <div class="notifications-center-actions-v3">

                        <form
                            method="post"
                            action="/notificaciones.php"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= h($csrf) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="open"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $notificacion['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="filtro"
                                value="<?= h($filtro) ?>"
                            >

                            <button
                                type="submit"
                                class="notifications-open-v3"
                            >
                                <?= $esTarea
                                    ? 'Abrir tarea'
                                    : 'Abrir evento' ?>
                                <span>→</span>
                            </button>

                        </form>


                        <?php if (
                            !$esResuelta
                            && !$esDescartada
                        ): ?>

                            <?php if (!$esLeida): ?>

                                <form
                                    method="post"
                                    action="/notificaciones.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($csrf) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_read"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $notificacion['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="filtro"
                                        value="<?= h($filtro) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="notifications-secondary-v3"
                                    >
                                        Marcar leída
                                    </button>

                                </form>

                            <?php endif; ?>


                            <form
                                method="post"
                                action="/notificaciones.php"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrf) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="dismiss"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $notificacion['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="filtro"
                                    value="<?= h($filtro) ?>"
                                >

                                <button
                                    type="submit"
                                    class="notifications-secondary-v3"
                                >
                                    Descartar
                                </button>

                            </form>

                        <?php elseif (
                            $esDescartada
                            && !$esResuelta
                        ): ?>

                            <form
                                method="post"
                                action="/notificaciones.php"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($csrf) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="restore"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $notificacion['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="filtro"
                                    value="<?= h($filtro) ?>"
                                >

                                <button
                                    type="submit"
                                    class="notifications-secondary-v3"
                                >
                                    Restaurar
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>


            </div>

        <?php endif; ?>

    </section>


    <section class="notifications-engine-v3">

        <div>

            <span class="section-kicker">
                Automatización
            </span>

            <h3>
                Motor de recordatorios activo
            </h3>

            <p>
                Los eventos y las tareas se evalúan automáticamente.
                Los avisos leídos, descartados y resueltos
                mantienen su estado de forma persistente.
            </p>

        </div>


        <div class="notifications-engine-status-v3">

            <span></span>

            <div>

                <strong>
                    Sistema operativo
                </strong>

                <small>
                    actualización automática
                </small>

            </div>

        </div>

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
