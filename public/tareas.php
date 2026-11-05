<?php

/* R4-D3 — Recurring Tasks UX */

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/database.php';
require_once '/var/www/app/tareas_repository.php';
require_once '/var/www/app/tareas_recurrencia_repository.php';
require_once '/var/www/app/tareas_recurrencia_materializer.php';
require_once '/var/www/app/materias_repository.php';
require_once '/var/www/app/eventos_repository.php';


iniciarSesion();


function h(
    mixed $valor
): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function tareaFlash(
    string $tipo,
    string $mensaje
): void {

    $_SESSION['tareas_flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];
}


function consumirTareaFlash(): ?array
{
    $flash =
        $_SESSION['tareas_flash']
        ?? null;


    unset(
        $_SESSION['tareas_flash']
    );


    return is_array($flash)
        ? $flash
        : null;
}


function tareaNullableInt(
    mixed $valor
): ?int {

    $valor =
        trim(
            (string) $valor
        );


    if ($valor === '') {
        return null;
    }


    if (
        !ctype_digit($valor)
        || (int) $valor <= 0
    ) {

        throw new InvalidArgumentException(
            'Identificador no válido.'
        );
    }


    return (int) $valor;
}


function tareaEstadoLabel(
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


function tareaPrioridadLabel(
    string $prioridad
): string {

    return match ($prioridad) {

        'BAJA' =>
            'Baja',

        'MEDIA' =>
            'Media',

        'ALTA' =>
            'Alta',

        'URGENTE' =>
            'Urgente',

        default =>
            $prioridad,
    };
}


function tareaFrecuenciaLabel(
    string $frecuencia
): string {

    return match ($frecuencia) {

        'DIARIA' =>
            'Diaria',

        'SEMANAL' =>
            'Semanal',

        'MENSUAL' =>
            'Mensual',

        default =>
            $frecuencia,
    };
}


function tareaIntervaloSerieLabel(
    string $frecuencia,
    int $intervalo
): string {

    $intervalo =
        max(
            1,
            $intervalo
        );


    $unidad =
        match ($frecuencia) {

            'DIARIA' =>
                $intervalo === 1
                    ? 'día'
                    : 'días',

            'SEMANAL' =>
                $intervalo === 1
                    ? 'semana'
                    : 'semanas',

            'MENSUAL' =>
                $intervalo === 1
                    ? 'mes'
                    : 'meses',

            default =>
                'períodos',
        };


    return
        'Cada '
        . $intervalo
        . ' '
        . $unidad;
}


function tareaFechaLabel(
    ?string $fecha
): string {

    if (
        $fecha === null
        || $fecha === ''
    ) {

        return 'Sin fecha';
    }


    try {

        $objeto =
            new DateTimeImmutable(
                $fecha
            );


        return $objeto
            ->format(
                'd/m/Y'
            );

    } catch (Throwable) {

        return $fecha;
    }
}


function tareaRedirect(
    string $destino = '/tareas.php'
): never {

    header(
        'Location: ' . $destino,
        true,
        303
    );

    exit;
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


    try {

        if ($accion === 'crear') {

            $frecuencia =
                strtoupper(
                    trim(
                        (string) (
                            $_POST['frecuencia']
                            ?? 'UNA_VEZ'
                        )
                    )
                );


            if ($frecuencia === 'UNA_VEZ') {

                crearTarea($tenantUserId,
                    (string) (
                        $_POST['titulo']
                        ?? ''
                    ),

                    (string) (
                        $_POST['descripcion']
                        ?? ''
                    ),

                    (string) (
                        $_POST['prioridad']
                        ?? 'MEDIA'
                    ),

                    (string) (
                        $_POST['estado']
                        ?? 'PENDIENTE'
                    ),

                    (string) (
                        $_POST['fecha_limite']
                        ?? ''
                    ),

                    (string) (
                        $_POST['hora_limite']
                        ?? ''
                    ),

                    tareaNullableInt(
                        $_POST['materia_id']
                        ?? ''
                    ),

                    tareaNullableInt(
                        $_POST['evento_id']
                        ?? ''
                    )
                );


                tareaFlash(
                    'success',
                    'Tarea creada correctamente.'
                );


                tareaRedirect();
            }


            if (
                !in_array(
                    $frecuencia,
                    frecuenciasTareaRecurrenteValidas(),
                    true
                )
            ) {

                throw new InvalidArgumentException(
                    'La frecuencia seleccionada no es válida.'
                );
            }


            $intervaloRaw =
                trim(
                    (string) (
                        $_POST['intervalo']
                        ?? '1'
                    )
                );


            if (
                !ctype_digit(
                    $intervaloRaw
                )
                || (int) $intervaloRaw <= 0
            ) {

                throw new InvalidArgumentException(
                    'El intervalo de recurrencia no es válido.'
                );
            }


            $fechaInicio =
                trim(
                    (string) (
                        $_POST['fecha_limite']
                        ?? ''
                    )
                );


            if ($fechaInicio === '') {

                throw new InvalidArgumentException(
                    'Una serie recurrente necesita una fecha de inicio.'
                );
            }


            $pdo =
                db();


            $pdo->beginTransaction();


            try {

                $serieId =
                    crearSerieTarea($tenantUserId, 
                        (string) (
                            $_POST['titulo']
                            ?? ''
                        ),

                        (string) (
                            $_POST['descripcion']
                            ?? ''
                        ),

                        (string) (
                            $_POST['prioridad']
                            ?? 'MEDIA'
                        ),

                        $frecuencia,

                        (int) $intervaloRaw,

                        $fechaInicio,

                        (string) (
                            $_POST['fecha_fin']
                            ?? ''
                        ),

                        (string) (
                            $_POST['hora_limite']
                            ?? ''
                        ),

                        tareaNullableInt(
                            $_POST['materia_id']
                            ?? ''
                        ),

                        tareaNullableInt(
                            $_POST['evento_id']
                            ?? ''
                        )
                    );


                $serie =
                    buscarSerieTarea($tenantUserId, 
                        $serieId
                    );


                if ($serie === null) {

                    throw new RuntimeException(
                        'No se pudo recuperar la serie recién creada.'
                    );
                }


                $resultado =
                    procesarSerieTareaMaterializador($tenantUserId, 
                        $serie
                    );


                if (
                    (
                        $resultado['estado']
                        ?? ''
                    ) !== 'CREADA'
                ) {

                    throw new RuntimeException(
                        'No se pudo crear la primera ocurrencia.'
                    );
                }


                $pdo->commit();


            } catch (Throwable $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }


                throw $e;
            }


            tareaFlash(
                'success',
                'Serie recurrente creada con su primera ocurrencia.'
            );


            tareaRedirect(
                '/tareas.php#series'
            );
        }


        if ($accion === 'actualizar') {

            $id =
                tareaNullableInt(
                    $_POST['id']
                    ?? ''
                );


            if ($id === null) {

                throw new InvalidArgumentException(
                    'La tarea indicada no es válida.'
                );
            }


            if (
                buscarTarea($tenantUserId,
                    $id
                ) === null
            ) {

                throw new InvalidArgumentException(
                    'La tarea ya no existe.'
                );
            }


            actualizarTarea($tenantUserId,
                $id,

                (string) (
                    $_POST['titulo']
                    ?? ''
                ),

                (string) (
                    $_POST['descripcion']
                    ?? ''
                ),

                (string) (
                    $_POST['prioridad']
                    ?? 'MEDIA'
                ),

                (string) (
                    $_POST['estado']
                    ?? 'PENDIENTE'
                ),

                (string) (
                    $_POST['fecha_limite']
                    ?? ''
                ),

                (string) (
                    $_POST['hora_limite']
                    ?? ''
                ),

                tareaNullableInt(
                    $_POST['materia_id']
                    ?? ''
                ),

                tareaNullableInt(
                    $_POST['evento_id']
                    ?? ''
                )
            );


            tareaFlash(
                'success',
                'Tarea actualizada.'
            );


            tareaRedirect();
        }


        if ($accion === 'estado') {

            $id =
                tareaNullableInt(
                    $_POST['id']
                    ?? ''
                );


            $estado =
                trim(
                    (string) (
                        $_POST['estado']
                        ?? ''
                    )
                );


            if ($id === null) {

                throw new InvalidArgumentException(
                    'La tarea indicada no es válida.'
                );
            }


            if (
                buscarTarea($tenantUserId,
                    $id
                ) === null
            ) {

                throw new InvalidArgumentException(
                    'La tarea ya no existe.'
                );
            }


            cambiarEstadoTarea($tenantUserId,
                $id,
                $estado
            );


            $mensaje =
                match ($estado) {

                    'COMPLETADA' =>
                        'Tarea completada.',

                    'EN_PROGRESO' =>
                        'Tarea puesta en progreso.',

                    'PENDIENTE' =>
                        'Tarea reabierta.',

                    'CANCELADA' =>
                        'Tarea cancelada.',

                    default =>
                        'Estado actualizado.',
                };


            tareaFlash(
                'success',
                $mensaje
            );


            tareaRedirect();
        }


        if ($accion === 'serie_actualizar') {

            $serieId =
                tareaNullableInt(
                    $_POST['serie_id']
                    ?? ''
                );


            if ($serieId === null) {

                throw new InvalidArgumentException(
                    'La serie indicada no es válida.'
                );
            }


            $serie =
                buscarSerieTarea($tenantUserId, 
                    $serieId
                );


            if ($serie === null) {

                throw new InvalidArgumentException(
                    'La serie ya no existe.'
                );
            }


            $intervaloRaw =
                trim(
                    (string) (
                        $_POST['intervalo']
                        ?? ''
                    )
                );


            if (
                $intervaloRaw === ''
                || !ctype_digit(
                    $intervaloRaw
                )
            ) {

                throw new InvalidArgumentException(
                    'El intervalo de recurrencia no es válido.'
                );
            }


            actualizarSerieTarea($tenantUserId, 
                $serieId,

                (string) (
                    $_POST['titulo']
                    ?? ''
                ),

                (string) (
                    $_POST['descripcion']
                    ?? ''
                ),

                (string) (
                    $_POST['prioridad']
                    ?? 'MEDIA'
                ),

                (string) (
                    $_POST['frecuencia']
                    ?? ''
                ),

                (int) $intervaloRaw,

                (string) (
                    $_POST['fecha_fin']
                    ?? ''
                ),

                (string) (
                    $_POST['hora_limite']
                    ?? ''
                ),

                tareaNullableInt(
                    $_POST['materia_id']
                    ?? ''
                ),

                tareaNullableInt(
                    $_POST['evento_id']
                    ?? ''
                )
            );


            $serieActualizada =
                buscarSerieTarea($tenantUserId, 
                    $serieId
                );


            if (
                $serieActualizada !== null
                && (int) (
                    $serieActualizada['activa']
                    ?? 0
                ) === 1
            ) {

                procesarSerieTareaMaterializador($tenantUserId, 
                    $serieActualizada
                );
            }


            tareaFlash(
                'success',
                'Serie recurrente actualizada. Las ocurrencias ya materializadas no fueron modificadas.'
            );


            tareaRedirect(
                '/tareas.php?editar_serie='
                . $serieId
                . '#series-editor'
            );
        }


        if ($accion === 'serie_estado') {

            $serieId =
                tareaNullableInt(
                    $_POST['serie_id']
                    ?? ''
                );


            if ($serieId === null) {

                throw new InvalidArgumentException(
                    'La serie indicada no es válida.'
                );
            }


            $serie =
                buscarSerieTarea($tenantUserId, 
                    $serieId
                );


            if ($serie === null) {

                throw new InvalidArgumentException(
                    'La serie ya no existe.'
                );
            }


            $activaRaw =
                trim(
                    (string) (
                        $_POST['activa']
                        ?? ''
                    )
                );


            if (
                $activaRaw !== '0'
                && $activaRaw !== '1'
            ) {

                throw new InvalidArgumentException(
                    'El estado de la serie no es válido.'
                );
            }


            $activa =
                $activaRaw === '1';


            cambiarActivaSerieTarea($tenantUserId, 
                $serieId,
                $activa
            );


            if ($activa) {

                $serie =
                    buscarSerieTarea($tenantUserId, 
                        $serieId
                    );


                if ($serie !== null) {

                    procesarSerieTareaMaterializador($tenantUserId, 
                        $serie
                    );
                }
            }


            tareaFlash(
                'success',
                $activa
                    ? 'Serie recurrente reanudada.'
                    : 'Serie recurrente pausada. La ocurrencia actual se conserva.'
            );


            tareaRedirect(
                '/tareas.php#series'
            );
        }


        throw new InvalidArgumentException(
            'Acción no reconocida.'
        );

    } catch (
        InvalidArgumentException $e
    ) {

        tareaFlash(
            'error',
            $e->getMessage()
        );


        tareaRedirect();

    } catch (Throwable $e) {

        error_log(
            'Agenda tareas: '
            . $e->getMessage()
        );


        tareaFlash(
            'error',
            'No se pudo completar la operación.'
        );


        tareaRedirect();
    }
}


$materias =
    listarMaterias($tenantUserId);


$eventos =
    listarEventos($tenantUserId);


$seriesTareas =
    listarSeriesTareas($tenantUserId, );


$seriesVista = [];


foreach (
    $seriesTareas
    as $serie
) {

    $abiertas =
        listarOcurrenciasAbiertasSerieTarea($tenantUserId, 
            (int) $serie['id']
        );


    $serie['ocurrencia_abierta'] =
        $abiertas[0]
        ?? null;


    $seriesVista[] =
        $serie;
}



// R4-D5-D — Protected historical reopening

$serieAbiertaPorId = [];

foreach ($seriesVista as $serieGuardVista) {

    $actualGuard =
        $serieGuardVista['ocurrencia_abierta']
        ?? null;

    if (
        is_array($actualGuard)
        && !empty($actualGuard['id'])
    ) {

        $serieAbiertaPorId[
            (int) $serieGuardVista['id']
        ] = (int) $actualGuard['id'];
    }
}


$filtroEstado =
    strtoupper(
        trim(
            (string) (
                $_GET['estado']
                ?? ''
            )
        )
    );


if (
    !in_array(
        $filtroEstado,
        estadosTareaValidos(),
        true
    )
) {

    $filtroEstado = '';
}


$filtroPrioridad =
    strtoupper(
        trim(
            (string) (
                $_GET['prioridad']
                ?? ''
            )
        )
    );


if (
    !in_array(
        $filtroPrioridad,
        prioridadesTareaValidas(),
        true
    )
) {

    $filtroPrioridad = '';
}


try {

    $filtroMateria =
        tareaNullableInt(
            $_GET['materia']
            ?? ''
        );

} catch (
    InvalidArgumentException
) {

    $filtroMateria = null;
}


$tareas =
    listarTareas($tenantUserId,
        $filtroEstado !== ''
            ? $filtroEstado
            : null,

        $filtroMateria
    );


if ($filtroPrioridad !== '') {

    $tareas =
        array_values(
            array_filter(
                $tareas,
                static fn (
                    array $tarea
                ): bool =>
                    (
                        $tarea['prioridad']
                        ?? ''
                    )
                    === $filtroPrioridad
            )
        );
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


$hoySql =
    $hoy->format(
        'Y-m-d'
    );


$stmtStats =
    db()->prepare(
        "
        SELECT
            COUNT(*) AS total,

            SUM(
                estado IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )
            ) AS abiertas,

            SUM(
                estado IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )
                AND fecha_limite < :hoy_vencidas
            ) AS vencidas,

            SUM(
                estado IN (
                    'PENDIENTE',
                    'EN_PROGRESO'
                )
                AND fecha_limite = :hoy_hoy
            ) AS hoy,

            SUM(
                estado = 'COMPLETADA'
            ) AS completadas

        FROM tareas
            WHERE user_id=:user_id
        "
    );


$stmtStats->execute([
    'user_id' => $tenantUserId,
        'hoy_vencidas' =>
        $hoySql,

    'hoy_hoy' =>
        $hoySql,
]);


$stats =
    $stmtStats->fetch()
    ?: [];


$totalTareas =
    (int) (
        $stats['total']
        ?? 0
    );


$tareasAbiertas =
    (int) (
        $stats['abiertas']
        ?? 0
    );


$tareasVencidas =
    (int) (
        $stats['vencidas']
        ?? 0
    );


$tareasHoy =
    (int) (
        $stats['hoy']
        ?? 0
    );


$tareasCompletadas =
    (int) (
        $stats['completadas']
        ?? 0
    );


$editarId =
    null;


try {

    $editarId =
        tareaNullableInt(
            $_GET['editar']
            ?? ''
        );

} catch (
    InvalidArgumentException
) {
}


$tareaEditar =
    $editarId !== null
        ? buscarTarea($tenantUserId,
            $editarId
        )
        : null;


$modoEdicion =
    $tareaEditar !== null;


$esOcurrenciaRecurrente =
    $modoEdicion
    && !empty(
        $tareaEditar['serie_id']
    );


$editarSerieId =
    null;


try {

    $editarSerieId =
        tareaNullableInt(
            $_GET['editar_serie']
            ?? ''
        );

} catch (
    InvalidArgumentException
) {
}


$serieEditar =
    $editarSerieId !== null
        ? buscarSerieTarea($tenantUserId, 
            $editarSerieId
        )
        : null;


$modoEdicionSerie =
    $serieEditar !== null;


$flash =
    consumirTareaFlash();


$csrf =
    csrfToken();

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
        Tareas | Agenda Personal
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

    <link
        rel="manifest"
        href="/manifest.webmanifest"
    >

    <link
        rel="icon"
        href="/assets/icons/agenda-personal.svg"
        type="image/svg+xml"
    >

    <link
        rel="apple-touch-icon"
        href="/assets/icons/icon-180.png"
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

    <link rel="stylesheet" href="/assets/css/tareas-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-tareas.css?v=4">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-tasks-v4">

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

            <a
                class="active"
                href="/tareas.php"
                aria-current="page"
            >
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
                <?= $tareasAbiertas ?>
                abiertas
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="tasks-shell-v4">

    <section class="tasks-hero-v4">

        <div>

            <span class="section-kicker">
                Ejecución
            </span>

            <h1>
                Convertí pendientes
                <span>en progreso.</span>
            </h1>

            <p>
                Organizá trabajo concreto, vinculalo a tus
                materias o evaluaciones y mantené claro qué
                necesita acción.
            </p>

        </div>


        <a
            href="#task-form"
            class="tasks-new-v4"
        >

            <span>
                +
            </span>

            Nueva tarea

        </a>

    </section>


    <?php if ($flash !== null): ?>

        <div
            class="agenda-message-v3 <?= (
                $flash['tipo']
                ?? ''
            ) === 'success'
                ? 'agenda-message-success-v3'
                : 'agenda-message-error-v3' ?>"
        >

            <span class="agenda-message-icon-v3">

                <?= (
                    $flash['tipo']
                    ?? ''
                ) === 'success'
                    ? '✓'
                    : '!' ?>

            </span>

            <span>
                <?= h(
                    $flash['mensaje']
                    ?? ''
                ) ?>
            </span>

        </div>

    <?php endif; ?>


    <section class="tasks-metrics-v4">

        <article>

            <span>
                Abiertas
            </span>

            <strong>
                <?= $tareasAbiertas ?>
            </strong>

            <small>
                pendiente o en progreso
            </small>

        </article>


        <article class="<?= $tareasVencidas > 0
            ? 'is-danger'
            : '' ?>">

            <span>
                Vencidas
            </span>

            <strong>
                <?= $tareasVencidas ?>
            </strong>

            <small>
                requieren atención
            </small>

        </article>


        <article>

            <span>
                Para hoy
            </span>

            <strong>
                <?= $tareasHoy ?>
            </strong>

            <small>
                con fecha límite hoy
            </small>

        </article>


        <article>

            <span>
                Completadas
            </span>

            <strong>
                <?= $tareasCompletadas ?>
            </strong>

            <small>
                historial resuelto
            </small>

        </article>

    </section>


    <section class="tasks-layout-v4">


        <aside
            class="tasks-editor-v4"
            id="task-form"
        >

            <header>

                <div>

                    <span class="section-kicker">
                        <?= $modoEdicion
                            ? 'Edición'
                            : 'Nueva tarea' ?>
                    </span>

                    <h2>
                        <?= $modoEdicion
                            ? 'Actualizar tarea'
                            : 'Definir siguiente acción' ?>
                    </h2>

                </div>


                <?php if ($modoEdicion): ?>

                    <a
                        href="/tareas.php"
                        class="tasks-editor-close-v4"
                    >
                        Cancelar edición
                    </a>

                <?php endif; ?>

            </header>


            <?php if (
                $esOcurrenciaRecurrente
            ): ?>

                <div class="tasks-occurrence-note-v4">

                    <strong>
                        ↻ Editar esta ocurrencia
                    </strong>

                    <span>
                        Los cambios afectan solamente esta tarea.
                        La plantilla y la regla de toda la serie no cambian.
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="post"
                action="/tareas.php"
                data-task-form
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h(
                        $csrf
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="accion"
                    value="<?= $modoEdicion
                        ? 'actualizar'
                        : 'crear' ?>"
                >


                <?php if ($modoEdicion): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) (
                            $tareaEditar['id']
                            ?? 0
                        ) ?>"
                    >

                <?php endif; ?>


                <label class="tasks-field-v4">

                    <span>
                        Título
                        <em>obligatorio</em>
                    </span>

                    <input
                        type="text"
                        name="titulo"
                        maxlength="180"
                        required
                        placeholder="Ej: Repasar JDBC"
                        value="<?= h(
                            $tareaEditar['titulo']
                            ?? ''
                        ) ?>"
                    >

                </label>


                <div class="tasks-form-grid-v4">

                    <label class="tasks-field-v4">

                        <span>
                            Prioridad
                        </span>

                        <?php
                            $editPrioridad =
                                (string) (
                                    $tareaEditar['prioridad']
                                    ?? 'MEDIA'
                                );
                        ?>

                        <select
                            name="prioridad"
                            required
                        >

                            <?php foreach (
                                prioridadesTareaValidas()
                                as $prioridad
                            ): ?>

                                <option
                                    value="<?= h(
                                        $prioridad
                                    ) ?>"
                                    <?= $editPrioridad
                                        === $prioridad
                                            ? 'selected'
                                            : '' ?>
                                >
                                    <?= h(
                                        tareaPrioridadLabel(
                                            $prioridad
                                        )
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label class="tasks-field-v4">

                        <span>
                            Estado
                        </span>

                        <?php
                            $editEstado =
                                (string) (
                                    $tareaEditar['estado']
                                    ?? 'PENDIENTE'
                                );
                        ?>

                        <select
                            name="estado"
                            required
                            data-task-state
                        >

                            <?php foreach (
                                estadosTareaValidos()
                                as $estado
                            ): ?>

                                <option
                                    value="<?= h(
                                        $estado
                                    ) ?>"
                                    <?= $editEstado
                                        === $estado
                                            ? 'selected'
                                            : '' ?>
                                >
                                    <?= h(
                                        tareaEstadoLabel(
                                            $estado
                                        )
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>

                </div>


                <label class="tasks-field-v4">

                    <span>
                        Materia
                        <em>opcional</em>
                    </span>

                    <select
                        name="materia_id"
                        data-task-materia
                    >

                        <option value="">
                            Sin materia
                        </option>

                        <?php foreach (
                            $materias
                            as $materia
                        ): ?>

                            <?php
                                $materiaId =
                                    (int) (
                                        $materia['id']
                                        ?? 0
                                    );
                            ?>

                            <option
                                value="<?= $materiaId ?>"
                                <?= (int) (
                                    $tareaEditar['materia_id']
                                    ?? 0
                                ) === $materiaId
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= h(
                                    (
                                        $materia['codigo']
                                        ?? ''
                                    )
                                    !== ''
                                        ? (
                                            $materia['codigo']
                                            . ' · '
                                            . $materia['nombre']
                                        )
                                        : $materia['nombre']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="tasks-field-v4">

                    <span>
                        Evento relacionado
                        <em>opcional</em>
                    </span>

                    <select
                        name="evento_id"
                        data-task-evento
                    >

                        <option value="">
                            Sin evento
                        </option>

                        <?php foreach (
                            $eventos
                            as $evento
                        ): ?>

                            <?php
                                $eventoId =
                                    (int) (
                                        $evento['id']
                                        ?? 0
                                    );

                                $eventoMateria =
                                    (int) (
                                        $evento['materia_id']
                                        ?? 0
                                    );
                            ?>

                            <option
                                value="<?= $eventoId ?>"
                                data-materia-id="<?= $eventoMateria ?>"
                                <?= (int) (
                                    $tareaEditar['evento_id']
                                    ?? 0
                                ) === $eventoId
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= h(
                                    (
                                        $evento['materia_codigo']
                                        ?? ''
                                    )
                                    . ' · '
                                    . (
                                        $evento['titulo']
                                        ?? 'Evento'
                                    )
                                    . ' · '
                                    . tareaFechaLabel(
                                        $evento['fecha']
                                        ?? null
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        Si elegís un evento, su materia se
                        sincroniza automáticamente.
                    </small>

                </label>


                <div class="tasks-form-grid-v4">

                    <label class="tasks-field-v4">

                        <span>
                            Fecha límite
                        </span>

                        <input
                            type="date"
                            name="fecha_limite"
                            value="<?= h(
                                $tareaEditar['fecha_limite']
                                ?? ''
                            ) ?>"
                        >

                    </label>


                    <label class="tasks-field-v4">

                        <span>
                            Hora
                        </span>

                        <input
                            type="time"
                            name="hora_limite"
                            value="<?= h(
                                isset(
                                    $tareaEditar['hora_limite']
                                )
                                && $tareaEditar['hora_limite']
                                    !== null
                                    ? substr(
                                        (string)
                                            $tareaEditar['hora_limite'],
                                        0,
                                        5
                                    )
                                    : ''
                            ) ?>"
                        >

                    </label>

                </div>


                <?php if (!$modoEdicion): ?>

                    <fieldset
                        class="tasks-recurrence-v4"
                        data-recurrence-box
                    >

                        <legend>
                            Repetición
                        </legend>


                        <label class="tasks-field-v4">

                            <span>
                                Frecuencia
                            </span>

                            <select
                                name="frecuencia"
                                data-task-frequency
                            >

                                <option value="UNA_VEZ">
                                    Una vez
                                </option>

                                <option value="DIARIA">
                                    Diaria
                                </option>

                                <option value="SEMANAL">
                                    Semanal
                                </option>

                                <option value="MENSUAL">
                                    Mensual
                                </option>

                            </select>

                            <small>
                                La fecha límite de arriba será
                                la primera ocurrencia de la serie.
                            </small>

                        </label>


                        <div
                            class="tasks-recurrence-options-v4"
                            data-recurrence-options
                            hidden
                        >

                            <label class="tasks-field-v4">

                                <span>
                                    Repetir cada
                                </span>

                                <input
                                    type="number"
                                    name="intervalo"
                                    min="1"
                                    max="365"
                                    value="1"
                                    inputmode="numeric"
                                    data-task-interval
                                >

                            </label>


                            <label class="tasks-field-v4">

                                <span>
                                    Finalizar
                                    <em>opcional</em>
                                </span>

                                <input
                                    type="date"
                                    name="fecha_fin"
                                    data-task-end-date
                                >

                            </label>

                        </div>


                        <p class="tasks-recurrence-help-v4">
                            Una serie mantiene una sola ocurrencia
                            abierta. Al completarla o cancelarla,
                            el motor habilita la siguiente.
                        </p>

                    </fieldset>

                <?php endif; ?>


                <label class="tasks-field-v4">

                    <span>
                        Descripción
                    </span>

                    <textarea
                        name="descripcion"
                        rows="5"
                        placeholder="Pasos, contexto o criterio de terminado..."
                    ><?= h(
                        $tareaEditar['descripcion']
                        ?? ''
                    ) ?></textarea>

                </label>


                <button
                    type="submit"
                    class="tasks-submit-v4"
                >

                    <?= $modoEdicion
                        ? 'Guardar cambios'
                        : 'Crear tarea' ?>

                </button>

            </form>

        </aside>


        <section class="tasks-workspace-v4">

            <section
                class="tasks-series-v4"
                id="series"
            >

                <header class="tasks-series-header-v4">

                    <div>

                        <span class="section-kicker">
                            Automatización
                        </span>

                        <h2>
                            Series recurrentes
                        </h2>

                        <small>
                            <?= count(
                                $seriesVista
                            ) ?>
                            configuradas
                        </small>

                    </div>

                </header>


                <?php if ($seriesVista === []): ?>

                    <div class="tasks-series-empty-v4">

                        <strong>
                            Sin series todavía
                        </strong>

                        <span>
                            Elegí una repetición al crear una
                            tarea para automatizarla.
                        </span>

                    </div>

                <?php else: ?>

                    <?php if ($modoEdicionSerie): ?>

                        <section
                            class="tasks-series-editor-v5"
                            id="series-editor"
                        >

                            <header class="tasks-series-editor-header-v5">

                                <div>

                                    <span class="section-kicker">
                                        Serie recurrente
                                    </span>

                                    <h3>
                                        Editar toda la serie
                                    </h3>

                                    <p>
                                        Los cambios se aplican a la plantilla
                                        que generará las próximas ocurrencias.
                                    </p>

                                </div>

                                <a
                                    href="/tareas.php#series"
                                    class="tasks-series-editor-close-v5"
                                >
                                    Cerrar editor
                                </a>

                            </header>


                            <div class="tasks-series-editor-note-v5">

                                <strong>
                                    Historial protegido
                                </strong>

                                <span>
                                    Las ocurrencias ya materializadas no se
                                    reescriben. La fecha de inicio de la serie
                                    también permanece inmutable.
                                </span>

                            </div>


                            <form
                                method="post"
                                action="/tareas.php"
                                class="tasks-series-editor-form-v5"
                                data-series-edit-form
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h(
                                        $csrf
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="serie_actualizar"
                                >

                                <input
                                    type="hidden"
                                    name="serie_id"
                                    value="<?= (int) (
                                        $serieEditar['id']
                                        ?? 0
                                    ) ?>"
                                >


                                <div class="tasks-series-editor-grid-v5">

                                    <label class="is-wide">

                                        <span>
                                            Título de la serie
                                        </span>

                                        <input
                                            type="text"
                                            name="titulo"
                                            maxlength="180"
                                            required
                                            value="<?= h(
                                                (string) (
                                                    $serieEditar['titulo']
                                                    ?? ''
                                                )
                                            ) ?>"
                                        >

                                    </label>


                                    <label class="is-wide">

                                        <span>
                                            Descripción
                                        </span>

                                        <textarea
                                            name="descripcion"
                                            rows="3"
                                        ><?= h(
                                            (string) (
                                                $serieEditar['descripcion']
                                                ?? ''
                                            )
                                        ) ?></textarea>

                                    </label>


                                    <label>

                                        <span>
                                            Prioridad
                                        </span>

                                        <select
                                            name="prioridad"
                                            required
                                        >

                                            <?php foreach (
                                                prioridadesTareaValidas()
                                                as $prioridadSerie
                                            ): ?>

                                                <option
                                                    value="<?= h(
                                                        $prioridadSerie
                                                    ) ?>"
                                                    <?= (
                                                        (
                                                            $serieEditar[
                                                                'prioridad'
                                                            ]
                                                            ?? ''
                                                        )
                                                        === $prioridadSerie
                                                    )
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= h(
                                                        tareaPrioridadLabel(
                                                            $prioridadSerie
                                                        )
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </label>


                                    <label>

                                        <span>
                                            Frecuencia
                                        </span>

                                        <select
                                            name="frecuencia"
                                            required
                                        >

                                            <?php foreach (
                                                frecuenciasTareaRecurrenteValidas()
                                                as $frecuenciaSerieEditar
                                            ): ?>

                                                <option
                                                    value="<?= h(
                                                        $frecuenciaSerieEditar
                                                    ) ?>"
                                                    <?= (
                                                        (
                                                            $serieEditar[
                                                                'frecuencia'
                                                            ]
                                                            ?? ''
                                                        )
                                                        === $frecuenciaSerieEditar
                                                    )
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= h(
                                                        match (
                                                            $frecuenciaSerieEditar
                                                        ) {
                                                            'DIARIA' =>
                                                                'Diaria',

                                                            'SEMANAL' =>
                                                                'Semanal',

                                                            'MENSUAL' =>
                                                                'Mensual',

                                                            default =>
                                                                $frecuenciaSerieEditar,
                                                        }
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </label>


                                    <label>

                                        <span>
                                            Intervalo
                                        </span>

                                        <input
                                            type="number"
                                            name="intervalo"
                                            min="1"
                                            max="365"
                                            required
                                            value="<?= (int) (
                                                $serieEditar['intervalo']
                                                ?? 1
                                            ) ?>"
                                        >

                                    </label>


                                    <label>

                                        <span>
                                            Inicio
                                            <em>protegido</em>
                                        </span>

                                        <input
                                            type="date"
                                            value="<?= h(
                                                (string) (
                                                    $serieEditar[
                                                        'fecha_inicio'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>"
                                            readonly
                                        >

                                    </label>


                                    <label>

                                        <span>
                                            Hasta
                                            <em>opcional</em>
                                        </span>

                                        <input
                                            type="date"
                                            name="fecha_fin"
                                            min="<?= h(
                                                (string) (
                                                    $serieEditar[
                                                        'fecha_inicio'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>"
                                            value="<?= h(
                                                (string) (
                                                    $serieEditar[
                                                        'fecha_fin'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>"
                                        >

                                    </label>


                                    <label>

                                        <span>
                                            Hora
                                            <em>opcional</em>
                                        </span>

                                        <input
                                            type="time"
                                            name="hora_limite"
                                            value="<?= h(
                                                !empty(
                                                    $serieEditar[
                                                        'hora_limite'
                                                    ]
                                                )
                                                    ? substr(
                                                        (string)
                                                            $serieEditar[
                                                                'hora_limite'
                                                            ],
                                                        0,
                                                        5
                                                    )
                                                    : ''
                                            ) ?>"
                                        >

                                    </label>


                                    <label>

                                        <span>
                                            Materia
                                            <em>opcional</em>
                                        </span>

                                        <select name="materia_id">

                                            <option value="">
                                                Sin materia
                                            </option>

                                            <?php foreach (
                                                $materias
                                                as $materiaSerie
                                            ): ?>

                                                <option
                                                    value="<?= (int)
                                                        $materiaSerie['id'] ?>"
                                                    <?= (
                                                        (int) (
                                                            $serieEditar[
                                                                'materia_id'
                                                            ]
                                                            ?? 0
                                                        )
                                                        ===
                                                        (int)
                                                            $materiaSerie['id']
                                                    )
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= h(
                                                        trim(
                                                            (
                                                                $materiaSerie[
                                                                    'codigo'
                                                                ]
                                                                ?? ''
                                                            )
                                                            . ' '
                                                            . (
                                                                $materiaSerie[
                                                                    'nombre'
                                                                ]
                                                                ?? ''
                                                            )
                                                        )
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </label>


                                    <label>

                                        <span>
                                            Evento
                                            <em>opcional</em>
                                        </span>

                                        <select name="evento_id">

                                            <option value="">
                                                Sin evento
                                            </option>

                                            <?php foreach (
                                                $eventos
                                                as $eventoSerie
                                            ): ?>

                                                <option
                                                    value="<?= (int)
                                                        $eventoSerie['id'] ?>"
                                                    <?= (
                                                        (int) (
                                                            $serieEditar[
                                                                'evento_id'
                                                            ]
                                                            ?? 0
                                                        )
                                                        ===
                                                        (int)
                                                            $eventoSerie['id']
                                                    )
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= h(
                                                        (string) (
                                                            $eventoSerie[
                                                                'titulo'
                                                            ]
                                                            ?? ''
                                                        )
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </label>

                                </div>


                                <footer class="tasks-series-editor-footer-v5">

                                    <span>
                                        Serie #<?= (int) (
                                            $serieEditar['id']
                                            ?? 0
                                        ) ?>
                                    </span>

                                    <button type="submit">
                                        Guardar cambios de serie
                                    </button>

                                </footer>

                            </form>

                        </section>

                    <?php endif; ?>


                    <div class="tasks-series-grid-v4">

                        <?php foreach (
                            $seriesVista
                            as $serie
                        ): ?>

                            <?php
                                $serieActiva =
                                    (int) (
                                        $serie['activa']
                                        ?? 0
                                    ) === 1;

                                $ocurrencia =
                                    $serie[
                                        'ocurrencia_abierta'
                                    ]
                                    ?? null;

                                $frecuenciaSerie =
                                    (string) (
                                        $serie[
                                            'frecuencia'
                                        ]
                                        ?? ''
                                    );

                                $intervaloSerie =
                                    (int) (
                                        $serie[
                                            'intervalo'
                                        ]
                                        ?? 1
                                    );
                            ?>

                            <article
                                class="
                                    tasks-series-card-v4
                                    <?= $serieActiva
                                        ? 'is-active'
                                        : 'is-paused' ?>
                                "
                                data-series-id="<?= (int)
                                    $serie['id'] ?>"
                            >

                                <div class="tasks-series-top-v4">

                                    <div class="tasks-series-badges-v4">

                                        <span class="series-frequency-v4">
                                            ↻
                                            <?= h(
                                                tareaFrecuenciaLabel(
                                                    $frecuenciaSerie
                                                )
                                            ) ?>
                                        </span>

                                        <span
                                            class="
                                                series-status-v4
                                                <?= $serieActiva
                                                    ? 'is-active'
                                                    : 'is-paused' ?>
                                            "
                                        >
                                            <?= $serieActiva
                                                ? 'Activa'
                                                : 'Pausada' ?>
                                        </span>

                                    </div>


                                    <span class="task-id-v4">
                                        Serie #<?= (int)
                                            $serie['id'] ?>
                                    </span>

                                </div>


                                <h3>
                                    <?= h(
                                        $serie['titulo']
                                    ) ?>
                                </h3>


                                <div class="tasks-series-meta-v4">

                                    <span>
                                        <?= h(
                                            tareaIntervaloSerieLabel(
                                                $frecuenciaSerie,
                                                $intervaloSerie
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        Inicio
                                        <?= h(
                                            tareaFechaLabel(
                                                $serie[
                                                    'fecha_inicio'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </span>

                                    <?php if (
                                        !empty(
                                            $serie[
                                                'fecha_fin'
                                            ]
                                        )
                                    ): ?>

                                        <span>
                                            Hasta
                                            <?= h(
                                                tareaFechaLabel(
                                                    $serie[
                                                        'fecha_fin'
                                                    ]
                                                )
                                            ) ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $serie[
                                                'materia_codigo'
                                            ]
                                        )
                                    ): ?>

                                        <span>
                                            <?= h(
                                                $serie[
                                                    'materia_codigo'
                                                ]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <div class="tasks-series-current-v4">

                                    <?php if (
                                        is_array(
                                            $ocurrencia
                                        )
                                    ): ?>

                                        <span>
                                            Próxima ocurrencia
                                        </span>

                                        <a
                                            href="/tareas.php?editar=<?= (int)
                                                $ocurrencia[
                                                    'id'
                                                ] ?>#task-form"
                                        >
                                            <?= h(
                                                tareaFechaLabel(
                                                    $ocurrencia[
                                                        'fecha_ocurrencia'
                                                    ]
                                                    ?? null
                                                )
                                            ) ?>

                                            <?php if (
                                                !empty(
                                                    $ocurrencia[
                                                        'hora_limite'
                                                    ]
                                                )
                                            ): ?>

                                                ·
                                                <?= h(
                                                    substr(
                                                        (string)
                                                            $ocurrencia[
                                                                'hora_limite'
                                                            ],
                                                        0,
                                                        5
                                                    )
                                                ) ?>

                                            <?php endif; ?>
                                        </a>

                                    <?php else: ?>

                                        <span>
                                            Sin ocurrencia abierta
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <form
                                    method="post"
                                    action="/tareas.php"
                                    class="tasks-series-actions-v4"
                                >

                                    <a
                                        href="/tareas.php?editar_serie=<?= (int)
                                            $serie['id'] ?>#series-editor"
                                        class="tasks-series-edit-link-v5"
                                    >
                                        Editar serie
                                    </a>

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h(
                                            $csrf
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="serie_estado"
                                    >

                                    <input
                                        type="hidden"
                                        name="serie_id"
                                        value="<?= (int)
                                            $serie['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="activa"
                                        value="<?= $serieActiva
                                            ? '0'
                                            : '1' ?>"
                                    >

                                    <button
                                        type="submit"
                                        data-task-confirm="<?= h(
                                            $serieActiva
                                                ? '¿Pausar futuras ocurrencias de esta serie? La tarea actual se conserva.'
                                                : '¿Reanudar esta serie recurrente?'
                                        ) ?>"
                                    >
                                        <?= $serieActiva
                                            ? 'Pausar serie'
                                            : 'Reanudar serie' ?>
                                    </button>

                                </form>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <header class="tasks-list-header-v4">

                <div>

                    <span class="section-kicker">
                        Workspace
                    </span>

                    <h2>
                        Tareas
                    </h2>

                    <small>
                        <?= count(
                            $tareas
                        ) ?>
                        visibles de
                        <?= $totalTareas ?>
                    </small>

                </div>


                <form
                    method="get"
                    action="/tareas.php"
                    class="tasks-filters-v4"
                >

                    <select
                        name="estado"
                        aria-label="Filtrar por estado"
                    >

                        <option value="">
                            Todos los estados
                        </option>

                        <?php foreach (
                            estadosTareaValidos()
                            as $estado
                        ): ?>

                            <option
                                value="<?= h(
                                    $estado
                                ) ?>"
                                <?= $filtroEstado
                                    === $estado
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= h(
                                    tareaEstadoLabel(
                                        $estado
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select
                        name="prioridad"
                        aria-label="Filtrar por prioridad"
                    >

                        <option value="">
                            Todas las prioridades
                        </option>

                        <?php foreach (
                            prioridadesTareaValidas()
                            as $prioridad
                        ): ?>

                            <option
                                value="<?= h(
                                    $prioridad
                                ) ?>"
                                <?= $filtroPrioridad
                                    === $prioridad
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= h(
                                    tareaPrioridadLabel(
                                        $prioridad
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <select
                        name="materia"
                        aria-label="Filtrar por materia"
                    >

                        <option value="">
                            Todas las materias
                        </option>

                        <?php foreach (
                            $materias
                            as $materia
                        ): ?>

                            <?php
                                $materiaId =
                                    (int) (
                                        $materia['id']
                                        ?? 0
                                    );
                            ?>

                            <option
                                value="<?= $materiaId ?>"
                                <?= $filtroMateria
                                    === $materiaId
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= h(
                                    $materia['codigo']
                                    ?? $materia['nombre']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <button type="submit">
                        Aplicar
                    </button>


                    <?php if (
                        $filtroEstado !== ''
                        || $filtroPrioridad !== ''
                        || $filtroMateria !== null
                    ): ?>

                        <a href="/tareas.php">
                            Limpiar
                        </a>

                    <?php endif; ?>

                </form>

            </header>


            <?php if ($tareas === []): ?>

                <div class="tasks-empty-v4">

                    <div class="tasks-empty-icon-v4">
                        ✓
                    </div>

                    <span class="section-kicker">
                        Sin tareas
                    </span>

                    <h3>
                        Tu ejecución empieza acá.
                    </h3>

                    <p>

                        <?php if (
                            $totalTareas === 0
                        ): ?>

                            Creá tu primera tarea para convertir
                            una materia, un parcial o cualquier
                            pendiente en una acción concreta.

                        <?php else: ?>

                            Ninguna tarea coincide con los filtros
                            seleccionados.

                        <?php endif; ?>

                    </p>


                    <?php if ($totalTareas === 0): ?>

                        <a href="#task-form">
                            Crear primera tarea
                        </a>

                    <?php else: ?>

                        <a href="/tareas.php">
                            Ver todas
                        </a>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="tasks-list-v4">

                    <?php foreach (
                        $tareas
                        as $tarea
                    ): ?>

                        <?php

                            $estado =
                                (string) (
                                    $tarea['estado']
                                    ?? 'PENDIENTE'
                                );

                            $prioridad =
                                (string) (
                                    $tarea['prioridad']
                                    ?? 'MEDIA'
                                );

                            $fechaLimite =
                                $tarea['fecha_limite']
                                ?? null;

                            $esRecurrente =
                                !empty(
                                    $tarea['serie_id']
                                );

                            $esAbierta =
                                in_array(
                                    $estado,
                                    [
                                        'PENDIENTE',
                                        'EN_PROGRESO',
                                    ],
                                    true
                                );

                            $esVencida =
                                $esAbierta
                                && $fechaLimite !== null
                                && $fechaLimite < $hoySql;

                            $esHoy =
                                $esAbierta
                                && $fechaLimite
                                    === $hoySql;

                        ?>

                        <article
                            class="
                                task-card-v4
                                priority-<?= h(
                                    strtolower(
                                        $prioridad
                                    )
                                ) ?>
                                state-<?= h(
                                    strtolower(
                                        $estado
                                    )
                                ) ?>
                                <?= $esVencida
                                    ? 'is-overdue'
                                    : '' ?>
                            "
                        >

                            <div class="task-check-v4">

                                <?php if (
                                    $estado
                                    !== 'COMPLETADA'
                                ): ?>

                                    <form
                                        method="post"
                                        action="/tareas.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h(
                                                $csrf
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $tarea['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="COMPLETADA"
                                        >

                                        <button
                                            type="submit"
                                            title="Completar"
                                            aria-label="Completar tarea"
                                        >
                                            ✓
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span
                                        class="is-complete"
                                        aria-label="Completada"
                                    >
                                        ✓
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="task-main-v4">

                                <div class="task-topline-v4">

                                    <div class="task-badges-v4">

                                        <span
                                            class="
                                                task-priority-v4
                                                priority-<?= h(
                                                    strtolower(
                                                        $prioridad
                                                    )
                                                ) ?>
                                            "
                                        >
                                            <?= h(
                                                tareaPrioridadLabel(
                                                    $prioridad
                                                )
                                            ) ?>
                                        </span>


                                        <span
                                            class="
                                                task-state-v4
                                                state-<?= h(
                                                    strtolower(
                                                        $estado
                                                    )
                                                ) ?>
                                            "
                                        >
                                            <?= h(
                                                tareaEstadoLabel(
                                                    $estado
                                                )
                                            ) ?>
                                        </span>


                                        <?php if (
                                            $esRecurrente
                                        ): ?>

                                            <span
                                                class="task-recurring-v4"
                                                title="Ocurrencia de serie recurrente"
                                            >
                                                ↻ Recurrente
                                            </span>

                                        <?php endif; ?>


                                        <?php if ($esVencida): ?>

                                            <span
                                                class="task-overdue-v4"
                                            >
                                                Vencida
                                            </span>

                                        <?php elseif ($esHoy): ?>

                                            <span
                                                class="task-today-v4"
                                            >
                                                Hoy
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <span class="task-id-v4">
                                        #<?= (int)
                                            $tarea['id'] ?>
                                    </span>

                                </div>


                                <h3>
                                    <?= h(
                                        $tarea['titulo']
                                    ) ?>
                                </h3>


                                <?php if (
                                    !empty(
                                        $tarea['descripcion']
                                    )
                                ): ?>

                                    <p>
                                        <?= nl2br(
                                            h(
                                                $tarea[
                                                    'descripcion'
                                                ]
                                            )
                                        ) ?>
                                    </p>

                                <?php endif; ?>


                                <div class="task-meta-v4">

                                    <?php if (
                                        !empty(
                                            $tarea[
                                                'materia_nombre'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i
                                                class="task-matter-dot-v4"
                                                style="
                                                    --matter-color:
                                                    <?= h(
                                                        $tarea[
                                                            'materia_color'
                                                        ]
                                                        ?? '#587089'
                                                    ) ?>;
                                                "
                                            ></i>

                                            <?= h(
                                                (
                                                    $tarea[
                                                        'materia_codigo'
                                                    ]
                                                    ?? ''
                                                )
                                                !== ''
                                                    ? $tarea[
                                                        'materia_codigo'
                                                    ]
                                                    : $tarea[
                                                        'materia_nombre'
                                                    ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $tarea[
                                                'evento_titulo'
                                            ]
                                        )
                                    ): ?>

                                        <span>
                                            Evento:
                                            <?= h(
                                                $tarea[
                                                    'evento_titulo'
                                                ]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>


                                    <span>
                                        <?= h(
                                            tareaFechaLabel(
                                                $fechaLimite
                                            )
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $tarea[
                                                    'hora_limite'
                                                ]
                                            )
                                        ): ?>

                                            ·
                                            <?= h(
                                                substr(
                                                    (string)
                                                        $tarea[
                                                            'hora_limite'
                                                        ],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        <?php endif; ?>

                                    </span>

                                </div>

                            </div>


                            <div class="task-actions-v4">

                                <a
                                    href="/tareas.php?editar=<?= (int)
                                        $tarea['id'] ?>#task-form"
                                >
                                    Editar
                                </a>


                                <?php if (
                                    $estado
                                    === 'PENDIENTE'
                                ): ?>

                                    <form
                                        method="post"
                                        action="/tareas.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h(
                                                $csrf
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $tarea['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="EN_PROGRESO"
                                        >

                                        <button type="submit">
                                            Iniciar
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if (
                                    in_array(
                                        $estado,
                                        [
                                            'COMPLETADA',
                                            'CANCELADA',
                                        ],
                                        true
                                    )
                                ): ?>

                                    <?php if (
                                        !empty($tarea['serie_id'])
                                        && isset(
                                            $serieAbiertaPorId[
                                                (int) $tarea['serie_id']
                                            ]
                                        )
                                        && $serieAbiertaPorId[
                                            (int) $tarea['serie_id']
                                        ] !== (int) $tarea['id']
                                    ): ?>

                                        <span
                                            class="tasks-reopen-locked-v5"
                                            title="Ya existe otra ocurrencia abierta para esta serie."
                                            aria-label="No se puede reabrir: la serie tiene otra ocurrencia abierta."
                                        >
                                            Serie activa
                                        </span>

                                    <?php else: ?>

                                    <form
                                        method="post"
                                        action="/tareas.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h(
                                                $csrf
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $tarea['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="PENDIENTE"
                                        >

                                        <button type="submit">
                                            Reabrir
                                        </button>

                                    </form>

                                    <?php endif; ?>

                                <?php endif; ?>


                                <?php if (
                                    $estado
                                    !== 'CANCELADA'
                                    && $estado
                                    !== 'COMPLETADA'
                                ): ?>

                                    <form
                                        method="post"
                                        action="/tareas.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h(
                                                $csrf
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $tarea['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="estado"
                                            value="CANCELADA"
                                        >

                                        <button
                                            type="submit"
                                            data-task-confirm="
                                                ¿Cancelar esta tarea?
                                            "
                                        >
                                            Cancelar
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

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

<script
    src="/assets/js/tareas.js?v=2"
    defer
></script>

<script
    src="/assets/js/mobile-shell.js?v=5"
    defer
></script>

<script
    src="/assets/js/pwa.js?v=1"
    defer
></script>

</body>

</html>
