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


function textoOpcional(string $valor): ?string
{
    $valor = trim($valor);

    return $valor !== ''
        ? $valor
        : null;
}


function etiquetaTipo(string $tipo): string
{
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


function etiquetaEstado(string $estado): string
{
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


function fechaValida(string $fecha): bool
{
    $dt = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    return $dt !== false
        && $dt->format('Y-m-d') === $fecha;
}


function horaValida(string $hora): bool
{
    if ($hora === '') {
        return true;
    }

    $dt = DateTimeImmutable::createFromFormat(
        '!H:i',
        $hora
    );

    return $dt !== false
        && $dt->format('H:i') === $hora;
}


function nombreMesCorto(int $mes): string
{
    return match ($mes) {
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
        default => '',
    };
}


function nombreDia(int $numero): string
{
    return match ($numero) {
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
        default => '',
    };
}


function fechaEvento(string $fecha): ?DateTimeImmutable
{
    $zona = new DateTimeZone(
        'America/Argentina/Buenos_Aires'
    );

    $dt = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha,
        $zona
    );

    return $dt !== false
        ? $dt
        : null;
}


function diasHastaFecha(string $fecha): int
{
    $zona = new DateTimeZone(
        'America/Argentina/Buenos_Aires'
    );

    $hoy = new DateTimeImmutable(
        'today',
        $zona
    );

    $destino = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha,
        $zona
    );

    if ($destino === false) {
        return 0;
    }

    return (int) $hoy
        ->diff($destino)
        ->format('%r%a');
}


function textoCuentaRegresiva(int $dias): string
{
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

    return 'Hace ' . abs($dias) . ' días';
}


function formatearNota(
    string|float|int|null $nota
): string {
    if ($nota === null || $nota === '') {
        return '';
    }

    $valor = number_format(
        (float) $nota,
        2,
        ',',
        ''
    );

    return rtrim(
        rtrim($valor, '0'),
        ','
    );
}


function validarEvento(int $tenantUserId, array $datos): array
{
    $errores = [];

    $materiaId = filter_var(
        $datos['materia_id'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    if ($materiaId === false) {

        $errores[] =
            'Seleccioná una materia válida.';

        $materiaId = null;

    } else {

        $materia =
            buscarMateria($tenantUserId, (int) $materiaId);

        if ($materia === null) {
            $errores[] =
                'La materia seleccionada no existe.';
        }
    }


    $titulo = trim(
        (string) ($datos['titulo'] ?? '')
    );

    if ($titulo === '') {

        $errores[] =
            'El título es obligatorio.';

    } elseif (mb_strlen($titulo) > 160) {

        $errores[] =
            'El título no puede superar 160 caracteres.';
    }


    $tipo = (string) (
        $datos['tipo'] ?? ''
    );

    $tipos = [
        'PARCIAL',
        'FINAL',
        'RECUPERATORIO',
        'TP',
        'ENTREGA',
        'OTRO',
    ];

    if (!in_array(
        $tipo,
        $tipos,
        true
    )) {
        $errores[] =
            'El tipo seleccionado no es válido.';
    }


    $fecha = trim(
        (string) ($datos['fecha'] ?? '')
    );

    if (!fechaValida($fecha)) {
        $errores[] =
            'Ingresá una fecha válida.';
    }


    $horaRaw = trim(
        (string) ($datos['hora'] ?? '')
    );

    if (!horaValida($horaRaw)) {
        $errores[] =
            'Ingresá una hora válida.';
    }

    $hora = $horaRaw !== ''
        ? $horaRaw . ':00'
        : null;


    $lugar = textoOpcional(
        (string) ($datos['lugar'] ?? '')
    );

    if (
        $lugar !== null
        && mb_strlen($lugar) > 160
    ) {
        $errores[] =
            'El lugar no puede superar 160 caracteres.';
    }


    $descripcion = textoOpcional(
        (string) ($datos['descripcion'] ?? '')
    );


    $estado = (string) (
        $datos['estado'] ?? 'PENDIENTE'
    );

    $estados = [
        'PENDIENTE',
        'ENTREGADO',
        'APROBADO',
        'DESAPROBADO',
        'AUSENTE',
        'CANCELADO',
    ];

    if (!in_array(
        $estado,
        $estados,
        true
    )) {
        $errores[] =
            'El estado seleccionado no es válido.';
    }


    $notaRaw = trim(
        (string) ($datos['nota'] ?? '')
    );

    $nota = null;

    if ($notaRaw !== '') {

        $notaNormalizada = str_replace(
            ',',
            '.',
            $notaRaw
        );

        if (!is_numeric($notaNormalizada)) {

            $errores[] =
                'La nota debe ser un número.';

        } else {

            $nota = (float) $notaNormalizada;

            if ($nota < 0 || $nota > 10) {
                $errores[] =
                    'La nota debe estar entre 0 y 10.';
            }
        }
    }


    if (
        $nota !== null
        && in_array(
            $estado,
            [
                'PENDIENTE',
                'ENTREGADO',
                'AUSENTE',
                'CANCELADO',
            ],
            true
        )
    ) {
        $errores[] =
            'Ese estado no debería tener una nota cargada.';
    }


    return [
        'errores' => $errores,
        'materia_id' =>
            $materiaId !== null
                ? (int) $materiaId
                : null,
        'titulo' => $titulo,
        'tipo' => $tipo,
        'fecha' => $fecha,
        'hora' => $hora,
        'lugar' => $lugar,
        'descripcion' => $descripcion,
        'estado' => $estado,
        'nota' => $nota,
    ];
}


function redirigirAgenda(
    ?int $editar = null
): never {

    /*
     * AGENDA_R3B_AJAX_REDIRECT
     *
     * Quick Add reutiliza exactamente el mismo
     * validarEvento() + crearEvento() de Agenda.
     * Sólo cambia el transporte final:
     * redirect normal o JSON para AJAX.
     */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && (string) (
            $_POST['ajax'] ?? ''
        ) === '1'
    ) {

        $mensajeAjax =
            obtenerMensaje();

        $tipoAjax =
            is_array($mensajeAjax)
                ? (string) (
                    $mensajeAjax['tipo']
                    ?? 'error'
                )
                : 'error';

        $textoAjax =
            is_array($mensajeAjax)
                ? (string) (
                    $mensajeAjax['texto']
                    ?? 'No se pudo completar la operación.'
                )
                : 'No se pudo completar la operación.';

        $okAjax =
            $tipoAjax === 'exito';


        header(
            'Content-Type: application/json; charset=utf-8'
        );

        header(
            'Cache-Control: no-store, max-age=0'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );


        http_response_code(
            $okAjax
                ? 200
                : 422
        );


        echo json_encode(
            [
                'ok' =>
                    $okAjax,

                'message' =>
                    $textoAjax,
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    $url = '/agenda.php';

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


    $accion = (string) (
        $_POST['accion'] ?? ''
    );


    try {

        if ($accion === 'crear') {

            $datos =
                validarEvento($tenantUserId, $_POST);

            if ($datos['errores'] !== []) {

                guardarMensaje(
                    'error',
                    implode(
                        ' ',
                        $datos['errores']
                    )
                );

                redirigirAgenda();
            }


            crearEvento($tenantUserId, 
                $datos['materia_id'],
                $datos['titulo'],
                $datos['tipo'],
                $datos['fecha'],
                $datos['hora'],
                $datos['lugar'],
                $datos['descripcion'],
                $datos['estado'],
                $datos['nota']
            );


            guardarMensaje(
                'exito',
                'Evento académico creado correctamente.'
            );

            redirigirAgenda();
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
                buscarEvento($tenantUserId, (int) $id)
                === null
            ) {
                throw new RuntimeException(
                    'El evento no existe.'
                );
            }


            $datos =
                validarEvento($tenantUserId, $_POST);


            if ($datos['errores'] !== []) {

                guardarMensaje(
                    'error',
                    implode(
                        ' ',
                        $datos['errores']
                    )
                );

                redirigirAgenda(
                    (int) $id
                );
            }


            actualizarEvento($tenantUserId, 
                (int) $id,
                $datos['materia_id'],
                $datos['titulo'],
                $datos['tipo'],
                $datos['fecha'],
                $datos['hora'],
                $datos['lugar'],
                $datos['descripcion'],
                $datos['estado'],
                $datos['nota']
            );


            guardarMensaje(
                'exito',
                'Evento actualizado correctamente.'
            );

            redirigirAgenda();
        }


        throw new RuntimeException(
            'Acción no reconocida.'
        );

    } catch (Throwable $e) {

        guardarMensaje(
            'error',
            $e->getMessage()
        );

        redirigirAgenda();
    }
}


$eventoEditar = null;

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

if (
    $editarId !== null
    && $editarId !== false
) {
    $eventoEditar =
        buscarEvento($tenantUserId, (int) $editarId);
}


$materiasTodas =
    listarMaterias($tenantUserId);

$materias = array_values(
    array_filter(
        $materiasTodas,
        static fn (array $materia): bool =>
            (int) $materia['activa'] === 1
    )
);


$filtroMateria = filter_input(
    INPUT_GET,
    'materia',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if (
    $filtroMateria === false
    || $filtroMateria === null
) {
    $filtroMateria = null;
}


$tiposValidosFiltro = [
    'PARCIAL',
    'FINAL',
    'RECUPERATORIO',
    'TP',
    'ENTREGA',
    'OTRO',
];

$filtroTipo = strtoupper(
    trim(
        (string) ($_GET['tipo'] ?? '')
    )
);

if (!in_array(
    $filtroTipo,
    $tiposValidosFiltro,
    true
)) {
    $filtroTipo = '';
}


$estadosValidosFiltro = [
    'PENDIENTE',
    'ENTREGADO',
    'APROBADO',
    'DESAPROBADO',
    'AUSENTE',
    'CANCELADO',
];

$filtroEstado = strtoupper(
    trim(
        (string) ($_GET['estado'] ?? '')
    )
);

if (!in_array(
    $filtroEstado,
    $estadosValidosFiltro,
    true
)) {
    $filtroEstado = '';
}


$eventosTodos =
    listarEventos($tenantUserId);

$eventos = [];

foreach ($eventosTodos as $evento) {

    if (
        $filtroMateria !== null
        && (int) $evento['materia_id']
            !== (int) $filtroMateria
    ) {
        continue;
    }

    if (
        $filtroTipo !== ''
        && $evento['tipo'] !== $filtroTipo
    ) {
        continue;
    }

    if (
        $filtroEstado !== ''
        && $evento['estado'] !== $filtroEstado
    ) {
        continue;
    }

    $eventos[] = $evento;
}


$zona = new DateTimeZone(
    'America/Argentina/Buenos_Aires'
);

$hoy = (
    new DateTimeImmutable(
        'today',
        $zona
    )
)->format('Y-m-d');


$proximos = [];
$historial = [];

foreach ($eventos as $evento) {

    $evento['dias_restantes'] =
        diasHastaFecha(
            $evento['fecha']
        );

    if (
        $evento['fecha'] >= $hoy
        && $evento['estado'] !== 'CANCELADO'
    ) {
        $proximos[] = $evento;
    } else {
        $historial[] = $evento;
    }
}


$pendientesGlobales = 0;
$evaluacionesGlobales = 0;
$entregasGlobales = 0;

foreach ($eventosTodos as $evento) {

    if (
        $evento['estado'] === 'PENDIENTE'
        && $evento['fecha'] >= $hoy
    ) {
        $pendientesGlobales++;
    }

    if (
        $evento['fecha'] >= $hoy
        && in_array(
            $evento['tipo'],
            [
                'PARCIAL',
                'FINAL',
                'RECUPERATORIO',
            ],
            true
        )
        && $evento['estado'] !== 'CANCELADO'
    ) {
        $evaluacionesGlobales++;
    }

    if (
        $evento['fecha'] >= $hoy
        && in_array(
            $evento['tipo'],
            [
                'TP',
                'ENTREGA',
            ],
            true
        )
        && $evento['estado'] !== 'CANCELADO'
    ) {
        $entregasGlobales++;
    }
}


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
        Agenda académica | Agenda Personal
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

    <link rel="stylesheet" href="/assets/css/agenda-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/global-shell-legibilidad.css?v=3">
    <link rel="stylesheet" href="/assets/css/quick-add-mobile.css?v=1">
    <link rel="stylesheet" href="/assets/css/theme-professional-hybrid-agenda.css?v=4">
    <script src="/assets/js/theme-bootstrap.js?v=1"></script>
    <link rel="stylesheet" href="/assets/css/theme-system-dark.css?v=14">
    <script src="/assets/js/theme-switcher.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/css/global-overlays-premium.css?v=2">
    <link rel="stylesheet" href="/assets/css/r8-account-placement.css?v=1">
    <script src="/assets/js/r8-account-placement.js?v=1" defer></script>
</head>


<body class="ui-v3 page-agenda-v3">


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

            <a
                class="active"
                href="/agenda.php"
            >
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
                Agenda activa
            </span>

        </div>

        <?php require '/var/www/app/account_menu.php'; ?>

    </div>

</header>


<main class="agenda-shell-v3">


    <section class="agenda-hero-v3">

        <div>

            <div class="hero-eyebrow">

                <span class="eyebrow-dot"></span>

                Carrera

            </div>

            <h1>
                Agenda académica
            </h1>

            <p>
                Organizá cada fecha importante
                y mantené claro qué viene después.
            </p>

        </div>


        <div class="agenda-hero-actions-v3">

            <a
                href="/calendario.php"
                class="agenda-outline-action-v3"
            >

                <svg viewBox="0 0 24 24">
                    <rect
                        x="3.5"
                        y="5"
                        width="17"
                        height="15"
                        rx="2"
                    />
                    <path
                        d="M7.5 2.5v5M16.5 2.5v5M3.5 9h17"
                    />
                </svg>

                Ver calendario

            </a>

            <div class="agenda-count-v3">

                <strong>
                    <?= count($proximos) ?>
                </strong>

                <span>
                    próximos
                </span>

            </div>

        </div>

    </section>


    <section class="agenda-metrics-v3">

        <article>

            <span class="agenda-metric-label-v3">
                Próximos
            </span>

            <strong>
                <?= count(
                    array_filter(
                        $eventosTodos,
                        static fn (array $e): bool =>
                            $e['fecha'] >= $hoy
                            && $e['estado'] !== 'CANCELADO'
                    )
                ) ?>
            </strong>

            <small>
                Fechas futuras
            </small>

        </article>


        <article>

            <span class="agenda-metric-label-v3">
                Pendientes
            </span>

            <strong>
                <?= $pendientesGlobales ?>
            </strong>

            <small>
                Requieren seguimiento
            </small>

        </article>


        <article>

            <span class="agenda-metric-label-v3">
                Evaluaciones
            </span>

            <strong>
                <?= $evaluacionesGlobales ?>
            </strong>

            <small>
                Parciales y finales
            </small>

        </article>


        <article>

            <span class="agenda-metric-label-v3">
                Entregas
            </span>

            <strong>
                <?= $entregasGlobales ?>
            </strong>

            <small>
                TP y trabajos
            </small>

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


    <section class="agenda-workspace-v3">


        <aside id="nuevo-evento" class="agenda-composer-v3">

            <div class="agenda-composer-inner-v3">

                <div class="composer-heading-v3">

                    <div class="composer-icon-v3">

                        <?php if ($eventoEditar): ?>

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
                            <?= $eventoEditar
                                ? 'Modo edición'
                                : 'Nueva fecha' ?>
                        </span>

                        <h2>
                            <?= $eventoEditar
                                ? 'Editar evento'
                                : 'Crear evento' ?>
                        </h2>

                        <p>
                            <?= $eventoEditar
                                ? 'Actualizá la información de esta fecha.'
                                : 'Sumá una nueva fecha importante a tu agenda.' ?>
                        </p>

                    </div>

                </div>


                <?php if ($eventoEditar): ?>

                    <div class="editing-banner-v3">

                        <span>
                            Editando
                        </span>

                        <strong>
                            <?= h(
                                $eventoEditar['titulo']
                            ) ?>
                        </strong>

                    </div>

                <?php endif; ?>


                <?php if ($materias === []): ?>

                    <div class="agenda-empty-small-v3">

                        <strong>
                            No hay materias activas
                        </strong>

                        <p>
                            Creá o activá una materia
                            para registrar eventos.
                        </p>

                    </div>

                <?php else: ?>

                    <form
                        method="post"
                        action="/agenda.php"
                        class="agenda-form-v3"
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
                            value="<?= $eventoEditar
                                ? 'actualizar'
                                : 'crear' ?>"
                        >

                        <?php if ($eventoEditar): ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $eventoEditar['id'] ?>"
                            >

                        <?php endif; ?>


                        <div class="agenda-field-v3">

                            <label for="materia_id">

                                <span>
                                    Materia
                                </span>

                                <small>
                                    Obligatorio
                                </small>

                            </label>

                            <select
                                id="materia_id"
                                name="materia_id"
                                required
                            >

                                <option value="">
                                    Seleccionar materia
                                </option>

                                <?php foreach (
                                    $materias
                                    as $materia
                                ): ?>

                                    <option
                                        value="<?= (int) $materia['id'] ?>"
                                        <?= isset(
                                            $eventoEditar['materia_id']
                                        )
                                        && (int) $eventoEditar['materia_id']
                                            === (int) $materia['id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $materia['codigo']
                                            ? '[' . h($materia['codigo']) . '] '
                                            : '' ?>

                                        <?= h(
                                            $materia['nombre']
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="agenda-form-row-v3">

                            <div class="agenda-field-v3">

                                <label for="tipo">
                                    Tipo
                                </label>

                                <select
                                    id="tipo"
                                    name="tipo"
                                    required
                                >

                                <?php

                                $tiposOpciones = [
                                    'PARCIAL' => 'Parcial',
                                    'FINAL' => 'Final',
                                    'RECUPERATORIO' => 'Recuperatorio',
                                    'TP' => 'Trabajo práctico',
                                    'ENTREGA' => 'Entrega',
                                    'OTRO' => 'Otro',
                                ];

                                $tipoActual =
                                    $eventoEditar['tipo']
                                    ?? 'PARCIAL';

                                foreach (
                                    $tiposOpciones
                                    as $valor => $texto
                                ):
                                ?>

                                    <option
                                        value="<?= h($valor) ?>"
                                        <?= $tipoActual === $valor
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($texto) ?>
                                    </option>

                                <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="agenda-field-v3">

                                <label for="estado">
                                    Estado
                                </label>

                                <select
                                    id="estado"
                                    name="estado"
                                >

                                <?php

                                $estadosOpciones = [
                                    'PENDIENTE' => 'Pendiente',
                                    'ENTREGADO' => 'Entregado',
                                    'APROBADO' => 'Aprobado',
                                    'DESAPROBADO' => 'Desaprobado',
                                    'AUSENTE' => 'Ausente',
                                    'CANCELADO' => 'Cancelado',
                                ];

                                $estadoActual =
                                    $eventoEditar['estado']
                                    ?? 'PENDIENTE';

                                foreach (
                                    $estadosOpciones
                                    as $valor => $texto
                                ):
                                ?>

                                    <option
                                        value="<?= h($valor) ?>"
                                        <?= $estadoActual === $valor
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($texto) ?>
                                    </option>

                                <?php endforeach; ?>

                                </select>

                            </div>

                        </div>


                        <div class="agenda-field-v3">

                            <label for="titulo">

                                <span>
                                    Título
                                </span>

                                <small>
                                    Obligatorio
                                </small>

                            </label>

                            <input
                                id="titulo"
                                name="titulo"
                                type="text"
                                maxlength="160"
                                required
                                placeholder="Ej: Parcial #2"
                                value="<?= h(
                                    $eventoEditar['titulo']
                                    ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="agenda-form-row-v3">

                            <div class="agenda-field-v3">

                                <label for="fecha">
                                    Fecha
                                </label>

                                <input
                                    id="fecha"
                                    name="fecha"
                                    type="date"
                                    required
                                    value="<?= h(
                                        $eventoEditar['fecha']
                                        ?? ''
                                    ) ?>"
                                >

                            </div>


                            <div class="agenda-field-v3">

                                <label for="hora">
                                    Hora
                                </label>

                                <input
                                    id="hora"
                                    name="hora"
                                    type="time"
                                    value="<?= h(
                                        isset($eventoEditar['hora'])
                                        && $eventoEditar['hora'] !== null
                                            ? substr(
                                                $eventoEditar['hora'],
                                                0,
                                                5
                                            )
                                            : ''
                                    ) ?>"
                                >

                            </div>

                        </div>


                        <div class="agenda-form-row-v3">

                            <div class="agenda-field-v3">

                                <label for="lugar">
                                    Lugar
                                </label>

                                <input
                                    id="lugar"
                                    name="lugar"
                                    type="text"
                                    maxlength="160"
                                    placeholder="Ej: Virtual"
                                    value="<?= h(
                                        $eventoEditar['lugar']
                                        ?? ''
                                    ) ?>"
                                >

                            </div>


                            <div class="agenda-field-v3 agenda-field-note-v3">

                                <label for="nota">
                                    Nota
                                </label>

                                <input
                                    id="nota"
                                    name="nota"
                                    type="number"
                                    min="0"
                                    max="10"
                                    step="0.01"
                                    placeholder="0 - 10"
                                    value="<?= h(
                                        isset($eventoEditar['nota'])
                                        && $eventoEditar['nota'] !== null
                                            ? formatearNota(
                                                $eventoEditar['nota']
                                            )
                                            : ''
                                    ) ?>"
                                >

                            </div>

                        </div>


                        <div class="agenda-field-v3">

                            <label for="descripcion">
                                Descripción
                            </label>

                            <textarea
                                id="descripcion"
                                name="descripcion"
                                maxlength="3000"
                                rows="4"
                                placeholder="Información adicional, temas, recordatorios..."
                            ><?= h(
                                $eventoEditar['descripcion']
                                ?? ''
                            ) ?></textarea>

                        </div>


                        <div class="composer-actions-v3">

                            <button
                                type="submit"
                                class="composer-save-v3"
                            >

                                <span>
                                    <?= $eventoEditar
                                        ? 'Guardar cambios'
                                        : 'Guardar evento' ?>
                                </span>

                                <span aria-hidden="true">
                                    →
                                </span>

                            </button>


                            <?php if ($eventoEditar): ?>

                                <a
                                    href="/agenda.php"
                                    class="composer-cancel-v3"
                                >
                                    Cancelar edición
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </aside>


        <div class="agenda-content-v3">


            <section class="agenda-filterbar-v3">

                <div class="filterbar-heading-v3">

                    <div>

                        <span>
                            Vista actual
                        </span>

                        <strong>
                            <?= count($eventos) ?>
                            de
                            <?= count($eventosTodos) ?>
                            eventos
                        </strong>

                    </div>

                </div>


                <form
                    method="get"
                    action="/agenda.php"
                    class="agenda-filters-v3"
                >

                    <div>

                        <label for="filtro_materia">
                            Materia
                        </label>

                        <select
                            id="filtro_materia"
                            name="materia"
                        >

                            <option value="">
                                Todas las materias
                            </option>

                            <?php foreach (
                                $materiasTodas
                                as $materiaFiltro
                            ): ?>

                                <option
                                    value="<?= (int) $materiaFiltro['id'] ?>"
                                    <?= $filtroMateria !== null
                                        && (int) $filtroMateria
                                            === (int) $materiaFiltro['id']
                                            ? 'selected'
                                            : '' ?>
                                >

                                    <?= $materiaFiltro['codigo']
                                        ? '[' . h($materiaFiltro['codigo']) . '] '
                                        : '' ?>

                                    <?= h(
                                        $materiaFiltro['nombre']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div>

                        <label for="filtro_tipo">
                            Tipo
                        </label>

                        <select
                            id="filtro_tipo"
                            name="tipo"
                        >

                            <option value="">
                                Todos
                            </option>

                            <?php foreach (
                                [
                                    'PARCIAL' => 'Parcial',
                                    'FINAL' => 'Final',
                                    'RECUPERATORIO' => 'Recuperatorio',
                                    'TP' => 'Trabajo práctico',
                                    'ENTREGA' => 'Entrega',
                                    'OTRO' => 'Otro',
                                ]
                                as $valor => $texto
                            ): ?>

                                <option
                                    value="<?= h($valor) ?>"
                                    <?= $filtroTipo === $valor
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= h($texto) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div>

                        <label for="filtro_estado">
                            Estado
                        </label>

                        <select
                            id="filtro_estado"
                            name="estado"
                        >

                            <option value="">
                                Todos
                            </option>

                            <?php foreach (
                                [
                                    'PENDIENTE' => 'Pendiente',
                                    'ENTREGADO' => 'Entregado',
                                    'APROBADO' => 'Aprobado',
                                    'DESAPROBADO' => 'Desaprobado',
                                    'AUSENTE' => 'Ausente',
                                    'CANCELADO' => 'Cancelado',
                                ]
                                as $valor => $texto
                            ): ?>

                                <option
                                    value="<?= h($valor) ?>"
                                    <?= $filtroEstado === $valor
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= h($texto) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <button
                        type="submit"
                        class="filter-apply-v3"
                    >

                        <svg viewBox="0 0 24 24">
                            <path
                                d="M4 6h16M7 12h10M10 18h4"
                            />
                        </svg>

                        Aplicar

                    </button>


                    <a
                        href="/agenda.php"
                        class="filter-clear-v3"
                    >
                        Limpiar
                    </a>

                </form>

            </section>


            <section class="agenda-section-v3">

                <div class="agenda-section-heading-v3">

                    <div>

                        <span class="section-kicker">
                            Próximamente
                        </span>

                        <h2>
                            Próximos eventos
                        </h2>

                    </div>

                    <span class="agenda-section-count-v3">
                        <?= count($proximos) ?>
                    </span>

                </div>


                <?php if ($proximos === []): ?>

                    <div class="agenda-empty-v3">

                        <div class="agenda-empty-icon-v3">

                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                                />
                                <path
                                    d="M8 2v4M16 2v4M4 9h16"
                                />
                                <path
                                    d="m9 14 2 2 4-4"
                                />
                            </svg>

                        </div>

                        <strong>
                            Agenda despejada
                        </strong>

                        <p>
                            No hay próximos eventos
                            para los filtros seleccionados.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="agenda-timeline-v3">

                    <?php foreach (
                        $proximos
                        as $evento
                    ): ?>

                        <?php

                        $fechaObj =
                            fechaEvento(
                                $evento['fecha']
                            );

                        ?>

                        <article id="evento-<?= (int) $evento['id'] ?>"
                            class="agenda-event-card-v3">

                            <span
                                class="agenda-event-accent-v3"
                                style="background: <?= h(
                                    $evento['materia_color']
                                ) ?>"
                            ></span>


                            <div class="agenda-date-v3">

                                <?php if (
                                    $fechaObj instanceof DateTimeImmutable
                                ): ?>

                                    <span class="agenda-weekday-v3">
                                        <?= h(
                                            nombreDia(
                                                (int) $fechaObj->format('N')
                                            )
                                        ) ?>
                                    </span>

                                    <strong>
                                        <?= $fechaObj->format('d') ?>
                                    </strong>

                                    <span>
                                        <?= h(
                                            nombreMesCorto(
                                                (int) $fechaObj->format('n')
                                            )
                                        ) ?>
                                    </span>

                                    <small>
                                        <?= $fechaObj->format('Y') ?>
                                    </small>

                                <?php endif; ?>

                            </div>


                            <div class="agenda-event-main-v3">

                                <div class="agenda-event-meta-v3">

                                    <span class="agenda-type-v3">
                                        <?= h(
                                            etiquetaTipo(
                                                $evento['tipo']
                                            )
                                        ) ?>
                                    </span>

                                    <span
                                        class="agenda-status-v3 agenda-status-<?= strtolower(
                                            $evento['estado']
                                        ) ?>"
                                    >
                                        <?= h(
                                            etiquetaEstado(
                                                $evento['estado']
                                            )
                                        ) ?>
                                    </span>

                                </div>


                                <h3>
                                    <?= h(
                                        $evento['titulo']
                                    ) ?>
                                </h3>


                                <p class="agenda-course-v3">

                                    <?php if (
                                        $evento['materia_codigo']
                                    ): ?>

                                        <span>
                                            [<?= h(
                                                $evento['materia_codigo']
                                            ) ?>]
                                        </span>

                                    <?php endif; ?>

                                    <?= h(
                                        $evento['materia_nombre']
                                    ) ?>

                                </p>


                                <div class="agenda-event-details-v3">

                                    <?php if (
                                        $evento['hora']
                                    ): ?>

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
                                                substr(
                                                    $evento['hora'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        $evento['lugar']
                                    ): ?>

                                        <span>

                                            <svg viewBox="0 0 24 24">
                                                <path
                                                    d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z"
                                                />
                                                <circle
                                                    cx="12"
                                                    cy="10"
                                                    r="2"
                                                />
                                            </svg>

                                            <?= h(
                                                $evento['lugar']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        $evento['nota'] !== null
                                    ): ?>

                                        <span>

                                            <svg viewBox="0 0 24 24">
                                                <path
                                                    d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3Z"
                                                />
                                            </svg>

                                            Nota
                                            <?= h(
                                                formatearNota(
                                                    $evento['nota']
                                                )
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    $evento['descripcion']
                                ): ?>

                                    <p class="agenda-description-v3">
                                        <?= h(
                                            $evento['descripcion']
                                        ) ?>
                                    </p>

                                <?php endif; ?>

                            </div>


                            <div class="agenda-event-side-v3">

                                <span class="agenda-countdown-v3">

                                    <?= h(
                                        textoCuentaRegresiva(
                                            (int) $evento['dias_restantes']
                                        )
                                    ) ?>

                                </span>


                                <a
                                    href="/agenda.php?editar=<?= (int) $evento['id'] ?>"
                                    class="agenda-edit-v3"
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

                            </div>

                        </article>

                    <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <section class="agenda-section-v3 agenda-history-v3">

                <div class="agenda-section-heading-v3">

                    <div>

                        <span class="section-kicker">
                            Registro
                        </span>

                        <h2>
                            Historial
                        </h2>

                    </div>

                    <span class="agenda-section-count-v3">
                        <?= count($historial) ?>
                    </span>

                </div>


                <?php if ($historial === []): ?>

                    <div class="agenda-history-empty-v3">

                        <span>
                            Todavía no hay historial académico.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="agenda-history-list-v3">

                    <?php foreach (
                        array_reverse($historial)
                        as $evento
                    ): ?>

                        <?php

                        $fechaObj =
                            fechaEvento(
                                $evento['fecha']
                            );

                        ?>

                        <article id="evento-<?= (int) $evento['id'] ?>"
                            class="agenda-history-item-v3">

                            <span
                                class="agenda-history-dot-v3"
                                style="background: <?= h(
                                    $evento['materia_color']
                                ) ?>"
                            ></span>


                            <div>

                                <strong>
                                    <?= h(
                                        $evento['titulo']
                                    ) ?>
                                </strong>

                                <span>

                                    <?= $fechaObj instanceof DateTimeImmutable
                                        ? h(
                                            $fechaObj->format('d/m/Y')
                                        )
                                        : h($evento['fecha']) ?>

                                    ·

                                    <?= h(
                                        $evento['materia_nombre']
                                    ) ?>

                                </span>

                            </div>


                            <span
                                class="agenda-status-v3 agenda-status-<?= strtolower(
                                    $evento['estado']
                                ) ?>"
                            >
                                <?= h(
                                    etiquetaEstado(
                                        $evento['estado']
                                    )
                                ) ?>
                            </span>


                            <a
                                href="/agenda.php?editar=<?= (int) $evento['id'] ?>"
                                class="agenda-history-edit-v3"
                            >
                                Editar
                            </a>

                        </article>

                    <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </section>

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

</body>
</html>
