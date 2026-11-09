<?php

declare(strict_types=1);

require_once '/var/www/app/security.php';

$tenantUserId = contextoTenantWebActualId();
require_once '/var/www/app/materias_repository.php';

iniciarSesion();


function h(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function textoOpcional(string $valor): ?string
{
    $valor = trim($valor);

    return $valor !== ''
        ? $valor
        : null;
}


function normalizarCodigo(string $codigo): ?string
{
    $codigo = trim($codigo);

    if ($codigo === '') {
        return null;
    }

    $codigo = preg_replace(
        '/^\[|\]$/',
        '',
        $codigo
    ) ?? $codigo;

    $codigo = trim($codigo);

    return $codigo !== ''
        ? mb_strtoupper($codigo)
        : null;
}


function normalizarComision(string $comision): ?string
{
    $comision = trim($comision);

    return $comision !== ''
        ? mb_strtoupper($comision)
        : null;
}


function etiquetaTurno(?string $turno): string
{
    return match ($turno) {
        'MANANA' => 'Mañana',
        'TARDE' => 'Tarde',
        'NOCHE' => 'Noche',
        'OTRO' => 'Otro',
        default => 'Sin especificar',
    };
}


function etiquetaCuatrimestre(
    int|string|null $cuatrimestre
): string {
    if (
        $cuatrimestre === null
        || $cuatrimestre === ''
    ) {
        return 'Sin cuatrimestre';
    }

    return (int) $cuatrimestre
        . '° cuatrimestre';
}


function validarMateria(array $datos): array
{
    $errores = [];


    $nombre = trim(
        (string) ($datos['nombre'] ?? '')
    );

    if ($nombre === '') {

        $errores[] =
            'El nombre es obligatorio.';

    } elseif (mb_strlen($nombre) > 120) {

        $errores[] =
            'El nombre no puede superar 120 caracteres.';
    }


    $codigo = normalizarCodigo(
        (string) ($datos['codigo'] ?? '')
    );

    if (
        $codigo !== null
        && mb_strlen($codigo) > 30
    ) {
        $errores[] =
            'El código no puede superar 30 caracteres.';
    }


    $cuatrimestreRaw = trim(
        (string) (
            $datos['cuatrimestre']
            ?? ''
        )
    );

    $cuatrimestre = null;

    if ($cuatrimestreRaw !== '') {

        $valor = filter_var(
            $cuatrimestreRaw,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 10,
                ],
            ]
        );

        if ($valor === false) {

            $errores[] =
                'El cuatrimestre debe estar entre 1 y 10.';

        } else {

            $cuatrimestre =
                (int) $valor;
        }
    }


    $turnoRaw = strtoupper(
        trim(
            (string) (
                $datos['turno']
                ?? ''
            )
        )
    );

    $turno = $turnoRaw !== ''
        ? $turnoRaw
        : null;

    if (
        $turno !== null
        && !in_array(
            $turno,
            [
                'MANANA',
                'TARDE',
                'NOCHE',
                'OTRO',
            ],
            true
        )
    ) {
        $errores[] =
            'El turno seleccionado no es válido.';
    }


    $comision = normalizarComision(
        (string) (
            $datos['comision']
            ?? ''
        )
    );

    if (
        $comision !== null
        && mb_strlen($comision) > 30
    ) {
        $errores[] =
            'La comisión no puede superar 30 caracteres.';
    }


    $docente = textoOpcional(
        (string) (
            $datos['docente']
            ?? ''
        )
    );

    if (
        $docente !== null
        && mb_strlen($docente) > 150
    ) {
        $errores[] =
            'El docente no puede superar 150 caracteres.';
    }


    $color = strtolower(
        trim(
            (string) (
                $datos['color']
                ?? '#39d353'
            )
        )
    );

    if (
        !preg_match(
            '/^#[0-9a-f]{6}$/',
            $color
        )
    ) {
        $errores[] =
            'El color debe ser hexadecimal.';
    }


    return [
        'errores' => $errores,
        'nombre' => $nombre,
        'codigo' => $codigo,
        'cuatrimestre' => $cuatrimestre,
        'turno' => $turno,
        'comision' => $comision,
        'docente' => $docente,
        'color' => $color,
    ];
}


function redirigirMaterias(
    ?int $editar = null
): never {
    $url = '/materias.php';

    if ($editar !== null) {
        $url .= '?editar=' . $editar;
    }

    header('Location: ' . $url);
    exit;
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


    try {

                // ARK3360_R36H_MATERIA_DELETE_HANDLER
        if ($accion === 'r36h_eliminar_materia') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($id === false || $id === null ||
                ($_POST['confirmacion'] ?? '') !== 'ELIMINAR_DEFINITIVAMENTE') {
                throw new RuntimeException('Confirmación de eliminación inválida.');
            }
            try {
                $resultado = eliminarMateriaSeguraR36H($tenantUserId, (int) $id);
            } catch (InvalidArgumentException $e) {
                guardarMensaje('error', $e->getMessage());
                redirigirMaterias();
            }
            guardarMensaje('exito', sprintf(
                'Materia eliminada definitivamente. Se conservaron %d tareas y %d series, desvinculadas de esa materia.',
                $resultado['tareas'], $resultado['series']
            ));
            redirigirMaterias();
        }

if ($accion === 'crear') {

            $datos =
                validarMateria($_POST);


            if ($datos['errores'] !== []) {

                guardarMensaje(
                    'error',
                    implode(
                        ' ',
                        $datos['errores']
                    )
                );

                redirigirMaterias();
            }


            crearMateria($tenantUserId, 
                $datos['nombre'],
                $datos['codigo'],
                $datos['cuatrimestre'],
                $datos['turno'],
                $datos['comision'],
                $datos['docente'],
                $datos['color']
            );


            guardarMensaje(
                'exito',
                'Materia creada correctamente.'
            );

            redirigirMaterias();
        }


        if ($accion === 'actualizar') {

            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );


            if ($id === false) {
                throw new RuntimeException(
                    'Identificador inválido.'
                );
            }


            if (
                buscarMateria($tenantUserId, (int) $id)
                === null
            ) {
                throw new RuntimeException(
                    'La materia no existe.'
                );
            }


            $datos =
                validarMateria($_POST);


            if ($datos['errores'] !== []) {

                guardarMensaje(
                    'error',
                    implode(
                        ' ',
                        $datos['errores']
                    )
                );

                redirigirMaterias(
                    (int) $id
                );
            }


            actualizarMateria($tenantUserId, 
                (int) $id,
                $datos['nombre'],
                $datos['codigo'],
                $datos['cuatrimestre'],
                $datos['turno'],
                $datos['comision'],
                $datos['docente'],
                $datos['color']
            );


            guardarMensaje(
                'exito',
                'Materia actualizada correctamente.'
            );

            redirigirMaterias();
        }


        if ($accion === 'cambiar_estado') {

            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            $activa = filter_var(
                $_POST['activa'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 0,
                        'max_range' => 1,
                    ],
                ]
            );


            if (
                $id === false
                || $activa === false
            ) {
                throw new RuntimeException(
                    'Solicitud inválida.'
                );
            }


            if (
                buscarMateria($tenantUserId, (int) $id)
                === null
            ) {
                throw new RuntimeException(
                    'La materia no existe.'
                );
            }


            cambiarEstadoMateria($tenantUserId, 
                (int) $id,
                (bool) $activa
            );


            guardarMensaje(
                'exito',
                (int) $activa === 1
                    ? 'Materia activada correctamente.'
                    : 'Materia desactivada correctamente.'
            );

            redirigirMaterias();
        }


        throw new RuntimeException(
            'Acción no reconocida.'
        );

    } catch (PDOException $e) {

        if (
            $e->getCode() === '23000'
        ) {

            guardarMensaje(
                'error',
                'Ya existe una materia con ese código.'
            );

        } else {

            guardarMensaje(
                'error',
                'No se pudo guardar la materia.'
            );
        }


        redirigirMaterias();

    } catch (Throwable $e) {

        guardarMensaje(
            'error',
            $e->getMessage()
        );

        redirigirMaterias();
    }
}


$editarId = filter_input(
    INPUT_GET,
    'editar',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);


$materiaEditar = null;

if (
    $editarId !== null
    && $editarId !== false
) {
    $materiaEditar =
        buscarMateria($tenantUserId, 
            (int) $editarId
        );
}


$materias =
    listarMaterias($tenantUserId);


$totalMaterias =
    count($materias);

$totalActivas = 0;

$cuatrimestres = [];
$docentes = [];
$grupos = [];


foreach ($materias as $materia) {

    if (
        (int) $materia['activa'] === 1
    ) {
        $totalActivas++;
    }


    if (
        $materia['cuatrimestre'] !== null
    ) {
        $cuatrimestres[
            (int) $materia['cuatrimestre']
        ] = true;
    }


    if (
        $materia['docente'] !== null
        && trim(
            (string) $materia['docente']
        ) !== ''
    ) {
        $docentes[
            mb_strtolower(
                trim(
                    (string) $materia['docente']
                )
            )
        ] = true;
    }


    $clave =
        $materia['cuatrimestre'] !== null
            ? (string) $materia['cuatrimestre']
            : 'sin';


    $grupos[$clave][] =
        $materia;
}


uksort(
    $grupos,
    static function (
        string $a,
        string $b
    ): int {

        if ($a === 'sin') {
            return 1;
        }

        if ($b === 'sin') {
            return -1;
        }

        return (int) $a
            <=> (int) $b;
    }
);


$mensaje =
    obtenerMensaje();

$token =
    csrfToken();

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
        Materias | Agenda Personal
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
<link rel="stylesheet" href="/assets/css/materias-legibilidad.css?v=4">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-materias.css?v=5">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
    <!-- ARK3360_R35B3_NOTIFICATION_BADGE -->
    <link rel="stylesheet" href="/assets/css/r35b-notification-badge.css?v=1">
<!-- ARK3360_R35I_E2_QUICK_DATE_PARITY -->
<link rel="stylesheet" href="/assets/css/r35i-e2-quick-date-parity.css?v=1">
<!-- /ARK3360_R35I_E2_QUICK_DATE_PARITY -->
    <!-- ARK3360_R35K_B_SEARCH_DARK_PARITY -->
    <link rel="stylesheet" href="/assets/css/r35k-b-search-dark-parity.css?v=r35kb-674398ee8d57edad">
    <!-- /ARK3360_R35K_B_SEARCH_DARK_PARITY -->

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
<link rel="stylesheet" href="/assets/css/r36h-materias-delete-pilot.css?v=5d4e1cc76da941ff">
</head>


<body class="ui-v3 page-subjects-v3">


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
                class="status-dot status-dot-online"
            ></span>

            <span>
                <?= $totalActivas ?>
                activas
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="subjects-shell-v3">


    <section class="subjects-hero-v3">

        <div>

            <div class="hero-eyebrow">

                <span class="eyebrow-dot"></span>

                Carrera

            </div>


            <h1>
                Materias
            </h1>


            <p>
                Tu catálogo académico central:
                materias, docentes, comisiones,
                turnos y colores en un solo lugar.
            </p>

        </div>


        <div class="subjects-hero-actions-v3">

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

                </svg>

                Abrir agenda

            </a>


            <div class="subjects-total-v3">

                <strong>
                    <?= $totalMaterias ?>
                </strong>

                <span>
                    materias
                </span>

            </div>

        </div>

    </section>


    <section class="subjects-metrics-v3">


        <article>

            <div class="subjects-metric-icon-v3">

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
                    Total
                </span>

                <strong>
                    <?= $totalMaterias ?>
                </strong>

                <small>
                    En tu catálogo
                </small>

            </div>

        </article>


        <article>

            <div class="subjects-metric-icon-v3">

                <svg viewBox="0 0 24 24">
                    <circle
                        cx="12"
                        cy="12"
                        r="8"
                    />
                    <path
                        d="m8.5 12 2.2 2.2 4.8-5"
                    />
                </svg>

            </div>

            <div>

                <span>
                    Activas
                </span>

                <strong>
                    <?= $totalActivas ?>
                </strong>

                <small>
                    En seguimiento
                </small>

            </div>

        </article>


        <article>

            <div class="subjects-metric-icon-v3">

                <svg viewBox="0 0 24 24">
                    <path
                        d="M4 7h16M7 4v6M17 4v6M5 12h14M5 17h14"
                    />
                </svg>

            </div>

            <div>

                <span>
                    Cuatrimestres
                </span>

                <strong>
                    <?= count($cuatrimestres) ?>
                </strong>

                <small>
                    Representados
                </small>

            </div>

        </article>


        <article>

            <div class="subjects-metric-icon-v3">

                <svg viewBox="0 0 24 24">
                    <circle
                        cx="12"
                        cy="8"
                        r="3"
                    />
                    <path
                        d="M6 20c.5-4 2.4-6 6-6s5.5 2 6 6"
                    />
                </svg>

            </div>

            <div>

                <span>
                    Docentes
                </span>

                <strong>
                    <?= count($docentes) ?>
                </strong>

                <small>
                    Diferentes
                </small>

            </div>

        </article>

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


    <section class="subjects-workspace-v3">


        <aside id="nueva-materia" class="subjects-composer-v3">

            <div class="subjects-composer-inner-v3">


                <div class="subjects-composer-heading-v3">

                    <div class="composer-icon-v3">

                        <?php if ($materiaEditar): ?>

                            <svg viewBox="0 0 24 24">
                                <path
                                    d="m5 16 10.5-10.5a2.1 2.1 0 0 1 3 3L8 19H5v-3Z"
                                />
                                <path
                                    d="m13.5 7.5 3 3"
                                />
                            </svg>

                        <?php else: ?>

                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M12 5v14M5 12h14"
                                />
                            </svg>

                        <?php endif; ?>

                    </div>


                    <div>

                        <span class="composer-kicker-v3">
                            <?= $materiaEditar
                                ? 'Modo edición'
                                : 'Catálogo académico' ?>
                        </span>

                        <h2>
                            <?= $materiaEditar
                                ? 'Editar materia'
                                : 'Nueva materia' ?>
                        </h2>

                        <p>
                            <?= $materiaEditar
                                ? 'Actualizá los datos académicos de esta materia.'
                                : 'Registrá una materia tal como figura en tu carrera.' ?>
                        </p>

                    </div>

                </div>


                <?php if ($materiaEditar): ?>

                    <div class="subject-editing-banner-v3">

                        <span
                            style="background: <?= h(
                                $materiaEditar['color']
                            ) ?>"
                        ></span>

                        <div>

                            <small>
                                Editando
                            </small>

                            <strong>

                                <?php if (
                                    $materiaEditar['codigo']
                                ): ?>

                                    [<?= h(
                                        $materiaEditar['codigo']
                                    ) ?>]

                                <?php endif; ?>

                                <?= h(
                                    $materiaEditar['nombre']
                                ) ?>

                            </strong>

                        </div>

                    </div>

                <?php endif; ?>


                <form
                    method="post"
                    action="/materias.php"
                    class="subjects-form-v3"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="<?= $materiaEditar
                            ? 'actualizar'
                            : 'crear' ?>"
                    >


                    <?php if ($materiaEditar): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $materiaEditar['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="subjects-field-v3">

                        <label for="nombre">

                            <span>
                                Nombre
                            </span>

                            <small>
                                Obligatorio
                            </small>

                        </label>

                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            maxlength="120"
                            required
                            placeholder="Ej: Programación Avanzada"
                            value="<?= h(
                                $materiaEditar['nombre']
                                ?? ''
                            ) ?>"
                        >

                    </div>


                    <div class="subjects-form-row-v3">

                        <div class="subjects-field-v3">

                            <label for="codigo">
                                Código
                            </label>

                            <input
                                id="codigo"
                                name="codigo"
                                type="text"
                                maxlength="30"
                                placeholder="Ej: S0D33"
                                value="<?= h(
                                    $materiaEditar['codigo']
                                    ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="subjects-field-v3">

                            <label for="cuatrimestre">
                                Cuatrimestre
                            </label>

                            <select
                                id="cuatrimestre"
                                name="cuatrimestre"
                            >

                                <option value="">
                                    Sin especificar
                                </option>


                                <?php for (
                                    $i = 1;
                                    $i <= 10;
                                    $i++
                                ): ?>

                                    <option
                                        value="<?= $i ?>"
                                        <?= isset(
                                            $materiaEditar['cuatrimestre']
                                        )
                                        && (int) $materiaEditar['cuatrimestre']
                                            === $i
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $i ?>°
                                    </option>

                                <?php endfor; ?>

                            </select>

                        </div>

                    </div>


                    <div class="subjects-form-row-v3">

                        <div class="subjects-field-v3">

                            <label for="turno">
                                Turno
                            </label>

                            <select
                                id="turno"
                                name="turno"
                            >

                                <option value="">
                                    Sin especificar
                                </option>


                                <?php foreach (
                                    [
                                        'MANANA' => 'Mañana',
                                        'TARDE' => 'Tarde',
                                        'NOCHE' => 'Noche',
                                        'OTRO' => 'Otro',
                                    ]
                                    as $valor => $texto
                                ): ?>

                                    <option
                                        value="<?= h($valor) ?>"
                                        <?= isset(
                                            $materiaEditar['turno']
                                        )
                                        && $materiaEditar['turno']
                                            === $valor
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($texto) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="subjects-field-v3">

                            <label for="comision">
                                Comisión
                            </label>

                            <input
                                id="comision"
                                name="comision"
                                type="text"
                                maxlength="30"
                                placeholder="Ej: ACT3AV"
                                value="<?= h(
                                    $materiaEditar['comision']
                                    ?? ''
                                ) ?>"
                            >

                        </div>

                    </div>


                    <div class="subjects-field-v3">

                        <label for="docente">
                            Docente
                        </label>

                        <input
                            id="docente"
                            name="docente"
                            type="text"
                            maxlength="150"
                            placeholder="Ej: Nombre del docente"
                            value="<?= h(
                                $materiaEditar['docente']
                                ?? ''
                            ) ?>"
                        >

                    </div>


                    <div class="subjects-field-v3">

                        <label for="color">
                            Color de la materia
                        </label>


                        <div class="subject-color-field-v3">

                            <input
                                id="color"
                                name="color"
                                type="color"
                                value="<?= h(
                                    $materiaEditar['color']
                                    ?? '#39d353'
                                ) ?>"
                            >


                            <span>
                                Se utiliza en Agenda
                                y Calendario.
                            </span>

                        </div>

                    </div>


                    <div class="composer-actions-v3">

                        <button
                            type="submit"
                            class="composer-save-v3"
                        >

                            <span>
                                <?= $materiaEditar
                                    ? 'Guardar cambios'
                                    : 'Guardar materia' ?>
                            </span>

                            <span aria-hidden="true">
                                →
                            </span>

                        </button>


                        <?php if ($materiaEditar): ?>

                            <a
                                href="/materias.php"
                                class="composer-cancel-v3"
                            >
                                Cancelar edición
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </div>

        </aside>


        <div class="subjects-content-v3">


            <div class="subjects-content-heading-v3">

                <div>

                    <span class="section-kicker">
                        Catálogo académico
                    </span>

                    <h2>
                        Mis materias
                    </h2>

                    <p>
                        Las materias inactivas permanecen
                        guardadas para conservar
                        el historial académico.
                    </p>

                </div>


                <div class="subjects-active-summary-v3">

                    <span class="status-dot status-dot-online"></span>

                    <strong>
                        <?= $totalActivas ?>
                    </strong>

                    <span>
                        activas
                    </span>

                </div>

            </div>


            <?php if ($materias === []): ?>

                <div class="subjects-empty-v3">

                    <div class="subjects-empty-icon-v3">

                        <svg viewBox="0 0 24 24">
                            <path
                                d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                            />
                            <path
                                d="M12 8v8M8 12h8"
                            />
                        </svg>

                    </div>

                    <strong>
                        Tu catálogo está vacío
                    </strong>

                    <p>
                        Creá tu primera materia
                        desde el panel lateral.
                    </p>

                </div>

            <?php else: ?>


                <?php foreach (
                    $grupos
                    as $claveGrupo => $materiasGrupo
                ): ?>

                    <section class="subjects-group-v3">

                        <div class="subjects-group-heading-v3">

                            <div>

                                <span>
                                    Cuatrimestre
                                </span>

                                <h3>
                                    <?= $claveGrupo === 'sin'
                                        ? 'Sin especificar'
                                        : h(
                                            $claveGrupo
                                            . '° cuatrimestre'
                                        ) ?>
                                </h3>

                            </div>


                            <span class="subjects-group-count-v3">

                                <?= count(
                                    $materiasGrupo
                                ) ?>

                                <?= count(
                                    $materiasGrupo
                                ) === 1
                                    ? 'materia'
                                    : 'materias' ?>

                            </span>

                        </div>


                        <div class="subjects-grid-v3">


                        <?php foreach (
                            $materiasGrupo
                            as $materia
                        ): ?>

                            <article
                                id="materia-<?= (int) $materia['id'] ?>"
                                class="subject-card-v3 <?= (int) $materia['activa'] === 1
                                    ? ''
                                    : 'subject-card-inactive-v3' ?>"
                                data-materia-card
                                data-active="<?= (int) $materia['activa'] ?>"
                                style="--subject-color: <?= h(
                                    $materia['color']
                                ) ?>;"
                            >

                                <span class="subject-card-accent-v3"></span>


                                <div class="subject-card-top-v3">

                                    <div class="subject-code-v3">

                                        <i></i>

                                        <span>
                                            <?= $materia['codigo']
                                                ? '[' . h(
                                                    $materia['codigo']
                                                ) . ']'
                                                : 'SIN CÓDIGO' ?>
                                        </span>

                                    </div>


                                    <span
                                        class="subject-state-v3 <?= (int) $materia['activa'] === 1
                                            ? 'subject-state-active-v3'
                                            : 'subject-state-inactive-v3' ?>"
                                    >

                                        <?= (int) $materia['activa'] === 1
                                            ? 'Activa'
                                            : 'Inactiva' ?>

                                    </span>

                                </div>


                                <div class="subject-card-title-v3">

                                    <h4>
                                        <?= h(
                                            $materia['nombre']
                                        ) ?>
                                    </h4>

                                    <span
                                        class="subject-color-preview-v3"
                                        title="<?= h(
                                            $materia['color']
                                        ) ?>"
                                    ></span>

                                </div>


                                <div class="subject-card-data-v3">


                                    <div>

                                        <span class="subject-data-icon-v3">

                                            <svg viewBox="0 0 24 24">
                                                <path
                                                    d="M4 7h16M7 4v6M17 4v6M5 12h14M5 17h14"
                                                />
                                            </svg>

                                        </span>

                                        <div>

                                            <span>
                                                Cuatrimestre
                                            </span>

                                            <strong>
                                                <?= h(
                                                    etiquetaCuatrimestre(
                                                        $materia['cuatrimestre']
                                                    )
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>


                                    <div>

                                        <span class="subject-data-icon-v3">

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

                                        </span>

                                        <div>

                                            <span>
                                                Turno
                                            </span>

                                            <strong>
                                                <?= h(
                                                    etiquetaTurno(
                                                        $materia['turno']
                                                    )
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>


                                    <div>

                                        <span class="subject-data-icon-v3">

                                            <svg viewBox="0 0 24 24">
                                                <path
                                                    d="M5 5h14v14H5z"
                                                />
                                                <path
                                                    d="M8 9h8M8 13h5"
                                                />
                                            </svg>

                                        </span>

                                        <div>

                                            <span>
                                                Comisión
                                            </span>

                                            <strong>
                                                <?= h(
                                                    $materia['comision']
                                                    ?? 'Sin especificar'
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>


                                    <div>

                                        <span class="subject-data-icon-v3">

                                            <svg viewBox="0 0 24 24">
                                                <circle
                                                    cx="12"
                                                    cy="8"
                                                    r="3"
                                                />
                                                <path
                                                    d="M6 20c.5-4 2.4-6 6-6s5.5 2 6 6"
                                                />
                                            </svg>

                                        </span>

                                        <div>

                                            <span>
                                                Docente
                                            </span>

                                            <strong>
                                                <?= h(
                                                    $materia['docente']
                                                    ?? 'Sin especificar'
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>

                                </div>


                                <div class="subject-card-footer-v3">


                                    

                                    <a
                                        href="/materia.php?id=<?= (int) $materia['id'] ?>"
                                        class="subject-open-v3"
                                    >
                                        <svg viewBox="0 0 24 24">
                                            <path d="M5 12h14"/>
                                            <path d="m14 7 5 5-5 5"/>
                                        </svg>

                                        Abrir
                                    </a>

<a
                                        href="/materias.php?editar=<?= (int) $materia['id'] ?>"
                                        class="subject-edit-v3"
                                    >

                                        <svg viewBox="0 0 24 24">
                                            <path
                                                d="m5 16 10.5-10.5a2.1 2.1 0 0 1 3 3L8 19H5v-3Z"
                                            />
                                            <path
                                                d="m13.5 7.5 3 3"
                                            />
                                        </svg>

                                        Editar

                                    </a>


                                    <form
                                        method="post"
                                        action="/materias.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h($token) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="cambiar_estado"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $materia['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="activa"
                                            value="<?= (int) $materia['activa'] === 1
                                                ? '0'
                                                : '1' ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="subject-state-action-v3"
                                        >

                                            <?= (int) $materia['activa'] === 1
                                                ? 'Desactivar'
                                                : 'Activar' ?>

                                        </button>

                                    </form>

                                    <!-- ARK3360_R36H_MATERIA_DELETE_BUTTON -->
                                    <form method="post" action="/materias.php"
                                          class="r36h-subject-delete-form"
                                          data-r36h-title="<?= h((string) $materia['nombre']) ?>"
                                          onsubmit="return window.confirm('¿Eliminar definitivamente la materia «' + this.dataset.r36hTitle + '»?\n\nSi tiene eventos asociados, la eliminación será bloqueada. Las tareas y series vinculadas se conservarán, pero perderán la referencia a esta materia.\n\nEsta acción no se puede deshacer.');">
                                        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                        <input type="hidden" name="accion" value="r36h_eliminar_materia">
                                        <input type="hidden" name="id" value="<?= (int) $materia['id'] ?>">
                                        <input type="hidden" name="confirmacion" value="ELIMINAR_DEFINITIVAMENTE">
                                        <button type="submit" class="r36h-subject-delete-button"
                                                title="Eliminar materia definitivamente"
                                                aria-label="Eliminar materia definitivamente">
                                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none"
                                                 stroke="currentColor" stroke-width="1.8"
                                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M3 6h18M8 6V4h8v2M5 6l1 15h12l1-15M10 10v7M14 10v7"/>
                                            </svg>
                                            <span>Eliminar</span>
                                        </button>
                                    </form>


                                </div>

                            </article>

                        <?php endforeach; ?>


                        </div>

                    </section>

                <?php endforeach; ?>


            <?php endif; ?>


        </div>

    </section>

</main>

    <script src="/assets/js/command-palette.js?v=r35ie1-c8ba12954f606e8f" defer></script>
    <script src="/assets/js/quick-add.js?v=r35ie3-ec2ed63bd623c25f60b6" defer></script>
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
