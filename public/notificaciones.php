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

                        // ARK3360_R36J2_POST_ACTIONS
            case 'r36j2_delete_one':
                if ($id === false || $id === null ||
                    ($_POST['confirmacion'] ?? '') !== 'ELIMINAR_DEFINITIVAMENTE') {
                    http_response_code(422);
                    $error = 'Confirmación inválida.';
                    break;
                }
                try {
                    r36j2DeleteOne($tenantUserId, (int)$id);
                    $_SESSION['r36j2_flash'] = 'Notificación eliminada definitivamente.';
                    redirigirCentro($filtro);
                } catch (InvalidArgumentException $e) {
                    http_response_code(422);
                    $error = $e->getMessage();
                } catch (Throwable $e) {
                    error_log('R36J2 DELETE: ' . $e->getMessage());
                    http_response_code(500);
                    $error = 'No se pudo eliminar la notificación.';
                }
                break;
            case 'r36j2_clear_history':
                if (($_POST['confirmacion'] ?? '') !== 'LIMPIAR_HISTORIAL') {
                    http_response_code(422);
                    $error = 'Confirmación inválida.';
                    break;
                }
                try {
                    $deleted = r36j2ClearHistory($tenantUserId);
                    $_SESSION['r36j2_flash'] = $deleted . ' avisos históricos eliminados definitivamente.';
                    redirigirCentro($filtro);
                } catch (InvalidArgumentException $e) {
                    http_response_code(422);
                    $error = $e->getMessage();
                } catch (Throwable $e) {
                    error_log('R36J2 CLEANUP: ' . $e->getMessage());
                    http_response_code(500);
                    $error = 'No se pudo limpiar el historial.';
                }
                break;
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
$r36j2Count=r36j2HistoryCount($tenantUserId);


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
    <!-- ARK3360_R35B3_NOTIFICATION_BADGE -->
    <link rel="stylesheet" href="/assets/css/r35b-notification-badge.css?v=1">
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

    <!-- ARK3360_R35S_E2_NOTIFICACIONES_HERO_ALIGN -->
    <link rel="stylesheet" href="/assets/css/r35s-e2-notificaciones-hero-align.css?v=91a6d7bca6877ac4">
    <!-- /ARK3360_R35S_E2_NOTIFICACIONES_HERO_ALIGN -->

<!-- ARK3360_R35S_E3_NOTIFICACIONES_HERO_CENTER -->
<link rel="stylesheet" href="/assets/css/r35s-e3-notificaciones-hero-center.css?v=e7f09b2a5bf5fc18">
<!-- /ARK3360_R35S_E3_NOTIFICACIONES_HERO_CENTER -->

<!-- ARK3360_R35S_E4_NOTIFICATION_PARAGRAPH_CENTER -->
<link rel="stylesheet" href="/assets/css/r35s-e4-notificaciones-parrafo-center.css?v=5ba50c285947d0f4">
<!-- /ARK3360_R35S_E4_NOTIFICATION_PARAGRAPH_CENTER -->

<!-- ARK3360_R35T_B_NOTIFICACIONES_CARDS_LIGHT_PILOT -->
<link rel="stylesheet" href="/assets/css/r35t-b-notificaciones-cards-light-pilot.css?v=77233bc49ade3f84">
<!-- /ARK3360_R35T_B_NOTIFICACIONES_CARDS_LIGHT_PILOT -->

<!-- ARK3360_R35T_C_COMPACT_NOTIFICATION_CARDS -->
<link rel="stylesheet" href="/assets/css/r35t-c-notificaciones-cards-compact.css?v=42a8e20f5d2d110b">
<!-- /ARK3360_R35T_C_COMPACT_NOTIFICATION_CARDS -->
<link rel="stylesheet" href="/assets/css/r36j2-notificaciones-delete.css?v=bfad74dcb0efe99f">
</head>


<body class="ui-v3 page-notifications-v3">


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

        <!-- ARK3360_R36J2_HISTORY_TOOLBAR -->
        <?php if ($r36j2Count > 0): ?>
            <div class="r36j2-toolbar">
                <form method="post" action="/notificaciones.php"
                      data-count="<?= (int)$r36j2Count ?>"
                      onsubmit="return window.confirm('¿Eliminar definitivamente ' + this.dataset.count + ' avisos resueltos o descartados?\n\nLos avisos activos permanecerán. Esta acción no se puede deshacer.');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="r36j2_clear_history">
                    <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
                    <input type="hidden" name="confirmacion" value="LIMPIAR_HISTORIAL">
                    <button type="submit" class="r36j2-delete-button">Limpiar historial (<?= (int)$r36j2Count ?>)</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['r36j2_flash'])): ?>
            <p class="r36j2-flash"><?= h((string)$_SESSION['r36j2_flash']) ?></p>
            <?php unset($_SESSION['r36j2_flash']); ?>
        <?php endif; ?>



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

                    
                        <!-- ARK3360_R36J2_SINGLE_BUTTON -->
                        <form method="post" action="/notificaciones.php"
                              class="r36j2-delete-form"
                              data-title="<?= h((string)$notificacion['titulo']) ?>"
                              onsubmit="return window.confirm('¿Eliminar definitivamente «' + this.dataset.title + '»?\n\nNo volverá a generarse. Esta acción no se puede deshacer.');">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="r36j2_delete_one">
                            <input type="hidden" name="id" value="<?= (int)$notificacion['id'] ?>">
                            <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
                            <input type="hidden" name="confirmacion" value="ELIMINAR_DEFINITIVAMENTE">
                            <button type="submit" class="r36j2-delete-button" title="Eliminar definitivamente">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 6h18M8 6V4h8v2M5 6l1 15h12l1-15M10 10v7M14 10v7"/>
                                </svg><span>Eliminar</span>
                            </button>
                        </form>

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
