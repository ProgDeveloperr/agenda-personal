<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUser = contextoTenantWebActual();
if (($tenantUser['rol'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    header('Cache-Control: no-store');
    exit('Acceso restringido');
}
$tenantUserId = (int) $tenantUser['id'];
require_once '/var/www/app/database.php';
require_once '/var/www/app/vida_repository.php';


iniciarSesion();


function h(
    mixed $valor
): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES
        | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function sistemaBytes(
    mixed $bytes
): string {

    $valor =
        max(
            0,
            (float) $bytes
        );


    $unidades = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB',
    ];


    $indice = 0;


    while (
        $valor >= 1024
        && $indice
            < count($unidades) - 1
    ) {

        $valor /= 1024;

        $indice++;
    }


    $decimales =
        $indice <= 1
            ? 0
            : 1;


    return number_format(
        $valor,
        $decimales,
        ',',
        '.'
    )
        . ' '
        . $unidades[$indice];
}


function sistemaEstado(
    bool $ok
): string {

    return $ok
        ? 'ok'
        : 'warning';
}


function sistemaFechaNacimientoValida(
    string $valor
): bool {

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $valor
        )
    ) {
        return false;
    }


    $zona =
        new DateTimeZone(
            'America/Argentina/Buenos_Aires'
        );


    $fecha =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $valor,
            $zona
        );


    if (
        !(
            $fecha
            instanceof DateTimeImmutable
        )
    ) {
        return false;
    }


    if (
        $fecha->format('Y-m-d')
        !== $valor
    ) {
        return false;
    }


    $hoy =
        new DateTimeImmutable(
            'today',
            $zona
        );


    $minima =
        $hoy->modify(
            '-120 years'
        );


    return
        $fecha <= $hoy
        && $fecha >= $minima;
}


function sistemaLeerEstado(): array
{
    $ruta =
        '/var/www/app/system_status.json';


    if (!is_readable($ruta)) {

        return [
            '_available' =>
                false,
        ];
    }


    $contenido =
        file_get_contents(
            $ruta
        );


    if ($contenido === false) {

        return [
            '_available' =>
                false,
        ];
    }


    try {

        $datos =
            json_decode(
                $contenido,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

    } catch (
        JsonException
    ) {

        return [
            '_available' =>
                false,
        ];
    }


    if (!is_array($datos)) {

        return [
            '_available' =>
                false,
        ];
    }


    $datos['_available'] =
        true;


    return $datos;
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


    if (
        $accion
        !== 'guardar_fecha_nacimiento'
    ) {

        http_response_code(
            400
        );

        echo 'Acción no reconocida.';

        exit;
    }


    $fecha =
        trim(
            (string) (
                $_POST['fecha_nacimiento']
                ?? ''
            )
        );


    if (
        !sistemaFechaNacimientoValida(
            $fecha
        )
    ) {

        header(
            'Location: /sistema.php?error=fecha',
            true,
            303
        );

        exit;
    }


    guardarFechaNacimientoVida(
        $tenantUserId,
        $fecha
    );


    header(
        'Location: /sistema.php?updated=1',
        true,
        303
    );

    exit;
}


$estado =
    sistemaLeerEstado();


$fechaNacimiento =
    obtenerFechaNacimientoVida($tenantUserId);


$horizonteAnios =
    obtenerHorizonteVidaAnios($tenantUserId);


$schemaVersion = '—';


try {

    $stmt =
        db()->prepare(
            "
            SELECT valor
            FROM configuracion
            WHERE clave='schema_version'
            LIMIT 1
            "
        );

    $stmt->execute();

    $schema =
        $stmt->fetchColumn();


    if ($schema !== false) {

        $schemaVersion =
            (string) $schema;
    }

} catch (Throwable) {
}


$materias = 0;
$eventos = 0;
$notificaciones = 0;
$notificacionesActivas = 0;

try {
    $consultasTenant = [
        'materias' => 'SELECT COUNT(*) FROM materias WHERE user_id = :user_id',
        'eventos' => 'SELECT COUNT(*) FROM eventos_academicos WHERE user_id = :user_id',
        'notificaciones' => 'SELECT COUNT(*) FROM notificaciones WHERE user_id = :user_id',
        'notificacionesActivas' => 'SELECT COUNT(*) FROM notificaciones WHERE user_id = :user_id AND resuelta_at IS NULL AND descartada_at IS NULL',
    ];

    $resultadoTenant = [];

    foreach ($consultasTenant as $clave => $consultaTenant) {
        $stmt = db()->prepare($consultaTenant);
        $stmt->execute(['user_id' => $tenantUserId]);
        $resultadoTenant[$clave] = (int) $stmt->fetchColumn();
    }

    $materias = $resultadoTenant['materias'];
    $eventos = $resultadoTenant['eventos'];
    $notificaciones = $resultadoTenant['notificaciones'];
    $notificacionesActivas = $resultadoTenant['notificacionesActivas'];

} catch (Throwable) {
}


// El tamaño global de MariaDB es compartido: no se expone
// como métrica personal de ningún usuario.
$dbBytes = null;


$collectedAt =
    (string) (
        $estado['collected_at']
        ?? ''
    );


$statusAgeSeconds =
    null;


if ($collectedAt !== '') {

    try {

        $capturado =
            new DateTimeImmutable(
                $collectedAt
            );


        $ahora =
            new DateTimeImmutable(
                'now',
                new DateTimeZone(
                    'America/Argentina/Buenos_Aires'
                )
            );


        $statusAgeSeconds =
            max(
                0,
                $ahora->getTimestamp()
                - $capturado->getTimestamp()
            );

    } catch (Throwable) {
    }
}


$statusFresh =
    $statusAgeSeconds !== null
    && $statusAgeSeconds <= 300;


$webStatus =
    (string) (
        $estado['containers']['web']['status']
        ?? 'unknown'
    );


$dbStatus =
    (string) (
        $estado['containers']['database']['status']
        ?? 'unknown'
    );


$dbHealth =
    (string) (
        $estado['containers']['database']['health']
        ?? 'unknown'
    );


$httpCode =
    (int) (
        $estado['network']['http_code']
        ?? 0
    );


$httpsCode =
    (int) (
        $estado['network']['https_code']
        ?? 0
    );


$backupTimer =
    (string) (
        $estado['automation']['backups']['active']
        ?? 'unknown'
    );


$backupEnabled =
    (string) (
        $estado['automation']['backups']['enabled']
        ?? 'unknown'
    );


$backupNext =
    (string) (
        $estado['automation']['backups']['next']
        ?? ''
    );


$backupLast =
    (string) (
        $estado['backup']['latest']
        ?? ''
    );


$backupIntegrity =
    (string) (
        $estado['backup']['integrity']
        ?? 'unknown'
    );


$backupSize =
    (int) (
        $estado['backup']['size_bytes']
        ?? 0
    );


$notificationTimer =
    (string) (
        $estado['automation']['notifications']['active']
        ?? 'unknown'
    );


$notificationEnabled =
    (string) (
        $estado['automation']['notifications']['enabled']
        ?? 'unknown'
    );


$notificationNext =
    (string) (
        $estado['automation']['notifications']['next']
        ?? ''
    );


$notificationInterval =
    (string) (
        $estado['automation']['notifications']['interval']
        ?? ''
    );


$notificationIntervalLabel =
    $notificationInterval;


if (
    preg_match(
        '/^(\d+)min$/',
        $notificationInterval,
        $intervalMatch
    )
) {

    $notificationIntervalLabel =
        'Cada '
        . $intervalMatch[1]
        . ' min';
}


$diskTotal =
    (int) (
        $estado['storage']['disk_total_bytes']
        ?? 0
    );


$diskUsed =
    (int) (
        $estado['storage']['disk_used_bytes']
        ?? 0
    );


$diskAvailable =
    (int) (
        $estado['storage']['disk_available_bytes']
        ?? 0
    );


$diskPercent =
    (int) (
        $estado['storage']['disk_used_percent']
        ?? 0
    );


$appBytes =
    (int) (
        $estado['storage']['application_bytes']
        ?? 0
    );


$backupsBytes =
    (int) (
        $estado['storage']['backups_bytes']
        ?? 0
    );


$pwaManifest =
    (bool) (
        $estado['pwa']['manifest']
        ?? false
    );


$pwaMobile =
    (bool) (
        $estado['pwa']['mobile_shell']
        ?? false
    );


$pwaIcon =
    (bool) (
        $estado['pwa']['icon']
        ?? false
    );


$swRegistrations =
    (int) (
        $estado['pwa']['service_worker_registrations']
        ?? 0
    );


$securityProtected =
    (bool) (
        $estado['security']['protected']
        ?? false
    );


$overallHealthy =
    ($estado['_available'] ?? false)
    && $statusFresh
    && ($estado['overall'] ?? '')
        === 'healthy'
    && ctype_digit($schemaVersion)
    && (int) $schemaVersion >= 5;


$token =
    csrfToken();


$mensaje =
    null;


if (
    ($_GET['updated'] ?? '')
    === '1'
) {

    $mensaje = [
        'tipo' =>
            'success',

        'texto' =>
            'Fecha de nacimiento actualizada.',
    ];
}


if (
    ($_GET['error'] ?? '')
    === 'fecha'
) {

    $mensaje = [
        'tipo' =>
            'error',

        'texto' =>
            'La fecha ingresada no es válida.',
    ];
}

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
        Sistema | Agenda Personal
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
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/sistema-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-sistema.css?v=4">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-system-v3">

<header class="app-header">

    <div class="app-header-inner">

        <a
            class="app-brand"
            href="/"
            aria-label="Agenda Personal"
        >

            <span class="app-brand-mark">

                <svg viewBox="0 0 24 24">

                    <rect
                        x="4"
                        y="5"
                        width="16"
                        height="15"
                        rx="3"
                    />

                    <path
                        d="M8 3v4M16 3v4M4 10h16"
                    />

                    <path
                        d="M8 14h3M13 14h3M8 17h3"
                    />

                </svg>

            </span>


            <span>

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
                class="status-dot <?= $overallHealthy
                    ? 'status-dot-online'
                    : '' ?>"
            ></span>

            <span>
                Centro de sistema
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="system-shell-v3">

    <section class="system-hero-v3">

        <div>

            <span class="section-kicker">
                Sistema
            </span>

            <h1>
                Todo bajo
                <span>control.</span>
            </h1>

            <p>
                Salud de Agenda Personal, automatizaciones,
                backups, almacenamiento y plataforma en un
                único lugar.
            </p>

        </div>


        <div class="system-overall-v3">

            <span
                class="system-health-dot-v3 <?= $overallHealthy
                    ? 'is-ok'
                    : 'is-warning' ?>"
            ></span>

            <div>

                <strong>
                    <?= $overallHealthy
                        ? 'Sistema operativo'
                        : 'Requiere revisión' ?>
                </strong>

                <small>

                    <?php if ($statusAgeSeconds !== null): ?>

                        actualizado hace
                        <?= max(
                            0,
                            (int) round(
                                $statusAgeSeconds / 60
                            )
                        ) ?>
                        min

                    <?php else: ?>

                        estado no disponible

                    <?php endif; ?>

                </small>

            </div>

        </div>

    </section>


    <?php if ($mensaje !== null): ?>

        <div
            class="agenda-message-v3 <?= $mensaje['tipo']
                === 'success'
                ? 'agenda-message-success-v3'
                : 'agenda-message-error-v3' ?>"
        >

            <span class="agenda-message-icon-v3">
                <?= $mensaje['tipo']
                    === 'success'
                    ? '✓'
                    : '!' ?>
            </span>

            <span>
                <?= h(
                    $mensaje['texto']
                ) ?>
            </span>

        </div>

    <?php endif; ?>


    <section class="system-metrics-v3">

        <article>

            <span>
                Web
            </span>

            <strong>
                <?= h(
                    strtoupper(
                        $webStatus
                    )
                ) ?>
            </strong>

            <small>
                HTTP <?= $httpCode ?>
            </small>

        </article>


        <article>

            <span>
                Base de datos
            </span>

            <strong>
                <?= h(
                    strtoupper(
                        $dbHealth
                    )
                ) ?>
            </strong>

            <small>
                MariaDB · schema v<?= h(
                    $schemaVersion
                ) ?>
            </small>

        </article>


        <article>

            <span>
                Backups
            </span>

            <strong>
                <?= $backupIntegrity === 'pass'
                    ? 'Íntegro'
                    : 'Revisar' ?>
            </strong>

            <small>
                retención 30 días
            </small>

        </article>


        <article>

            <span>
                Disco
            </span>

            <strong>
                <?= $diskPercent ?> %
            </strong>

            <small>
                <?= sistemaBytes(
                    $diskAvailable
                ) ?>
                libres
            </small>

        </article>

    </section>


    <section class="system-grid-v3">


        <article class="system-card-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Salud
                    </span>

                    <h2>
                        Servicios
                    </h2>

                </div>

                <span
                    class="system-card-status-v3 <?= sistemaEstado(
                        $webStatus === 'running'
                        && $dbStatus === 'running'
                        && $dbHealth === 'healthy'
                    ) ?>"
                >
                    <?= $webStatus === 'running'
                        && $dbStatus === 'running'
                        && $dbHealth === 'healthy'
                        ? 'Operativo'
                        : 'Revisar' ?>
                </span>

            </header>


            <div class="system-list-v3">

                <div>

                    <span>
                        Agenda Web
                    </span>

                    <strong>
                        <?= h(
                            $webStatus
                        ) ?>
                    </strong>

                    <small>
                        reinicios:
                        <?= (int) (
                            $estado['containers']['web']['restarts']
                            ?? 0
                        ) ?>
                    </small>

                </div>


                <div>

                    <span>
                        MariaDB
                    </span>

                    <strong>
                        <?= h(
                            $dbHealth
                        ) ?>
                    </strong>

                    <small>
                        reinicios:
                        <?= (int) (
                            $estado['containers']['database']['restarts']
                            ?? 0
                        ) ?>
                    </small>

                </div>


                <div>

                    <span>
                        Schema
                    </span>

                    <strong>
                        v<?= h(
                            $schemaVersion
                        ) ?>
                    </strong>

                    <small>
                        versión productiva
                    </small>

                </div>


                <div>

                    <span>
                        HTTP
                    </span>

                    <strong>
                        <?= $httpCode ?>
                    </strong>

                    <small>
                        puerto 8086
                    </small>

                </div>

            </div>

        </article>


        <article class="system-card-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Automatización
                    </span>

                    <h2>
                        Recordatorios
                    </h2>

                </div>

                <span
                    class="system-card-status-v3 <?= sistemaEstado(
                        $notificationTimer === 'active'
                        && $notificationEnabled === 'enabled'
                    ) ?>"
                >
                    <?= $notificationTimer === 'active'
                        ? 'Activo'
                        : 'Revisar' ?>
                </span>

            </header>


            <div class="system-highlight-v3">

                <strong>
                    <?= $notificacionesActivas ?>
                </strong>

                <span>
                    avisos activos
                </span>

            </div>


            <div class="system-facts-v3">

                <div>

                    <span>
                        Timer
                    </span>

                    <strong>
                        <?= h(
                            $notificationEnabled
                        ) ?>
                        /
                        <?= h(
                            $notificationTimer
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Frecuencia
                    </span>

                    <strong>
                        <?= h(
                            $notificationIntervalLabel !== ''
                                ? $notificationIntervalLabel
                                : '—'
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Historial
                    </span>

                    <strong>
                        <?= $notificaciones ?>
                        notificaciones
                    </strong>

                </div>

            </div>

        </article>


        <article class="system-card-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Protección
                    </span>

                    <h2>
                        Backups
                    </h2>

                </div>

                <span
                    class="system-card-status-v3 <?= sistemaEstado(
                        $backupTimer === 'active'
                        && $backupEnabled === 'enabled'
                        && $backupIntegrity === 'pass'
                    ) ?>"
                >
                    <?= $backupIntegrity === 'pass'
                        ? 'Verificado'
                        : 'Revisar' ?>
                </span>

            </header>


            <div class="system-backup-name-v3">

                <span>
                    Último snapshot
                </span>

                <strong>
                    <?= h(
                        $backupLast !== ''
                            ? $backupLast
                            : '—'
                    ) ?>
                </strong>

            </div>


            <div class="system-facts-v3">

                <div>

                    <span>
                        Integridad
                    </span>

                    <strong>
                        <?= h(
                            strtoupper(
                                $backupIntegrity
                            )
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Tamaño
                    </span>

                    <strong>
                        <?= sistemaBytes(
                            $backupSize
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Próximo backup
                    </span>

                    <strong>
                        <?= h(
                            $backupNext !== ''
                                ? $backupNext
                                : '—'
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Retención
                    </span>

                    <strong>
                        30 días
                    </strong>

                </div>

            </div>

        </article>


        <article class="system-card-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Recursos
                    </span>

                    <h2>
                        Almacenamiento
                    </h2>

                </div>

                <span class="system-card-status-v3">
                    <?= $diskPercent ?> %
                </span>

            </header>


            <div class="system-storage-v3">

                <div class="system-storage-track-v3">

                    <span
                        style="width: <?= min(
                            100,
                            max(
                                0,
                                $diskPercent
                            )
                        ) ?>%;"
                    ></span>

                </div>


                <div class="system-storage-labels-v3">

                    <strong>
                        <?= sistemaBytes(
                            $diskUsed
                        ) ?>
                        usados
                    </strong>

                    <span>
                        <?= sistemaBytes(
                            $diskTotal
                        ) ?>
                        totales
                    </span>

                </div>

            </div>


            <div class="system-facts-v3">

                <div>

                    <span>
                        Agenda Personal
                    </span>

                    <strong>
                        <?= sistemaBytes(
                            $appBytes
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Backups
                    </span>

                    <strong>
                        <?= sistemaBytes(
                            $backupsBytes
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Base de datos
                    </span>

                    <strong>
                        <?= $dbBytes !== null
                            ? sistemaBytes(
                                $dbBytes
                            )
                            : '—' ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Libre
                    </span>

                    <strong>
                        <?= sistemaBytes(
                            $diskAvailable
                        ) ?>
                    </strong>

                </div>

            </div>

        </article>


        <article class="system-card-v3 system-card-wide-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Preferencias
                    </span>

                    <h2>
                        Calendario de Vida
                    </h2>

                </div>

                <span class="system-card-status-v3">
                    Configuración
                </span>

            </header>


            <div class="system-preferences-v3">

                <form
                    method="post"
                    action="/sistema.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h(
                            $token
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="guardar_fecha_nacimiento"
                    >


                    <label>

                        <span>
                            Fecha de nacimiento
                        </span>

                        <small>
                            Define la posición actual
                            en el Calendario de Vida.
                        </small>

                        <input
                            type="date"
                            name="fecha_nacimiento"
                            value="<?= h(
                                $fechaNacimiento
                                ?? ''
                            ) ?>"
                            required
                        >

                    </label>


                    <button
                        type="submit"
                        class="system-save-v3"
                    >
                        Guardar fecha
                    </button>

                </form>


                <div class="system-readonly-setting-v3">

                    <div>

                        <span>
                            Horizonte de Vida
                        </span>

                        <small>
                            Base estructural de la matriz actual.
                        </small>

                    </div>

                    <strong>
                        <?= (int) $horizonteAnios ?>
                        años
                    </strong>

                    <em>
                        Solo lectura
                    </em>

                </div>

            </div>

        </article>


        <article class="system-card-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Plataforma
                    </span>

                    <h2>
                        PWA
                    </h2>

                </div>

                <span
                    class="system-card-status-v3 <?= sistemaEstado(
                        $pwaManifest
                        && $pwaMobile
                        && $pwaIcon
                    ) ?>"
                >
                    Base lista
                </span>

            </header>


            <div class="system-list-v3">

                <div>

                    <span>
                        Manifest
                    </span>

                    <strong>
                        <?= $pwaManifest
                            ? 'Ready'
                            : 'Missing' ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Mobile shell
                    </span>

                    <strong>
                        <?= $pwaMobile
                            ? 'Ready'
                            : 'Missing' ?>
                    </strong>

                </div>


                <div>

                    <span>
                        HTTPS
                    </span>

                    <strong>
                        <?= $httpsCode === 200
                            ? 'Activo'
                            : 'Pendiente' ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Service worker
                    </span>

                    <strong>
                        <?= $swRegistrations > 0
                            ? 'Configurado'
                            : 'Pendiente' ?>
                    </strong>

                </div>

            </div>

        </article>


        <article class="system-card-v3">

            <header>

                <div>

                    <span class="section-kicker">
                        Seguridad
                    </span>

                    <h2>
                        Superficie web
                    </h2>

                </div>

                <span
                    class="system-card-status-v3 <?= sistemaEstado(
                        $securityProtected
                    ) ?>"
                >
                    <?= $securityProtected
                        ? 'Protegida'
                        : 'Revisar' ?>
                </span>

            </header>


            <div class="system-security-v3">

                <strong>
                    <?= $securityProtected
                        ? 'Rutas sensibles aisladas'
                        : 'Se detectó una anomalía' ?>
                </strong>

                <p>
                    Credenciales, código interno y schema
                    permanecen fuera del DocumentRoot público.
                </p>

            </div>


            <div class="system-facts-v3">

                <div>

                    <span>
                        Materias
                    </span>

                    <strong>
                        <?= $materias ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Eventos
                    </span>

                    <strong>
                        <?= $eventos ?>
                    </strong>

                </div>

            </div>

        </article>

    </section>


    <section class="system-footer-v3">

        <div>

            <span class="section-kicker">
                Diagnóstico
            </span>

            <strong>
                <?= h(
                    (string) (
                        $estado['host']['hostname']
                        ?? 'Servidor'
                    )
                ) ?>
            </strong>

            <small>
                kernel
                <?= h(
                    (string) (
                        $estado['host']['kernel']
                        ?? '—'
                    )
                ) ?>
            </small>

        </div>


        <div>

            <span>
                Colector
            </span>

            <strong>
                <?= $statusFresh
                    ? 'Actualizado'
                    : 'Desactualizado' ?>
            </strong>

            <small>
                actualización automática cada 2 min
            </small>

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
